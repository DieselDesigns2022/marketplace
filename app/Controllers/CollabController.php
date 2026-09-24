<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Repositories\CollabRepository;
use App\Services\CollabIpRiskWorkflow;
use App\Services\CollabPayoutService;
use App\Services\CollabService;
use App\Services\CreditService;
use App\Services\EmailQueueService;
use App\Services\NotificationService;

final class CollabController
{
    private CollabRepository $repo;

    public function __construct() { $this->repo = new CollabRepository(); }

    private function seller(): array
    {
        H::requireSeller();
        return $this->repo->approvedDesigner((int)H::user()['id']) ?? H::abort(403);
    }

    private function event(int $id): array { return $this->repo->event($id) ?? H::abort(404); }
    private function requireHost(array $collab, array $designer): void { if ((int)$collab['host_designer_id'] !== (int)$designer['id']) H::abort(403); }

    public function index(): void
    {
        $designer = $this->seller();
        H::view('collabs/index', [
            'hosted'=>DB::rows('select * from collab_events where host_designer_id=? order by created_at desc', [$designer['id']]),
            'participating'=>DB::rows('select c.*,cp.eligibility,cp.membership_status from collab_participants cp join collab_events c on c.id=cp.collab_id where cp.designer_id=? and c.host_designer_id<>? order by c.created_at desc', [$designer['id'],$designer['id']]),
        ]);
    }

    public function find(): void
    {
        $designer = $this->seller();
        H::view('collabs/find', ['collabs'=>DB::rows('select c.*,d.display_name host_name,cp.membership_status from collab_events c join designers d on d.id=c.host_designer_id left join collab_participants cp on cp.collab_id=c.id and cp.designer_id=? where c.participation_type="open" and c.status="collecting" and c.upload_deadline>now() order by c.upload_deadline', [$designer['id']])]);
    }

    public function estimatePayout(): void
    {
        $this->seller();
        H::verifyCsrf();
        header('Content-Type: application/json');

        try {
            $priceCents = CreditService::parseCents((string)($_POST['price'] ?? ''), false);
            $countInput = (string)($_POST['designers'] ?? '');
            if (!preg_match('/^[1-9]\d*$/', $countInput)) {
                throw new \InvalidArgumentException('Enter a valid designer count.');
            }
            $estimate = (new CollabPayoutService())->estimate($priceCents, (int)$countInput);
            echo json_encode([
                'ok'=>true,
                'fee'=>CreditService::formatCents($estimate['fee_cents']),
                'pool'=>CreditService::formatCents($estimate['pool_cents']),
                'per_designer'=>CreditService::formatCents($estimate['per_designer_cents']),
                'remainder_cents'=>$estimate['remainder_cents'],
            ], JSON_THROW_ON_ERROR);
        } catch (\DomainException|\InvalidArgumentException $error) {
            http_response_code(422);
            echo json_encode(['ok'=>false, 'error'=>$error->getMessage()], JSON_THROW_ON_ERROR);
        }
    }

