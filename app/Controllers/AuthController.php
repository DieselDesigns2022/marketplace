<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
class AuthController
{
    private function afterLoginRedirect(): string
    {
        $to = $_SESSION['after_login_redirect'] ?? (!empty($_SESSION['seller_intent']) ? '/apply' : '/dashboard');
        unset($_SESSION['after_login_redirect'], $_SESSION['seller_intent']);
        return is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/dashboard';

    }

    public function register()
    {
        if($_POST)
        {
           DB::exec('insert into users (name,email,password_hash,role,referral_code) values (?,?,?,?,?)',[$_POST['name'],$_POST['email'],password_hash($_POST['password'],PASSWORD_DEFAULT),'buyer',bin2hex(random_bytes(4))]);
            $_SESSION['user']=DB::row('select id,name,email,role from users where id=?',[DB::id()]);
            H::redirect($this->afterLoginRedirect());

        }
        H::view('auth/register');

    }
    public function login()
    {
        $error=null;
        if($_POST)
        {
           $u=DB::row('select * from users where email=? and status="active"',[$_POST['email']]);
            if($u&&password_verify($_POST['password'],$u['password_hash']))
           {
               $_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
                H::redirect($this->afterLoginRedirect());

           }
            $error='Invalid credentials';

        }
        H::view('auth/login',compact('error'));

    }
    public function logout()
    {
        session_destroy();
        H::redirect('/login');

    }
    public function logoutRedirect()
    {
        H::redirect('/login');

    }
    public function forgot()
    {
        H::view('auth/forgot');

    }
    public function account()
    {
        H::requireLogin();
        if($_POST)
        {
           DB::exec('update users set name=?, updated_at=now() where id=?',[$_POST['name'],H::user()['id']]);
            $_SESSION['user']['name']=$_POST['name'];

        }
        H::view('auth/account');

    }

}
