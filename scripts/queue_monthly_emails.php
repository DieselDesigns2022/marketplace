#!/usr/bin/env php
<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\EmailDigestService;
use App\Services\OperationalErrorSanitizer;
try{$end=$argv[1]??null;$queued=EmailDigestService::queueDigest('monthly',$end);fwrite(STDOUT,json_encode(['monthly_queued'=>$queued],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(0);}catch(Throwable $e){fwrite(STDERR,'Monthly email queue failure: '.OperationalErrorSanitizer::sanitize($e->getMessage(),300).PHP_EOL);exit(1);}
