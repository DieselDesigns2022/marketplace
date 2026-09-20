<?php
namespace App\Services;

use App\Core\Database as DB;

/** The single authority for paid-promotion validation, state and delivery. */
final class PromoService
{
    private static function notify(int $userId,string $key,string $message): void
    {
        try { NotificationService::promoStatus($userId,$key,$message); }
        catch (\Throwable $e) { try { NotificationService::reportFailure('promo_status',$e); } catch(\Throwable $ignored) { error_log('Creative Moth promotion notification failed.'); } }
    }
    public static function packages(?string $placement=null): array
    { return $placement ? DB::rows('select * from promo_packages where placement=? order by duration_value',[$placement]) : DB::rows('select * from promo_packages order by field(placement,"marketplace","homepage","category","weekly_email"),duration_value'); }

    public static function seller(int $userId): array
    { return DB::row('select * from designers where user_id=? and status="approved"',[$userId]) ?? throw new \InvalidArgumentException('An approved seller account is required.'); }

    public static function products(int $designerId): array
    { return DB::rows('select id,title,slug from products where designer_id=? and status="approved" order by title',[$designerId]); }

    public static function categories(): array
    { return DB::rows('select id,name,slug from categories where is_active=1 order by sort_order,name'); }

    public static function validate(array $seller,string $targetType,?int $productId,int $packageId,?int $categoryId): array
    {
        if(!in_array($targetType,['shop','product'],true))throw new \InvalidArgumentException('Choose a valid promotion target.');
        if(($seller['status']??'')!=='approved')throw new \InvalidArgumentException('The shop is not approved.');
        $product=null;
        if($targetType==='product'){
            $product=DB::row('select * from products where id=? and designer_id=? and status="approved"',[$productId,(int)$seller['id']]);
            if(!$product)throw new \InvalidArgumentException('Choose one of your approved products.');
        }
        $package=DB::row('select * from promo_packages where id=?',[$packageId]) ?? throw new \InvalidArgumentException('Choose a valid package.');
        if($package['placement']==='category'){
            if(!$categoryId||!DB::row('select id from categories where id=? and is_active=1',[$categoryId]))throw new \InvalidArgumentException('Choose an active category.');
        } else $categoryId=null;
        return compact('product','package','categoryId');
    }

    public static function createPending(array $seller,string $targetType,?int $productId,int $packageId,?int $categoryId=null): array
    {
        $v=self::validate($seller,$targetType,$productId,$packageId,$categoryId);$p=$v['package'];
        DB::exec('insert into ads(product_id,designer_id,placement,target_type,category_id,package_id,duration_value,duration_unit,price_cents,currency,payment_status,status,click_token) values(?,?,?,?,?,?,?,?,?,?,"pending","pending_payment",?)',[$targetType==='product'?$productId:null,$seller['id'],$p['placement'],$targetType,$v['categoryId'],$p['id'],$p['duration_value'],$p['duration_unit'],$p['price_cents'],StripeService::currency(),bin2hex(random_bytes(32))]);
        return DB::row('select * from ads where id=?',[DB::id()]);
    }

    public static function storeCheckoutIds(int $id,?string $session,?string $intent=null): void
    { DB::exec('update ads set stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id) where id=?',[$session,$intent,$id]); }

