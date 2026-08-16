#!/usr/bin/env php
<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\EmailDigestService;
use App\Services\OperationalErrorSanitizer;
try{$end=$argv[1]??null;$favorite=EmailDigestService::queueFavoriteShops($end);$digest=EmailDigestService::queueDigest('weekly',$end);fwrite(STDOUT,json_encode(['weekly_queued'=>$digest,'favorite_shop_queued'=>$favorite],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(0);}catch(Throwable $e){fwrite(STDERR,'Weekly email queue failure: '.OperationalErrorSanitizer::sanitize($e->getMessage(),300).PHP_EOL);exit(1);}
