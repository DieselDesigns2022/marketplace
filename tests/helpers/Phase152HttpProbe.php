<?php
if(getenv('RUN_DISPOSABLE_DB_TESTS')!=='1'||!preg_match('/^phase152_fixture_[a-f0-9]+$/',getenv('PHASE152_FIXTURE_DB')?:'')){http_response_code(403);exit;}
$env=getenv();require dirname(__DIR__,2).'/app/bootstrap.php';
foreach(['DB_HOST','DB_USER','DB_PASS','DB_CHARSET'] as $key)if(isset($env[$key]))$_ENV[$key]=$env[$key];
$_ENV['DB_NAME']=$env['PHASE152_FIXTURE_DB'];
$_SESSION['user']=['id'=>(int)$env['PHASE152_SELLER_USER'],'role'=>'designer'];
$_SESSION['_csrf']='phase152';
$_POST['_csrf']='phase152';
$action=$_GET['action']??'';$id=(int)($_GET['id']??0);
$seller=new \App\Controllers\SellerController();$public=new \App\Controllers\PublicController();
register_shutdown_function(function(){if(!empty($_SESSION['_flash'])){echo "\nFLASH:".json_encode($_SESSION['_flash']);unset($_SESSION['_flash']);}});
match($action){
    'upload'=>$seller->importProducts(),
    'mapping'=>$seller->mapProductImport(),
    'confirm'=>$seller->confirmProductImport($id),
    'preview'=>$seller->productImportPreview($id),
    'process'=>$seller->processProductImportBatch($id),
    'summary'=>$seller->productImportSummary($id),
    'edit'=>$seller->editProduct($id?:null),
    'submit'=>$seller->submitProduct($id),
    'products'=>$seller->products(),
    'browse'=>$public->browse(),
    'category'=>$public->category($_GET['slug']??''),
    'product'=>$public->product($_GET['slug']??''),
    'store'=>$public->store($_GET['slug']??''),
    'home'=>$public->home(),
    'sitemap'=>$public->sitemap(),
    default=>http_response_code(404),
};
