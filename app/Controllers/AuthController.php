<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\ReferralService;
use App\Services\EmailPreferenceService;

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
        $referralCode = ReferralService::normalize((string)($_POST['ref'] ?? $_GET['ref'] ?? $_SESSION['registration_referral'] ?? ''));
        $referralValid = $referralCode !== '' && (new ReferralService)->referrer($referralCode) !== null;
        $referralInvalid = $referralCode !== '' && !$referralValid;
        if ($referralValid) {
            $_SESSION['registration_referral'] = $referralCode;
        } elseif ($referralCode !== '') {
            unset($_SESSION['registration_referral']);
            $referralCode = '';
        }
        if ($_POST) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                H::flash('error', 'Name, email, and password are required.');
                H::view('auth/register',compact('referralCode','referralValid','referralInvalid'));
                return;
            }

            if (DB::row('select id from users where email=? limit 1', [$email])) {
                H::flash('error', 'An account already exists with that email. Please log in instead.');
                H::view('auth/register',compact('referralCode','referralValid','referralInvalid'));
                return;
            }

            try {
                DB::begin();
                do {$code=ReferralService::generateCode();} while(DB::row('select id from users where referral_code=?',[$code]));
                DB::exec(
                    'insert into users (name,email,password_hash,role,referral_code) values (?,?,?,?,?)',
                    [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'buyer', $code]
                );
                $id = (int)DB::id();
                if ($referralValid) {
                    (new ReferralService)->attach($id, $referralCode, 'buyer');
                }
                DB::commit();unset($_SESSION['registration_referral']);
                $_SESSION['user'] = DB::row('select id,name,email,role,referral_code from users where id=?', [$id]);
                H::redirect($this->afterLoginRedirect());
            } catch (\Throwable $e) {
                if(DB::pdo()->inTransaction())DB::rollBack();
                H::flash('error', 'Account could not be created. If you already have an account, please log in instead.');
                H::view('auth/register',compact('referralCode','referralValid','referralInvalid'));
                return;
            }
        }

        H::view('auth/register',compact('referralCode','referralValid','referralInvalid'));
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
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /login', true, 303);
        exit;
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
        $userId=(int)H::user()['id'];
        $preferences=EmailPreferenceService::ensure($userId);
        if ($_POST) {
            H::verifyCsrf();
            $name=trim((string)($_POST['name']??''));
            if($name===''||mb_strlen($name)>120){H::flash('error','Enter a name between 1 and 120 characters.');}
            else{
                DB::begin();
                try{DB::exec('update users set name=?, updated_at=now() where id=?',[$name,$userId]);EmailPreferenceService::save($userId,['weekly'=>isset($_POST['weekly_emails']),'monthly'=>isset($_POST['monthly_emails']),'favorite_shop'=>isset($_POST['favorite_shop_emails'])]);DB::commit();$_SESSION['user']['name']=$name;H::flash('success','Account and email preferences saved.');H::redirect('/account#email-preferences');}
                catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();H::flash('error','Account settings could not be saved. Please try again.');}
            }
            $preferences=EmailPreferenceService::ensure($userId);
        }

        H::view('auth/account',compact('preferences'));
    }
}