    public static function activate(int $id,int $amount,string $currency,?string $session=null,?string $intent=null,?string $paidAt=null): bool
    {
        DB::begin();try{$ad=DB::row('select a.*,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? for update',[$id]);
            if(!$ad)throw new \RuntimeException('Promotion campaign not found.');
            if($ad['payment_status']==='paid'){self::storeCheckoutIds($id,$session,$intent);DB::commit();self::notify((int)$ad['user_id'],'promo:'.$id.':active','Your paid promotion is active.');return false;}
            if($amount!==(int)$ad['price_cents']||strtolower($currency)!==strtolower($ad['currency']))throw new \RuntimeException('Promotion payment does not match its price snapshot.');
            $when=$paidAt?:gmdate('Y-m-d H:i:s');$ends=null;$total=null;
            if($ad['placement']==='weekly_email')$total=(int)$ad['duration_value'];
            else $ends=(new \DateTimeImmutable($when,new \DateTimeZone('UTC')))->modify('+'.(int)$ad['duration_value'].' days')->format('Y-m-d H:i:s');
            DB::exec('update ads set payment_status="paid",status="active",paid_at=?,starts_at=?,ends_at=?,email_total_appearances=?,email_appearances_used=0,stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id) where id=?',[$when,$when,$ends,$total,$session,$intent,$id]);DB::commit();
            self::notify((int)$ad['user_id'],'promo:'.$id.':active','Your paid promotion is active.');return true;
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    public static function paymentFailed(int $id,?string $session=null,?string $intent=null): void { self::terminalPayment($id,'failed','payment_failed',$session,$intent); }
    public static function checkoutExpired(int $id,?string $session=null): void { self::terminalPayment($id,'cancelled','cancelled',$session,null); }
    public static function checkoutSetupFailed(int $id): void { self::terminalPayment($id,'failed','payment_failed',null,null,'checkout_setup_failed','Promotion checkout could not be started. No charge was confirmed and no promotion activated.'); }
    private static function terminalPayment(int $id,string $payment,string $status,?string $session,?string $intent,?string $event=null,?string $message=null): void
    {
        DB::begin();
        try {
            $ad=DB::row('select a.*,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? for update',[$id]);
            if(!$ad||$ad['payment_status']==='paid'){DB::commit();return;}
            DB::exec('update ads set payment_status=?,status=?,stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id) where id=?',[$payment,$status,$session,$intent,$id]);
            DB::commit();
        } catch(\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();throw $e; }
        self::notify((int)$ad['user_id'],'promo:'.$id.':'.($event??$status),$message??('Your promotion payment is '.$payment.'.'));
    }

    public static function expireWebsite(): int
    {
        $expired=DB::rows('select a.id,d.user_id from ads a join designers d on d.id=a.designer_id where a.status="active" and a.placement<>"weekly_email" and a.ends_at<=now(6) order by a.id');
        $count=0;
        foreach($expired as $candidate){
            DB::begin();
            try {
                $ad=DB::row('select status,ends_at,(ends_at<=now(6)) is_expired from ads where id=? for update',[(int)$candidate['id']]);
                $ended=$ad&&$ad['status']==='active'&&(int)$ad['is_expired']===1;
                if($ended){DB::exec('update ads set status="ended" where id=? and status="active"',[(int)$candidate['id']]);$count++;}
                DB::commit();
                if($ended)self::notify((int)$candidate['user_id'],'promo:'.$candidate['id'].':complete','Your promotion is complete.');
            } catch(\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();throw $e; }
        }
        return $count;
    }
    public static function notifyWebsiteEnding(): int
    {
        $count=0;
        foreach(DB::rows('select a.id,d.user_id from ads a join designers d on d.id=a.designer_id where a.payment_status="paid" and a.status="active" and a.placement in ("marketplace","homepage","category") and a.ends_at>now(6) and a.ends_at<=date_add(now(6),interval 24 hour)') as $ad){
            $key='promo:'.$ad['id'].':ending';
            if(DB::row('select id from notifications where event_key=?',[$key]))continue;
            self::notify((int)$ad['user_id'],$key,'Your paid promotion ends within 24 hours.');
            if(DB::row('select id from notifications where event_key=?',[$key]))$count++;
        }
        return $count;
    }
    public static function notifyWeeklyEndingForQueuedIssue(array $promos,string $start,string $end): int
    {
        if(!self::validPeriodDate($start)||!self::validPeriodDate($end)||$start>=$end)return 0;
        $count=0;$seen=[];
        foreach($promos as $promo){$id=(int)($promo['id']??0);if($id<1||isset($seen[$id]))continue;$seen[$id]=true;$ad=DB::row('select a.id,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? and a.placement="weekly_email" and a.payment_status="paid" and ((a.status="active" and a.email_total_appearances-a.email_appearances_used=1) or (a.status="ended" and exists(select 1 from promo_email_appearances pea where pea.ad_id=a.id and pea.period_start=? and pea.period_end=?)))',[$id,$start,$end]);if(!$ad)continue;$key='promo:'.$id.':ending';if(DB::row('select id from notifications where event_key=?',[$key]))continue;self::notify((int)$ad['user_id'],$key,'Your weekly promotion has 1 paid email appearance remaining.');if(DB::row('select id from notifications where event_key=?',[$key]))$count++;}
        return $count;
    }
    public static function pause(int $id): bool { return self::transition($id,'pause'); }
    public static function resume(int $id): bool { return self::transition($id,'resume'); }
    public static function cancel(int $id): bool { return self::transition($id,'cancel'); }
    private static function transition(int $id,string $action): bool
    { DB::begin();try{$a=DB::row('select a.*,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? for update',[$id]);if(!$a){DB::commit();return false;}$ok=false;
        if($action==='pause'&&$a['status']==='active'){$ok=DB::exec('update ads set status="paused",paused_at=now() where id=?',[$id]);}
        elseif($action==='resume'&&$a['status']==='paused'&&$a['payment_status']==='paid'){$ok=DB::exec('update ads set status="active",ends_at=case when ends_at is null then null else date_add(ends_at,interval timestampdiff(second,paused_at,now()) second) end,paused_at=null where id=?',[$id]);}
        elseif($action==='cancel'&&!in_array($a['status'],['cancelled','ended'],true)){$ok=DB::exec('update ads set status="cancelled" where id=?',[$id]);}
        DB::commit();if($ok)self::notify((int)$a['user_id'],'promo:'.$id.':'.$action.':'.time(),$action==='cancel'?'Your promotion was removed by Creative Moth.':'Your promotion was '.$action.'d.');return $ok;
      }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}}

    public static function chooseWebsite(string $placement,?int $categoryId=null): ?array
    {
        if(!in_array($placement,['marketplace','homepage','category'],true))return null;self::expireWebsite();DB::begin();
        try{$sql='select a.id from ads a join designers d on d.id=a.designer_id left join products p on p.id=a.product_id where a.status="active" and a.payment_status="paid" and a.placement=? and a.starts_at<=now(6) and a.ends_at>now(6) and d.status="approved" and (a.target_type="shop" or (p.designer_id=a.designer_id and p.status="approved"))';$params=[$placement];
            if($placement==='category'){$sql.=' and a.category_id=? and exists(select 1 from categories c where c.id=a.category_id and c.is_active=1)';$params[]=$categoryId;}
            $sql.=' order by a.last_served_at is not null,a.last_served_at asc,a.id asc limit 1 for update';$row=DB::row($sql,$params);
            if(!$row){DB::commit();return null;}DB::exec('update ads set impressions=impressions+1,last_served_at=now(6) where id=?',[$row['id']]);$card=self::card((int)$row['id']);DB::commit();return $card;
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    public static function card(int $id,bool $requireActive=false): ?array
    { $a=DB::row('select a.*,d.display_name,d.store_slug,d.banner_path,d.avatar_path,d.bio,d.average_rating,d.review_count,d.status designer_status,p.title product_title,p.slug product_slug,p.price,p.status product_status,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) product_image from ads a join designers d on d.id=a.designer_id left join products p on p.id=a.product_id where a.id=?',[$id]);if(!$a||$a['designer_status']!=='approved'||($a['target_type']==='product'&&$a['product_status']!=='approved'))return null;if($requireActive&&($a['status']!=='active'||$a['payment_status']!=='paid'))return null;
      return ['id'=>(int)$a['id'],'product_id'=>$a['product_id']!==null?(int)$a['product_id']:null,'type'=>$a['target_type'],'title'=>$a['target_type']==='shop'?$a['display_name']:$a['product_title'],'image'=>$a['target_type']==='shop'?($a['banner_path']?:$a['avatar_path']):$a['product_image'],'store'=>$a['display_name'],'bio'=>$a['bio']??'','price'=>$a['target_type']==='product'?$a['price']:null,'average_rating'=>(float)$a['average_rating'],'review_count'=>(int)$a['review_count'],'click_token'=>$a['click_token'],'destination'=>$a['target_type']==='shop'?'/store/'.$a['store_slug']:'/product/'.$a['product_slug']]; }
    public static function click(string $token): string { if(!preg_match('/^[a-f0-9]{64}$/',$token))return '/browse';$a=DB::row('select id from ads where click_token=? and payment_status="paid" and status<>"cancelled"',[$token]);if(!$a)return '/browse';$card=self::card((int)$a['id']);if(!$card)return '/browse';DB::exec('update ads set clicks=clicks+1 where id=?',[$a['id']]);return $card['destination']; }

    public static function weekly(string $start,string $end): array
    { $rows=DB::rows('select a.id from ads a join designers d on d.id=a.designer_id left join products p on p.id=a.product_id where a.placement="weekly_email" and a.status="active" and a.payment_status="paid" and date(a.paid_at)<=? and a.email_appearances_used<a.email_total_appearances and d.status="approved" and (a.target_type="shop" or (p.designer_id=a.designer_id and p.status="approved")) and not exists(select 1 from promo_email_appearances pea where pea.ad_id=a.id and pea.period_start=? and pea.period_end=?) order by a.paid_at,a.id',[$end,$start,$end]);$out=[];foreach($rows as $r)if($c=self::card((int)$r['id'],true))$out[]=$c;return $out; }
    public static function revalidateQueuedWeekly(array $promos,string $start,string $end): array
    {
        $fresh=[];$seen=[];
        foreach($promos as $promo){
            $id=(int)($promo['id']??0);if($id<1||isset($seen[$id]))continue;$seen[$id]=true;
            $eligible=DB::row('select a.id from ads a join designers d on d.id=a.designer_id left join products p on p.id=a.product_id where a.id=? and a.placement="weekly_email" and a.payment_status="paid" and date(a.paid_at)<=? and d.status="approved" and (a.target_type="shop" or (p.designer_id=a.designer_id and p.status="approved")) and (a.status="active" or (a.status="ended" and exists(select 1 from promo_email_appearances pea where pea.ad_id=a.id and pea.period_start=? and pea.period_end=?)))',[$id,$end,$start,$end]);
            if($eligible&&($card=self::card($id)))$fresh[]=$card;
        }
        return $fresh;
    }
    public static function recordWeeklyDelivery(int $emailMessageId): void
    {
        $message=DB::row('select * from email_messages where id=? and status="sent" and classification="marketing" and template="marketplace_digest"',[$emailMessageId]);if(!$message)return;
        try{$data=json_decode($message['template_data'],true,512,JSON_THROW_ON_ERROR);}catch(\Throwable $e){throw new \RuntimeException('Sent weekly promotion data is invalid.',0,$e);}
        $start=(string)($data['period_start']??'');$end=(string)($data['period_end']??'');$promos=$data['paid_promos']??null;
        if(($data['marketing_preference']??'')!=='weekly'||!is_array($promos)||!self::validPeriodDate($start)||!self::validPeriodDate($end)||$start>=$end)throw new \RuntimeException('Sent weekly promotion accounting data is incomplete.');
        $seen=[];
        foreach($promos as $promo){
            $id=(int)($promo['id']??0);if($id<1||isset($seen[$id]))continue;$seen[$id]=true;$completed=false;$userId=0;
            DB::begin();
            try {
                $ad=DB::row('select a.*,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? for update',[$id]);
                if(!$ad||$ad['placement']!=='weekly_email'||$ad['payment_status']!=='paid'){DB::commit();continue;}
                DB::exec('insert ignore into promo_email_sends(ad_id,email_message_id,period_start,period_end) values(?,?,?,?)',[$id,$emailMessageId,$start,$end]);
                if((int)DB::pdo()->query('select row_count()')->fetchColumn()===0){DB::commit();continue;}
                DB::exec('insert ignore into promo_email_appearances(ad_id,period_start,period_end) values(?,?,?)',[$id,$start,$end]);
                if((int)DB::pdo()->query('select row_count()')->fetchColumn()>0){
                    DB::exec('update ads set email_appearances_used=email_appearances_used+1 where id=?',[$id]);
                    $used=(int)$ad['email_appearances_used']+1;
                    if($used>=(int)$ad['email_total_appearances']){DB::exec('update ads set status="ended" where id=?',[$id]);$completed=true;$userId=(int)$ad['user_id'];}
                }
                DB::commit();
                if($completed)self::notify($userId,'promo:'.$id.':complete','Your promotion is complete.');
            } catch(\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();throw $e; }
        }
    }
    private static function validPeriodDate(string $date): bool { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));return $d&&$d->format('Y-m-d')===$date; }
    public static function reconcileWeeklyDeliveries(): array
    {
        $result=['reviewed'=>0,'reconciled'=>0];
        foreach(DB::rows('select id from email_messages where status="sent" and classification="marketing" and template="marketplace_digest" and last_error like "Delivered; promotion accounting requires reconciliation:%" order by id') as $message){$result['reviewed']++;try{self::recordWeeklyDelivery((int)$message['id']);DB::exec('update email_messages set last_error=null where id=? and status="sent" and last_error like "Delivered; promotion accounting requires reconciliation:%"',[$message['id']]);$result['reconciled']++;}catch(\Throwable $e){try{NotificationService::reportFailure('promo_delivery_reconciliation_retry',$e);}catch(\Throwable $ignored){}}}
        return $result;
    }
    private static function queuedWeeklyPromoIds(): array
    {
        $ids=[];foreach(DB::rows('select template_data from email_messages where status in ("pending","processing") and classification="marketing" and template="marketplace_digest"') as $message){try{$data=json_decode($message['template_data'],true,512,JSON_THROW_ON_ERROR);}catch(\Throwable $e){continue;}if(($data['marketing_preference']??'')!=='weekly'||!is_array($data['paid_promos']??null))continue;foreach($data['paid_promos'] as $promo){$id=(int)($promo['id']??0);if($id>0)$ids[$id]=true;}}
        return $ids;
    }
    public static function hasQueuedWeeklyRecipients(int $id): bool { return isset(self::queuedWeeklyPromoIds()[$id]); }
    public static function remove(int $id): bool
    {
        DB::begin();try{$ad=DB::row('select a.*,d.user_id from ads a join designers d on d.id=a.designer_id where a.id=? for update',[$id]);if(!$ad){DB::commit();return false;}$allowed=in_array($ad['status'],['active','paused'],true)||($ad['status']==='ended'&&$ad['placement']==='weekly_email'&&self::hasQueuedWeeklyRecipients($id));if(!$allowed){DB::commit();return false;}DB::exec('update ads set status="cancelled" where id=?',[$id]);DB::commit();self::notify((int)$ad['user_id'],'promo:'.$id.':remove:'.time(),'Your promotion was removed by Creative Moth.');return true;}catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }
    public static function updatePrice(int $packageId,int $cents): bool { if($cents<1)throw new \InvalidArgumentException('Price must be at least $0.01.');if(!DB::row('select id from promo_packages where id=?',[$packageId]))return false;DB::exec('update promo_packages set price_cents=? where id=?',[$cents,$packageId]);return true; }
    public static function sellerCampaign(int $id,int $designerId): ?array { self::expireWebsite();return DB::row('select * from ads where id=? and designer_id=?',[$id,$designerId]); }
    public static function campaigns(?int $designerId=null): array { self::expireWebsite();$where=$designerId?'where a.designer_id=?':'where a.payment_status="paid"';$rows=DB::rows('select a.*,d.display_name,d.store_slug,u.email,c.name category_name,p.title product_title,(select count(*) from promo_email_sends pes where pes.ad_id=a.id) email_recipient_sends from ads a join designers d on d.id=a.designer_id join users u on u.id=d.user_id left join products p on p.id=a.product_id left join categories c on c.id=a.category_id '.$where.' order by a.created_at desc,a.id desc',$designerId?[$designerId]:[]);$queued=$designerId===null?self::queuedWeeklyPromoIds():[];foreach($rows as &$row)$row['emergency_removable']=$designerId===null&&$row['status']==='ended'&&$row['placement']==='weekly_email'&&isset($queued[(int)$row['id']]);unset($row);return $rows; }
}
