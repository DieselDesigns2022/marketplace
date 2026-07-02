<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
class BuyerController
{
    public function home()
    {
        H::requireLogin();
        H::view('buyer/home',['orders'=>DB::rows('select * from orders where user_id=? order by created_at desc limit 5',[H::user()['id']])]);

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
        $items=DB::rows('select oi.*,coalesce(oi.product_title,p.title) title,coalesce(oi.product_slug,p.slug) slug,(select id from product_files pf where pf.product_id=p.id order by id limit 1) file_id from order_items oi join products p on p.id=oi.product_id where oi.order_id=?',[$order['id']]);
        H::view('buyer/order',['order'=>$order,'items'=>$items]);

    }
    public function downloads()
    {
        $this->purchases();

    }
    public function download($file)
    {
        H::requireLogin();
        $f=DB::row('select pf.*,oi.id order_item_id,oi.order_id,oi.fulfillment_type,oi.download_expires_at,o.status order_status from product_files pf join order_items oi on oi.product_id=pf.product_id join orders o on o.id=oi.order_id where pf.id=? and o.user_id=? order by oi.id desc limit 1',[$file,H::user()['id']]);
        if(!$f || $f['fulfillment_type'] !== 'downloadable' || !in_array($f['order_status'], ['paid','fulfilled','completed'], true) || (!empty($f['download_expires_at']) && strtotime($f['download_expires_at']) < time())) {
            if($f) DB::exec('insert into downloads (user_id,order_id,order_item_id,product_id,product_file_id,status,message,ip_address,user_agent) values (?,?,?,?,?,?,?,?,?)',[H::user()['id'],$f['order_id'],$f['order_item_id'],$f['product_id'],$file,'denied','Order is not paid/fulfilled or access expired.',$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
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
        H::view('buyer/wishlist',['products'=>DB::rows('select p.* from wishlists w join products p on p.id=w.product_id where w.user_id=?',[H::user()['id']])]);

    }
    public function following()
    {
        H::requireLogin();
        H::view('buyer/following',['designers'=>DB::rows('select d.* from follows f join designers d on d.id=f.designer_id where f.user_id=?',[H::user()['id']])]);

    }
    public function referrals()
    {
        H::requireLogin();
        H::view('buyer/referrals',['credits'=>DB::row('select * from marketplace_credits where user_id=?',[H::user()['id']]),'tx'=>DB::rows('select * from credit_transactions where user_id=?',[H::user()['id']])]);

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
