<?php
namespace App\Controllers;
use App\Core\Database as DB; use App\Core\Helpers as H;
class AuthController {
 public function register(){ if($_POST){DB::exec('insert into users (name,email,password_hash,role,referral_code) values (?,?,?,?,?)',[$_POST['name'],$_POST['email'],password_hash($_POST['password'],PASSWORD_DEFAULT),'buyer',bin2hex(random_bytes(4))]); $_SESSION['user']=DB::row('select id,name,email,role from users where id=?',[DB::id()]); H::redirect('/dashboard');} H::view('auth/register'); }
 public function login(){ if($_POST){$u=DB::row('select * from users where email=? and status="active"',[$_POST['email']]); if($u&&password_verify($_POST['password'],$u['password_hash'])){$_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]; H::redirect('/dashboard');} $error='Invalid credentials';} H::view('auth/login',compact('error')); }
 public function logout(){ session_destroy(); H::redirect('/'); }
 public function forgot(){ H::view('auth/forgot'); }
 public function account(){ H::requireLogin(); if($_POST){DB::exec('update users set name=?, updated_at=now() where id=?',[$_POST['name'],H::user()['id']]); $_SESSION['user']['name']=$_POST['name'];} H::view('auth/account'); }
}
