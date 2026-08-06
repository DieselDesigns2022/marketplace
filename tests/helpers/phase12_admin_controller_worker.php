<?php
require dirname(__DIR__,2).'/app/bootstrap.php';
if(getenv('PHASE12_FIXTURE_DB'))$_ENV['DB_NAME']=getenv('PHASE12_FIXTURE_DB');
$userId=(int)($argv[1]??0);$role=$argv[2]??'buyer';$validCsrf=($argv[3]??'invalid')==='valid';$action=$argv[4]??'invalid';$seller=(int)($argv[5]??0);$reason=$argv[6]??'valid reason';
$_SESSION['user']=['id'=>$userId,'role'=>$role,'status'=>'active'];$_SESSION['_csrf']='phase12-test-csrf';$_SERVER['REQUEST_METHOD']='POST';$_POST=['_csrf'=>$validCsrf?'phase12-test-csrf':'wrong','action'=>$action,'id'=>$seller,'reason'=>$reason,'creator_rank'=>'Silver'];(new \App\Controllers\AdminController())->designers();
