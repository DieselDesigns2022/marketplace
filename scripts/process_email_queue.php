#!/usr/bin/env php
<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\EmailQueueService;
use App\Services\OperationalErrorSanitizer;
try{$limit=(int)($argv[1]??($_ENV['MAIL_QUEUE_BATCH_SIZE']??50));$result=EmailQueueService::process($limit);fwrite(STDOUT,json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL);exit(0);}catch(Throwable $e){$safe=OperationalErrorSanitizer::sanitize($e->getMessage(),300);fwrite(STDERR,'Email queue operational failure: '.$safe.PHP_EOL);exit(1);}
