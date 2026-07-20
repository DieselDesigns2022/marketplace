<?php
namespace App\Controllers;
use App\Core\Database as DB; use App\Core\Helpers as H;
final class NotificationController {
 public function index():void { H::requireLogin();$page=max(1,(int)($_GET['page']??1));$size=20;$uid=(int)H::user()['id'];$total=(int)(DB::row('select count(*) c from notifications where user_id=?',[$uid])['c']??0);$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$rows=DB::rows('select * from notifications where user_id=? order by created_at desc,id desc limit '.$size.' offset '.(($page-1)*$size),[$uid]);H::view('notifications/index',['notifications'=>$rows,'page'=>$page,'pages'=>$pages]); }
 public function readAll():void { H::requireLogin();DB::exec('update notifications set read_at=coalesce(read_at,now()) where user_id=?',[(int)H::user()['id']]);H::redirect('/notifications'); }
 public function read($id):void { H::requireLogin();if(!ctype_digit((string)$id)||(int)$id<1)H::abort(404);DB::exec('update notifications set read_at=coalesce(read_at,now()) where id=? and user_id=?',[(int)$id,(int)H::user()['id']]);H::redirect('/notifications'); }
}
