<?php
$environment=[];foreach(['DB_HOST','DB_USER','DB_PASS','DB_CHARSET','PHASE122_FIXTURE_DB'] as $key)if(($value=getenv($key))!==false)$environment[$key]=$value;
require dirname(__DIR__,2).'/app/bootstrap.php';foreach($environment as $key=>$value)$_ENV[$key]=$value;$_ENV['DB_NAME']=$environment['PHASE122_FIXTURE_DB'];
use App\Controllers\SellerController;use App\Core\Helpers as H;
$seller=(int)($argv[1]??0);$batch=(int)($argv[2]??0);$csrf=$argv[3]??'valid';$payload=json_decode(base64_decode($argv[4]??''),true)?:[];
$_SESSION['user']=['id'=>$seller,'role'=>'designer'];$_SESSION['_csrf']='phase122';$_SERVER['REQUEST_METHOD']='POST';$_POST=$payload+['_csrf'=>$csrf==='valid'?'phase122':'wrong'];
H::verifyCsrf();(new SellerController())->mutateProductBatch($batch);
