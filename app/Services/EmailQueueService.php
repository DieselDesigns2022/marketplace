<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use Throwable;

final class EmailQueueService
{
    public static function validEnvelope(string $email,string $subject): bool
    { return filter_var(strtolower(trim($email)),FILTER_VALIDATE_EMAIL)!==false && !preg_match('/[\r\n]/',$subject.$email); }
    public static function retryDelay(int $attempt): ?int { return [1=>5,2=>30][$attempt]??null; }
    public static function sellerSubject(string $type): string
    {
        return ['new_sale'=>'You made a sale on Asset Moth','coupon_used'=>'A coupon was used on your Asset Moth sale','product_approved'=>'Your Asset Moth product was approved','product_rejected'=>'Your Asset Moth product needs changes','product_flagged'=>'Your Asset Moth product was flagged for review'][$type]??'Asset Moth seller update';
    }
    public static function receiptTitle(?string $snapshot, ?string $legacyLiveTitle): string
    {
        $snapshot = trim((string) $snapshot);
        if ($snapshot !== '') return $snapshot;
        $legacyLiveTitle = trim((string) $legacyLiveTitle);
        return $legacyLiveTitle !== '' ? $legacyLiveTitle : 'Purchased product';
    }
    public static function authorizedAdminTest(array $user,string $recipient,array $data): bool
    { return ($data['admin_test_send']??false)===true && ($user['role']??'')==='admin' && ($user['status']??'')==='active' && hash_equals(strtolower(trim((string)($user['email']??''))),strtolower(trim($recipient))); }
    public static function queue(string $classification,string $email,string $subject,string $template,array $data,string $dedupe,array $links=[]): bool
    {
        $email=strtolower(trim($email));
        if (!in_array($classification,['transactional','marketing'],true) || !self::validEnvelope($email,$subject)) return false;
        unset($data['admin_test_send']);
        if(!empty($links['admin_test']))$data['admin_test_send']=true;
        if($classification==='marketing'){
            if(!empty($links['waitlist_entry_id'])){$w=DB::row('select id,unsubscribe_nonce from waitlist_entries where id=?',[$links['waitlist_entry_id']]);if(!$w)return false;$data['unsubscribe_url']=UnsubscribeService::url('w',(int)$w['id'],$w['unsubscribe_nonce']);}
            elseif(!empty($data['user_id'])){$p=DB::row('select user_id,unsubscribe_nonce from email_preferences where user_id=?',[$data['user_id']]);if(!$p)return false;$data['unsubscribe_url']=UnsubscribeService::url('u',(int)$p['user_id'],$p['unsubscribe_nonce']);}
            else return false;
        }
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return DB::exec('insert ignore into email_messages (classification,recipient_email,recipient_name,subject,template,template_data,campaign_id,campaign_recipient_id,waitlist_entry_id,deduplication_key,next_attempt_at) values (?,?,?,?,?,?,?,?,?,?,now())',[
            $classification,$email,mb_substr(strip_tags((string)($data['name']??'')),0,120),mb_substr(strip_tags($subject),0,190),$template,$json,$links['campaign_id']??null,$links['campaign_recipient_id']??null,$links['waitlist_entry_id']??null,mb_substr($dedupe,0,190)]);
    }

    public static function paidOrder(int $orderId): void
    {
        $o=DB::row('select o.*,u.email,u.name from orders o join users u on u.id=o.user_id where o.id=? and o.payment_status="paid" and o.manual_review_required=0',[$orderId]);
        if(!$o)return;
        $items=DB::rows('select oi.id,oi.product_id,oi.product_title, p.title legacy_live_title,oi.license_type,oi.license_name,oi.license_snapshot,oi.unit_price,oi.license_price,oi.total_price,oi.coupon_discount from order_items oi left join products p on p.id=oi.product_id where oi.order_id=? order by oi.id',[$orderId]);
        foreach ($items as &$item) $item['title']=self::receiptTitle($item['product_title']??null,$item['legacy_live_title']??null);
        unset($item);
        self::queue('transactional',$o['email'],'Your Asset Moth receipt','purchase_receipt',['name'=>$o['name'],'order'=>$o,'items'=>$items],"order:$orderId:receipt");
        self::queue('transactional',$o['email'],'Your downloads are ready','download_ready',['name'=>$o['name'],'order_id'=>$orderId],"order:$orderId:downloads");
    }
    public static function refund(int $orderId,string $status,int $cumulativeRefundCents,string $transitionKey): void { $o=DB::row('select o.*,u.email,u.name from orders o join users u on u.id=o.user_id where o.id=?',[$orderId]); if($o)self::queue('transactional',$o['email'],'Refund status update','refund_status',['name'=>$o['name'],'order'=>$o,'refund_status'=>$status,'cumulative_refund_amount'=>$cumulativeRefundCents/100],$transitionKey.':email'); }
    public static function foundationSellerEmail(string $email,string $type,array $data,string $eventKey): bool { return self::queue('transactional',$email,self::sellerSubject($type),'seller_notification',$data,$eventKey); }

