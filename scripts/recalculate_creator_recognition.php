#!/usr/bin/env php
<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\CreatorRecognitionService;
if(getenv('PHASE12_DB_NAME'))$_ENV['DB_NAME']=getenv('PHASE12_DB_NAME'); // Disposable integration fixture only.
$mode=$argv[1]??'--dry-run';
if(!in_array($mode,['--dry-run','--apply','--daily'],true)){fwrite(STDERR,"Usage: php scripts/recalculate_creator_recognition.php [--dry-run|--apply|--daily]\n");exit(2);}
try{
    $dry=$mode==='--dry-run';$communicate=$mode==='--daily';
    $rows=(new CreatorRecognitionService)->recalculateAll($dry,$communicate);$changed=count(array_filter($rows,fn($r)=>$r['changed']));
    echo strtoupper(substr($mode,2)).': reviewed '.count($rows)." approved sellers; $changed change(s).\n";
    foreach($rows as $r)if($r['changed'])echo 'Seller #'.$r['designer_id'].': '.$r['qualifying_sales'].' sales, '.$r['effective_rank'].', Founder '.($r['founder_position']?'#'.$r['founder_position'].($r['founder_active']?' active':' inactive'):'not assigned')."\n";
    exit(0);
}catch(Throwable $e){fwrite(STDERR,"Creator recognition recalculation failed. Review the application error log.\n");error_log($e);exit(1);}
