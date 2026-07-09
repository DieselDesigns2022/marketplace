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
        if ($_POST) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                H::flash('error', 'Name, email, and password are required.');
                H::view('auth/register');
                return;
            }

            if (DB::row('select id from users where email=? limit 1', [$email])) {
                H::flash('error', 'An account already exists with that email. Please log in instead.');
                H::view('auth/register');
                return;
            }

            try {
                DB::exec(
                    'insert into users (name,email,password_hash,role,referral_code) values (?,?,?,?,?)',
                    [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'buyer', bin2hex(random_bytes(4))]
                );
                $_SESSION['user'] = DB::row('select id,name,email,role from users where id=?', [DB::id()]);
                H::redirect($this->afterLoginRedirect());
            } catch (\Throwable $e) {
                H::flash('error', 'Account could not be created. If you already have an account, please log in instead.');
                H::view('auth/register');
                return;
            }
        }

        H::view('auth/register');
    }

    public function login()
    {
        $error = null;
        if ($_POST) {
            $u = DB::row('select * from users where email=? and status="active"', [$_POST['email']]);
            if ($u && password_verify($_POST['password'], $u['password_hash'])) {
                $_SESSION['user'] = ['id'=>$u['id'], 'name'=>$u['name'], 'email'=>$u['email'], 'role'=>$u['role']];
                H::redirect($this->afterLoginRedirect());
            }
            $error = 'Invalid credentials';
        }

        H::view('auth/login', compact('error'));
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
        if ($_POST) {
            DB::exec('update users set name=?, updated_at=now() where id=?', [$_POST['name'], H::user()['id']]);
            $_SESSION['user']['name'] = $_POST['name'];
        }

        H::view('auth/account');
    }
}