    public function create(): void
    {
        $designer = $this->seller();
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            H::verifyCsrf();
            try {
                $values = $this->validatedValues($_POST);
                DB::begin();
                DB::exec('insert into collab_events(host_designer_id,title,slug,description,price_cents,participation_type,minimum_file_count,upload_deadline,sale_starts_at,sale_close_date,ip_risk_state) values(?,?,?,?,?,?,?,?,?,? ,"review_required")', array_merge([$designer['id']],$values));
                $id = (int)DB::id();
                DB::exec('insert into collab_participants(collab_id,designer_id,membership_status) values(?,?,"host")', [$id,$designer['id']]);
                DB::commit();
                $this->scanAfterMutation($id);
                H::redirect('/seller/collabs/'.$id);
            } catch (\DomainException|\InvalidArgumentException $error) {
                if (DB::pdo()->inTransaction()) DB::rollBack();
                $errors[] = $error->getMessage();
            }
        }
        H::view('collabs/form', ['collab'=>null,'errors'=>$errors]);
    }

    public function edit($id): void
    {
        $designer = $this->seller(); $collab = $this->event((int)$id); $this->requireHost($collab,$designer);
        if (!$this->repo->changesOpen((int)$id)) H::abort(409);
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            H::verifyCsrf();
            try {
                $values = $this->validatedValues($_POST, (int)$id);
                DB::begin();
                $locked = DB::row('select * from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [(int)$id]) ?? throw new \DomainException('The collab is no longer editable.');
                $ipChanged = $values[0] !== $locked['title'] || $values[2] !== $locked['description'];
                DB::exec('update collab_events set title=?,slug=?,description=?,price_cents=?,participation_type=?,minimum_file_count=?,upload_deadline=?,sale_starts_at=?,sale_close_date=? where id=? and snapshot_at is null', array_merge($values,[(int)$id]));
                if ($ipChanged) CollabIpRiskWorkflow::invalidate((int)$id);
                DB::commit();
                $this->scanAfterMutation((int)$id);
                H::flash('success','Collab settings updated.'); H::redirect('/seller/collabs/'.$id);
            } catch (\DomainException|\InvalidArgumentException $error) { if(DB::pdo()->inTransaction())DB::rollBack();$errors[]=$error->getMessage(); }
        }
        H::view('collabs/form', ['collab'=>$collab,'errors'=>$errors]);
    }

    public function show($id): void
    {
        $designer=$this->seller(); $collab=$this->event((int)$id); $participant=$this->repo->participant((int)$id,(int)$designer['id']);
        if (!$participant) H::abort(403);
        $participants=$this->repo->participants((int)$id);
        $estimate=(new CollabPayoutService())->estimate((int)$collab['price_cents'],max(1,(int)($collab['eligible_count']?:count(array_filter($participants,fn($p)=>in_array($p['membership_status'],['host','accepted'],true))))));
        H::view('collabs/show', ['collab'=>$collab,'participant'=>$participant,'participants'=>$participants,'files'=>DB::rows('select * from collab_files where collab_id=? and designer_id=? order by id',[$id,$designer['id']]),'estimate'=>$estimate,'changesOpen'=>$this->repo->changesOpen((int)$id),'inviteToken'=>$_SESSION['collab_invite_token_'.$id]??null]);
    }

    public function request($id): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $collab=$this->event((int)$id);
        if ($collab['participation_type']!=='open' || !$this->repo->changesOpen((int)$id)) H::abort(409);
        DB::begin();
        try {
            if (!DB::row('select id from collab_events where id=? and participation_type="open" and snapshot_at is null and upload_deadline>now() for update', [$id])) throw new \DomainException('This collab is no longer accepting requests.');
            DB::exec('insert into collab_participants(collab_id,designer_id,membership_status) values(?,?,"requested") on duplicate key update membership_status=if(membership_status="denied","requested",membership_status)',[$id,$designer['id']]);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error instanceof \DomainException?$error->getMessage():'Your request could not be saved.');
            H::redirect('/seller/collabs/find');
        }
        $this->notifyHost($collab,'collab_join_requested','Collab join requested',$designer['display_name'].' requested to join '.$collab['title'],'request:'.$designer['id']);
        H::redirect('/seller/collabs/find');
    }

    public function decide($id,$participantId): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $collab=$this->event((int)$id); $this->requireHost($collab,$designer);
        if (!$this->repo->changesOpen((int)$id)) H::abort(409);
        $decision=($_POST['decision']??'')==='accept'?'accepted':'denied';
        DB::begin();
        try {
            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) throw new \DomainException('The File Upload Deadline has passed.');
            $participant=DB::row('select cp.*,d.user_id from collab_participants cp join designers d on d.id=cp.designer_id where cp.id=? and cp.collab_id=? and cp.membership_status in ("requested","invited") for update',[(int)$participantId,(int)$id])??throw new \DomainException('That request is no longer pending.');
            DB::exec('update collab_participants set membership_status=? where id=?',[$decision,$participantId]);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error->getMessage()); H::redirect('/seller/collabs/'.$id);
        }
        $this->notifyUser((int)$participant['user_id'],'collab_request_'.$decision,'Collab request '.$decision,'Your request to join “'.$collab['title'].'” was '.$decision.'.','collab:'.$id.':decision:'.$participantId.':'.$decision,'/seller/collabs/'.$id);
        H::redirect('/seller/collabs/'.$id);
    }

    public function invite($id): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $collab=$this->event((int)$id); $this->requireHost($collab,$designer);
        if (!$this->repo->changesOpen((int)$id)) H::abort(409);
        $email=strtolower(trim((string)($_POST['email']??'')));
        $target=DB::row('select d.*,u.email,u.id user_id,u.name from designers d join users u on u.id=d.user_id where lower(u.email)=? and d.status="approved"',[$email]);
        if (!$target) { H::flash('error','Invitee must be an approved seller.'); H::redirect('/seller/collabs/'.$id); }
        $token=bin2hex(random_bytes(32));
        try {
            DB::begin();
            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) throw new \DomainException('The upload deadline has passed.');
            DB::exec('insert into collab_invitations(collab_id,email,token_hash,invited_by_designer_id,expires_at) values(?,?,?,?,?)',[$id,$email,hash('sha256',$token),$designer['id'],$collab['upload_deadline']]);
            DB::exec('insert into collab_participants(collab_id,designer_id,membership_status,invited_email) values(?,?,"invited",?)',[$id,$target['id'],$email]);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error instanceof \DomainException ? $error->getMessage() : 'That seller is already invited or participating.');
            H::redirect('/seller/collabs/'.$id);
        }
        $url='/collabs/invite/'.$token;
        $this->notifyUser((int)$target['user_id'],'collab_invited','Collab invitation','You were invited to “'.$collab['title'].'”.','collab:'.$id.':invite:'.$target['id'],$url);
        try { EmailQueueService::foundationSellerEmail($email,'collab_invited',['name'=>$target['name'],'title'=>'Collab invitation','message'=>'You were invited to “'.$collab['title'].'”.','action_url'=>$url],'collab:'.$id.':invite:'.$target['id'].':email'); } catch (\Throwable $error) { NotificationService::reportFailure('collab_invite_email',$error); }
        H::redirect('/seller/collabs/'.$id);
    }

    public function generateShareLink($id): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $collab=$this->event((int)$id); $this->requireHost($collab,$designer);
        if (!$this->repo->changesOpen((int)$id)) H::abort(409);
        $token=bin2hex(random_bytes(32));
        DB::begin();
        try {
            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) throw new \DomainException('The File Upload Deadline has passed.');
            DB::exec('update collab_events set invite_token_hash=?,invite_expires_at=upload_deadline where id=?',[hash('sha256',$token),$id]);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error->getMessage()); H::redirect('/seller/collabs/'.$id);
        }
        $_SESSION['collab_invite_token_'.$id]=$token;
        H::redirect('/seller/collabs/'.$id);
    }

    public function inviteLink($token): void
    {
        $this->seller();
        $hash=hash('sha256',(string)$token);
        $collab=DB::row('select c.* from collab_events c left join collab_invitations i on i.collab_id=c.id and i.token_hash=? and lower(i.email)=lower(?) and i.accepted_at is null and i.expires_at>=now() where (c.invite_token_hash=? and c.invite_expires_at>=now() or i.id is not null) and c.snapshot_at is null limit 1',[$hash,H::user()['email'],$hash])??H::abort(404);
        H::view('collabs/invite',['collab'=>$collab,'token'=>$token]);
    }

    public function acceptInvite($token): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $hash=hash('sha256',(string)$token);
        DB::begin();
        try {
            $direct=DB::row('select i.* from collab_invitations i where i.token_hash=? and lower(i.email)=lower(?) and i.accepted_at is null and i.expires_at>=now() for update',[$hash,H::user()['email']]);
            $collab=$direct ? DB::row('select * from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update',[$direct['collab_id']]) : DB::row('select * from collab_events where invite_token_hash=? and invite_expires_at>=now() and snapshot_at is null and upload_deadline>now() for update',[$hash]);
            if (!$collab) throw new \DomainException('This invitation is invalid, expired, or closed.');
            DB::exec('insert into collab_participants(collab_id,designer_id,membership_status) values(?,?,"accepted") on duplicate key update membership_status=if(membership_status in ("invited","requested","denied"),"accepted",membership_status)',[$collab['id'],$designer['id']]);
            if ($direct) DB::exec('update collab_invitations set accepted_at=now(),accepted_designer_id=? where id=?',[$designer['id'],$direct['id']]);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error instanceof \DomainException ? $error->getMessage() : 'Invitation could not be accepted.');
            H::redirect('/seller/collabs');
        }
        H::redirect('/seller/collabs/'.$collab['id']);
    }

    public function upload($id): void { $this->storeUpload((int)$id, null); }
    public function replace($id,$file): void { $this->storeUpload((int)$id, (int)$file); }

    private function storeUpload(int $id, ?int $replaceId): void
    {
        $designer = $this->seller();
        H::verifyCsrf();
        if (!$this->repo->changesOpen($id)) H::abort(409);
        $upload = $_FILES['file'] ?? [];
        $submittedKind = (string)($_POST['file_kind'] ?? '');
        $absolute = null;
        $old = null;
        try {
            DB::begin();
            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) {
                throw new \DomainException('The File Upload Deadline has passed.');
            }
            $participant = DB::row('select * from collab_participants where collab_id=? and designer_id=? and membership_status in ("host","accepted") for update', [$id,$designer['id']]);
            if (!$participant) throw new \DomainException('You are not an accepted participant.');
            if ($replaceId !== null) {
                $old = DB::row('select * from collab_files where id=? and collab_id=? and designer_id=? for update', [$replaceId,$id,$designer['id']]);
                if (!$old) throw new \DomainException('The file being replaced no longer exists.');
                $kind = \App\Services\CollabUploadValidator::replacementKind((string)$old['file_kind'], $submittedKind);
            } else {
                $kind = $submittedKind;
                if ($kind === 'terms') {
                    $old = DB::row('select * from collab_files where collab_id=? and designer_id=? and file_kind="terms" order by id desc limit 1 for update', [$id,$designer['id']]);
                }
            }
            $validated = (new \App\Services\CollabUploadValidator())->validate($upload,$kind);
            $directory = app_path('storage/protected_uploads/collabs/'.$id.'/'.$designer['id']);
            if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) throw new \RuntimeException('Protected upload directory unavailable.');
            $stored = bin2hex(random_bytes(16)).'.'.$validated['extension'];
            $absolute = $directory.'/'.$stored;
            if (!move_uploaded_file($validated['temporary'],$absolute)) throw new \RuntimeException('File could not be saved.');
            DB::exec('insert into collab_files(collab_id,participant_id,designer_id,file_kind,original_name,stored_name,storage_path,mime_type,byte_size,sha256) values(?,?,?,?,?,?,?,?,?,?)', [$id,$participant['id'],$designer['id'],$kind,$validated['original'],$stored,'collabs/'.$id.'/'.$designer['id'].'/'.$stored,$validated['mime'],$validated['size'],hash_file('sha256',$absolute)]);
            if ($old) DB::exec('delete from collab_files where id=? and designer_id=?', [$old['id'],$designer['id']]);
            CollabIpRiskWorkflow::invalidate($id);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            if ($absolute && is_file($absolute)) @unlink($absolute);
            H::flash('error',$error instanceof \DomainException ? $error->getMessage() : 'The replacement could not be saved; the existing file was retained.');
            H::redirect('/seller/collabs/'.$id);
        }
        if ($old) $this->unlinkProtected((string)$old['storage_path']);
        $this->scanAfterMutation($id);
        H::redirect('/seller/collabs/'.$id);
    }

    public function deleteFile($id,$file): void
    {
        $designer=$this->seller(); H::verifyCsrf(); $collab=$this->event((int)$id);
        if (!$this->repo->changesOpen((int)$id)) H::abort(409);
        DB::begin();
        try {
            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) throw new \DomainException('The File Upload Deadline has passed.');
            $row=DB::row('select * from collab_files where id=? and collab_id=? and designer_id=? for update',[$file,$id,$designer['id']])??throw new \DomainException('That file no longer exists.');
            DB::exec('delete from collab_files where id=? and designer_id=?',[$row['id'],$designer['id']]);
            CollabIpRiskWorkflow::invalidate((int)$id);
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error->getMessage()); H::redirect('/seller/collabs/'.$id);
        }
        $this->unlinkProtected((string)$row['storage_path']);
        $this->scanAfterMutation((int)$id); H::redirect('/seller/collabs/'.$id);
    }

    private function validatedValues(array $input, ?int $id=null): array
    {
        $title=trim((string)($input['title']??'')); $slug=H::slug((string)($input['slug']??$title));
        $price=CreditService::parseCents((string)($input['price']??''),false); $minimum=(int)($input['minimum_file_count']??0); $type=$input['participation_type']??'';
        CollabService::validateDates(str_replace('T',' ',(string)$input['upload_deadline']),str_replace('T',' ',(string)$input['sale_starts_at']),(string)$input['sale_close_date']);
        $description=trim((string)($input['description']??''));
        if (mb_strlen($title)<3 || $description==='' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug) || $price<50 || $minimum<1 || !in_array($type,['open','closed'],true)) throw new \DomainException('Review the title, description, slug, price, participation type, minimum, and dates.');
        if (!DB::row('select 1 ok where cast(? as datetime)>now()', [str_replace('T',' ',(string)$input['upload_deadline'])])) throw new \DomainException('The File Upload Deadline must be in the future.');
        if (DB::row('select id from collab_events where slug=? and id<>?',[$slug,$id??0])) throw new \DomainException('That collab slug is already in use.');
        return [$title,$slug,$description,$price,$type,$minimum,str_replace('T',' ',(string)$input['upload_deadline']),str_replace('T',' ',(string)$input['sale_starts_at']),(string)$input['sale_close_date']];
    }

    private function unlinkProtected(string $path): void { $real=(new CollabService())->protectedRealPath($path); if($real) @unlink($real); }
    private function scanAfterMutation(int $id): void
    {
        try {
            (new CollabIpRiskWorkflow())->scan($id);
        } catch (\Throwable $error) {
            NotificationService::reportFailure('collab_ip_rescan',$error);
            H::flash('warning','The content was saved but its IP scan failed, so the collab remains blocked until scanning succeeds.');
        }
    }
    private function notifyHost(array $collab,string $type,string $title,string $message,string $suffix):void { $u=DB::row('select user_id from designers where id=?',[$collab['host_designer_id']]);$this->notifyUser((int)$u['user_id'],$type,$title,$message,'collab:'.$collab['id'].':'.$suffix,'/seller/collabs/'.$collab['id']); }
    private function notifyUser(int $userId,string $type,string $title,string $message,string $key,string $url):void { try{NotificationService::create($userId,$type,'designer',$title,$message,$key,$url);}catch(\Throwable$error){NotificationService::reportFailure('collab_notification',$error);} }
}
