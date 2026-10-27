<?php
namespace App\Services;
use App\Core\Database as DB;
use DomainException;
use Throwable;

final class SocialContentService
{
    public const TYPES=['spotlight'=>'New Seller Spotlight','sale'=>'Grand Opening Sale','battle'=>'Battle','wheel'=>'Prize Wheel','passport'=>'Passport','giveaway'=>'Giveaway','referral'=>'Referral','highlight'=>'Event / Prize Highlight'];
    private function access(int $actor,bool $manage=false): void
    {
        $permissions=new AdminPermissionService();
        if (!$permissions->can($actor,'social_content.view') || ($manage && !$permissions->can($actor,'social_content.manage'))) {
            throw new DomainException('Social Content permission is required.');
        }
    }
    private function card(string $key,string $type,string $title,string $url,string $date,string $label,string $caption,string $detail='',string $image=''): array
    {
        return ['key'=>$key,'type'=>$type,'title'=>$title,'url'=>$url,'date'=>$date,'date_label'=>$label,'caption'=>$caption,'detail'=>$detail,'image'=>$image,'status'=>'pending','version'=>0,'done_at'=>null,'done_by_name'=>null];
    }
    public function items(int $actor): array
    {
        $this->access($actor);
        $items=[];
        $event=DB::row('select starts_at,ends_at,is_enabled from grand_opening_events where id=1');
        $state=$event ? GrandOpeningSaleService::state($event) : 'unscheduled';
        $shops=DB::rows('select d.id,d.display_name,d.store_slug,d.avatar_path,d.created_at,d.prize_wheel_opt_in,
            s.is_participating,s.discount_basis_points,s.product_scope,
            exists(select 1 from battle_participants bp join battle_events be on be.id=bp.battle_id where bp.designer_id=d.id and bp.status in ("active","champion") and be.status in ("signup","seeded","active")) in_battle
            from designers d join users u on u.id=d.user_id
            left join grand_opening_sales s on s.designer_id=d.id and s.event_id=1
            where d.status="approved" and u.status="active" and coalesce(d.store_slug,"")<>""
              and exists(select 1 from products p where p.designer_id=d.id and p.status="approved")
            order by d.created_at desc,d.id desc');
        foreach ($shops as $d) {
            $details=[];
            if ($d['is_participating']) $details[]='Sale: '.number_format((int)$d['discount_basis_points']/100,2).'% off '.($d['product_scope']==='shop'?'shop-wide':'selected products').' (event '.$state.')';
            if ($d['in_battle']) $details[]='Battle participant';
            if ($d['prize_wheel_opt_in']) $details[]='Prize Wheel opted in';
            $items[]=$this->card('spotlight:'.$d['id'],'spotlight',$d['display_name'],'/store/'.rawurlencode($d['store_slug']),$d['created_at'],'Store joined',
                'Meet '.$d['display_name'].' on Creative Moth! Explore their digital designs and find your next creative project. Shop their store:',implode(' · ',$details),$d['avatar_path']??'');
        }
        if ($event && in_array($state,['upcoming','live'],true)) {
            $key=substr(hash('sha256',$event['starts_at'].'|'.$event['ends_at']),0,16);
            if ($state==='upcoming') {
                $items[]=$this->card('sale:'.$key.':save-date','sale','Grand Opening — save the date','/',$event['starts_at'],'Sale starts',
                    'Save the date for the Creative Moth Grand Opening! Discover independent designers and get ready for opening deals. Explore the marketplace:');
            } else {
                foreach (['launch'=>'Grand Opening Sale is live','reminder'=>'Grand Opening Sale reminder','last-call'=>'Grand Opening Sale — last call'] as $beat=>$title) {
                    if ($beat==='last-call' && strtotime($event['ends_at'].' UTC')>time()+86400) continue;
                    $items[]=$this->card('sale:'.$key.':'.$beat,'sale',$title,'/browse?grand_opening=1',$event['ends_at'],'Sale ends',
                        ($beat==='last-call'?'Last chance! ':'').'Shop Grand Opening deals from participating Creative Moth designers. Sale discounts include paid license add-ons, and the better sale or coupon savings applies automatically. Find your next design:');
                }
            }
            $items[]=$this->card('passport:'.$key,'passport','Grand Opening Passport','/dashboard/passport',$event['starts_at'],'Event starts',
                'Make the most of the Creative Moth celebration! Sign in to see your Grand Opening Passport activities and progress:');
        }
        foreach (DB::rows('select id,title,slug,status,current_round,created_at from battle_events where status in ("signup","seeded","active","completed") order by id') as $b) {
            $phase=$b['status']==='active'?'round-'.(int)$b['current_round']:$b['status'];
            $url='/battle-of-the-designers/'.rawurlencode($b['slug']);
            $caption=match($b['status']) {
                'signup'=>'Designers: Battle signups are open! Check the event details and join the celebration:',
                'active'=>'Meet the designers in the current Battle round! Visit the event page for matchups and voting details:',
                'completed'=>'The Battle has wrapped up! See the results and celebrate the participating designers:',
                default=>'The Battle lineup is taking shape! Explore the event and meet the participating designers:',
            };
            $items[]=$this->card('battle:'.$b['id'].':'.$phase,'battle',$b['title'].' — '.$phase,$url,$b['created_at'],'Event created',$caption);
        }
        $wheel=DB::row('select is_enabled,heading,enabled_at from prize_wheel_settings where id=1');
        if ($wheel && $wheel['is_enabled']) foreach ($shops as $d) {
            if (!$d['prize_wheel_opt_in']) continue;
            $items[]=$this->card('wheel:'.$d['id'].':'.substr(hash('sha256',(string)$wheel['enabled_at']),0,12),'wheel',$d['display_name'].' — Prize Wheel','/store/'.rawurlencode($d['store_slug']),$wheel['enabled_at']??'','Wheel enabled',
                'Discover '.$d['display_name'].' on Creative Moth! Visit their shop for Prize Wheel availability, entry requirements, and prize details:', '', $d['avatar_path']??'');
        }
        foreach (DB::rows('select id,content_type,title,target_path,relevant_at,caption from social_content_manual order by id') as $m) {
            $items[]=$this->card('manual:'.$m['id'],$m['content_type'],$m['title'],$m['target_path'],$m['relevant_at']??'','Planned date',$m['caption'],'Added by your team for the social media manager to post.');
        }
        $states=[];
        foreach (DB::rows('select s.content_key,s.status,s.version,s.done_at,u.name done_by_name from social_content_states s left join users u on u.id=s.done_by') as $s) $states[$s['content_key']]=$s;
        foreach ($items as &$item) if (isset($states[$item['key']])) {
            $s=$states[$item['key']];
            foreach (['status','version','done_at','done_by_name'] as $field) $item[$field]=$s[$field];
        }
        unset($item);
        return $items;
    }
    public function mark(int $actor,string $key,string $to,int $version): void
    {
        $this->access($actor,true);
        if (!in_array($to,['pending','done'],true) || $version<0) throw new DomainException('Invalid queue action.');
        $found=null;
        foreach ($this->items($actor) as $item) if ($item['key']===$key) {$found=$item;break;}
        if (!$found) throw new DomainException('This content is no longer eligible. Refresh the queue.');
        DB::begin();
        try {
            DB::exec('insert ignore into social_content_states(content_key,status,version) values(?,"pending",0)',[$key]);
            $row=DB::row('select status,version from social_content_states where content_key=? for update',[$key]);
            if ((int)$row['version']!==$version) throw new DomainException('Someone already changed this item. Refresh before trying again.');
            if ($row['status']!==$to) {
                DB::exec('update social_content_states set status=?,version=version+1,done_at=?,done_by=? where content_key=?',[$to,$to==='done'?gmdate('Y-m-d H:i:s'):null,$to==='done'?$actor:null,$key]);
                DB::exec('insert into social_content_audits(content_key,action,actor_id,title_snapshot) values(?,?,?,?)',[$key,$to==='done'?'done':'reopened',$actor,$found['title']]);
            }
            DB::commit();
        } catch (Throwable $e) {if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }
    public function create(int $actor,array $input): void
    {
        $this->access($actor,true);
        $type=(string)($input['content_type']??'');$title=trim((string)($input['title']??''));
        $path=trim((string)($input['target_path']??''));$caption=trim((string)($input['caption']??''));
        if (!in_array($type,['giveaway','referral','highlight'],true)) throw new DomainException('Choose a valid content type.');
        if ($title==='' || mb_strlen($title)>190 || $caption==='' || mb_strlen($caption)>4000) throw new DomainException('Enter a title (up to 190 characters) and caption (up to 4,000 characters).');
        if (strlen($path)>500 || !preg_match('~^/(?:[a-zA-Z0-9_-]+/?)*(?:\?[a-zA-Z0-9_=&%.-]+)?$~D',$path) || preg_match('~^/(admin|seller|api|download)(/|\?|$)~i',$path)) throw new DomainException('Use a public site path such as /browse or /battle-of-the-designers.');
        $date=GrandOpeningSaleService::utcInput(trim((string)($input['relevant_at']??'')));
        DB::begin();
        try {
            DB::exec('insert into social_content_manual(content_type,title,target_path,relevant_at,caption,created_by) values(?,?,?,?,?,?)',[$type,$title,$path,$date,$caption,$actor]);
            $id=DB::id();
            DB::exec('insert into social_content_audits(content_key,action,actor_id,title_snapshot) values(?,"created",?,?)',['manual:'.$id,$actor,$title]);
            DB::commit();
        } catch(Throwable $e) {if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }
}