    public static function process(int $limit=50): array
    {
        $limit=max(1,min(100,$limit)); $result=['claimed'=>0,'sent'=>0,'failed'=>0];
        DB::exec('update email_messages set status="pending",claimed_at=null where status="processing" and claimed_at < date_sub(now(),interval 15 minute)');
        for($i=0;$i<$limit;$i++) {
            DB::begin();
            $m=DB::row('select * from email_messages where status="pending" and (next_attempt_at is null or next_attempt_at<=now()) order by id limit 1 for update skip locked');
            if(!$m){DB::commit();break;}
            DB::exec('update email_messages set status="processing",claimed_at=now() where id=? and status="pending"',[$m['id']]); DB::commit(); $result['claimed']++;
            $delivered=false;try {
                if ($m['classification']==='marketing' && !self::stillConsented($m)) { self::cancelSuppressed($m); continue; }
                self::deliver($m);$delivered=true;
            } catch(Throwable $e) { self::recordFailure($m,$e->getMessage()); $result['failed']++;continue; }
            if($delivered){self::recordDelivered($m);$result['sent']++;}
        }
        return $result;
    }
    private static function stillConsented(array $m): bool
    {
        if(!empty($m['waitlist_entry_id'])) return (bool)DB::row('select id from waitlist_entries where id=? and status in ("subscribed","invited") and unsubscribed_at is null',[$m['waitlist_entry_id']]);
        $d=json_decode($m['template_data'],true)?:[];
        if(($d['admin_test_send']??false)===true){$user=!empty($d['user_id'])?DB::row('select id,email,role,status from users where id=?',[$d['user_id']]):null;return $user?self::authorizedAdminTest($user,$m['recipient_email'],$d):false;}
        if(!empty($d['user_id'])) return (bool)DB::row('select user_id from email_preferences where user_id=? and marketing_opt_in=1 and marketing_opted_out_at is null',[$d['user_id']]);
        return false;
    }
    private static function deliver(array $m): void
    {
        $data=json_decode($m['template_data'],true,512,JSON_THROW_ON_ERROR); $template=preg_replace('/[^a-z0-9_]/','',$m['template']);
        $body=self::render($template,$data); $transport=$_ENV['MAIL_TRANSPORT']??'log';
        if($transport!=='log') throw new \RuntimeException('Configured mail transport is unavailable');
        $dir=app_path('storage/logs'); if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Mail log directory is not writable');
        self::appendLogOnce($dir.'/mail.log',['timestamp'=>gmdate('c'),'message_id'=>(int)$m['id'],'template'=>$template,'recipient_hash'=>hash('sha256',strtolower($m['recipient_email'])),'subject'=>$m['subject'],'body_bytes'=>strlen($body)]);
    }
    public static function appendLogOnce(string $path,array $entry): bool
    {
        $id=(int)($entry['message_id']??0);if($id<1)throw new \InvalidArgumentException('Invalid log message identifier.');
        $handle=fopen($path,'c+');if(!$handle)throw new \RuntimeException('Mail log open failed');$locked=false;
        try {
            if(!flock($handle,LOCK_EX))throw new \RuntimeException('Mail log lock failed');$locked=true;rewind($handle);$lastValidOffset=0;$found=false;
            while(($line=fgets($handle))!==false){$offset=ftell($handle);if(!str_ends_with($line,"\n")){if(!ftruncate($handle,$lastValidOffset))throw new \RuntimeException('Incomplete mail log repair failed');break;}$existing=json_decode(rtrim($line,"\r\n"),true);if(!is_array($existing)||!isset($existing['message_id'])||(int)$existing['message_id']<1)throw new \RuntimeException('Mail log contains a malformed complete record');$lastValidOffset=$offset;if((int)$existing['message_id']===$id)$found=true;}
            if($found)return false;
            if(fseek($handle,0,SEEK_END)!==0)throw new \RuntimeException('Mail log seek failed');$json=json_encode($entry,JSON_UNESCAPED_SLASHES);if($json===false)throw new \RuntimeException('Mail log encoding failed');$bytes=$json."\n";$written=0;$length=strlen($bytes);
            while($written<$length){$count=fwrite($handle,substr($bytes,$written));if($count===false||$count===0)throw new \RuntimeException('Mail log write stalled');$written+=$count;}
            if(!fflush($handle))throw new \RuntimeException('Mail log flush failed');return true;
        } finally {if($locked)@flock($handle,LOCK_UN);@fclose($handle);}
    }
    private static function render(string $template,array $data): string { ob_start(); require app_path('app/Views/emails/'.$template.'.php'); $content=(string)ob_get_clean(); ob_start(); require app_path('app/Views/emails/layout.php'); return (string)ob_get_clean(); }
    private static function recordFailure(array $m,string $error): void { $attempt=(int)$m['attempt_count']+1; $safe=self::safeError($error); $delay=self::retryDelay($attempt); DB::exec('update email_messages set status=?,attempt_count=?,next_attempt_at=if(?="pending",date_add(now(),interval ? minute),null),last_error=?,claimed_at=null where id=? and status="processing"',[$delay===null?'failed':'pending',$attempt,$delay===null?'failed':'pending',$delay??0,$safe,$m['id']]); self::syncSafely($m,$delay===null?'failed':'queued',$safe); }
    private static function cancelSuppressed(array $m): void { DB::exec('update email_messages set status="cancelled",last_error="Consent withdrawn before delivery" where id=? and status="processing"',[$m['id']]); self::syncSafely($m,'suppressed','Consent withdrawn before delivery'); }
    private static function recordDelivered(array $m): void { try { DB::begin(); DB::exec('update email_messages set status="sent",attempt_count=attempt_count+1,sent_at=now(),claimed_at=null,last_error=null where id=? and status="processing"',[$m['id']]); if(!empty($m['waitlist_entry_id'])) { if($m['template']==='waitlist_confirmation')DB::exec('update waitlist_entries set confirmation_sent_at=coalesce(confirmation_sent_at,now()) where id=?',[$m['waitlist_entry_id']]); if($m['template']==='launch_invite')DB::exec('update waitlist_entries set invited_at=coalesce(invited_at,now()),status=if(status="subscribed","invited",status) where id=?',[$m['waitlist_entry_id']]); } if(!empty($m['campaign_recipient_id']))DB::exec('update email_campaign_recipients set status="sent",last_error=null where id=?',[$m['campaign_recipient_id']]); DB::commit(); } catch(Throwable $e) { try{if(DB::pdo()->inTransaction())DB::rollBack();}catch(Throwable $ignored){self::safeReport('post_delivery_rollback',$ignored);}try{DB::exec('update email_messages set status="sent",attempt_count=attempt_count+1,sent_at=coalesce(sent_at,now()),claimed_at=null,last_error=? where id=? and status="processing"',["Delivered; reconciliation required: ".self::safeError($e->getMessage()),$m['id']]);}catch(Throwable $fallback){self::safeReport('post_delivery_fallback',$fallback);}self::safeReport('post_delivery_reconciliation',$e);return;}if(!empty($m['campaign_id']))try{EmailCampaignService::recalculate((int)$m['campaign_id']);}catch(Throwable $e){try{DB::exec('update email_messages set last_error=? where id=? and status="sent"',["Delivered; campaign reconciliation required: ".self::safeError($e->getMessage()),$m['id']]);}catch(Throwable $diagnostic){self::safeReport('campaign_reconciliation_diagnostic',$diagnostic);}self::safeReport('campaign_reconciliation',$e);} }
    private static function safeError(string $error): string{return OperationalErrorSanitizer::sanitize($error,400);}
    private static function syncSafely(array $m,string $status,?string $error):void{try{self::syncRecipient($m,$status,$error);}catch(Throwable $e){try{DB::exec('update email_messages set last_error=? where id=? and status in ("pending","failed","cancelled")',["Message state saved; reconciliation required: ".self::safeError($e->getMessage()),$m['id']]);}catch(Throwable $diagnostic){self::safeReport('queue_reconciliation_diagnostic',$diagnostic);}self::safeReport('queue_recipient_reconciliation',$e);}}
    private static function safeReport(string $context,Throwable $error):void{try{NotificationService::reportFailure($context,$error);}catch(Throwable $ignored){/* Reporting must never alter queue state or worker progress. */}}
    private static function syncRecipient(array $m,string $status,?string $error): void { if(!empty($m['campaign_recipient_id'])) { DB::exec('update email_campaign_recipients set status=?,last_error=? where id=?',[$status,$error,$m['campaign_recipient_id']]); if(!empty($m['campaign_id'])) EmailCampaignService::recalculate((int)$m['campaign_id']); } }
}
