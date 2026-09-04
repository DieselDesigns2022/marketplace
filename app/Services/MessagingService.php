<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;

final class MessagingService
{
    private const MAX_SIZE=10485760;
    private const MIMES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public function side(array $conversation,int $userId): ?string
    { return (int)$conversation['buyer_user_id']===$userId?'buyer':((int)$conversation['seller_user_id']===$userId?'seller':null); }
    public function canAccessConversation(array $conversation,int $userId): bool
    { return $this->side($conversation,$userId)!==null; }
    public function conversation(int $id,int $userId): array
    {
        $c=DB::row('select c.*,d.display_name shop_name,u.name buyer_name from message_conversations c join designers d on d.id=c.designer_id join users u on u.id=c.buyer_user_id where c.id=?',[$id])??H::abort(404);
        if(!$this->canAccessConversation($c,$userId))H::abort(404);
        return $c;
    }
    public function startProduct(int $productId,int $buyerId): int
    {
        $p=DB::row('select p.id,p.title,p.designer_id,d.user_id seller_user_id from products p join designers d on d.id=p.designer_id where p.id=? and p.status in ("approved","published") and d.status="approved"',[$productId])??H::abort(404);
        if(!$this->canStartProduct($p,$buyerId))H::abort(403);
        return $this->create($buyerId,(int)$p['seller_user_id'],(int)$p['designer_id'],(int)$p['id'],null,null,'Product: '.(string)$p['title'],'product:'.$p['id']);
    }
    public function startStore(int $designerId,int $buyerId): int
    {
        $d=DB::row('select id,user_id,display_name from designers where id=? and status="approved"',[$designerId])??H::abort(404);
        return $this->create($buyerId,(int)$d['user_id'],(int)$d['id'],null,null,null,'Storefront: '.(string)$d['display_name'],'store:'.$d['id']);
    }
    public function startBuyerOrderItem(int $itemId,int $buyerId): int
    {
        if(!$this->buyerOrderItemEligible($itemId,$buyerId))H::abort(404);
        $i=DB::row('select oi.id,oi.order_id,oi.product_id,oi.product_title,p.title live_title,oi.designer_id,d.user_id seller_user_id from order_items oi join orders o on o.id=oi.order_id left join products p on p.id=oi.product_id join designers d on d.id=oi.designer_id where oi.id=? and o.user_id=? and o.payment_status in ("paid","partially_refunded")',[$itemId,$buyerId])??H::abort(404);
        return $this->create($buyerId,(int)$i['seller_user_id'],(int)$i['designer_id'],(int)$i['product_id'],(int)$i['order_id'],(int)$i['id'],'Purchased product: '.(string)($i['product_title']?:$i['live_title']?:('Order item #'.$i['id'])),'order-item:'.$i['id']);
    }
    public function startSellerOrderItem(int $itemId,int $sellerId): int
    {
        if(!$this->sellerOrderItemEligible($itemId,$sellerId))H::abort(404);
        $i=DB::row('select oi.id,oi.order_id,oi.product_id,oi.product_title,p.title live_title,oi.designer_id,o.user_id buyer_id from order_items oi join orders o on o.id=oi.order_id left join products p on p.id=oi.product_id join designers d on d.id=oi.designer_id where oi.id=? and d.user_id=? and o.payment_status in ("paid","partially_refunded")',[$itemId,$sellerId])??H::abort(404);
        return $this->create((int)$i['buyer_id'],$sellerId,(int)$i['designer_id'],(int)$i['product_id'],(int)$i['order_id'],(int)$i['id'],'Purchased product: '.(string)($i['product_title']?:$i['live_title']?:('Order item #'.$i['id'])),'order-item:'.$i['id']);
    }
    public function canStartProduct(array $product,int $buyerId): bool
    { return $buyerId>0&&$buyerId!==(int)($product['seller_user_id']??0); }
    public function buyerOrderItemEligible(int $itemId,int $buyerId): bool
    { return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where oi.id=? and o.user_id=? and o.payment_status in ("paid","partially_refunded")',[$itemId,$buyerId]); }
    public function sellerOrderItemEligible(int $itemId,int $sellerId): bool
    { return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id join designers d on d.id=oi.designer_id where oi.id=? and d.user_id=? and o.payment_status in ("paid","partially_refunded")',[$itemId,$sellerId]); }
    public function canMessagePair(int $a,int $b): bool
    { return $a>0&&$b>0&&$a!==$b&&!$this->blocked($a,$b); }
    private function create(int $buyer,int $seller,int $designer,?int $product,?int $order,?int $item,string $label,string $context): int
    {
        if(!$this->canMessagePair($buyer,$seller))H::abort(403);
        $key='b'.$buyer.':s'.$seller.':'.$context;
        DB::exec('insert ignore into message_conversations(buyer_user_id,seller_user_id,designer_id,product_id,order_id,order_item_id,context_label,context_key) values(?,?,?,?,?,?,?,?)',[$buyer,$seller,$designer,$product,$order,$item,mb_substr(strip_tags($label),0,190),$key]);
        return (int)(DB::row('select id from message_conversations where context_key=?',[$key])['id']??0);
    }
    public function inbox(int $userId,string $side,bool $archived): array
    {
        if(!in_array($side,['buyer','seller'],true))H::abort(404);
        $idColumn=$side.'_user_id';$archive=$side.'_archived_at';$read=$side.'_last_read_message_id';
        return DB::rows("select c.*,d.display_name shop_name,u.name buyer_name,(select count(*) from conversation_messages m where m.conversation_id=c.id and m.sender_user_id<>? and m.id>coalesce(c.$read,0)) unread_count,(select body from conversation_messages m where m.conversation_id=c.id order by m.id desc limit 1) latest_body,(select m.id from conversation_messages m where m.conversation_id=c.id order by m.id desc limit 1) latest_message_id,(select count(*) from message_attachments a join conversation_messages m on m.id=a.message_id where m.conversation_id=c.id and m.id=(select max(m2.id) from conversation_messages m2 where m2.conversation_id=c.id)) latest_attachment_count from message_conversations c join designers d on d.id=c.designer_id join users u on u.id=c.buyer_user_id where c.$idColumn=? and c.last_message_at is not null and c.$archive is ".($archived?'not null':'null').' order by coalesce(c.last_message_at,c.created_at) desc',[$userId,$userId]);
    }
    public function messages(array $c): array
    { return DB::rows('select m.*,u.name sender_name from conversation_messages m join users u on u.id=m.sender_user_id where m.conversation_id=? order by m.id',[$c['id']]); }
    public function markRead(array $c,int $userId): void
    {
        $side=$this->side($c,$userId);if(!$side)H::abort(404);
        $max=(int)(DB::row('select max(id) id from conversation_messages where conversation_id=? and sender_user_id<>?',[$c['id'],$userId])['id']??0);
        if(!$max)return;
        DB::exec("update message_conversations set {$side}_last_read_message_id=greatest(coalesce({$side}_last_read_message_id,0),?) where id=?",[$max,$c['id']]);
        DB::exec('update notifications n join conversation_messages m on n.event_key=concat("internal-message:",m.id,":recipient:",?) set n.read_at=coalesce(n.read_at,now()) where n.user_id=? and n.notification_type="internal_message" and m.conversation_id=? and m.sender_user_id<>? and m.id<=?',[$userId,$userId,$c['id'],$userId,$max]);
    }
    public function blocked(int $a,int $b): bool
    { return (bool)DB::row('select id from message_blocks where removed_at is null and ((blocker_user_id=? and blocked_user_id=?) or (blocker_user_id=? and blocked_user_id=?)) limit 1',[$a,$b,$b,$a]); }
    public function send(array $c,int $sender,string $body,array $files): int
    {
        $side=$this->side($c,$sender);if(!$side)H::abort(404);$recipient=$side==='buyer'?(int)$c['seller_user_id']:(int)$c['buyer_user_id'];
        if(!$this->canMessagePair($sender,$recipient))H::abort(403);$body=trim($body);if(mb_strlen($body)>10000)throw new \InvalidArgumentException('Message is too long.');
        $uploads=$this->validateUploads($files);if($body===''&&!$uploads)throw new \InvalidArgumentException('Enter a message or attach an image.');
        $stored=[];$dir=app_path('storage/protected_uploads/messages');if($uploads&&!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Attachment storage is unavailable.');
        try { foreach($uploads as $upload){$name=bin2hex(random_bytes(24)).'.'.$upload['ext'];$path=$dir.'/'.$name;if(!move_uploaded_file($upload['tmp_name'],$path))throw new \RuntimeException('Attachment storage failed.');chmod($path,0640);$upload['stored_name']=$name;$stored[]=$upload;}
            DB::begin();DB::exec('insert into conversation_messages(conversation_id,sender_user_id,body) values(?,?,?)',[$c['id'],$sender,$body===''?null:$body]);$messageId=(int)DB::id();foreach($stored as $f)DB::exec('insert into message_attachments(message_id,original_name,stored_name,mime_type,byte_size,width,height) values(?,?,?,?,?,?,?)',[$messageId,$f['original_name'],$f['stored_name'],$f['mime'],$f['size'],$f['width'],$f['height']]);DB::exec("update message_conversations set last_message_at=now(),buyer_archived_at=null,seller_archived_at=null,{$side}_last_read_message_id=? where id=?",[$messageId,$c['id']]);DB::commit();
        } catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();foreach($stored as $f)@unlink($dir.'/'.$f['stored_name']);throw $e;}
        $recipientSide=$side==='buyer'?'seller':'buyer';
        try{NotificationService::internalMessage($recipient,$recipientSide,$messageId,$side==='buyer'?(string)$c['buyer_name']:(string)$c['shop_name'],'/'.$recipientSide.'/messages/'.$c['id']);}catch(\Throwable $e){NotificationService::reportFailure('internal-message notification',$e);}
        try{$u=DB::row('select email,name from users where id=? and status="active"',[$recipient]);if($u)EmailQueueService::queue('transactional',$u['email'],'You have a new Asset Moth message','internal_message',['name'=>$u['name'],'sender'=>$side==='buyer'?(string)$c['buyer_name']:(string)$c['shop_name'],'shop'=>$c['shop_name'],'context'=>$c['context_label']??null,'order_id'=>$c['order_id']??null,'conversation_url'=>H::baseUrl().'/'.($side==='buyer'?'seller':'buyer').'/messages/'.$c['id']],"internal-message:$messageId:recipient:$recipient");}catch(\Throwable $e){NotificationService::reportFailure('internal-message email',$e);}
        return $messageId;
    }
    private function validateUploads(array $files): array
    {
        if(!isset($files['name']))return[];
        foreach(['name','error','tmp_name','size'] as $key)if(!is_array($files[$key]??null))throw new \InvalidArgumentException('Malformed attachment upload.');
        $indexes=array_keys($files['name']);if(count($indexes)>5)throw new \InvalidArgumentException('A message may have at most 5 attachments.');$out=[];$finfo=new \finfo(FILEINFO_MIME_TYPE);
        foreach($indexes as $i){foreach(['error','tmp_name','size'] as $key)if(!array_key_exists($i,$files[$key]))throw new \InvalidArgumentException('Malformed attachment upload.');$original=$files['name'][$i];if(!is_string($original)||$original===''){if((int)$files['error'][$i]===UPLOAD_ERR_NO_FILE)continue;throw new \InvalidArgumentException('Malformed attachment upload.');}$error=$files['error'][$i];$tmp=$files['tmp_name'][$i];$rawSize=$files['size'][$i];if(!is_int($error)||!is_string($tmp)||(!is_int($rawSize)&&!ctype_digit((string)$rawSize)))throw new \InvalidArgumentException('Malformed attachment upload.');if($error!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('An attachment upload failed.');$size=(int)$rawSize;$actualSize=@filesize($tmp);if($actualSize===false||$actualSize!==$size||$size<1||$size>self::MAX_SIZE)throw new \InvalidArgumentException('Each attachment must be 10 MB or smaller.');if(!is_file($tmp)||!is_uploaded_file($tmp))throw new \InvalidArgumentException('Attachment is not a genuine PHP upload.');$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','webp'],true))throw new \InvalidArgumentException('Attachments must use a JPG, PNG, or WEBP extension.');$bytes=@file_get_contents($tmp);$image=$bytes===false?false:@getimagesizefromstring($bytes);$mime=$finfo->file($tmp);$expected=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext];if(!$image||!is_string($mime)||!hash_equals($expected,$mime)||!hash_equals($expected,(string)($image['mime']??'')))throw new \InvalidArgumentException('Attachments must be genuine JPG, PNG, or WEBP images.');$width=(int)$image[0];$height=(int)$image[1];if(!SellerReceiptService::sourceDimensionsAllowed($width,$height))throw new \InvalidArgumentException('Attachment dimensions exceed the safe 25-megapixel limit.');if(!SellerReceiptService::hasExactImageContainer($bytes,$mime))throw new \InvalidArgumentException('Attachment contains malformed or trailing data.');if(!extension_loaded('gd')||!function_exists('imagecreatefromstring'))throw new \RuntimeException('Image decoding is unavailable.');$decoded=@imagecreatefromstring($bytes);if(!$decoded)throw new \InvalidArgumentException('Attachment could not be decoded.');imagedestroy($decoded);$out[]=['tmp_name'=>$tmp,'original_name'=>mb_substr(basename($original),0,190),'size'=>$size,'mime'=>$mime,'ext'=>self::MIMES[$mime],'width'=>$width,'height'=>$height];}
        return $out;
    }
    public function archive(array $c,int $userId,bool $archive): void{$side=$this->side($c,$userId);DB::exec("update message_conversations set {$side}_archived_at=".($archive?'now()':'null').' where id=?',[$c['id']]);}
    public function block(array $c,int $userId,bool $block): void{$other=$this->side($c,$userId)==='buyer'?(int)$c['seller_user_id']:(int)$c['buyer_user_id'];if($block)DB::exec('insert into message_blocks(blocker_user_id,blocked_user_id) select ?,? where not exists(select id from message_blocks where blocker_user_id=? and blocked_user_id=? and removed_at is null)',[$userId,$other,$userId,$other]);else DB::exec('update message_blocks set removed_at=now() where blocker_user_id=? and blocked_user_id=? and removed_at is null',[$userId,$other]);}
    public function report(array $c,int $userId,string $reason,string $details): void{if(!in_array($reason,['abuse','spam','inappropriate','other'],true))throw new \InvalidArgumentException('Choose a report reason.');DB::exec('insert into message_reports(conversation_id,reporter_user_id,reason,details) values(?,?,?,?) on duplicate key update reason=values(reason),details=values(details),status="open",moderator_user_id=null,moderator_notes=null,reviewed_at=null',[$c['id'],$userId,$reason,mb_substr(trim($details),0,1000)]);}
    public function attachments(array $messages): array{$ids=array_column($messages,'id');if(!$ids)return[];$rows=DB::rows('select * from message_attachments where message_id in ('.implode(',',array_fill(0,count($ids),'?')).') order by id',$ids);$out=[];foreach($rows as $r)$out[$r['message_id']][]=$r;return$out;}
    public function attachmentForUser(int $attachmentId,int $userId): ?array
    { $a=DB::row('select a.*,m.conversation_id from message_attachments a join conversation_messages m on m.id=a.message_id where a.id=?',[$attachmentId]);if(!$a)return null;$c=DB::row('select buyer_user_id,seller_user_id from message_conversations where id=?',[$a['conversation_id']]);return $c&&$this->canAccessConversation($c,$userId)?$a:null; }
    public function attachmentForReportedConversation(int $reportId,int $attachmentId): ?array
    { return DB::row('select a.* from message_attachments a join conversation_messages m on m.id=a.message_id join message_reports r on r.conversation_id=m.conversation_id where r.id=? and a.id=?',[$reportId,$attachmentId]); }
}
