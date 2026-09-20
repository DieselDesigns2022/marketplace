<?php
namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class SellerReviewService
{
    public function markDownloaded(int $itemId, int $buyerId): bool
    {
        $item=DB::row('select oi.id,oi.product_id,o.id order_id,o.user_id,o.payment_status from order_items oi join orders o on o.id=oi.order_id where oi.id=? and o.user_id=? and o.payment_status="paid"',[$itemId,$buyerId]);
        if(!$item) throw new DomainException('This download is not review eligible.');
        return $this->markEligible($itemId,$buyerId,$item);
    }

    public function markCustomFinalDownloaded(int $fileId, int $buyerId): bool
    {
        $item=DB::row('select oi.id,oi.product_id from custom_order_files f join custom_orders co on co.id=f.custom_order_id join orders o on o.id=co.order_id join order_items oi on oi.id=co.order_item_id and oi.custom_service_id=co.custom_service_id where f.id=? and f.file_kind="final" and co.buyer_user_id=? and co.status="completed" and o.user_id=? and o.payment_status in ("paid","partially_refunded")',[$fileId,$buyerId,$buyerId]);
        if(!$item) throw new DomainException('This custom-design download is not review eligible.');
        return $this->markEligible((int)$item['id'],$buyerId);
    }

    public function create(int $buyerId,int $itemId,mixed $rating,string $text): int
    {
        [$rating,$text]=$this->validateContent($rating,$text);
        $item=DB::row('select oi.id,oi.order_id,oi.product_id,oi.custom_service_id,oi.designer_id,oi.review_eligible_at,o.user_id buyer_id,o.payment_status,u.name buyer_name,coalesce(oi.product_title,p.title,"Purchased item") product_title,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,d.user_id seller_user_id from order_items oi join orders o on o.id=oi.order_id join users u on u.id=o.user_id join designers d on d.id=oi.designer_id left join products p on p.id=oi.product_id where oi.id=? and o.user_id=?',[$itemId,$buyerId]);
        if(!$item||(!($item['payment_status']==='paid'||($item['custom_service_id']!==null&&$item['payment_status']==='partially_refunded')))||empty($item['review_eligible_at'])) throw new DomainException('A successful authorized download is required before reviewing.');
        if((int)$item['seller_user_id']===$buyerId) throw new DomainException('You cannot review your own store.');
        if(DB::row('select id from seller_reviews where order_item_id=?',[$itemId])) throw new DomainException('This purchased item already has a review.');
        $id=$this->atomic(function()use($buyerId,$item,$rating,$text){
            DB::exec('insert into seller_reviews(buyer_id,designer_id,product_id,custom_service_id,order_id,order_item_id,buyer_name_snapshot,seller_name_snapshot,product_title_snapshot,rating,review_text,verified_purchase,reviewed_at) values(?,?,?,?,?,?,?,?,?,?,?,1,now())',[$buyerId,$item['designer_id'],$item['product_id'],$item['custom_service_id'],$item['order_id'],$item['id'],$item['buyer_name'],$item['seller_name'],$item['product_title'],$rating,$text?:null]);
            $id=(int)DB::id();DB::exec('update order_items set reviewed_at=now() where id=?',[$item['id']]);$this->recalculate((int)$item['designer_id']);return $id;
        });
        try{NotificationService::newSellerReview((int)$item['seller_user_id'],$id,$rating,$item['product_title']);}catch(Throwable $e){NotificationService::reportFailure('new seller review notification',$e);}return $id;
    }

    public function edit(int $buyerId,int $reviewId,mixed $rating,string $text): void
    {
        [$rating,$text]=$this->validateContent($rating,$text);$r=DB::row('select id,designer_id from seller_reviews where id=? and buyer_id=?',[$reviewId,$buyerId]);
        if(!$r) throw new DomainException('Review not found.');
        $this->atomic(function()use($r,$rating,$text){DB::exec('update seller_reviews set rating=?,review_text=?,edited_at=now() where id=?',[$rating,$text?:null,$r['id']]);$this->recalculate((int)$r['designer_id']);});
    }

    public function reply(int $sellerUserId,int $reviewId,string $text): void
    {
        $text=trim($text);if($text===''||mb_strlen($text)>2000)throw new InvalidArgumentException('Reply must be between 1 and 2,000 characters.');
        $r=DB::row('select sr.id,sr.buyer_id,sr.designer_id,rr.id reply_id,rr.moderation_status from seller_reviews sr join designers d on d.id=sr.designer_id left join seller_review_replies rr on rr.review_id=sr.id where sr.id=? and d.user_id=?',[$reviewId,$sellerUserId]);if(!$r)throw new DomainException('Review does not belong to this seller.');
        if($r['reply_id']&&$r['moderation_status']==='removed')throw new DomainException('An Admin-removed response cannot be edited or republished.');
        DB::exec('insert into seller_review_replies(review_id,designer_id,reply_text) values(?,?,?) on duplicate key update reply_text=values(reply_text),updated_at=now()',[$reviewId,$r['designer_id'],$text]);
        try{NotificationService::sellerReplied((int)$r['buyer_id'],$reviewId);}catch(Throwable $e){NotificationService::reportFailure('seller reply notification',$e);}
    }

    public function report(int $sellerUserId,int $reviewId,string $reason,string $details): void
    {
        $allowed=['spam','harassment','personal_information','hate_threatening','unrelated','other'];if(!in_array($reason,$allowed,true)||mb_strlen($details)>2000)throw new InvalidArgumentException('Invalid report.');
        $r=DB::row('select sr.id,sr.designer_id from seller_reviews sr join designers d on d.id=sr.designer_id where sr.id=? and d.user_id=?',[$reviewId,$sellerUserId]);if(!$r)throw new DomainException('Review does not belong to this seller.');
        DB::exec('insert into review_reports(review_id,reporter_user_id,designer_id,reason,details) values(?,?,?,?,?)',[$reviewId,$sellerUserId,$r['designer_id'],$reason,$details?:null]);
    }

    public function moderate(int $adminId,int $reviewId,string $action,string $reason,string $note): void
    {
        [$reason,$note]=$this->validateModeration($adminId,$action,$reason,$note,['under_review','removed','restored']);
        $r=DB::row('select id,designer_id,moderation_status from seller_reviews where id=?',[$reviewId]);if(!$r)throw new DomainException('Review not found.');
        $next=$action==='restored'?'published':$action;if($r['moderation_status']===$next)throw new DomainException('The review already has that moderation status.');
        $this->atomic(function()use($adminId,$r,$action,$next,$reason,$note){
            DB::exec('insert into seller_review_moderation_audits(review_id,admin_user_id,action,previous_status,reason,internal_note) values(?,?,?,?,?,?)',[$r['id'],$adminId,$action,$r['moderation_status'],$reason,$note]);
            DB::exec('update seller_reviews set moderation_status=?,removed_by=?,removed_at=?,moderation_reason=? where id=?',[$next,$next==='removed'?$adminId:null,$next==='removed'?date('Y-m-d H:i:s'):null,$reason,$r['id']]);
            if($next==='removed')DB::exec('update review_reports set status="review_removed",admin_user_id=?,admin_note=?,reviewed_at=now(),resolved_at=now() where review_id=? and status in ("open","reviewing","reviewed")',[$adminId,$note,$r['id']]);
            $this->recalculate((int)$r['designer_id']);
        });
    }

    public function resolveReport(int $adminId,int $reportId,string $status,string $note): void
    {
        $allowed=['reviewing','reviewed','no_action','resolved'];$note=trim($note);if($adminId<1||!in_array($status,$allowed,true)||$note===''||mb_strlen($note)>2000)throw new InvalidArgumentException('A report outcome and internal note are required.');
        $report=DB::row('select id,status from review_reports where id=?',[$reportId]);if(!$report)throw new DomainException('Report not found.');if($report['status']===$status)throw new DomainException('The report already has that status.');
        $resolved=in_array($status,['no_action','resolved'],true)?date('Y-m-d H:i:s'):null;
        DB::exec('update review_reports set status=?,admin_user_id=?,admin_note=?,reviewed_at=now(),resolved_at=? where id=?',[$status,$adminId,$note,$resolved,$reportId]);
    }

    public function moderateReply(int $adminId,int $replyId,string $action,string $reason,string $note): void
    {
        [$reason,$note]=$this->validateModeration($adminId,$action,$reason,$note,['removed','restored']);$reply=DB::row('select id,moderation_status from seller_review_replies where id=?',[$replyId]);if(!$reply)throw new DomainException('Seller response not found.');$next=$action==='restored'?'published':'removed';if($reply['moderation_status']===$next)throw new DomainException('The seller response already has that status.');
        $this->atomic(function()use($adminId,$reply,$action,$next,$reason,$note){DB::exec('insert into seller_review_reply_moderation_audits(reply_id,admin_user_id,action,previous_status,reason,internal_note) values(?,?,?,?,?,?)',[$reply['id'],$adminId,$action,$reply['moderation_status'],$reason,$note]);DB::exec('update seller_review_replies set moderation_status=?,moderated_by=?,moderated_at=now(),moderation_reason=? where id=?',[$next,$adminId,$reason,$reply['id']]);});
    }

    public function recalculate(int $designerId): void
    {
        $s=DB::row('select count(*) review_count,coalesce(avg(rating),0) average_rating,sum(rating=5) c5,sum(rating=4) c4,sum(rating=3) c3,sum(rating=2) c2,sum(rating=1) c1 from seller_reviews where designer_id=? and moderation_status="published"',[$designerId]);
        DB::exec('update designers set average_rating=?,review_count=?,rating_5_count=?,rating_4_count=?,rating_3_count=?,rating_2_count=?,rating_1_count=? where id=?',[round((float)$s['average_rating'],2),(int)$s['review_count'],(int)$s['c5'],(int)$s['c4'],(int)$s['c3'],(int)$s['c2'],(int)$s['c1'],$designerId]);
    }

    private function markEligible(int $itemId,int $buyerId,?array $item=null):bool
    {
        $item??=DB::row('select oi.id,oi.product_id from order_items oi join orders o on o.id=oi.order_id where oi.id=? and o.user_id=?',[$itemId,$buyerId]);
        if(!$item) throw new DomainException('This download is not review eligible.');
        DB::exec('update order_items set downloaded_at=coalesce(downloaded_at,now()),review_eligible_at=coalesce(review_eligible_at,now()) where id=? and review_eligible_at is null',[$itemId]);
        $changed=(int)(DB::row('select row_count() c')['c']??0)===1;
        if($changed){
            try{NotificationService::reviewAvailable($buyerId,$itemId,$item['product_id']===null?null:(int)$item['product_id']);}
            catch(Throwable $e){NotificationService::reportFailure('review availability notification',$e);}
        }
        return $changed;
    }

    private function validateContent(mixed $rating,string $text): array {if(!is_string($rating)&&!is_int($rating))throw new InvalidArgumentException('Rating must be a whole number from 1 to 5.');$raw=(string)$rating;if(!preg_match('/^[1-5]$/',$raw))throw new InvalidArgumentException('Rating must be a whole number from 1 to 5.');$text=trim($text);if(mb_strlen($text)>2000)throw new InvalidArgumentException('Review text may not exceed 2,000 characters.');return[(int)$raw,$text];}
    private function validateModeration(int $adminId,string $action,string $reason,string $note,array $actions):array{$reason=trim($reason);$note=trim($note);if($adminId<1||!in_array($action,$actions,true)||$reason===''||$note===''||mb_strlen($reason)>500||mb_strlen($note)>2000)throw new InvalidArgumentException('A valid moderation action, reason, and internal note are required.');return[$reason,$note];}
    private function atomic(callable $fn):mixed{$own=!DB::pdo()->inTransaction();if($own)DB::begin();try{$v=$fn();if($own)DB::commit();return$v;}catch(Throwable $e){if($own&&DB::pdo()->inTransaction())DB::rollBack();throw$e;}}
}
