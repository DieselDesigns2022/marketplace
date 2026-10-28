<?php
require __DIR__.'/GrandOpeningPricingBootstrap.php';
$barrier=$argv[4];
file_put_contents($barrier.'.'.$argv[3],'ready');
$until=microtime(true)+15;
while(!is_file($barrier)&&microtime(true)<$until)usleep(10000);
if(!is_file($barrier))throw new RuntimeException('Vote barrier timed out.');
try{
 $id=(new \App\Services\BattleService())->vote((int)$argv[1],(int)$argv[2],(int)$argv[3],'198.51.100.'.$argv[3],'Concurrent fixture '.$argv[3],2);
 echo json_encode(['accepted'=>true,'id'=>$id]);
}catch(DomainException $e){echo json_encode(['accepted'=>false,'message'=>$e->getMessage()]);}
