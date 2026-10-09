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
            'hosted'=>DB::rows('select c.*,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.collab_id=c.id and o.payment_status in ("paid","partially_refunded")) sold_count from collab_events c where c.host_designer_id=? order by c.created_at desc', [$designer['id']]),
            'participating'=>DB::rows('select c.*,cp.eligibility,cp.membership_status,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.collab_id=c.id and o.payment_status in ("paid","partially_refunded")) sold_count from collab_participants cp join collab_events c on c.id=cp.collab_id where cp.designer_id=? and c.host_designer_id<>? order by c.created_at desc', [$designer['id'],$designer['id']]),
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
                $hostTimezone = $this->validatedTimezone($_POST);
                DB::begin();
                DB::exec('insert into collab_events(host_designer_id,title,slug,description,price_cents,quantity_limit,participation_type,minimum_file_count,upload_deadline,sale_starts_at,sale_close_date,ip_risk_state) values(?,?,?,?,?,?,?,?,?,?,? ,"review_required")', array_merge([$designer['id']],$values));
                $id = (int)DB::id();

                DB::exec(
                    'update collab_events set host_timezone=? where id=?',
                    [$hostTimezone,$id]
                );

                DB::exec(
                    'update collab_events set require_mockup=? where id=?',
                    [isset($_POST['require_mockup']) ? 1 : 0, $id]
                );

                DB::exec(
                    'update collab_events set host_notes=? where id=?',
                    [trim((string)($_POST['host_notes'] ?? '')) ?: null, $id]
                );

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
                $hostTimezone = $this->validatedTimezone($_POST);
                DB::begin();
                $locked = DB::row('select * from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [(int)$id]) ?? throw new \DomainException('The collab is no longer editable.');
                $ipChanged = $values[0] !== $locked['title'] || $values[2] !== $locked['description'];
                DB::exec('update collab_events set title=?,slug=?,description=?,price_cents=?,quantity_limit=?,participation_type=?,minimum_file_count=?,upload_deadline=?,sale_starts_at=?,sale_close_date=? where id=? and snapshot_at is null', array_merge($values,[(int)$id]));
                DB::exec(
                    'update collab_events
                     set require_mockup=?
                     where id=? and snapshot_at is null',
                    [isset($_POST['require_mockup']) ? 1 : 0, (int)$id]
                );

                DB::exec(
                    'update collab_events
                     set host_timezone=?
                     where id=? and snapshot_at is null',
                    [$hostTimezone,(int)$id]
                );

                DB::exec(
                    'update collab_events
                     set host_notes=?
                     where id=? and snapshot_at is null',
                    [trim((string)($_POST['host_notes'] ?? '')) ?: null,(int)$id]
                );

                if ($ipChanged) CollabIpRiskWorkflow::invalidate((int)$id);
                DB::commit();
                $this->scanAfterMutation((int)$id);
                H::flash('success','Collab settings updated.'); H::redirect('/seller/collabs/'.$id);
            } catch (\DomainException|\InvalidArgumentException $error) { if(DB::pdo()->inTransaction())DB::rollBack();$errors[]=$error->getMessage(); }
        }
        H::view('collabs/form', ['collab'=>$collab,'errors'=>$errors]);
    }

    public function closeSubmissions($id): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab,$designer);

        try {
            DB::begin();

            $locked = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$id]
            ) ?? throw new \DomainException(
                'Collab not found.'
            );

            if ($locked['snapshot_at'] !== null) {
                throw new \DomainException(
                    'This collab has already been finalized.'
                );
            }

            if (
                strtotime((string)$locked['upload_deadline']) <= time()
            ) {
                throw new \DomainException(
                    'The File Upload Deadline has already passed.'
                );
            }

            $sales = (int)(
                DB::row(
                    'select count(distinct oi.order_id) c
                     from order_items oi
                     join orders o on o.id=oi.order_id
                     where oi.collab_id=?
                       and o.payment_status in ("paid","partially_refunded")',
                    [(int)$id]
                )['c'] ?? 0
            );

            if ($sales > 0) {
                throw new \DomainException(
                    'This collab already has sales and cannot use pre-sale early finalization.'
                );
            }

            $pendingUpdates = (int)(
                DB::row(
                    'select count(*) c
                     from collab_file_update_requests
                     where collab_id=?
                       and status in ("pending","ip_review")',
                    [(int)$id]
                )['c'] ?? 0
            );

            if ($pendingUpdates > 0) {
                throw new \DomainException(
                    'Resolve all pending file update requests before closing submissions.'
                );
            }

            $participants = DB::rows(
                'select
                    cp.id,
                    cp.designer_id,
                    d.display_name,
                    sum(f.file_kind="contribution") contribution_count,
                    sum(
                        f.file_kind="contribution"
                        and f.category_id is null
                    ) uncategorized_count,
                    sum(f.file_kind="terms") terms_count,
                    sum(f.file_kind="mockup") mockup_count
                 from collab_participants cp
                 join designers d on d.id=cp.designer_id
                 left join collab_files f on f.participant_id=cp.id
                 where cp.collab_id=?
                   and cp.membership_status in ("host","accepted")
                 group by
                    cp.id,
                    cp.designer_id,
                    d.display_name
                 order by cp.designer_id',
                [(int)$id]
            );

            if (!$participants) {
                throw new \DomainException(
                    'There are no active participants to finalize.'
                );
            }

            $notReady = [];

            foreach ($participants as $participant) {
                $problems = [];

                if (
                    (int)$participant['contribution_count'] <
                    (int)$locked['minimum_file_count']
                ) {
                    $problems[] = 'needs more contribution files';
                }

                if (
                    (int)$participant['uncategorized_count'] > 0
                ) {
                    $problems[] = 'has uncategorized contribution files';
                }

                if ((int)$participant['terms_count'] < 1) {
                    $problems[] = 'is missing Terms/About files';
                }

                if (
                    !empty($locked['require_mockup'])
                    && (int)$participant['mockup_count'] < 1
                ) {
                    $problems[] = 'is missing a required mockup';
                }

                if ($problems) {
                    $notReady[] =
                        $participant['display_name'].
                        ' — '.
                        implode(', ',$problems);
                }
            }

            if ($notReady) {
                throw new \DomainException(
                    'This collab cannot be closed yet: '.
                    implode('; ',$notReady).'.'
                );
            }

            /*
             * Lock regular uploads immediately.
             * Existing finalization logic requires deadline <= now.
             */
            $closedAtUtc = gmdate(
                'Y-m-d H:i:s',
                time() - 1
            );

            /*
             * Finishing early means the sale may start as soon as
             * the bundle successfully reaches Ready.
             */
            $saleStartUtc = gmdate('Y-m-d H:i:s');

            DB::exec(
                'update collab_events
                 set upload_deadline=?,
                     sale_starts_at=?,
                     status="collecting"
                 where id=?
                   and snapshot_at is null',
                [
                    $closedAtUtc,
                    $saleStartUtc,
                    (int)$id
                ]
            );

            DB::commit();

            /*
             * finalize() performs a fresh IP scan first.
             */
            (new \App\Services\CollabService())
                ->finalize((int)$id);

            $final = DB::row(
                'select
                    status,
                    snapshot_at,
                    final_zip_path,
                    ip_risk_state
                 from collab_events
                 where id=?',
                [(int)$id]
            );

            if (
                $final
                && $final['status'] === 'ready'
                && !empty($final['snapshot_at'])
                && !empty($final['final_zip_path'])
            ) {
                H::flash(
                    'success',
                    'Submissions closed early. The final snapshot and bundle ZIP are ready.'
                );
            } elseif (
                $final
                && in_array(
                    (string)$final['ip_risk_state'],
                    ['review_required','rejected'],
                    true
                )
            ) {
                H::flash(
                    'warning',
                    'Submissions are closed, but Admin IP review is required before the bundle can go live.'
                );
            } else {
                H::flash(
                    'warning',
                    'Submissions are closed, but bundle processing did not finish. Review the collab status before continuing.'
                );
            }

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            H::flash(
                'warning',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : 'The collab could not be closed early.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function extendDeadline($id): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab, $designer);

        $rawDeadline = trim((string)($_POST['upload_deadline'] ?? ''));

        if ($rawDeadline === '') {
            H::flash('warning', 'Choose a new File Upload Deadline.');
            H::redirect('/seller/collabs/'.$id);
        }

        try {
            $hostTimezone = new \DateTimeZone(
                (string)($collab['host_timezone'] ?: 'America/New_York')
            );

            $localDeadline = \DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i',
                $rawDeadline,
                $hostTimezone
            );

            if (!$localDeadline) {
                throw new \DomainException(
                    'Choose a valid File Upload Deadline.'
                );
            }

            $nowLocal = new \DateTimeImmutable('now', $hostTimezone);

            if ($localDeadline <= $nowLocal) {
                throw new \DomainException(
                    'The new File Upload Deadline must be in the future.'
                );
            }

            $saleClose = new \DateTimeImmutable(
                (string)$collab['sale_close_date'].' 23:59:59',
                $hostTimezone
            );

            if ($localDeadline > $saleClose) {
                throw new \DomainException(
                    'The File Upload Deadline must be before the Sales Close Date.'
                );
            }

            $newDeadlineUtc = $localDeadline
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');

            DB::begin();

            $locked = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$id]
            ) ?? throw new \DomainException('Collab not found.');

            $sales = (int)(
                DB::row(
                    'select count(distinct oi.order_id) c
                     from order_items oi
                     join orders o on o.id=oi.order_id
                     where oi.collab_id=?
                       and o.payment_status in ("paid","partially_refunded")',
                    [(int)$id]
                )['c'] ?? 0
            );

            if ($sales > 0) {
                throw new \DomainException(
                    'This deadline cannot be extended because the collab already has sales. Sellers may submit file update requests instead.'
                );
            }

            /*
             * If the existing Sale Start would occur before the new
             * upload deadline, move Sale Start to the new deadline.
             * A collab cannot sell while contributors are still uploading.
             */
            $saleStartUtc = (string)$locked['sale_starts_at'];

            if (
                strtotime($saleStartUtc) <
                strtotime($newDeadlineUtc)
            ) {
                $saleStartUtc = $newDeadlineUtc;
            }

            /*
             * Preserve the old archive path long enough to remove the
             * physical ZIP after the database transaction succeeds.
             */
            $oldZip = (string)($locked['final_zip_path'] ?? '');

            DB::exec(
                'update collab_files
                 set included_in_snapshot=0
                 where collab_id=?',
                [(int)$id]
            );

            DB::exec(
                'update collab_participants
                 set eligibility="pending",
                     qualifying_file_count=null,
                     exclusion_reason=null,
                     eligibility_snapshotted_at=null
                 where collab_id=?
                   and membership_status in ("host","accepted")',
                [(int)$id]
            );

            DB::exec(
                'update collab_events
                 set upload_deadline=?,
                     sale_starts_at=?,
                     status="collecting",
                     snapshot_at=null,
                     eligible_count=null,
                     final_zip_path=null,
                     final_zip_sha256=null,
                     ready_at=null,
                     ended_at=null,
                     zip_error=null,
                     ip_risk_state="review_required",
                     ip_content_fingerprint=null
                 where id=?',
                [
                    $newDeadlineUtc,
                    $saleStartUtc,
                    (int)$id
                ]
            );

            DB::commit();

            if ($oldZip !== '') {
                $real = (new CollabService())->protectedRealPath($oldZip);

                if ($real && is_file($real)) {
                    @unlink($real);
                }
            }

            (new CollabIpRiskWorkflow())->scan((int)$id);

            H::flash(
                'success',
                'File Upload Deadline extended. The collab is reopened for uploads and will be reviewed and rebuilt after the new deadline.'
            );

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            H::flash(
                'warning',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : 'The deadline could not be extended.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function extendSale($id): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab, $designer);

        try {
            if (empty($collab['snapshot_at'])) {
                throw new \DomainException(
                    'Use Edit settings to change sale dates before the collab is finalized.'
                );
            }

            $rawClose = trim(
                (string)($_POST['sale_close_date'] ?? '')
            );

            if ($rawClose === '') {
                throw new \DomainException(
                    'Choose a new Sales Close Date.'
                );
            }

            $hostTimezone = new \DateTimeZone(
                (string)(
                    $collab['host_timezone']
                    ?: 'America/New_York'
                )
            );

            $newClose = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $rawClose,
                $hostTimezone
            );

            if (!$newClose) {
                throw new \DomainException(
                    'Choose a valid Sales Close Date.'
                );
            }

            $newClose = $newClose->setTime(23, 59, 59);

            DB::begin();

            $locked = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$id]
            ) ?? throw new \DomainException(
                'Collab not found.'
            );

            $currentClose = new \DateTimeImmutable(
                (string)$locked['sale_close_date'].' 23:59:59',
                $hostTimezone
            );

            $nowLocal = new \DateTimeImmutable(
                'now',
                $hostTimezone
            );

            if ($newClose <= $currentClose) {
                throw new \DomainException(
                    'The new Sales Close Date must be later than the current close date.'
                );
            }

            if ($newClose <= $nowLocal) {
                throw new \DomainException(
                    'The new Sales Close Date must be in the future.'
                );
            }

            $wasEnded =
                (string)$locked['status'] === 'ended';

            DB::exec(
                'update collab_events
                 set sale_close_date=?,
                     ended_at=null,
                     status=case
                         when status="ended"
                         then "processing"
                         else status
                     end
                 where id=?',
                [
                    $newClose->format('Y-m-d'),
                    (int)$id
                ]
            );

            DB::commit();

            if ($wasEnded) {
                try {
                    (new CollabService())->finalize(
                        (int)$id
                    );
                } catch (\Throwable $error) {
                    H::flash(
                        'warning',
                        'The sale date was extended, but the collab ZIP is still rebuilding. Please check the collab status shortly.'
                    );
                    H::redirect(
                        '/seller/collabs/'.$id
                    );
                }
            }

            H::flash(
                'success',
                'Sales Close Date extended to '.
                $newClose->format('F j, Y').
                '.'
            );

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            H::flash(
                'warning',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : 'The Sales Close Date could not be extended.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function restock($id): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab, $designer);

        try {
            if (empty($collab['snapshot_at'])) {
                throw new \DomainException(
                    'Use Edit settings to change quantity before the collab is finalized.'
                );
            }

            if ($collab['quantity_limit'] === null) {
                throw new \DomainException(
                    'This collab already has unlimited copies.'
                );
            }

            $rawQuantity = trim(
                (string)($_POST['quantity_limit'] ?? '')
            );

            if (
                $rawQuantity === ''
                || !ctype_digit($rawQuantity)
                || (int)$rawQuantity < 1
            ) {
                throw new \DomainException(
                    'Enter a valid new quantity.'
                );
            }

            $newQuantity = (int)$rawQuantity;

            DB::begin();

            $locked = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$id]
            ) ?? throw new \DomainException(
                'Collab not found.'
            );

            if ($locked['quantity_limit'] === null) {
                throw new \DomainException(
                    'This collab already has unlimited copies.'
                );
            }

            $currentQuantity =
                (int)$locked['quantity_limit'];

            $soldCount = (int)(
                DB::row(
                    'select count(*) c
                     from order_items oi
                     join orders o
                       on o.id=oi.order_id
                     where oi.collab_id=?
                       and o.payment_status in (
                           "paid",
                           "partially_refunded"
                       )',
                    [(int)$id]
                )['c'] ?? 0
            );

            if ($newQuantity <= $currentQuantity) {
                throw new \DomainException(
                    'The new quantity must be greater than the current quantity of '.
                    $currentQuantity.
                    '.'
                );
            }

            if ($newQuantity < $soldCount) {
                throw new \DomainException(
                    'The quantity cannot be lower than the number already sold.'
                );
            }

            DB::exec(
                'update collab_events
                 set quantity_limit=?
                 where id=?',
                [
                    $newQuantity,
                    (int)$id
                ]
            );

            DB::commit();

            H::flash(
                'success',
                'Collab quantity increased to '.
                $newQuantity.
                ' copies.'
            );

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            H::flash(
                'warning',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : 'The collab quantity could not be updated.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function show($id): void
    {
        $designer=$this->seller(); $collab=$this->event((int)$id); $participant=$this->repo->participant((int)$id,(int)$designer['id']);
        if (!$participant) H::abort(403);
        $participants=$this->repo->participants((int)$id);
        $soldCount=(int)(DB::row('select count(*) c from order_items oi join orders o on o.id=oi.order_id where oi.collab_id=? and o.payment_status in ("paid","partially_refunded")',[(int)$id])['c']??0);
        $hostPreviewFiles = [];
        if ((int)$collab['host_designer_id'] === (int)$designer['id']) {
            foreach (DB::rows(
                'select id,designer_id,original_name,mime_type,category_id from collab_files where collab_id=? and file_kind="contribution" order by designer_id,id',
                [(int)$id]
            ) as $previewFile) {
                $hostPreviewFiles[(int)$previewFile['designer_id']][] = $previewFile;
            }
        }

        H::view('collabs/show', ['collab'=>$collab,'participant'=>$participant,'participants'=>$participants,'files'=>DB::rows('select * from collab_files where collab_id=? and designer_id=? order by id',[$id,$designer['id']]),'soldCount'=>$soldCount,'hostPreviewFiles'=>$hostPreviewFiles,'changesOpen'=>$this->repo->changesOpen((int)$id),'inviteToken'=>$_SESSION['collab_invite_token_'.$id]??null]);
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

    public function removeParticipant($id,$participantId): void
    {
        $designer=$this->seller();
        H::verifyCsrf();
        $collab=$this->event((int)$id);
        $this->requireHost($collab,$designer);

        if (!$this->repo->changesOpen((int)$id)) H::abort(409);

        $files=[];

        try {
            DB::begin();

            if (!DB::row(
                'select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update',
                [(int)$id]
            )) {
                throw new \DomainException('The File Upload Deadline has passed.');
            }

            $target=DB::row(
                'select cp.*,d.user_id,d.display_name
                 from collab_participants cp
                 join designers d on d.id=cp.designer_id
                 where cp.id=?
                   and cp.collab_id=?
                   and cp.designer_id<>?
                   and cp.membership_status not in ("left","removed")
                 for update',
                [(int)$participantId,(int)$id,(int)$designer['id']]
            ) ?? throw new \DomainException('That participant cannot be removed.');

            $files=DB::rows(
                'select storage_path from collab_files where participant_id=?',
                [(int)$target['id']]
            );

            DB::exec(
                'delete from collab_files where participant_id=?',
                [(int)$target['id']]
            );

            DB::exec(
                'update collab_participants
                 set membership_status="removed",
                     eligibility="pending",
                     qualifying_file_count=null,
                     exclusion_reason=null,
                     eligibility_snapshotted_at=null
                 where id=?',
                [(int)$target['id']]
            );

            CollabIpRiskWorkflow::invalidate((int)$id);

            DB::commit();

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error->getMessage());
            H::redirect('/seller/collabs/'.$id);
        }

        foreach ($files as $file) {
            $this->unlinkProtected((string)$file['storage_path']);
        }

        $this->scanAfterMutation((int)$id);

        $this->notifyUser(
            (int)$target['user_id'],
            'collab_removed',
            'Removed from collab',
            'You were removed from “'.$collab['title'].'”.',
            'collab:'.$id.':removed:'.$target['id'],
            '/seller/collabs'
        );

        H::flash('success',$target['display_name'].' was removed from the collab.');
        H::redirect('/seller/collabs/'.$id);
    }

    public function leaveCollab($id): void
    {
        $designer=$this->seller();
        H::verifyCsrf();
        $collab=$this->event((int)$id);

        if ((int)$collab['host_designer_id']===(int)$designer['id']) {
            H::flash('warning','The collab host cannot leave their own collab.');
            H::redirect('/seller/collabs/'.$id);
        }

        if (!$this->repo->changesOpen((int)$id)) H::abort(409);

        $files=[];

        try {
            DB::begin();

            if (!DB::row(
                'select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update',
                [(int)$id]
            )) {
                throw new \DomainException('The File Upload Deadline has passed.');
            }

            $participant=DB::row(
                'select * from collab_participants
                 where collab_id=?
                   and designer_id=?
                   and membership_status="accepted"
                 for update',
                [(int)$id,(int)$designer['id']]
            ) ?? throw new \DomainException('You are not an active participant in this collab.');

            $files=DB::rows(
                'select storage_path from collab_files where participant_id=?',
                [(int)$participant['id']]
            );

            DB::exec(
                'delete from collab_files where participant_id=?',
                [(int)$participant['id']]
            );

            DB::exec(
                'update collab_participants
                 set membership_status="left",
                     eligibility="pending",
                     qualifying_file_count=null,
                     exclusion_reason=null,
                     eligibility_snapshotted_at=null
                 where id=?',
                [(int)$participant['id']]
            );

            CollabIpRiskWorkflow::invalidate((int)$id);

            DB::commit();

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('warning',$error->getMessage());
            H::redirect('/seller/collabs/'.$id);
        }

        foreach ($files as $file) {
            $this->unlinkProtected((string)$file['storage_path']);
        }

        $this->scanAfterMutation((int)$id);

        $this->notifyHost(
            $collab,
            'collab_participant_left',
            'Participant left collab',
            $designer['display_name'].' left “'.$collab['title'].'”.',
            'left:'.$designer['id']
        );

        H::flash('success','You left the collab.');
        H::redirect('/seller/collabs');
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

    public function previewFile($id,$file): void
    {
        $designer = $this->seller();
        $collab = $this->event((int)$id);

        $row = DB::row(
            'select * from collab_files where id=? and collab_id=?',
            [(int)$file,(int)$id]
        );

        if (!$row) {
            H::abort(404);
        }

        $isHost =
            (int)$collab['host_designer_id'] === (int)$designer['id'];

        $isOwner =
            (int)$row['designer_id'] === (int)$designer['id'];

        if (!$isHost && !$isOwner) {
            H::abort(403);
        }

        if (!str_starts_with((string)$row['mime_type'],'image/')) {
            H::abort(404);
        }

        $real = (new CollabService())->protectedRealPath((string)$row['storage_path']);

        if (!$real) H::abort(404);

        $storeRow = DB::row(
            'select store_slug from designers where id=?',
            [(int)$row['designer_id']]
        );

        $storeSlug = trim((string)($storeRow['store_slug'] ?? ''));

        $storeUrl = $storeSlug !== ''
            ? 'creativemoth.com/store/' . $storeSlug
            : null;

        $ext = match (strtolower((string)$row['mime_type'])) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => strtolower((string)pathinfo((string)$row['original_name'], PATHINFO_EXTENSION)) ?: 'png',
        };

        /*
         * Keep generated previews beside the protected source tree.
         * This location is already writable by the collab upload system
         * and remains outside public web storage.
         */
        $cacheDir = dirname($real) . '/.preview-cache';

        if (
            !is_dir($cacheDir) &&
            !mkdir($cacheDir, 0750, true) &&
            !is_dir($cacheDir)
        ) {
            error_log(
                'Collab preview cache directory could not be created: ' .
                $cacheDir
            );

            H::abort(500);
        }

        /*
         * The participant grid uses this route inside <img>, while
         * clicking the image opens it as a browser document.
         *
         * Never generate giant original-resolution images merely
         * to display a 92px thumbnail.
         */
        $fetchDestination = strtolower(
            trim((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''))
        );

        $isThumbnailRequest = $fetchDestination === 'image';

        $previewVariant = $isThumbnailRequest
            ? 'thumb'
            : 'large';

        $maxPreviewDimension = $isThumbnailRequest
            ? 180
            : 500;

        $cacheKey = sha1(
            (string)$row['id'] . '|' .
            (string)$row['designer_id'] . '|' .
            (string)$row['storage_path'] . '|' .
            (string)filesize($real) . '|' .
            (string)filemtime($real) . '|' .
            (string)$storeUrl . '|' .
            $previewVariant . '|' .
            $maxPreviewDimension
        );

        $previewAbs = $cacheDir . '/' . $cacheKey . '.' . $ext;

        if (!is_file($previewAbs)) {
            if ($isThumbnailRequest) {
                $result = \App\Services\WatermarkService::createCollabThumbnail(
                    $real,
                    $previewAbs,
                    180
                );
            } else {
                $result = \App\Services\WatermarkService::createProtectedImagePreview(
                    $real,
                    $previewAbs,
                    true,
                    $storeUrl,
                    500
                );
            }

            if (empty($result['ok'])) {
                @unlink($previewAbs);

                error_log(
                    'Collab protected preview generation failed for file ' .
                    (int)$row['id'] .
                    ': ' .
                    (string)($result['message'] ?? 'Unknown watermark error')
                );

                H::abort(500);
            }
        }

        $generatedSize = @getimagesize($previewAbs);

        if (
            !$generatedSize ||
            max(
                (int)$generatedSize[0],
                (int)$generatedSize[1]
            ) > $maxPreviewDimension
        ) {
            @unlink($previewAbs);

            error_log(
                'Collab preview exceeded requested dimensions for file ' .
                (int)$row['id']
            );

            H::abort(500);
        }

        $previewMime = (new \finfo(FILEINFO_MIME_TYPE))->file($previewAbs)
            ?: (string)$row['mime_type'];

        header(
            'X-Creative-Moth-Preview-Size: ' .
            (int)$generatedSize[0] .
            'x' .
            (int)$generatedSize[1]
        );

        $inlineName = 'preview-' . preg_replace(
            '/[^A-Za-z0-9._-]/',
            '-',
            (string)$row['original_name']
        );

        header('Content-Type: ' . $previewMime);
        header('Content-Length: ' . filesize($previewAbs));
        header('Content-Disposition: inline; filename="' . $inlineName . '"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        readfile($previewAbs);
        exit;
    }

    public function upload($id): void { $this->storeUpload((int)$id, null); }
    public function replace($id,$file): void
    {
        /*
         * Legacy route protection:
         * replacing an existing collab file must always go through
         * the host-approved update-request workflow.
         */
        $this->requestFileUpdate($id,$file);
    }

    public function requestFileUpdate($id,$file): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);

        $participant = $this->repo->participant(
            (int)$id,
            (int)$designer['id']
        );

        if (
            !$participant ||
            !in_array(
                $participant['membership_status'],
                ['host','accepted'],
                true
            )
        ) {
            H::abort(403);
        }

        $current = DB::row(
            'select *
             from collab_files
             where id=?
               and collab_id=?
               and designer_id=?',
            [
                (int)$file,
                (int)$id,
                (int)$designer['id']
            ]
        );

        if (!$current) {
            H::abort(404);
        }

        $existing = DB::row(
            'select id
             from collab_file_update_requests
             where collab_file_id=?
               and status in ("pending","ip_review")
             limit 1',
            [(int)$file]
        );

        if ($existing) {
            H::flash(
                'warning',
                'This file already has a pending update request.'
            );

            H::redirect('/seller/collabs/'.$id);
        }

        $upload = $_FILES['file'] ?? [];

        try {
            $kind = \App\Services\CollabUploadValidator::replacementKind(
                (string)$current['file_kind'],
                (string)$current['file_kind']
            );

            $validated =
                (new \App\Services\CollabUploadValidator())
                    ->validate($upload,$kind);

            $directory = app_path(
                'storage/protected_uploads/collabs/'.
                (int)$id.'/'.
                (int)$designer['id'].
                '/update-requests'
            );

            if (
                !is_dir($directory) &&
                !mkdir($directory,0750,true) &&
                !is_dir($directory)
            ) {
                throw new \RuntimeException(
                    'Protected update-request directory unavailable.'
                );
            }

            $stored =
                bin2hex(random_bytes(16)).
                '.'.
                $validated['extension'];

            $absolute = $directory.'/'.$stored;

            if (
                !move_uploaded_file(
                    $validated['temporary'],
                    $absolute
                )
            ) {
                throw new \RuntimeException(
                    'Replacement file could not be saved.'
                );
            }

            $reason = trim(
                (string)($_POST['reason'] ?? '')
            );

            DB::exec(
                'insert into collab_file_update_requests(
                    collab_id,
                    collab_file_id,
                    base_sha256,
                    designer_id,
                    reason,
                    original_name,
                    stored_name,
                    storage_path,
                    mime_type,
                    byte_size,
                    sha256
                 ) values(?,?,?,?,?,?,?,?,?,?,?)',
                [
                    (int)$id,
                    (int)$file,
                    (string)$current['sha256'],
                    (int)$designer['id'],
                    $reason !== '' ? mb_substr($reason,0,1000) : null,
                    $validated['original'],
                    $stored,
                    'collabs/'.
                        (int)$id.'/'.
                        (int)$designer['id'].
                        '/update-requests/'.
                        $stored,
                    $validated['mime'],
                    $validated['size'],
                    hash_file('sha256',$absolute)
                ]
            );

            $requestId = (int)DB::id();

            $host = DB::row(
                'select u.id
                 from designers d
                 join users u on u.id=d.user_id
                 where d.id=?',
                [(int)$collab['host_designer_id']]
            );

            if ($host) {
                \App\Services\NotificationService::create(
                    (int)$host['id'],
                    'collab_file_update_request',
                    'designer',
                    'Collab file update requested',
                    $designer['display_name'].
                    ' requested to update a file in “'.
                    $collab['title'].'”.',
                    'collab:'.
                    (int)$id.
                    ':file-update-request:'.
                    $requestId,
                    '/seller/collabs/'.$id
                );
            }

            H::flash(
                'success',
                'File update request submitted to the collab host.'
            );

        } catch (\Throwable $error) {
            H::flash(
                'warning',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : 'The file update request could not be submitted.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function approveFileUpdate($id,$request): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab,$designer);

        try {
            $result =
                (new \App\Services\CollabFileUpdateService())
                    ->hostApprove(
                        (int)$request,
                        (int)$designer['id']
                    );

            if (($result['state'] ?? '') === 'ip_review') {
                H::flash(
                    'success',
                    'Host approval saved. This replacement requires Admin IP review before it can replace the current file.'
                );
            } else {
                H::flash(
                    'success',
                    'File update approved and processed.'
                );
            }

        } catch (\DomainException|\InvalidArgumentException $error) {
            H::flash(
                'warning',
                $error->getMessage()
            );

        } catch (\Throwable $error) {
            \App\Services\NotificationService::reportFailure(
                'collab_file_update_approval',
                $error
            );

            H::flash(
                'warning',
                'The update could not be completed. The existing collab file and bundle remain unchanged.'
            );
        }

        H::redirect('/seller/collabs/'.$id);
    }

    public function denyFileUpdate($id,$request): void
    {
        $designer = $this->seller();
        H::verifyCsrf();

        $collab = $this->event((int)$id);
        $this->requireHost($collab,$designer);

        $row = DB::row(
            'select *
             from collab_file_update_requests
             where id=?
               and collab_id=?
               and status="pending"',
            [
                (int)$request,
                (int)$id
            ]
        );

        if (!$row) {
            H::flash(
                'warning',
                'That update request is no longer pending.'
            );

            H::redirect('/seller/collabs/'.$id);
        }

        DB::exec(
            'update collab_file_update_requests
             set status="denied",
                 reviewed_by_designer_id=?,
                 reviewed_at=now()
             where id=?
               and status="pending"',
            [
                (int)$designer['id'],
                (int)$request
            ]
        );

        $real = (new \App\Services\CollabService())
            ->protectedRealPath(
                (string)$row['storage_path']
            );

        if ($real && is_file($real)) {
            @unlink($real);
        }

        H::flash(
            'success',
            'File update request denied. The current collab file was not changed.'
        );

        H::redirect('/seller/collabs/'.$id);
    }

    private function storeUpload(int $id, ?int $replaceId): void
    {
        $designer = $this->seller();
        H::verifyCsrf();
        if (!$this->repo->changesOpen($id)) H::abort(409);
        $submittedKind = (string)($_POST['file_kind'] ?? '');
        $submittedCategoryId = trim((string)($_POST['category_id'] ?? ''));
        $old = null;
        $written = [];

        if ($replaceId !== null) {
            $uploads = [$_FILES['file'] ?? []];
        } else {
            $batch = $_FILES['files'] ?? [];
            $uploads = [];

            if (isset($batch['name']) && is_array($batch['name'])) {
                foreach ($batch['name'] as $i => $name) {
                    if (($batch['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;

                    $uploads[] = [
                        'name' => $name,
                        'type' => $batch['type'][$i] ?? '',
                        'tmp_name' => $batch['tmp_name'][$i] ?? '',
                        'error' => $batch['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $batch['size'][$i] ?? 0,
                    ];
                }
            }

            if (!$uploads) throw new \DomainException('Choose at least one file to upload.');
        }

        try {
            DB::begin();

            if (!DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() for update', [$id])) {
                throw new \DomainException('The File Upload Deadline has passed.');
            }

            $participant = DB::row(
                'select * from collab_participants where collab_id=? and designer_id=? and membership_status in ("host","accepted") for update',
                [$id,$designer['id']]
            );

            if (!$participant) throw new \DomainException('You are not an accepted participant.');

            if ($replaceId !== null) {
                $old = DB::row(
                    'select * from collab_files where id=? and collab_id=? and designer_id=? for update',
                    [$replaceId,$id,$designer['id']]
                );

                if (!$old) throw new \DomainException('The file being replaced no longer exists.');

                $kind = \App\Services\CollabUploadValidator::replacementKind(
                    (string)$old['file_kind'],
                    $submittedKind
                );
            } else {
                $kind = $submittedKind;
            }

            $categoryId = null;

            if ($kind === 'contribution') {
                if ($replaceId !== null) {
                    $categoryId = !empty($old['category_id']) ? (int)$old['category_id'] : null;
                } else {
                    if ($submittedCategoryId === '' || !ctype_digit($submittedCategoryId)) {
                        throw new \DomainException('Choose a category for contribution files.');
                    }

                    $category = DB::row(
                        'select id
                         from categories
                         where id=?
                           and is_active=1
                           and slug not in (?, ?)
                           and not (lower(name) in (?, ?) and slug<>?)',
                        [
                            (int)$submittedCategoryId,
                            'sublimation',
                            'png',
                            'png',
                            'png files',
                            'png-files'
                        ]
                    );

                    if (!$category) {
                        throw new \DomainException('Choose a valid marketplace category.');
                    }

                    $categoryId = (int)$category['id'];
                }
            }

            $directory = app_path('storage/protected_uploads/collabs/'.$id.'/'.$designer['id']);

            if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) {
                throw new \RuntimeException('Protected upload directory unavailable.');
            }

            foreach ($uploads as $upload) {
                $validated = (new \App\Services\CollabUploadValidator())->validate($upload,$kind);

                $stored = bin2hex(random_bytes(16)).'.'.$validated['extension'];
                $absolute = $directory.'/'.$stored;

                if (!move_uploaded_file($validated['temporary'],$absolute)) {
                    throw new \RuntimeException('File could not be saved.');
                }

                $written[] = $absolute;

                DB::exec(
                    'insert into collab_files(collab_id,participant_id,designer_id,file_kind,category_id,original_name,stored_name,storage_path,mime_type,byte_size,sha256) values(?,?,?,?,?,?,?,?,?,?,?)',
                    [$id,$participant['id'],$designer['id'],$kind,$categoryId,$validated['original'],$stored,'collabs/'.$id.'/'.$designer['id'].'/'.$stored,$validated['mime'],$validated['size'],hash_file('sha256',$absolute)]
                );

                $newFileId = (int)DB::pdo()->lastInsertId();

                if (str_starts_with(strtolower((string)$validated['mime']), 'image/')) {
                    $thumbExt = match (strtolower((string)$validated['mime'])) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        default      => null,
                    };

                    if ($thumbExt !== null) {
                        $thumbDir = public_path('uploads/collab-thumbnails');

                        if (!is_dir($thumbDir)) {
                            @mkdir($thumbDir, 0755, true);
                        }

                        @chmod($thumbDir, 0755);

                        $thumbPath =
                            $thumbDir .
                            '/c' . $id .
                            '-f' . $newFileId .
                            '.' . $thumbExt;

                        $thumbResult =
                            \App\Services\WatermarkService::createCollabThumbnail(
                                $absolute,
                                $thumbPath,
                                120
                            );

                        if (!empty($thumbResult['ok'])) {
                            @chmod($thumbPath, 0644);
                        } else {
                            @unlink($thumbPath);

                            error_log(
                                'Collab thumbnail generation failed for file ' .
                                $newFileId .
                                ': ' .
                                ($thumbResult['message'] ?? 'unknown error')
                            );
                        }
                    }
                }
            }

            if ($old) {
                DB::exec('delete from collab_files where id=? and designer_id=?', [$old['id'],$designer['id']]);
            }

            CollabIpRiskWorkflow::invalidate($id);
            DB::commit();

        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();

            foreach ($written as $absolute) {
                if (is_file($absolute)) @unlink($absolute);
            }

            H::flash(
                'error',
                $error instanceof \DomainException
                    ? $error->getMessage()
                    : ($replaceId !== null ? 'The replacement could not be saved; the existing file was retained.' : 'The files could not be saved.')
            );

            H::redirect('/seller/collabs/'.$id);
        }

        if ($old) $this->unlinkProtected((string)$old['storage_path']);
        $this->scanAfterMutation($id);
        H::redirect('/seller/collabs/'.$id);
    }

    public function updateFileCategory($id,$file): void
    {
        $designer=$this->seller();
        H::verifyCsrf();

        if (!$this->repo->changesOpen((int)$id)) H::abort(409);

        $categoryRaw=trim((string)($_POST['category_id']??''));

        if ($categoryRaw==='' || !ctype_digit($categoryRaw)) {
            H::flash('warning','Choose a category.');
            H::redirect('/seller/collabs/'.$id);
        }

        $category=DB::row(
            'select id
             from categories
             where id=?
               and is_active=1
               and slug not in (?, ?)
               and not (lower(name) in (?, ?) and slug<>?)',
            [
                (int)$categoryRaw,
                'sublimation',
                'png',
                'png',
                'png files',
                'png-files'
            ]
        );

        if (!$category) {
            H::flash('warning','Choose a valid marketplace category.');
            H::redirect('/seller/collabs/'.$id);
        }

        $row=DB::row(
            'select id
             from collab_files
             where id=?
               and collab_id=?
               and designer_id=?
               and file_kind="contribution"',
            [(int)$file,(int)$id,(int)$designer['id']]
        ) ?? H::abort(404);

        DB::exec(
            'update collab_files set category_id=? where id=?',
            [(int)$category['id'],(int)$row['id']]
        );

        H::flash('success','Contribution category updated.');
        H::redirect('/seller/collabs/'.$id);
    }

    public function updateFileName($id,$file): void
    {
        $designer=$this->seller();
        H::verifyCsrf();

        if (!$this->repo->changesOpen((int)$id)) {
            H::abort(409);
        }

        $row=DB::row(
            'select id,original_name
             from collab_files
             where id=?
               and collab_id=?
               and designer_id=?',
            [(int)$file,(int)$id,(int)$designer['id']]
        ) ?? H::abort(404);

        $newName=trim((string)($_POST['original_name']??''));

        if (
            $newName==='' ||
            mb_strlen($newName)>255 ||
            $newName==='.' ||
            $newName==='..' ||
            str_contains($newName,"\0") ||
            str_contains($newName,'/') ||
            str_contains($newName,'\\')
        ) {
            H::flash('warning','Enter a valid file name.');
            H::redirect('/seller/collabs/'.$id);
        }

        $oldExtension=strtolower(
            (string)pathinfo(
                (string)$row['original_name'],
                PATHINFO_EXTENSION
            )
        );

        $newExtension=strtolower(
            (string)pathinfo(
                $newName,
                PATHINFO_EXTENSION
            )
        );

        if ($oldExtension!==$newExtension) {
            H::flash(
                'warning',
                'The file extension cannot be changed. Keep the original .'
                .($oldExtension!==''?$oldExtension:'file')
                .' extension.'
            );
            H::redirect('/seller/collabs/'.$id);
        }

        if (
            $oldExtension!=='' &&
            trim(
                (string)pathinfo(
                    $newName,
                    PATHINFO_FILENAME
                )
            )===''
        ) {
            H::flash('warning','Enter a file name before the extension.');
            H::redirect('/seller/collabs/'.$id);
        }

        if ($newName===(string)$row['original_name']) {
            H::flash('success','File name is already up to date.');
            H::redirect('/seller/collabs/'.$id);
        }

        DB::exec(
            'update collab_files
             set original_name=?
             where id=?
               and collab_id=?
               and designer_id=?',
            [
                $newName,
                (int)$row['id'],
                (int)$id,
                (int)$designer['id']
            ]
        );

        CollabIpRiskWorkflow::invalidate((int)$id);
        $this->scanAfterMutation((int)$id);

        H::flash('success','File name updated.');
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
        $title=trim((string)($input['title']??''));
        $rawSlug=trim((string)($input['slug']??''));
        $slug=H::slug($rawSlug !== '' ? $rawSlug : $title);

        $price=CreditService::parseCents((string)($input['price']??''),false);

        $quantityRaw=trim((string)($input['quantity_limit']??''));
        $quantity=$quantityRaw===''?null:(ctype_digit($quantityRaw)?(int)$quantityRaw:0);

        $minimum=(int)($input['minimum_file_count']??0);
        $type=$input['participation_type']??'';
        $description=trim((string)($input['description']??''));

        $hostTimezone=$this->validatedTimezone($input);
        $hostZone=new \DateTimeZone($hostTimezone);
        $utc=new \DateTimeZone('UTC');

        try {
            $deadlineLocal=new \DateTimeImmutable(
                str_replace('T',' ',(string)($input['upload_deadline']??'')),
                $hostZone
            );

            $startLocal=new \DateTimeImmutable(
                str_replace('T',' ',(string)($input['sale_starts_at']??'')),
                $hostZone
            );

            $closeLocal=new \DateTimeImmutable(
                (string)($input['sale_close_date']??'').' 23:59:59',
                $hostZone
            );
        } catch (\Throwable $error) {
            throw new \DomainException('Enter valid collab dates and times.');
        }

        if (
            mb_strlen($title)<3
            || $description===''
            || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)
            || $price<50
            || ($quantityRaw!=='' && $quantity<1)
            || $minimum<1
            || !in_array($type,['open','closed'],true)
        ) {
            throw new \DomainException(
                'Review the title, description, slug, price, quantity, participation type, minimum, and dates.'
            );
        }

        if ($deadlineLocal > $startLocal || $startLocal > $closeLocal) {
            throw new \DomainException(
                'Dates must follow File Upload Deadline ≤ Sale Start ≤ end of Sale Close Date.'
            );
        }

        $nowHost=new \DateTimeImmutable('now',$hostZone);

        if ($deadlineLocal <= $nowHost) {
            throw new \DomainException('The File Upload Deadline must be in the future.');
        }

        if (DB::row(
            'select id from collab_events where slug=? and id<>?',
            [$slug,$id??0]
        )) {
            throw new \DomainException('That collab slug is already in use.');
        }

        return [
            $title,
            $slug,
            $description,
            $price,
            $quantity,
            $type,
            $minimum,
            $deadlineLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            $closeLocal->format('Y-m-d')
        ];
    }

    private function validatedTimezone(array $input): string
    {
        $timezone=trim((string)($input['host_timezone']??''));

        if (
            $timezone===''
            || !in_array($timezone,\DateTimeZone::listIdentifiers(),true)
        ) {
            throw new \DomainException('Choose a valid host time zone.');
        }

        return $timezone;
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
