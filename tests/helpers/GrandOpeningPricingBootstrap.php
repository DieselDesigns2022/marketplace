<?php
if (PHP_SAPI!=='cli') throw new RuntimeException('CLI-only test.');
$testDsn=getenv('CM_SALE_TEST_DSN');
if (!$testDsn || !preg_match('/dbname=(cm_sale_test_[a-z0-9_]+)$/D',$testDsn,$testMatch)) {
    throw new RuntimeException('An isolated cm_sale_test_ database is required.');
}
$testRoot=dirname(__DIR__,2);
spl_autoload_register(static function(string $class) use ($testRoot): void {
    if (str_starts_with($class,'App\\')) {
        $path=$testRoot.'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if (is_file($path)) require_once $path;
    }
});
if (!function_exists('app_path')) {
    function app_path(string $path=''): string { return dirname(__DIR__,2).($path!==''?'/'.ltrim($path,'/'):''); }
    function public_path(string $path=''): string { return app_path('public'.($path!==''?'/'.ltrim($path,'/'):'')); }
}
class_alias(App\Core\Helpers::class,'H');
$_ENV=[
    'APP_URL'=>'https://example.invalid','STRIPE_SECRET_KEY'=>'sk_test_fixture_only',
    'STRIPE_CURRENCY'=>'usd','PLATFORM_COMMISSION_PERCENT'=>'9','PLATFORM_COMMISSION_FIXED_CENTS'=>'30',
    'DB_NAME'=>$testMatch[1],
];
$_SESSION=[];
$_GET=[];
$_POST=[];
$_SERVER['REQUEST_METHOD']='GET';
$testPdo=new PDO($testDsn,'root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$testProperty=new ReflectionProperty(App\Core\Database::class,'pdo');
$testProperty->setAccessible(true);
$testProperty->setValue(null,$testPdo);
