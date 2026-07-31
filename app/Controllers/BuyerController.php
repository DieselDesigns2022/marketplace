<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\SellerReceiptService;
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
        $summary=DB::row('select (select count(*) from orders where user_id=?) purchase_count,(select count(*) from wishlists where user_id=?) wishlist_count,(select count(*) from notifications where user_id=? and read_at is null) unread_count',[$uid,$uid,$uid]);
        $eligibleFiles=DB::rows('select pf.storage_path from order_items oi join orders o on o.id=oi.order_id join product_files pf on pf.product_id=oi.product_id where o.user_id=? and o.payment_status="paid" and oi.fulfillment_type="downloadable" and (oi.download_expires_at is null or oi.download_expires_at>=now())',[$uid]);
        $summary['available_downloads']=count(array_filter($eligibleFiles,fn(array $file):bool=>$this->protectedFileAvailable($file['storage_path']??null)));
        H::view('buyer/home',['summary'=>$summary,'orders'=>DB::rows('select * from orders where user_id=? order by created_at desc limit 5',[$uid]),'wishlist'=>DB::rows('select p.title,p.slug,(select image_path from product_images where product_id=p.id order by sort_order,id limit 1) preview_image from wishlists w join products p on p.id=w.product_id where w.user_id=? order by w.created_at desc limit 4',[$uid]),'notifications'=>DB::rows('select * from notifications where user_id=? order by created_at desc limit 5',[$uid])]);

    }
    public function purchases()
    {
        H::requireLogin();
        H::view('buyer/purchases',['orders'=>DB::rows('select o.*, group_concat(concat(coalesce(oi.product_title,p.title)," (",oi.license_name,")") separator ", ") product_titles from orders o join order_items oi on oi.order_id=o.id join products p on p.id=oi.product_id where o.user_id=? group by o.id order by o.created_at desc',[H::user()['id']])]);

    }
    public function order($id)
    {
        H::requireLogin();
        $order=DB::row('select * from orders where id=? and user_id=?',[(int)$id,H::user()['id']])??H::abort(404);
        $items=DB::rows('select oi.*,coalesce(oi.product_title,p.title) title,coalesce(oi.product_slug,p.slug) slug,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image,(select id from product_files pf where pf.product_id=p.id order by id limit 1) file_id,(select storage_path from product_files pf where pf.product_id=p.id order by id limit 1) file_storage_path from order_items oi join products p on p.id=oi.product_id left join designers d on d.id=oi.designer_id where oi.order_id=?',[$order['id']]);
        foreach($items as &$item) $item['file_available']=$this->protectedFileAvailable($item['file_storage_path']??null);
        unset($item);
        H::view('buyer/order',['order'=>$order,'items'=>$items,'sellerGroups'=>SellerReceiptService::groupItemsBySeller($items)]);

    }
    public function downloads()
    {
        H::requireLogin();
        $uid=(int)H::user()['id'];
        $items=DB::rows('select oi.*,o.payment_status,o.status order_status,o.created_at purchase_date,coalesce(oi.product_title,p.title) title,coalesce(oi.product_slug,p.slug) slug,coalesce(oi.seller_name,d.display_name,"Seller") seller_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image,pf.id file_id,pf.original_name file_name,pf.storage_path from order_items oi join orders o on o.id=oi.order_id join products p on p.id=oi.product_id left join product_files pf on pf.product_id=oi.product_id left join designers d on d.id=oi.designer_id where o.user_id=? order by o.created_at desc,oi.id desc,pf.id',[$uid]);
        foreach($items as &$item) $item['file_available']=$this->protectedFileAvailable($item['storage_path']??null);
        unset($item);
        H::view('buyer/downloads',['items'=>$items]);

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
        DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,ip_address,user_agent) values (?,?,?,?,?,?,?,?)',[H::user()['id'],$f['order_id'],$f['order_item_id'],$f['product_id'],$f['id'],'served',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
        DB::exec('update order_items set download_count=download_count+1 where id=?',[$f['order_item_id']]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($f['original_name']).'"');
        readfile($real);
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
