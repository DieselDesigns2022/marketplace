<?php

namespace App\Services;

use App\Controllers\StripeController;
use App\Core\Database as DB;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class CreatorRecognitionService
{
    public const RANKS = ['Bronze'=>0,'Silver'=>25,'Gold'=>100,'Platinum'=>500,'Diamond'=>1500];
    public const FOUNDER_LIMIT = 50;
    private const RANK_FIELDS = ['calculated_rank','creator_rank'];
    private const FOUNDER_FIELDS = ['founder_position','founder_active','founder_override_state'];

    public static function rankForSales(int $sales): string
    {
        $rank='Bronze';
        foreach(self::RANKS as $name=>$minimum)if($sales >= $minimum)$rank=$name;
        return $rank;
    }

    public static function progress(int $sales): array
    {
        $rank=self::rankForSales($sales);$names=array_keys(self::RANKS);$i=array_search($rank,$names,true);
        if($i===count($names)-1)return ['rank'=>$rank,'next'=>null,'needed'=>0,'percent'=>100];
        $next=$names[$i+1];$low=self::RANKS[$rank];$high=self::RANKS[$next];
        return ['rank'=>$rank,'next'=>$next,'needed'=>$high-$sales,'percent'=>(int)floor(100*max(0,$sales-$low)/($high-$low))];
    }

    public static function utcNow(): string
    { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }

    public static function founderActive(?string $latest,string $mode,string $now): bool
    {
        if($mode==='force_active')return true;
        if($mode==='force_inactive'||!$latest)return false;
        $utc=new DateTimeZone('UTC');
        $latestAt=new DateTimeImmutable($latest,$utc);$nowAt=new DateTimeImmutable($now,$utc);
        return $nowAt <= $latestAt->modify('+60 days');
    }

    public static function planFounderPositions(array $eligible,array $reserved): array
    {
        usort($eligible,static fn($a,$b)=>strcmp($a['tenth_at'],$b['tenth_at'])?:((int)$a['tenth_order_id']<=>(int)$b['tenth_order_id'])?:((int)$a['designer_id']<=>(int)$b['designer_id']));
        $used=array_fill_keys(array_map('intval',$reserved),true);$plan=[];
        foreach($eligible as $row){$position=null;for($p=1;$p<=self::FOUNDER_LIMIT;$p++)if(!isset($used[$p])){$position=$p;$used[$p]=true;break;}if($position)$plan[(int)$row['designer_id']]=$position;}
        return $plan;
    }

    public function qualifyingOrders(int $designerId): array
    {
        $orders=DB::rows('select o.id,o.paid_at,o.finalized_at,o.created_at,o.payment_status,o.tax_amount from orders o join order_items oi on oi.order_id=o.id where oi.designer_id=? and o.payment_status in ("paid","partially_refunded","refunded") and o.status in ("paid","completed","refunded") and o.manual_review_required=0 group by o.id,o.paid_at,o.finalized_at,o.created_at,o.payment_status,o.tax_amount order by coalesce(o.paid_at,o.finalized_at,o.created_at),o.id',[$designerId]);
        $out=[];
        foreach($orders as $order){
            if($order['payment_status']==='refunded')continue;
            $items=DB::rows('select id,designer_id,total_price,commission_rate from order_items where order_id=? order by id',[$order['id']]);
            $refund=DB::row('select coalesce(max(amount),0) amount from payment_transactions where order_id=? and transaction_type in ("partial_refund","refund")',[$order['id']]);
            $allocation=StripeController::allocateSellerRefund($items,StripeService::cents($refund['amount']??0),StripeService::cents($order['tax_amount']??0));
            $remaining=0;
            foreach($items as $item)if((int)$item['designer_id']===$designerId)$remaining+=max(0,StripeService::cents($item['total_price'])-(int)($allocation[(int)$item['id']]['gross_refund_cents']??0));
            if($remaining>0)$out[]=['id'=>(int)$order['id'],'at'=>$order['paid_at']?:($order['finalized_at']?:$order['created_at'])];
        }
        return $out;
    }

    public function recalculate(int $designerId,bool $dryRun=false,bool $notify=true,string $source='automatic',?int $proposedPosition=null,?string $triggerKey=null): array
    {
        $owns=!DB::pdo()->inTransaction();if($owns)DB::begin();
        try{
            // Required universal order: designer, global Founder mutex, occupied positions.
            $d=DB::row('select * from designers where id=? for update',[$designerId]);
            if(!$d)throw new DomainException('Seller not found.');
            $orders=$this->qualifyingOrders($designerId);$count=count($orders);$calculated=self::rankForSales($count);
            $effective=!empty($d['rank_override'])&&$d['rank_override_value']?$d['rank_override_value']:$calculated;
            $last=$count?$orders[$count-1]['at']:null;$position=$d['founder_position']!==null?(int)$d['founder_position']:null;
            $earned=$d['founder_earned_at']??null;$tenthId=$d['founder_qualifying_order_id']??null;
            $needsFounderLock=$position!==null||($d['status']==='approved'&&$count>=10);
            if($needsFounderLock&&!$dryRun)DB::row('select id from creator_recognition_lock where id=1 for update');
            if($position===null&&$d['status']==='approved'&&$count>=10){
                $occupied=array_map('intval',array_column(DB::rows('select founder_position from designers where founder_position is not null order by founder_position'),'founder_position'));
                $candidates=[];
                foreach(DB::rows('select id from designers where status="approved" and founder_position is null') as $candidate){
                    $candidateOrders=(int)$candidate['id']===$designerId?$orders:$this->qualifyingOrders((int)$candidate['id']);
                    if(count($candidateOrders)>=10)$candidates[]=['designer_id'=>(int)$candidate['id'],'tenth_at'=>$candidateOrders[9]['at'],'tenth_order_id'=>$candidateOrders[9]['id']];
                }
                $correctPosition=self::planFounderPositions($candidates,$occupied)[$designerId]??null;
                $position=$proposedPosition!==null&&$proposedPosition===$correctPosition&&!in_array($proposedPosition,$occupied,true)?$proposedPosition:$correctPosition;
                if($position!==null){$earned=$orders[9]['at'];$tenthId=$orders[9]['id'];}
            }
            $mode=$d['founder_override_state']??'automatic';$now=self::utcNow();
            $active=$position!==null&&self::founderActive($last,$mode,$now);
            $inactiveAt=$position!==null&&!$active?($d['founder_inactive_at']?:$now):null;
            $after=['qualifying_sales_count'=>$count,'calculated_rank'=>$calculated,'creator_rank'=>$effective,'last_qualifying_sale_at'=>$last,'founder_position'=>$position,'founder_earned_at'=>$earned,'founder_qualifying_order_id'=>$tenthId,'founder_active'=>$active?1:0,'founder_inactive_at'=>$inactiveAt,'founder_override_state'=>$mode];
            $changed=$this->changedFields($d,$after);$rankChanged=(bool)array_intersect($changed,self::RANK_FIELDS);$founderChanged=(bool)array_intersect($changed,self::FOUNDER_FIELDS);
            $result=$after+['designer_id'=>$designerId,'qualifying_sales'=>$count,'effective_rank'=>$effective,'changed'=>(bool)$changed,'changed_fields'=>$changed];
            if($dryRun){if($owns)DB::rollBack();return $result;}
            if(!$changed){
                $recovery=$triggerKey?DB::row('select * from creator_recognition_events where trigger_key=?',[$triggerKey]):null;
                if($owns)DB::commit();
                if($notify&&$owns&&$recovery&&$this->eventMatchesCurrentState($recovery,$after))$this->communicateEvent($recovery);
                return $result;
            }
            DB::exec('update designers set qualifying_sales_count=?,calculated_rank=?,creator_rank=?,last_qualifying_sale_at=?,founder_position=?,founder_earned_at=?,founder_qualifying_order_id=?,founder_active=?,founder_inactive_at=? where id=?',[$count,$calculated,$effective,$last,$position,$earned,$tenthId,$active?1:0,$inactiveAt,$designerId]);
            $result['previous']=$d;$result['rank_changed']=$rankChanged;$result['founder_changed']=$founderChanged;
            if($rankChanged||$founderChanged){
                DB::exec('insert into creator_recognition_events(designer_id,source,trigger_key,before_state,after_state,rank_changed,founder_changed) values(?,?,?,?,?,?,?)',[$designerId,$source,$triggerKey,json_encode($this->recognitionState($d)),json_encode($this->recognitionState($after)),$rankChanged?1:0,$founderChanged?1:0]);
                $eventId=(int)DB::id();$event='recognition-event:'.$eventId;$result['event_key']=$event;
                if($rankChanged)DB::exec('insert into creator_rank_history(designer_id,previous_calculated_rank,new_calculated_rank,previous_effective_rank,new_effective_rank,qualifying_sales_count,change_source,event_key) values(?,?,?,?,?,?,?,?)',[$designerId,$d['calculated_rank']??$d['creator_rank'],$calculated,$d['creator_rank'],$effective,$count,$source,$event.':rank']);
                if($founderChanged)DB::exec('insert into creator_badge_history(designer_id,action,before_state,after_state,founder_position,change_source,event_key) values(?,?,?,?,?,?,?)',[$designerId,$position!=$d['founder_position']?'earned':($active?'reactivated':'inactive'),json_encode($this->founderState($d)),json_encode($this->founderState($after)),$position,$source,$event.':founder']);
            }
            if($owns)DB::commit();
            // A joined transaction owns neither commit nor rollback. Its caller recovers
            // communication after commit by replaying the stable trigger key.
            if($notify&&$owns&&!empty($result['event_key']))$this->communicate($result);
            return $result;
        }catch(Throwable $e){if($owns&&DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    public function recalculateAll(bool $dryRun=false,bool $notify=false): array
    {
        $eligible=[];$ids=[];
        foreach(DB::rows('select id,founder_position from designers where status="approved" order by id') as $d){$ids[]=(int)$d['id'];if($d['founder_position']===null&&count($orders=$this->qualifyingOrders((int)$d['id']))>=10)$eligible[]=['designer_id'=>(int)$d['id'],'tenth_at'=>$orders[9]['at'],'tenth_order_id'=>$orders[9]['id']];}
        $reserved=array_map('intval',array_column(DB::rows('select founder_position from designers where founder_position is not null'),'founder_position'));$plan=self::planFounderPositions($eligible,$reserved);$order=array_flip(array_keys($plan));
        usort($ids,static fn($a,$b)=>($order[$a]??PHP_INT_MAX)<=>($order[$b]??PHP_INT_MAX)?:$a<=>$b);
        $results=[];foreach($ids as $id)$results[]=$this->recalculate($id,$dryRun,$notify,$notify?'automatic':'backfill',$plan[$id]??null);return $results;
    }

    public function setRankOverride(int $designerId,?string $rank,int $adminId,string $reason): bool
    {
        $this->assertAdmin($adminId);$reason=$this->reason($reason);if($rank!==null&&!isset(self::RANKS[$rank]))throw new DomainException('Invalid creator rank.');
        DB::begin();
        try{
            $d=DB::row('select * from designers where id=? for update',[$designerId]);if(!$d)throw new DomainException('Seller not found.');$effective=$rank??$d['calculated_rank'];
            if((bool)$d['rank_override']===($rank!==null)&&($d['rank_override_value']??null)===$rank&&$d['creator_rank']===$effective){DB::commit();return false;}
            DB::exec('insert into admin_logs(admin_user_id,action,entity_type,entity_id,metadata) values(?,?,?,?,?)',[$adminId,$rank?'creator_rank_override_set':'creator_rank_override_removed','designer',$designerId,json_encode(['reason'=>$reason,'rank'=>$rank])]);$auditId=(int)DB::id();$event='admin-recognition:'.$auditId;
            DB::exec('update designers set rank_override=?,rank_override_value=?,rank_override_reason=?,rank_override_admin_id=?,rank_override_at=utc_timestamp(),creator_rank=? where id=?',[$rank!==null?1:0,$rank,$reason,$adminId,$effective,$designerId]);
            DB::exec('insert into creator_rank_history(designer_id,previous_calculated_rank,new_calculated_rank,previous_effective_rank,new_effective_rank,qualifying_sales_count,change_source,changed_by,reason,event_key) values(?,?,?,?,?,?,?,?,?,?)',[$designerId,$d['calculated_rank'],$d['calculated_rank'],$d['creator_rank'],$effective,$d['qualifying_sales_count'],$rank?'admin_override':'admin_override_removed',$adminId,$reason,$event.':rank']);
            DB::commit();
            $oldIndex=array_search($d['creator_rank'],array_keys(self::RANKS),true);$newIndex=array_search($effective,array_keys(self::RANKS),true);$raised=$rank!==null&&$newIndex>$oldIndex;
            $message=$rank?'An administrator set your displayed creator rank to '.$rank.'.':'Your displayed rank returned to '.$effective.'.';
            $this->adminCommunicate($designerId,$event,'Creator rank updated',$message,$raised?'creator_rank':null);return true;
        }catch(Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    public function founderAction(int $designerId,string $action,int $adminId,string $reason): bool
    {
        $this->assertAdmin($adminId);$reason=$this->reason($reason);
        if(!in_array($action,['grant','force_active','force_inactive','automatic','restore'],true))throw new DomainException('Invalid Founder action.');
        DB::begin();
        try{
            $d=DB::row('select * from designers where id=? for update',[$designerId]);
            if(!$d)throw new DomainException('Seller not found.');
            DB::row('select id from creator_recognition_lock where id=1 for update');
            $orders=$this->qualifyingOrders($designerId);$count=count($orders);$calculated=self::rankForSales($count);
            $effective=!empty($d['rank_override'])&&$d['rank_override_value']?$d['rank_override_value']:$calculated;
            $last=$count?$orders[$count-1]['at']:null;$currentTenth=$count>=10?$orders[9]['id']:null;
            $position=$d['founder_position']!==null?(int)$d['founder_position']:null;
            $qualificationOrder=$d['founder_qualifying_order_id']?:($position!==null?$currentTenth:null);
            $earnedAt=$d['founder_earned_at']?:($position!==null&&$currentTenth?$orders[9]['at']:null);
            $rankChanged=$d['calculated_rank']!==$calculated||$d['creator_rank']!==$effective;
            $stateRefresh=(int)$d['qualifying_sales_count']!==$count||$rankChanged||(string)($d['last_qualifying_sale_at']??'')!==(string)($last??'');
            $auditRepair=(string)($d['founder_qualifying_order_id']??'')!==(string)($qualificationOrder??'')||(string)($d['founder_earned_at']??'')!==(string)($earnedAt??'');

            $actionNoOp=($action==='grant'&&$position!==null)||($action==='restore'&&$d['founder_override_state']!=='force_inactive');
            if($action==='grant'&&$position===null&&$d['status']!=='approved')throw new DomainException('Only an approved seller may receive a new Founder position.');
            if($action!=='grant'&&!$position)throw new DomainException('This seller has no reserved Founder position.');
            if($action==='grant'&&$position===null){
                $used=array_map('intval',array_column(DB::rows('select founder_position from designers where founder_position is not null order by founder_position'),'founder_position'));
                for($p=1;$p<=self::FOUNDER_LIMIT;$p++)if(!in_array($p,$used,true)){$position=$p;break;}
                if(!$position)throw new DomainException('All 50 Founder positions are permanently reserved.');
            }
            if($position!==null&&!$qualificationOrder&&$currentTenth){$qualificationOrder=$currentTenth;$earnedAt=$earnedAt?:$orders[9]['at'];}
            $mode=$actionNoOp?$d['founder_override_state']:($action==='force_active'?'force_active':($action==='force_inactive'?'force_inactive':'automatic'));
            $active=$position!==null&&self::founderActive($last,$mode,self::utcNow());
            $before=['position'=>$d['founder_position']!==null?(int)$d['founder_position']:null,'active'=>(bool)$d['founder_active'],'mode'=>$d['founder_override_state']];
            $after=['position'=>$position,'active'=>$active,'mode'=>$mode];$founderChanged=$before!==$after;

            if(!$founderChanged&&!$rankChanged){
                if($stateRefresh||$auditRepair)DB::exec('update designers set qualifying_sales_count=?,calculated_rank=?,creator_rank=?,last_qualifying_sale_at=?,founder_qualifying_order_id=?,founder_earned_at=? where id=?',[$count,$calculated,$effective,$last,$qualificationOrder,$earnedAt,$designerId]);
                DB::commit();return false;
            }

            $adminEvent=null;
            if($founderChanged){
                DB::exec('insert into admin_logs(admin_user_id,action,entity_type,entity_id,metadata) values(?,?,?,?,?)',[$adminId,'creator_founder_'.$action,'designer',$designerId,json_encode(['reason'=>$reason,'position'=>$position])]);
                $adminEvent='admin-recognition:'.(int)DB::id();
            }
            if(!$earnedAt&&$position!==null)$earnedAt=self::utcNow();
            $inactiveAt=$active?null:($d['founder_inactive_at']?:self::utcNow());
            DB::exec('update designers set qualifying_sales_count=?,calculated_rank=?,creator_rank=?,last_qualifying_sale_at=?,founder_position=?,founder_earned_at=?,founder_qualifying_order_id=?,founder_active=?,founder_override_state=?,founder_override_reason=case when ? then ? else founder_override_reason end,founder_override_admin_id=case when ? then ? else founder_override_admin_id end,founder_inactive_at=? where id=?',[$count,$calculated,$effective,$last,$position,$earnedAt,$qualificationOrder,$active?1:0,$mode,$founderChanged?1:0,$reason,$founderChanged?1:0,$adminId,$inactiveAt,$designerId]);

            $rankEventResult=null;
            if($rankChanged){
                $recognitionAfter=['qualifying_sales_count'=>$count,'calculated_rank'=>$calculated,'creator_rank'=>$effective,'last_qualifying_sale_at'=>$last,'founder_position'=>$position,'founder_earned_at'=>$earnedAt,'founder_qualifying_order_id'=>$qualificationOrder,'founder_active'=>$active?1:0,'founder_inactive_at'=>$inactiveAt,'founder_override_state'=>$mode];
                DB::exec('insert into creator_recognition_events(designer_id,source,before_state,after_state,rank_changed,founder_changed) values(?,?,?,?,1,0)',[$designerId,'admin_founder_refresh',json_encode($this->recognitionState($d)),json_encode($this->recognitionState($recognitionAfter))]);
                $rankEvent='recognition-event:'.(int)DB::id();
                DB::exec('insert into creator_rank_history(designer_id,previous_calculated_rank,new_calculated_rank,previous_effective_rank,new_effective_rank,qualifying_sales_count,change_source,changed_by,reason,event_key) values(?,?,?,?,?,?,?,?,?,?)',[$designerId,$d['calculated_rank'],$calculated,$d['creator_rank'],$effective,$count,'admin_founder_refresh',$adminId,$reason,$rankEvent.':rank']);
                $rankEventResult=$recognitionAfter+['designer_id'=>$designerId,'previous'=>$this->recognitionState($d),'event_key'=>$rankEvent,'rank_changed'=>true,'founder_changed'=>false];
            }
            if($founderChanged)DB::exec('insert into creator_badge_history(designer_id,action,before_state,after_state,founder_position,change_source,admin_user_id,reason,event_key) values(?,?,?,?,?,?,?,?,?)',[$designerId,$action,json_encode($before),json_encode($after),$position,'admin',$adminId,$reason,$adminEvent.':founder']);
            DB::commit();
            if($rankEventResult)$this->communicate($rankEventResult);
            if(!$founderChanged)return false;
            $becameActive=!$before['active']&&$active;$title=$action==='force_inactive'?'Founder status updated':($becameActive?'Founder badge restored':'Founder status updated');
            $message=$active?'Founder position #'.$position.' is active.':'Founder position #'.$position.' remains reserved but its badge is inactive.';
            $emailType=($action==='grant'||$becameActive)?'founder_badge':null;
            $this->adminCommunicate($designerId,$adminEvent,$action==='grant'?'Founder recognition earned':$title,$message,$emailType);return true;
        }catch(Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    private function changedFields(array $before,array $after): array
    { $changed=[];foreach($after as $field=>$value)if((string)($before[$field]??'')!==(string)($value??''))$changed[]=$field;return $changed; }
    private function assertAdmin(int $adminId): void
    { if(!DB::row('select id from users where id=? and role="admin" and status="active"',[$adminId]))throw new DomainException('An active administrator is required.'); }
    private function reason(string $reason): string
    { $reason=trim($reason);$length=mb_strlen($reason);if($length<3||$length>500)throw new DomainException('Reason must be between 3 and 500 characters.');return $reason; }
    private function founderState(array $d): array
    { return ['position'=>$d['founder_position']??null,'earned_at'=>$d['founder_earned_at']??null,'qualifying_order_id'=>$d['founder_qualifying_order_id']??null,'active'=>(bool)($d['founder_active']??false),'inactive_at'=>$d['founder_inactive_at']??null,'mode'=>$d['founder_override_state']??'automatic']; }
    private function recognitionState(array $d): array
    { $state=[];foreach(['qualifying_sales_count','calculated_rank','creator_rank','last_qualifying_sale_at','founder_position','founder_earned_at','founder_qualifying_order_id','founder_active','founder_inactive_at','founder_override_state'] as $field)$state[$field]=$d[$field]??null;return $state; }
    private function adminCommunicate(int $designerId,string $event,string $title,string $message,?string $emailType): void
    { try{$d=DB::row('select d.user_id,u.email,u.name from designers d join users u on u.id=d.user_id where d.id=?',[$designerId]);if(!$d)return;NotificationService::recognition((int)$d['user_id'],$event.':notification',$title,$message);if($emailType)EmailQueueService::foundationSellerEmail($d['email'],$emailType,['name'=>$d['name'],'title'=>$title,'message'=>$message,'action_url'=>'/seller/rank'],$event.':email');}catch(Throwable $e){NotificationService::reportFailure('admin_creator_recognition',$e);} }
    private function eventMatchesCurrentState(array $event,array $current): bool
    {
        $after=json_decode((string)$event['after_state'],true)?:[];
        $fields=[];
        if(!empty($event['rank_changed']))$fields=array_merge($fields,self::RANK_FIELDS);
        if(!empty($event['founder_changed']))$fields=array_merge($fields,self::FOUNDER_FIELDS);
        foreach(array_unique($fields) as $field)if((string)($current[$field]??'')!==(string)($after[$field]??''))return false;
        return (bool)$fields;
    }

    private function communicateEvent(array $event): void
    {
        $before=json_decode((string)$event['before_state'],true)?:[];$after=json_decode((string)$event['after_state'],true)?:[];
        $result=$after+['designer_id'=>(int)$event['designer_id'],'previous'=>$before,'event_key'=>'recognition-event:'.(int)$event['id'],'rank_changed'=>(bool)$event['rank_changed'],'founder_changed'=>(bool)$event['founder_changed']];
        $this->communicate($result);
    }
    private function communicate(array $r): void
    {
        try{
            $d=DB::row('select d.user_id,u.email,u.name from designers d join users u on u.id=d.user_id where d.id=?',[$r['designer_id']]);if(!$d)return;$old=$r['previous'];$event=$r['event_key'];
            if(!empty($r['rank_changed'])){$rankUp=array_search($r['calculated_rank'],array_keys(self::RANKS),true)>array_search($old['calculated_rank']??$old['creator_rank'],array_keys(self::RANKS),true);if($rankUp&&$r['calculated_rank']!=='Bronze'){$message='You earned '.$r['calculated_rank'].' creator rank.';NotificationService::recognition((int)$d['user_id'],$event.':rank','Creator rank earned',$message);EmailQueueService::foundationSellerEmail($d['email'],'creator_rank',['name'=>$d['name'],'title'=>'Creator rank earned','message'=>$message,'action_url'=>'/seller/rank'],$event.':rank:email');}elseif($r['calculated_rank']!==($old['calculated_rank']??$old['creator_rank']))NotificationService::recognition((int)$d['user_id'],$event.':rank','Creator rank updated','Your calculated creator rank is now '.$r['calculated_rank'].'.');}
            if(!empty($r['founder_changed'])){if($r['founder_position']&&!$old['founder_position']){$message='You earned Founder position #'.$r['founder_position'].'.';NotificationService::recognition((int)$d['user_id'],$event.':founder','Founder recognition earned',$message);EmailQueueService::foundationSellerEmail($d['email'],'founder_badge',['name'=>$d['name'],'title'=>'Founder recognition earned','message'=>$message,'action_url'=>'/seller/rank'],$event.':founder:email');}elseif($r['founder_active']&&!$old['founder_active']){$message='Your reserved Founder badge is active again.';NotificationService::recognition((int)$d['user_id'],$event.':founder','Founder badge restored',$message);EmailQueueService::foundationSellerEmail($d['email'],'founder_badge',['name'=>$d['name'],'title'=>'Founder badge restored','message'=>$message,'action_url'=>'/seller/rank'],$event.':founder:email');}elseif(!$r['founder_active']&&$old['founder_active'])NotificationService::recognition((int)$d['user_id'],$event.':founder','Founder badge inactive','Your Founder badge is inactive; its position remains reserved.');}
        }catch(Throwable $e){NotificationService::reportFailure('creator_recognition',$e);}
    }
}
