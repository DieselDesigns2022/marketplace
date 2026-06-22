<?php
namespace App\Controllers;
use App\Core\Database as DB; use App\Core\Helpers as H;
class BuyerController {
 public function home(){H::requireLogin(); H::view('buyer/home',['orders'=>DB::rows('select * from orders where user_id=? order by created_at desc limit 5',[H::user()['id']])]);}
 public function purchases(){H::requireLogin(); H::view('buyer/purchases',['items'=>DB::rows('select oi.*,p.title,p.slug,o.created_at,(select id from product_files pf where pf.product_id=p.id order by id limit 1) file_id from order_items oi join orders o on o.id=oi.order_id join products p on p.id=oi.product_id where o.user_id=?',[H::user()['id']])]);}
 public function downloads(){ $this->purchases(); }
 public function download($file){H::requireLogin(); $f=DB::row('select pf.* from product_files pf join order_items oi on oi.product_id=pf.product_id join orders o on o.id=oi.order_id where pf.id=? and o.user_id=?',[$file,H::user()['id']])??H::abort(403); DB::exec('insert into downloads (user_id,product_id,product_file_id,ip_address,user_agent) values (?,?,?,?,?)',[H::user()['id'],$f['product_id'],$f['id'],$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); $path=app_path('storage/protected_uploads/'.$f['storage_path']); if(!is_file($path)) H::abort(404); header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($f['original_name']).'"'); readfile($path); exit;}
 public function wishlist(){H::requireLogin(); H::view('buyer/wishlist',['products'=>DB::rows('select p.* from wishlists w join products p on p.id=w.product_id where w.user_id=?',[H::user()['id']])]);}
 public function following(){H::requireLogin(); H::view('buyer/following',['designers'=>DB::rows('select d.* from follows f join designers d on d.id=f.designer_id where f.user_id=?',[H::user()['id']])]);}
 public function referrals(){H::requireLogin(); H::view('buyer/referrals',['credits'=>DB::row('select * from marketplace_credits where user_id=?',[H::user()['id']]),'tx'=>DB::rows('select * from credit_transactions where user_id=?',[H::user()['id']])]);}
 public function toggleWishlist($id){H::requireLogin(); $x=DB::row('select id from wishlists where user_id=? and product_id=?',[H::user()['id'],$id]); $x?DB::exec('delete from wishlists where id=?',[$x['id']]):DB::exec('insert into wishlists (user_id,product_id) values (?,?)',[H::user()['id'],$id]); H::redirect($_SERVER['HTTP_REFERER']??'/browse');}
 public function toggleFollow($id){H::requireLogin(); $x=DB::row('select id from follows where user_id=? and designer_id=?',[H::user()['id'],$id]); $x?DB::exec('delete from follows where id=?',[$x['id']]):DB::exec('insert into follows (user_id,designer_id) values (?,?)',[H::user()['id'],$id]); H::redirect($_SERVER['HTTP_REFERER']??'/browse');}
}
