<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\CollabService;
try{$result=(new CollabService)->processDue();echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,"Collab deadline processing failed: ".$e->getMessage().PHP_EOL);exit(1);}
