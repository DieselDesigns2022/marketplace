<?php
namespace App\Services;
use App\Core\Database as DB;
use DomainException;
use Throwable;
final class BuyerReferralGiveawayService
{
    private function atomic(callable $fn): mixed
    {
        $owns=!DB::pdo()->inTransaction();if($owns)DB::begin();
        try {$result=$fn();if($owns)DB::commit();return $result;}
        catch(Throwable $e){if($owns&&DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }
    private function audit(string $action,?int $subject,?int $actor,array $data):void
    {
        DB::exec('insert into buyer_referral_giveaway_audits(action,subject_id,actor_id,details) values(?,?,?,?)',[$action,$subject,$actor,json_encode($data,JSON_THROW_ON_ERROR)]);
    }
    private function admin(int $actor):void
    {
        $p=new AdminPermissionService();
        if(!$p->can($actor,'referrals.view')||!$p->can($actor,'prizes.manage'))throw new DomainException('Referral reporting and prize management permissions are required.');
    }
    public function settings():array
    {
        return DB::row('select * from buyer_referral_giveaway_settings where id=1')??throw new DomainException('Giveaway settings are unavailable.');
    }
    public function enable(int $actor,bool $enabled):void
    {
        $this->admin($actor);$this->atomic(function()use($actor,$enabled){
            $s=DB::row('select * from buyer_referral_giveaway_settings where id=1 for update');
            if($enabled && !$s['starts_at']){
                $event=DB::row('select starts_at,ends_at from grand_opening_events where id=1');
                if(empty($event['starts_at'])||empty($event['ends_at'])||strtotime($event['ends_at'].' UTC')<=time())throw new DomainException('Save future Grand Opening dates first.');
                DB::exec('update buyer_referral_giveaway_settings set starts_at=?,ends_at=? where id=1',[$event['starts_at'],$event['ends_at']]);
            }
            DB::exec('update buyer_referral_giveaway_settings set is_enabled=?,updated_by=? where id=1',[$enabled?1:0,$actor]);
            $this->audit($enabled?'enabled':'paused',1,$actor,[]);
        });
    }
    public function refresh():void
    {
        $this->atomic(function(){
            $s=DB::row('select * from buyer_referral_giveaway_settings where id=1 for update');
            if(!$s || !$s['starts_at'])return;
            $refs=DB::rows('select r.id,r.referrer_user_id,r.referred_user_id,r.referral_type,r.seller_intent,r.buyer_status,r.buyer_rewarded_at,r.buyer_qualifying_order_id,r.created_at attached_at,
                u.created_at joined_at,u.status buyer_state,u.email buyer_email,u.merged_into_user_id buyer_merge,
                f.status referrer_state,f.email referrer_email,f.merged_into_user_id referrer_merge
                from referrals r join users u on u.id=r.referred_user_id join users f on f.id=r.referrer_user_id
                where (r.buyer_rewarded_at>=? and r.buyer_rewarded_at<?)
                or (r.referral_type="buyer" and r.created_at>=? and r.created_at<?)
                or exists(select 1 from buyer_referral_giveaway_entries e where e.referral_id=r.id)
                order by r.id for update',[$s['starts_at'],$s['ends_at'],$s['starts_at'],$s['ends_at']]);
            foreach($refs as $r){
                $e=DB::row('select * from buyer_referral_giveaway_entries where referral_id=? for update',[$r['id']]);
                $status='pending';$reason='Waiting for the existing referral program to qualify this buyer referral.';$cents=0;$orders=[];
                $identity=$r['referrer_user_id']===$r['referred_user_id'] || strcasecmp(trim($r['buyer_email']),trim($r['referrer_email']))===0 || $r['buyer_merge'] || $r['referrer_merge'];
                if($e && ((int)$e['referrer_user_id']!==(int)$r['referrer_user_id'] || (int)$e['referred_user_id']!==(int)$r['referred_user_id']))$identity=true;
                if($identity){$status='review';$reason='Identity or account-merge review required.';}
                elseif($r['buyer_state']!=='active'||$r['referrer_state']!=='active'){$status='review';$reason='Account is inactive; review before a prize is awarded.';}
                elseif($r['buyer_status']!=='rewarded'||empty($r['buyer_rewarded_at'])||empty($r['buyer_qualifying_order_id'])){
                    if($r['buyer_status']==='rejected'||$r['seller_intent']||$r['referral_type']!=='buyer'){$status='ineligible';$reason='The existing program has not awarded a qualifying buyer referral.';}
                    elseif(strtotime($s['ends_at'].' UTC')<=time()){$status='ineligible';$reason='The existing program did not qualify this buyer referral before the giveaway closed.';}
                }
                elseif($r['buyer_rewarded_at']<$s['starts_at']||$r['buyer_rewarded_at']>=$s['ends_at']){$status='ineligible';$reason='The existing buyer referral qualified outside the giveaway dates.';}
                else {
                    $qualification=DB::row('select id,user_id from orders where id=? for update',[$r['buyer_qualifying_order_id']]);
                    if(!$qualification||(int)$qualification['user_id']!==(int)$r['referred_user_id']){$status='review';$reason='Original qualifying order needs review.';}
                    else {
                        // The original program decides qualification. Recheck its payment evidence only for refunds/review.
                        $paid=DB::rows('select o.id,o.stripe_paid_amount,coalesce(o.paid_at,o.created_at) paid_at from orders o
                            where o.user_id=? and o.payment_status="paid" and o.status not in ("failed","cancelled","refunded","partially_refunded")
                            and coalesce(o.manual_review_required,0)=0 and o.refunded_at is null and o.partially_refunded_at is null and o.stripe_paid_amount>0
                            and coalesce(o.paid_at,o.created_at)<=date_add(?,interval 30 day)
                            and coalesce(o.paid_at,o.created_at)<=?
                            and not exists(select 1 from marketplace_refund_observations x where x.order_id=o.id and x.cumulative_refund_cents>0)
                            order by coalesce(o.paid_at,o.created_at),o.id for update',[$r['referred_user_id'],$r['joined_at'],$r['buyer_rewarded_at']]);
                        foreach($paid as $o){$amount=CreditService::parseCents((string)$o['stripe_paid_amount']);$cents+=$amount;$orders[]=['id'=>(int)$o['id'],'cents'=>$amount,'paid_at'=>$o['paid_at']];}
                        if($cents>=800){$status='eligible';$reason='Existing buyer referral qualified during the giveaway; original payment evidence remains valid.';}
                        else{$status='ineligible';$reason='Refunds or payment review removed the original qualifying spend. Normal referral rewards are unchanged.';}
                    }
                }
                if(!empty($e['excluded_reason'])){$status='excluded';$reason=$e['excluded_reason'];}
                $json=json_encode($orders,JSON_THROW_ON_ERROR);
                if(!$e){
                    DB::exec('insert into buyer_referral_giveaway_entries(referral_id,referrer_user_id,referred_user_id,qualifying_orders) values(?,?,?,?)',[$r['id'],$r['referrer_user_id'],$r['referred_user_id'],'[]']);
                    $id=(int)DB::id();
                }else $id=(int)$e['id'];
                if(!$e||$e['status']!==$status||(int)$e['qualifying_cents']!==$cents||json_decode($e['qualifying_orders'],true)!==$orders||$e['reason']!==$reason){
                    DB::exec('update buyer_referral_giveaway_entries set status=?,reason=?,qualifying_cents=?,qualifying_orders=?,first_qualified_at=case when ?="eligible" then coalesce(first_qualified_at,utc_timestamp()) else first_qualified_at end where id=?',[$status,$reason,$cents,$json,$status,$id]);
                    $this->audit('eligibility_updated',$id,null,['from'=>$e['status']??null,'to'=>$status,'reason'=>$reason,'orders'=>$orders]);
                }
            }
            // Referral deletion must never leave an eligible orphan.
            foreach(DB::rows('select e.id from buyer_referral_giveaway_entries e left join referrals r on r.id=e.referral_id where r.id is null and e.status<>"review" for update') as $orphan){
                DB::exec('update buyer_referral_giveaway_entries set status="review",reason="Referral relationship missing." where id=?',[$orphan['id']]);
                $this->audit('missing_referral',(int)$orphan['id'],null,[]);
            }
        });
    }
    public function entries():array
    {
        return DB::rows('select e.*,u.name referrer_name,b.name buyer_name from buyer_referral_giveaway_entries e left join users u on u.id=e.referrer_user_id left join users b on b.id=e.referred_user_id order by e.id desc');
    }
    public function personal(int $userId):array
    {
        $this->refresh();$s=$this->settings();
        $user=DB::row('select referral_code from users where id=? and status="active"',[$userId]);
        if(!$user)throw new DomainException('Active account required.');
        $counts=DB::rows('select status,count(*) total from buyer_referral_giveaway_entries where referrer_user_id=? group by status',[$userId]);
        return ['settings'=>$s,'canRefer'=>(bool)DB::row('select id from designers where user_id=? and status="approved"',[$userId]),'counts'=>array_column($counts,'total','status')];
    }
    public function exclude(int $actor,int $entryId,string $reason,bool $restore=false):void
    {
        $this->admin($actor);$reason=trim($reason);
        if(mb_strlen($reason)<5||mb_strlen($reason)>500)throw new DomainException('Enter a reason between 5 and 500 characters.');
        $this->atomic(function()use($actor,$entryId,$reason,$restore){
            DB::row('select id from buyer_referral_giveaway_settings where id=1 for update');
            $e=DB::row('select id from buyer_referral_giveaway_entries where id=? for update',[$entryId]);
            if(!$e)throw new DomainException('Entry not found.');
            DB::exec('update buyer_referral_giveaway_entries set excluded_reason=? where id=?',[$restore?null:$reason,$entryId]);
            $this->audit($restore?'exclusion_removed':'excluded',$entryId,$actor,['reason'=>$reason]);$this->refresh();
        });
    }
    public function draw(int $actor):int
    {
        $this->admin($actor);return $this->atomic(function()use($actor){
            $s=DB::row('select * from buyer_referral_giveaway_settings where id=1 for update');
            if(!$s['starts_at']||strtotime($s['ends_at'].' UTC')>time())throw new DomainException('Drawing opens after the giveaway ends.');
            $last=DB::row('select * from buyer_referral_giveaway_draws order by id desc limit 1 for update');
            if($last && $last['status']!=='disqualified')return (int)$last['id'];
            $this->refresh();
            $pool=DB::rows('select e.id,e.referral_id,e.referrer_user_id,e.referred_user_id,e.qualifying_cents,e.qualifying_orders from buyer_referral_giveaway_entries e where e.status="eligible" and not exists(select 1 from final_giveaway_draws f where f.winner_entry_key=concat("referral:",e.id)) and not exists(select 1 from buyer_referral_giveaway_draws d where d.winner_user_id=e.referrer_user_id and d.status="disqualified") order by e.id for update');
            if(!$pool)throw new DomainException('No eligible entries are available.');
            $snapshot=json_encode($pool,JSON_THROW_ON_ERROR);$index=random_int(0,count($pool)-1);$winner=$pool[$index];
            DB::exec('insert into buyer_referral_giveaway_draws(winner_entry_id,winner_user_id,pool_snapshot,pool_hash,pool_count,selected_index,drawn_by) values(?,?,?,?,?,?,?)',[$winner['id'],$winner['referrer_user_id'],$snapshot,hash('sha256',$snapshot),count($pool),$index,$actor]);
            $id=(int)DB::id();$this->audit('winner_selected',$id,$actor,['entry'=>$winner['id'],'pool_hash'=>hash('sha256',$snapshot)]);return $id;
        });
    }
    public function disqualify(int $actor,int $drawId,string $reason):void
    {
        $this->admin($actor);$reason=trim($reason);if(mb_strlen($reason)<5||mb_strlen($reason)>500)throw new DomainException('A disqualification reason is required.');
        $this->atomic(function()use($actor,$drawId,$reason){
            DB::row('select id from buyer_referral_giveaway_settings where id=1 for update');
            $d=DB::row('select * from buyer_referral_giveaway_draws where id=? for update',[$drawId]);
            if(!$d||$d['status']!=='selected')throw new DomainException('Only a selected, unawarded winner can be disqualified.');
            DB::exec('update buyer_referral_giveaway_draws set status="disqualified",reason=? where id=?',[$reason,$drawId]);
            $this->audit('winner_disqualified',$drawId,$actor,['reason'=>$reason]);
        });
    }
    public function award(int $actor,int $drawId,int $donationId):void
    {
        $this->admin($actor);$this->atomic(function()use($actor,$drawId,$donationId){
            DB::row('select id from buyer_referral_giveaway_settings where id=1 for update');
            $d=DB::row('select * from buyer_referral_giveaway_draws where id=? for update',[$drawId]);
            if(!$d||$d['status']==='disqualified')throw new DomainException('Select an eligible winner first.');
            if($d['status']==='awarded')return;
            $this->refresh();$e=DB::row('select * from buyer_referral_giveaway_entries where id=? for update',[$d['winner_entry_id']]);
            if(!$e||$e['status']!=='eligible'||(int)$e['referrer_user_id']!==(int)$d['winner_user_id'])throw new DomainException('Winner eligibility changed. Review the entry before awarding.');
            $prizes=new PrizeDonationService();
            $assignment=$prizes->assign($donationId,1,'giveaway','Buyer Referral Giveaway draw #'.$drawId,$actor,'buyer-referral-giveaway:draw:'.$drawId);
            if(!$prizes->award($assignment,$actor,(int)$d['winner_user_id'],null,null))throw new DomainException('Prize could not be awarded.');
            DB::exec('update buyer_referral_giveaway_draws set status="awarded",assignment_id=? where id=?',[$assignment,$drawId]);
            $this->audit('prize_awarded',$drawId,$actor,['assignment_id'=>$assignment]);
        });
    }
}
