<?php
namespace App\Core {
    final class Helpers {
        public static array $permissions=[];
        public static bool $loggedIn=true;
        public static function canAdmin(string $key):bool {return in_array($key,self::$permissions,true);}
        public static function requireLogin():void {if(!self::$loggedIn)throw new \RuntimeException('login');}
        public static function abort(int $code):void {throw new \RuntimeException((string)$code);}
    }
}
namespace {
    if(PHP_SAPI!=='cli')throw new \RuntimeException('CLI only.');
    require __DIR__.'/../app/Services/PromoGraphicAccessService.php';
    require __DIR__.'/../app/Services/UserDirectoryFilter.php';
    require __DIR__.'/../app/Controllers/AdminPromoGraphicController.php';
    use App\Core\Helpers as H;
    use App\Services\PromoGraphicAccessService as Access;
    use App\Services\UserDirectoryFilter as Filter;
    $checks=0;
    $check=static function(bool $ok,string $name)use(&$checks):void {if(!$ok)throw new \RuntimeException($name);++$checks;};
    $controller=new App\Controllers\AdminPromoGraphicController();
    $gate=new \ReflectionMethod($controller,'gate');$gate->setAccessible(true);
    $keys=['promotions.view','promotions.manage','social_content.view','social_content.manage'];
    for($mask=0;$mask<16;$mask++) {
        H::$permissions=[];foreach($keys as $bit=>$key)if($mask&(1<<$bit))H::$permissions[]=$key;
        foreach(['promotions.view'=>(bool)(($mask&1)||($mask&4)), 'promotions.manage'=>(bool)(($mask&2)||(($mask&4)&&($mask&8)))] as $permission=>$expected) {
            $check(Access::can($permission)===$expected,'Permission matrix '.$mask.' '.$permission);
            $allowed=true;try{$gate->invoke($controller,$permission);}catch(\RuntimeException $e){$allowed=false;}
            $check($allowed===$expected,'Controller permission '.$mask.' '.$permission);
        }
    }
    H::$permissions=['social_content.view','social_content.manage'];
    $check(!H::canAdmin('promotions.manage'),'Social access does not grant general promotion management');
    $check(!Access::can('users.manage'),'Unknown permission cannot use library exception');
    H::$loggedIn=false;
    $blocked=false;try{$gate->invoke($controller,'promotions.view');}catch(\RuntimeException $e){$blocked=$e->getMessage()==='login';}
    $check($blocked,'Library still requires login');H::$loggedIn=true;
    $users=[
        ['id'=>1,'name'=>'Angela','email'=>'owner@example.invalid','role'=>'admin','status'=>'active','designer_id'=>10],
        ['id'=>2,'name'=>'Social Manager','email'=>'social@example.invalid','role'=>'admin','status'=>'active','designer_id'=>null],
        ['id'=>3,'name'=>'Seller','email'=>'shop@example.invalid','role'=>'designer','status'=>'disabled','designer_id'=>11],
        ['id'=>4,'name'=>'Buyer','email'=>'buyer@example.invalid','role'=>'buyer','status'=>'active','designer_id'=>null],
    ];
    $ids=static fn(array $f):array=>array_column(Filter::apply($users,$f),'id');
    $check($ids([])===[1,2,3,4],'Default includes everyone');
    $check($ids(['user_role'=>'admin'])===[1,2],'Admin filter');
    $check($ids(['user_role'=>'seller'])===[1,3],'Seller filter includes dual-role admin');
    $check($ids(['user_role'=>'buyer'])===[4],'Buyer filter');
    $check($ids(['user_status'=>'disabled'])===[3],'Disabled filter');
    $check($ids(['user_role'=>'seller','user_status'=>'active'])===[1],'Combined role/status');
    $check($ids(['user_q'=>'OWNER@EXAMPLE'])===[1],'Case-insensitive email search');
    $check($ids(['user_q'=>'  social  '])===[2],'Trimmed name search');
    $check($ids(['user_role'=>'seller','user_q'=>'social'])===[],'All filters must match');
    $check($ids(['user_role'=>'unknown','user_status'=>'unknown'])===[1,2,3,4],'Unknown options reset');
    $check(Filter::state(['user_q'=>['bad']])['user_q']==='','Array search rejected');
    $check(mb_strlen(Filter::state(['user_q'=>str_repeat('a',200)])['user_q'])===150,'Search length bounded');
    $check($ids(['user_q'=>"' OR 1=1 --"])===[],'Search treated as text');
    $view=file_get_contents(__DIR__.'/../app/Views/admin/users.php');
    $check(substr_count($view,'action="<?=H::e($userAction)?>"')===2,'Filters preserved on account and permission saves');
    $check(str_contains($view,"H::e(\$userFilters['user_q'])"),'Search value escaped');
    $library=file_get_contents(__DIR__.'/../app/Views/admin/promo_graphics.php');
    $check(str_contains($library,"PromoGraphicAccessService::can('promotions.manage')"),'Library buttons use same permission check');
    $nav=file_get_contents(__DIR__.'/../app/Views/partials/dashboard_nav.php');
    $check(str_contains($nav,"PromoGraphicAccessService::can('promotions.view')"),'Library navigation uses same permission check');
    echo "PASS: $checks graphics-access and user-filter checks\n";
}
