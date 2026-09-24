<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\SellerReceiptService;
use App\Services\SellerReviewService;
class BuyerController
{
    private function protectedFileAvailable(?string $storagePath): bool
    {
        if (!$storagePath) return false;
        $base=realpath(app_path('storage/protected_uploads/products'));
        $real=realpath(app_path('storage/protected_uploads/'.ltrim($storagePath,'/')));
        return (bool)($base && $real && ($real===$base || str_starts_with($real,$base.DIRECTORY_SEPARATOR)) && is_file($real) && is_readable($real));
    }

    public function home()
    {
        H::requireLogin();
        $uid=(int)H::user()['id'];
        $summary=DB::row('select (select count(*) from orders where user_id=? and payment_status in ("paid","partially_refunded","refunded")) purchase_count,(select count(*) from wishlists where user_id=?) wishlist_count,(select count(*) from notifications where user_id=? and read_at is null) unread_count',[$uid,$uid,$uid]);
        $eligibleFiles=DB::rows('select pf.storage_path from order_items oi join orders o on o.id=oi.order_id join product_files pf on pf.product_id=oi.product_id where o.user_id=? and o.payment_status="paid" and oi.fulfillment_type="downloadable" and (oi.download_expires_at is null or oi.download_expires_at>=now())',[$uid]);
        $customFiles=DB::rows('select f.storage_path from custom_order_files f join custom_orders co on co.id=f.custom_order_id join orders o on o.id=co.order_id where co.buyer_user_id=? and co.status="completed" and f.file_kind="final" and o.payment_status in ("paid","partially_refunded")',[$uid]);
        $customBase=realpath(app_path('storage/protected_uploads/custom_designs'));$customAvailable=array_filter($customFiles,static function(array $file)use($customBase):bool{$real=realpath(app_path('storage/protected_uploads/'.ltrim($file['storage_path']??'','/')));return(bool)($customBase&&$real&&str_starts_with($real,$customBase.DIRECTORY_SEPARATOR)&&is_file($real)&&is_readable($real));});
        $collabAvailable=(int)(DB::row('select count(*) c from order_items oi join orders o on o.id=oi.order_id join collab_events ce on ce.id=oi.collab_id where o.user_id=? and o.payment_status in ("paid","partially_refunded") and ce.final_zip_path is not null and coalesce((select sum(a.merchandise_refund_cents) from marketplace_refund_allocations a where a.order_item_id=oi.id),0)<round(oi.total_price*100)',[$uid])['c']??0);
        $summary['available_downloads']=count(array_filter($eligibleFiles,fn(array $file):bool=>$this->protectedFileAvailable($file['storage_path']??null)))+count($customAvailable)+$collabAvailable;
        H::view('buyer/home',['summary'=>$summary,'orders'=>DB::rows('select * from orders where user_id=? and payment_status in ("paid","partially_refunded","refunded") order by created_at desc limit 5',[$uid]),'wishlist'=>DB::rows('select p.title,p.slug,(select image_path from product_images where product_id=p.id order by sort_order,id limit 1) preview_image from wishlists w join products p on p.id=w.product_id where w.user_id=? order by w.created_at desc limit 4',[$uid]),'notifications'=>DB::rows('select * from notifications where user_id=? order by created_at desc limit 5',[$uid])]);

    }
    public function purchases()
    {
        H::requireLogin();
        H::view('buyer/purchases',['orders'=>DB::rows('select o.*, group_concat(concat(coalesce(oi.product_title,p.title,"Purchased item")," (",oi.license_name,")") separator ", ") product_titles from orders o join order_items oi on oi.order_id=o.id left join products p on p.id=oi.product_id where o.user_id=? and o.payment_status in ("paid","partially_refunded","refunded") group by o.id order by o.created_at desc',[H::user()['id']])]);

    }
    public function order($id)
    {
        H::requireLogin();
        $order=DB::row('select * from orders where id=? and user_id=?',[(int)$id,H::user()['id']])??H::abort(404);
        $items=DB::rows('select oi.*,(select id from seller_reviews sr where sr.order_item_id=oi.id) review_id,coalesce(oi.product_title,p.title,"Purchased item") title,coalesce(oi.product_slug,p.slug) slug,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image,(select id from product_files pf where pf.product_id=p.id order by id limit 1) file_id,(select storage_path from product_files pf where pf.product_id=p.id order by id limit 1) file_storage_path from order_items oi left join products p on p.id=oi.product_id left join designers d on d.id=oi.designer_id where oi.order_id=?',[$order['id']]);
        foreach($items as &$item) $item['file_available']=$this->protectedFileAvailable($item['file_storage_path']??null);
        unset($item);
        $collabItems=DB::rows('select oi.*,c.title,c.slug,c.final_zip_path,c.final_zip_sha256,coalesce((select sum(a.merchandise_refund_cents) from marketplace_refund_allocations a where a.order_item_id=oi.id),0) refunded_cents from order_items oi join collab_events c on c.id=oi.collab_id where oi.order_id=?',[$order['id']]);
        foreach($collabItems as &$collabItem)$collabItem['download_eligible']=\App\Services\CollabService::itemDownloadable((string)$order['payment_status'],\App\Services\CreditService::parseCents((string)$collabItem['total_price'],false),(int)$collabItem['refunded_cents']);unset($collabItem);
        $customOrder=DB::row('select * from custom_orders where order_id=? and buyer_user_id=?',[$order['id'],H::user()['id']]);
        $customFinals=$customOrder&&$customOrder['status']==='completed'&&in_array($order['payment_status'],['paid','partially_refunded'],true)?DB::rows('select * from custom_order_files where custom_order_id=? and file_kind="final" order by id',[$customOrder['id']]):[];
        H::view('buyer/order',['order'=>$order,'items'=>$items,'collabItems'=>$collabItems,'sellerGroups'=>SellerReceiptService::groupItemsBySeller(array_values(array_filter($items,fn($item)=>empty($item['collab_id'])))),'customOrder'=>$customOrder,'customFinals'=>$customFinals]);

    }
    public function downloads()
    {
        H::requireLogin();
        $uid=(int)H::user()['id'];
        $items=DB::rows('select oi.*,(select id from seller_reviews sr where sr.order_item_id=oi.id) review_id,o.payment_status,o.status order_status,o.created_at purchase_date,coalesce(oi.product_title,p.title) title,coalesce(oi.product_slug,p.slug) slug,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image,pf.id file_id,pf.original_name file_name,pf.storage_path from order_items oi join orders o on o.id=oi.order_id join products p on p.id=oi.product_id left join product_files pf on pf.product_id=oi.product_id left join designers d on d.id=oi.designer_id where o.user_id=? order by o.created_at desc,oi.id desc,pf.id',[$uid]);
        foreach($items as &$item) $item['file_available']=$this->protectedFileAvailable($item['storage_path']??null);
        unset($item);
        $customFinals=DB::rows('select f.*,co.id custom_order_id,co.order_item_id,co.service_snapshot,o.created_at purchase_date,d.display_name seller_name,oi.review_eligible_at,(select id from seller_reviews sr where sr.order_item_id=co.order_item_id) review_id from custom_order_files f join custom_orders co on co.id=f.custom_order_id join orders o on o.id=co.order_id join order_items oi on oi.id=co.order_item_id join designers d on d.id=co.designer_id where co.buyer_user_id=? and co.status="completed" and f.file_kind="final" and o.payment_status in ("paid","partially_refunded") order by f.created_at desc',[$uid]);
        $collabs=DB::rows('select oi.id order_item_id,c.id collab_id,c.title,c.slug,o.id order_id,o.created_at purchase_date,o.payment_status,oi.download_count,oi.total_price,coalesce((select sum(a.merchandise_refund_cents) from marketplace_refund_allocations a where a.order_item_id=oi.id),0) refunded_cents from order_items oi join collab_events c on c.id=oi.collab_id join orders o on o.id=oi.order_id where o.user_id=? and o.payment_status in ("paid","partially_refunded","refunded") order by o.created_at desc',[$uid]);
        foreach($collabs as &$collab)$collab['download_eligible']=\App\Services\CollabService::itemDownloadable((string)$collab['payment_status'],\App\Services\CreditService::parseCents((string)$collab['total_price'],false),(int)$collab['refunded_cents']);unset($collab);
        H::view('buyer/downloads',['items'=>$items,'customFinals'=>$customFinals,'collabs'=>$collabs]);

    }
    public function download($file)
    {
        H::requireLogin();
        $f=DB::row('select pf.*,oi.id order_item_id,oi.order_id,oi.fulfillment_type,oi.download_expires_at,o.status order_status,o.payment_status from product_files pf join order_items oi on oi.product_id=pf.product_id join orders o on o.id=oi.order_id where pf.id=? and o.user_id=? and oi.fulfillment_type="downloadable" and o.payment_status="paid" and (oi.download_expires_at is null or oi.download_expires_at>=now()) order by oi.id desc limit 1',[$file,H::user()['id']]);
        if(!$f) {
            $denied=DB::row('select pf.*,oi.id order_item_id,oi.order_id,oi.fulfillment_type,oi.download_expires_at,o.status order_status,o.payment_status from product_files pf join order_items oi on oi.product_id=pf.product_id join orders o on o.id=oi.order_id where pf.id=? and o.user_id=? order by oi.id desc limit 1',[$file,H::user()['id']]);
            if($denied) DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,message,ip_address,user_agent) values (?,?,?,?,?,?,?,?,?)',[H::user()['id'],$denied['order_id'],$denied['order_item_id'],$denied['product_id'],$file,'denied','Order is not paid by Stripe webhook confirmation or access expired.',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
            H::abort(403);
        }
        $base=realpath(app_path('storage/protected_uploads/products'));
        $path=app_path('storage/protected_uploads/'.ltrim($f['storage_path'],'/'));
        $real=realpath($path);
        $insideProtectedProducts = $base && $real && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR));
        if(!$insideProtectedProducts || !is_file($real) || !is_readable($real)) {
            DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,message,ip_address,user_agent) values (?,?,?,?,?,?,?,?,?)',[H::user()['id'],$f['order_id'],$f['order_item_id'],$f['product_id'],$file,'denied','Protected file is unavailable.',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
            H::abort(404);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($f['original_name']).'"');
        $delivered=readfile($real);
        if($delivered===false) {
            try { DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,message,ip_address,user_agent) values (?,?,?,?,?,?,?,?,?)',[H::user()['id'],$f['order_id'],$f['order_item_id'],$f['product_id'],$f['id'],'denied','Protected file delivery failed.',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); } catch (\Throwable $e) { error_log('Download failure logging failed.'); }
            exit;
        }
        DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,ip_address,user_agent) values (?,?,?,?,?,?,?,?)',[H::user()['id'],$f['order_id'],$f['order_item_id'],$f['product_id'],$f['id'],'served',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
        DB::exec('update order_items set download_count=download_count+1 where id=?',[$f['order_item_id']]);
        try { (new SellerReviewService())->markDownloaded((int)$f['order_item_id'],(int)H::user()['id']); } catch (\Throwable $e) { \App\Services\NotificationService::reportFailure('post-download review eligibility',$e); }
        exit;

    }
    public function wishlist()
    {
        H::requireLogin();
        H::view('buyer/wishlist',['products'=>DB::rows('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from wishlists w join products p on p.id=w.product_id left join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where w.user_id=? order by w.created_at desc',[H::user()['id']])]);

    }
    public function following()
    {
        H::requireLogin();
        H::view('buyer/following',['designers'=>DB::rows('select d.* from follows f join designers d on d.id=f.designer_id where f.user_id=?',[H::user()['id']])]);

    }
    public function referrals()
    {
        H::requireLogin();
        $credit=new \App\Services\CreditService;$ref=new \App\Services\ReferralService;$id=(int)H::user()['id'];
        H::view('buyer/referrals',['balances'=>$credit->balances($id),'tx'=>$credit->ledger($id),'referrals'=>$ref->dashboard($id)]);

    }
    public function toggleWishlist($id)
    {
        H::requireLogin();
        $x=DB::row('select id from wishlists where user_id=? and product_id=?',[H::user()['id'],$id]);
        $x?DB::exec('delete from wishlists where id=?',[$x['id']]):DB::exec('insert into wishlists (user_id,product_id) values (?,?)',[H::user()['id'],$id]);
        H::redirect($_SERVER['HTTP_REFERER']??'/browse');

    }
    public function toggleFollow($id)
    {
        H::requireLogin();
        $d=DB::row('select * from designers where id=? and status="approved"',[$id]);
        if(!$d)
        {
           H::flash('error','This designer is not available to follow.');
            H::redirect($_SERVER['HTTP_REFERER']??'/browse');

        }
        if((int)$d['user_id']===(int)H::user()['id'])
        {
           H::flash('warning','This is your store.');
            H::redirect('/store/'.$d['store_slug']);

        }
        $x=DB::row('select id from follows where user_id=? and designer_id=?',[H::user()['id'],$id]);
        if($x)
        {
           DB::exec('delete from follows where id=?',[$x['id']]);
            H::flash('success','Designer unfollowed.');

        }
        else
        {
           DB::exec('insert ignore into follows (user_id,designer_id) values (?,?)',[H::user()['id'],$id]);
            H::flash('success','Designer followed.');

        }
        $count=DB::row('select count(*) c from follows where designer_id=?',[$id])['c']??0;
        DB::exec('update designers set follower_count=? where id=?',[$count,$id]);
        H::redirect('/store/'.$d['store_slug']);

    }

}
