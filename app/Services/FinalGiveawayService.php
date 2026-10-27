<?php
namespace App\Services;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use DomainException;
use Throwable;

final class FinalGiveawayService
{
    public const POOLS=['all'=>'Grand Prize — all earned entries','passport'=>'Passport completion','referral'=>'Buyer Referral Prize','prize_wheel'=>'Entries won on store wheels'];
    private function atomic(callable $fn):mixed
    {
        $owns=!DB::pdo()->inTransaction();if($owns)DB::begin();
        try{$result=$fn();if($owns)DB::commit();return $result;}
        catch(Throwable $e){if($owns&&DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }
    private function manage(int $actor):void
    {
        $p=new AdminPermissionService();
        if(!$p->can($actor,'prizes.manage')||!$p->can($actor,'prizes.view'))throw new DomainException('Prize viewing and management permissions are required.');
    }
    private function audit(int $actor,string $action,array $details):void
    {
        DB::exec('insert into final_giveaway_audits(actor_id,action,details) values(?,?,?)',[$actor,$action,json_encode($details,JSON_THROW_ON_ERROR)]);
    }
    public function live():bool
    {
        return (bool)DB::row('select id from final_giveaway_settings where id=1 and is_live=1');
    }
    public function setLive(int $actor,bool $show):void
    {
        $this->manage($actor);
        $this->atomic(function()use($actor,$show){
            DB::row('select id from final_giveaway_settings where id=1 for update');
            DB::exec('update final_giveaway_settings set is_live=?,live_until=null,updated_by=? where id=1',[$show?1:0,$actor]);
            $this->audit($actor,$show?'show_live':'giveaway_done',[]);
        });
    }
    public function prizes():array
    {
        return DB::rows('select p.id,p.title,p.prize_type,p.quantity_remaining,p.description,p.restrictions,p.fulfillment_instructions,p.prize_file_storage_path,d.display_name shop_name from prize_donations p join designers d on d.id=p.designer_id where p.status="available" and p.quantity_remaining>0 and p.seller_authorized=1 and find_in_set("giveaway",p.usage_designations)>0 order by p.title,p.id');
    }
    public function history():array
    {
        return DB::rows('select d.*,e.status email_status,e.last_error email_error from final_giveaway_draws d left join email_messages e on e.deduplication_key=concat("final-giveaway:",d.id,":email") order by d.id desc limit 100');
    }
    public function pool(string $type,bool $refresh=true):array
    {
        if(!isset(self::POOLS[$type]))throw new DomainException('Choose a giveaway from the list.');
        $event=DB::row('select * from grand_opening_events where id=1');
        if(!$event||!$event['starts_at']||!$event['ends_at'])throw new DomainException('Save the Grand Opening dates first.');
        $entries=[];
        if($type!=='referral'){
            // Existing entry records are authoritative. Never manufacture entries from purchases or stamps.
            $rows=DB::rows('select e.id,e.user_id,e.source_type from grand_opening_giveaway_entries e join users u on u.id=e.user_id where (e.campaign_key=? or e.created_at>=?) and e.created_at<? and u.status="active" and u.merged_into_user_id is null order by e.id',[$event['campaign_key'],$event['starts_at'],$event['ends_at']]);
            foreach($rows as $e){if($type==='all'||$type===$e['source_type'])$entries[]=['key'=>'grand:'.$e['id'],'user_id'=>(int)$e['user_id'],'source'=>$e['source_type']];}
        }
        if(in_array($type,['all','referral'],true)){
            if($refresh)(new BuyerReferralGiveawayService())->refresh();
            $rows=DB::rows('select e.id,e.referrer_user_id from buyer_referral_giveaway_entries e join users u on u.id=e.referrer_user_id where e.status="eligible" and u.status="active" and u.merged_into_user_id is null and not exists(select 1 from buyer_referral_giveaway_draws d where (d.winner_entry_id=e.id and d.status in ("selected","awarded")) or (d.winner_user_id=e.referrer_user_id and d.status="disqualified")) order by e.id');
            foreach($rows as $e)$entries[]=['key'=>'referral:'.$e['id'],'user_id'=>(int)$e['referrer_user_id'],'source'=>'referral'];
        }
        $used=array_fill_keys(array_column(DB::rows('select winner_entry_key from final_giveaway_draws'),'winner_entry_key'),true);
        return array_values(array_filter($entries,static fn($e)=>!isset($used[$e['key']])));
    }
    public function draw(int $actor,string $type,int $prizeId,string $requestKey):int
    {
        $this->manage($actor);
        if(!isset(self::POOLS[$type])||!preg_match('/^[a-f0-9]{32,64}$/D',$requestKey))throw new DomainException('Reload the page and choose a giveaway.');
        return $this->atomic(function()use($actor,$type,$prizeId,$requestKey){
            // Shared lock with the existing referral drawing prevents cross-screen duplicate awards.
            DB::row('select id from buyer_referral_giveaway_settings where id=1 for update');
            DB::row('select id from final_giveaway_settings where id=1 for update');
            $existing=DB::row('select * from final_giveaway_draws where request_key=?',[$requestKey]);
            if($existing){
                if($existing['pool_type']!==$type||(int)$existing['prize_donation_id']!==$prizeId)throw new DomainException('This spin was already used for another prize. Reload the page.');
                return (int)$existing['id'];
            }
            if(DB::row('select id from final_giveaway_draws where removed_at is null limit 1'))throw new DomainException('Confirm Remove entry for the previous winner before spinning again.');
            $event=DB::row('select * from grand_opening_events where id=1 for update');
            if(!$event||!$event['ends_at']||strtotime($event['ends_at'].' UTC')>time())throw new DomainException('Final drawings open after the Grand Opening ends.');
            if(in_array($type,['all','referral'],true)){
                $r=(new BuyerReferralGiveawayService())->settings();
                if(!$r['ends_at']||strtotime($r['ends_at'].' UTC')>time())throw new DomainException('The referral entry window must be configured and closed before this drawing.');
            }
            $prize=DB::row('select p.*,d.user_id donor_user_id from prize_donations p join designers d on d.id=p.designer_id where p.id=? for update',[$prizeId]);
            if(!$prize||$prize['status']!=='available'||!$prize['seller_authorized']||(int)$prize['quantity_remaining']<1||!in_array('giveaway',explode(',',$prize['usage_designations']),true))throw new DomainException('Choose an available prize authorized for giveaways.');
            $delivery=trim((string)$prize['description'])."\n\n".trim((string)$prize['fulfillment_instructions']);
            if(trim((string)$prize['restrictions'])!=='')$delivery.="\n\nRestrictions: ".$prize['restrictions'];
            if(trim((string)$prize['fulfillment_instructions'])===''&&!$prize['prize_file_storage_path'])throw new DomainException('This donation has no file or delivery instructions. Update the donation before awarding it.');
            if($prize['prize_file_storage_path'])$this->safeFile($prize['prize_file_storage_path']);
            $pool=$this->pool($type);
            if(!$pool)throw new DomainException('There are no unused eligible entries in this giveaway.');
            $snapshot=json_encode($pool,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $index=random_int(0,count($pool)-1);$entry=$pool[$index];
            $winner=DB::row('select id,name,email,status,merged_into_user_id from users where id=? for update',[$entry['user_id']]);
            if(!$winner||$winner['status']!=='active'||$winner['merged_into_user_id']||!EmailQueueService::validEnvelope($winner['email'],'Your Creative Moth prize'))throw new DomainException('Winner account needs review. No prize or entry was consumed.');
            $parts=preg_split('/\s+/u',trim($winner['name']));
            $label=($parts[0]?:'Winner').(count($parts)>1?' '.mb_substr(end($parts),0,1).'.':'');
            DB::exec('insert into final_giveaway_draws(request_key,pool_type,campaign_key,winner_entry_key,winner_user_id,winner_label,prize_donation_id,prize_title,delivery_instructions,delivery_file_path,delivery_file_name,pool_snapshot,pool_hash,pool_count,selected_index,drawn_by,reveal_at) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,date_add(utc_timestamp(),interval 2 second))',[$requestKey,$type,$event['campaign_key'],$entry['key'],$winner['id'],mb_substr($label,0,190),$prizeId,$prize['title'],$delivery,$prize['prize_file_storage_path'],$prize['prize_file_original_name'],$snapshot,hash('sha256',$snapshot),count($pool),$index,$actor]);
            $id=(int)DB::id();$prizes=new PrizeDonationService();
            $assignment=$prizes->assign($prizeId,1,'giveaway','Final Giveaway draw #'.$id,$actor,'final-giveaway:'.$id);
            if(!$prizes->award($assignment,$actor,(int)$winner['id'],$winner['name'],$winner['email']))throw new DomainException('Prize award failed. No entry consumed.');
            $url=H::baseUrl().'/dashboard/giveaway-prizes/'.$id;
            $body='Creative Moth prize award: '.$prize['title']."\n\n".$delivery."\n\nYour prize details and secure download: ".$url;
            DB::exec('insert into message_conversations(buyer_user_id,seller_user_id,designer_id,context_label,context_key,last_message_at) values(?,?,?,?,?,utc_timestamp())',[$winner['id'],$prize['donor_user_id'],$prize['designer_id'],mb_substr('Grand Prize: '.$prize['title'],0,190),'final-giveaway:'.$id]);
            $conversation=(int)DB::id();
            DB::exec('insert into conversation_messages(conversation_id,sender_user_id,body) values(?,?,?)',[$conversation,$actor,$body]);$message=(int)DB::id();
            DB::exec('update final_giveaway_draws set assignment_id=?,conversation_id=?,message_id=? where id=?',[$assignment,$conversation,$message,$id]);
            NotificationService::create((int)$winner['id'],'giveaway_prize','buyer','You won '.$prize['title'],'Your prize is ready in Messages.','final-giveaway:'.$id.':notice','/buyer/messages/'.$conversation);
            if(!EmailQueueService::queueAt('transactional',$winner['email'],'You won a Creative Moth prize!','final_giveaway_prize',['name'=>$winner['name'],'title'=>$prize['title'],'instructions'=>$delivery,'prize_url'=>$url],'final-giveaway:'.$id.':email',gmdate('Y-m-d H:i:s',time()+2)))throw new DomainException('Prize email could not be queued. Nothing was awarded.');
            // Award is delivered; custom/service redemption may still require the donor's work.
            $this->audit($actor,'draw_awarded',['draw_id'=>$id,'entry'=>$entry['key'],'pool_hash'=>hash('sha256',$snapshot),'assignment_id'=>$assignment]);
            return $id;
        });
    }
    public function removeEntry(int $actor,int $drawId):void
    {
        $this->manage($actor);$this->atomic(function()use($actor,$drawId){
            DB::row('select id from buyer_referral_giveaway_settings where id=1 for update');
            DB::row('select id from final_giveaway_settings where id=1 for update');
            $d=DB::row('select * from final_giveaway_draws where id=? for update',[$drawId]);
            if(!$d)throw new DomainException('Drawing not found.');
            if($d['removed_at'])return;
            if(strtotime($d['reveal_at'].' UTC')>time())throw new DomainException('Wait for the winner to be revealed.');
            DB::exec('update final_giveaway_draws set removed_at=utc_timestamp() where id=?',[$drawId]);
            $this->audit($actor,'entry_removal_confirmed',['draw_id'=>$drawId,'entry'=>$d['winner_entry_key']]);
        });
    }
    private function labels(array $entries):array
    {
        if(!$entries)return [];
        $ids=array_values(array_unique(array_map('intval',array_column($entries,'user_id'))));
        $users=DB::rows('select id,name from users where id in ('.implode(',',array_fill(0,count($ids),'?')).')',$ids);
        $names=[];foreach($users as $u){$parts=preg_split('/\s+/u',trim($u['name']));$names[(int)$u['id']]=($parts[0]?:'Participant').(count($parts)>1?' '.mb_substr(end($parts),0,1).'.':'');}
        return array_map(static fn($e)=>['key'=>$e['key'],'name'=>$names[(int)$e['user_id']]??'Participant'],$entries);
    }
    public function publicState():array
    {
        if(!$this->live())return ['live'=>false];
        $rows=DB::rows('select id,prize_title,winner_label,pool_type,pool_count,pool_hash,winner_entry_key,reveal_at,removed_at from final_giveaway_draws order by id desc limit 100');
        $draws=[];$wheel=[];
        foreach($rows as $r){
            $remaining=max(0,strtotime($r['reveal_at'].' UTC')-time());
            $draw=['id'=>(int)$r['id'],'prize'=>$r['prize_title'],'giveaway'=>self::POOLS[$r['pool_type']],'entries'=>(int)$r['pool_count'],'remaining'=>$remaining,'removed'=>(bool)$r['removed_at']];
            if(!$remaining)$draw+=['winner'=>$r['winner_label'],'ticket'=>$r['winner_entry_key'],'hash'=>$r['pool_hash']];
            $draws[]=$draw;
        }
        if($rows&&!$rows[0]['removed_at']){
            $snapshot=DB::row('select pool_snapshot from final_giveaway_draws where id=?',[$rows[0]['id']]);
            $wheel=$this->labels(json_decode($snapshot['pool_snapshot'],true,512,JSON_THROW_ON_ERROR));
        }else{
            try{$wheel=$this->labels($this->pool($rows[0]['pool_type']??'all',false));}catch(DomainException $e){$wheel=[];}
        }
        return ['live'=>true,'draws'=>$draws,'wheel'=>$wheel];
    }
    public function prizeForUser(int $id,int $userId):?array
    {
        return DB::row('select * from final_giveaway_draws where id=? and winner_user_id=?',[$id,$userId]);
    }
    public function safeFile(string $path):string
    {
        $base=realpath(app_path('storage/protected_uploads/prize_donations'));
        $real=realpath(app_path('storage/protected_uploads/'.ltrim($path,'/')));
        if(!$base||!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($real)||!is_readable($real))throw new DomainException('Prize file is missing. Fix the donation file before drawing.');
        return $real;
    }
}
