<?php
namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\NotificationService;
use App\Services\SellerReviewService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class ReviewController
{
    private SellerReviewService $service;
    public function __construct(){ $this->service=new SellerReviewService(); }

    private function safeMessage(Throwable $e,string $context):string
    {
        if($e instanceof DomainException||$e instanceof InvalidArgumentException)return $e->getMessage();
        NotificationService::reportFailure($context,$e);
        return 'The request could not be completed. Please try again.';
    }

    public function buyerList():void
    {
        H::requireLogin();$id=(int)H::user()['id'];
        $reviews=DB::rows('select sr.*,rr.reply_text,rr.created_at reply_created_at,rr.updated_at reply_updated_at,rr.moderation_status reply_moderation_status from seller_reviews sr left join seller_review_replies rr on rr.review_id=sr.id where sr.buyer_id=? order by sr.reviewed_at desc',[$id]);
        H::view('buyer/reviews',['reviews'=>$reviews]);
    }

    public function buyerForm($id):void
    {
        H::requireLogin();$uid=(int)H::user()['id'];$isNew=str_contains(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/reviews/new/');
        $review=$isNew?null:DB::row('select * from seller_reviews where id=? and buyer_id=?',[(int)$id,$uid]);if(!$isNew&&!$review)H::abort(404);
        $sql='select oi.*,o.created_at purchase_date,coalesce(oi.product_title,p.title,JSON_UNQUOTE(JSON_EXTRACT(co.service_snapshot,"$.title")),"Purchased item") product_title,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,d.store_slug,coalesce((select image_path from product_images pi where pi.product_id=oi.product_id order by pi.sort_order,pi.id limit 1),(select image_path from custom_service_images csi where csi.custom_service_id=oi.custom_service_id order by csi.sort_order,csi.id limit 1)) preview_image from order_items oi join orders o on o.id=oi.order_id left join products p on p.id=oi.product_id left join custom_orders co on co.order_item_id=oi.id left join designers d on d.id=oi.designer_id where oi.id=? and o.user_id=?';
        $item=$review?DB::row($sql,[$review['order_item_id'],$uid]):DB::row($sql.' and ((oi.custom_service_id is null and o.payment_status="paid") or (oi.custom_service_id is not null and o.payment_status in ("paid","partially_refunded"))) and oi.review_eligible_at is not null',[(int)$id,$uid]);if(!$item)H::abort(404);
        if($review){$item['product_title']=$review['product_title_snapshot'];$item['seller_name']=$review['seller_name_snapshot'];}
        if($_SERVER['REQUEST_METHOD']==='POST'){H::verifyCsrf();try{if($review)$this->service->edit($uid,(int)$review['id'],$_POST['rating']??'',$_POST['review_text']??'');else$id=$this->service->create($uid,(int)$item['id'],$_POST['rating']??'',$_POST['review_text']??'');H::flash('success',$review?'Review updated.':'Review published.');H::redirect('/dashboard/reviews/'.$id);}catch(Throwable $e){$error=$this->safeMessage($e,'buyer review save');}}
        H::view('buyer/review_form',['review'=>$review,'item'=>$item,'error'=>$error??null]);
    }

    public function sellerList():void
    {
        H::requireSeller();$d=DB::row('select * from designers where user_id=?',[H::user()['id']]);$filter=$_GET['filter']??'all';$sort=$_GET['sort']??'newest';$where=['sr.designer_id=?'];$args=[$d['id']];
        if(in_array($filter,['1','2','3','4','5'],true)){$where[]='sr.rating=?';$args[]=(int)$filter;}elseif($filter==='replied')$where[]='rr.id is not null';elseif($filter==='needs_reply')$where[]='rr.id is null';
        $order=['oldest'=>'sr.reviewed_at asc','highest'=>'sr.rating desc,sr.reviewed_at desc','lowest'=>'sr.rating asc,sr.reviewed_at desc'][$sort]??'sr.reviewed_at desc';
        $reviews=DB::rows('select sr.*,rr.id reply_id,rr.reply_text,rr.created_at reply_created_at,rr.updated_at reply_updated_at,rr.moderation_status reply_moderation_status,exists(select 1 from review_reports rp where rp.review_id=sr.id and rp.designer_id=sr.designer_id) reported from seller_reviews sr left join seller_review_replies rr on rr.review_id=sr.id where '.implode(' and ',$where).' order by '.$order,$args);
        H::view('seller/reviews',['d'=>$d,'reviews'=>$reviews,'filter'=>$filter,'sort'=>$sort]);
    }

    public function reply($id):never{H::requireSeller();H::verifyCsrf();try{$this->service->reply((int)H::user()['id'],(int)$id,$_POST['reply_text']??'');H::flash('success','Public response saved.');}catch(Throwable $e){H::flash('error',$this->safeMessage($e,'seller review reply'));}H::redirect('/seller/reviews#review-'.(int)$id);}
    public function report($id):never{H::requireSeller();H::verifyCsrf();try{$this->service->report((int)H::user()['id'],(int)$id,$_POST['reason']??'',$_POST['details']??'');H::flash('success','Report submitted. The review remains published while Admin reviews it.');}catch(Throwable $e){H::flash('error',$this->safeMessage($e,'seller review report'));}H::redirect('/seller/reviews#review-'.(int)$id);}

    public function admin():void
    {
        $posting=$_SERVER['REQUEST_METHOD']==='POST';H::requireAdminPermission($posting?'reviews.manage':'reviews.view');
        if($posting){H::verifyCsrf();try{$operation=$_POST['operation']??'review';if($operation==='report')$this->service->resolveReport((int)H::user()['id'],(int)($_POST['report_id']??0),$_POST['report_status']??'',$_POST['internal_note']??'');elseif($operation==='reply')$this->service->moderateReply((int)H::user()['id'],(int)($_POST['reply_id']??0),$_POST['action']??'',$_POST['reason']??'',$_POST['internal_note']??'');else $this->service->moderate((int)H::user()['id'],(int)($_POST['review_id']??0),$_POST['action']??'',$_POST['reason']??'',$_POST['internal_note']??'');H::flash('success','Review moderation saved.');}catch(Throwable $e){H::flash('error',$this->safeMessage($e,'Admin review moderation'));}H::redirect('/admin/reviews');}
        $filters=['q'=>trim($_GET['q']??''),'seller'=>trim($_GET['seller']??''),'buyer'=>trim($_GET['buyer']??''),'product'=>trim($_GET['product']??''),'rating'=>$_GET['rating']??'','date_from'=>$_GET['date_from']??'','date_to'=>$_GET['date_to']??'','reported'=>$_GET['reported']??'','status'=>$_GET['status']??''];$where=['1=1'];$args=[];
        if($filters['q']!==''){$where[]='(sr.id=? or sr.order_id=? or sr.order_item_id=?)';array_push($args,(int)$filters['q'],(int)$filters['q'],(int)$filters['q']);}
        foreach(['seller'=>'sr.seller_name_snapshot','buyer'=>'sr.buyer_name_snapshot','product'=>'sr.product_title_snapshot'] as $key=>$column)if($filters[$key]!==''){$where[]="$column like ?";$args[]='%'.$filters[$key].'%';}
        if(in_array($filters['rating'],['1','2','3','4','5'],true)){$where[]='sr.rating=?';$args[]=(int)$filters['rating'];}
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters['date_from'])){$where[]='sr.reviewed_at>=?';$args[]=$filters['date_from'].' 00:00:00';}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters['date_to'])){$where[]='sr.reviewed_at<=?';$args[]=$filters['date_to'].' 23:59:59';}
        if($filters['reported']==='yes')$where[]='exists(select 1 from review_reports rx where rx.review_id=sr.id)';elseif($filters['reported']==='no')$where[]='not exists(select 1 from review_reports rx where rx.review_id=sr.id)';
        if(in_array($filters['status'],['published','under_review','removed'],true)){$where[]='sr.moderation_status=?';$args[]=$filters['status'];}
        $reviews=DB::rows('select sr.*,rr.id reply_id,rr.reply_text,rr.created_at reply_created_at,rr.updated_at reply_updated_at,rr.moderation_status reply_moderation_status from seller_reviews sr left join seller_review_replies rr on rr.review_id=sr.id where '.implode(' and ',$where).' order by sr.reviewed_at desc',$args);$reports=[];foreach($reviews as $review)$reports[(int)$review['id']]=DB::rows('select * from review_reports where review_id=? order by created_at desc',[$review['id']]);
        H::view('admin/reviews',['reviews'=>$reviews,'reports'=>$reports,'filters'=>$filters]);
    }
}
