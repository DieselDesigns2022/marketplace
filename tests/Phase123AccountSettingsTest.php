<?php
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\AccountSecurityService;

$passed=0;$failed=[];
$test=function(string $name,callable $fn)use(&$passed,&$failed){try{$fn();$passed++;}catch(Throwable $e){$failed[]="$name: {$e->getMessage()}";}};
$yes=function($value,string $message='assertion failed'){if(!$value)throw new RuntimeException($message);};
$controller=file_get_contents(app_path('app/Controllers/AuthController.php'));
$view=file_get_contents(app_path('app/Views/auth/account.php'));

$test('email normalization and validation',function()use($yes){$yes(AccountSecurityService::normalizeEmail('  Buyer@Example.COM ')==='buyer@example.com');$yes(AccountSecurityService::validEmail('buyer@example.com'));$yes(!AccountSecurityService::validEmail('not-an-email'));});
$test('account email update and login use the same email normalization',function()use($yes,$controller){$yes(substr_count($controller,'AccountSecurityService::normalizeEmail')===2);});
$test('successful name update is scoped to authenticated id',function()use($yes,$controller){$yes(str_contains($controller,"update users set name=?, updated_at=now() where id=?"));$yes(str_contains($controller,"\$_SESSION['user']['name']=\$name"));});
$test('email update verifies password and refreshes session',function()use($yes,$controller){$yes(str_contains($controller,"password_verify(\$password,(string)\$account['password_hash'])"));$yes(str_contains($controller,"\$_SESSION['user']['email']=\$email"));});
$test('duplicate and invalid email are blocked',function()use($yes,$controller){$yes(str_contains($controller,'email=? and id<>?'));$yes(str_contains($controller,'AccountSecurityService::validEmail($email)'));});
$test('password update verifies current password and confirmation',function()use($yes,$controller){$yes(str_contains($controller,"password_verify(\$current,(string)\$account['password_hash'])"));$yes(str_contains($controller,'if($new!==$confirm)'));});
$test('change-password policy requires eight characters',function()use($yes){$yes(AccountSecurityService::validPassword('12345678'));$yes(!AccountSecurityService::validPassword('1234567'));});
$test('password is stored as a secure hash',function()use($yes,$controller){$hash=password_hash('correct horse battery staple',PASSWORD_DEFAULT);$yes($hash!=='correct horse battery staple'&&password_verify('correct horse battery staple',$hash));$yes(str_contains($controller,'password_hash($new,PASSWORD_DEFAULT)'));});
$test('preferences remain independently mapped',function()use($yes,$controller){foreach(['weekly_emails','monthly_emails','favorite_shop_emails'] as $field)$yes(str_contains($controller,"isset(\$_POST['$field'])"));});
$test('all account forms carry CSRF and distinct actions',function()use($yes,$view){$yes(substr_count($view,'name="_csrf"')===4);foreach(['profile','email','password','preferences'] as $action)$yes(str_contains($view,'name="action" value="'.$action.'"'));});
$test('account handler preserves authentication and CSRF guards',function()use($yes,$controller){$account=substr($controller,strpos($controller,'public function account()'));$yes(str_contains($account,'H::requireLogin()'));$yes(str_contains($account,'H::verifyCsrf()'));$yes(str_contains($account,"(int)H::user()['id']"));});

if($failed){fwrite(STDERR,implode("\n",$failed)."\n");exit(1);}fwrite(STDOUT,"Phase 12.3 account settings checks passed ($passed tests).\n");
