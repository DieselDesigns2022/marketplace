#!/usr/bin/env php
<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\PromoService;
use App\Services\NotificationService;
use App\Services\OperationalErrorSanitizer;
try{
    $warnings=PromoService::notifyWebsiteEnding();
    $expired=PromoService::expireWebsite();
    $reconciliation=PromoService::reconcileWeeklyDeliveries();
    fwrite(STDOUT,json_encode(['ending_notifications'=>$warnings,'expired'=>$expired,'reconciliation'=>$reconciliation],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
}catch(Throwable $e){
    try{NotificationService::reportFailure('promo_maintenance',$e);}catch(Throwable $ignored){}
    fwrite(STDERR,'Promotion maintenance failed: '.OperationalErrorSanitizer::sanitize($e->getMessage(),300).PHP_EOL);
    exit(1);
}
