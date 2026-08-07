<?php
if($argc<4){fwrite(STDERR,"usage: worker recalculate|grant designer admin\n");exit(2);}require dirname(__DIR__,2).'/app/bootstrap.php';
if(getenv('PHASE12_FIXTURE_DB'))$_ENV['DB_NAME']=getenv('PHASE12_FIXTURE_DB');
try{$service=new \App\Services\CreatorRecognitionService();$action=$argv[1];$designer=(int)$argv[2];$admin=(int)$argv[3];if($action==='recalculate')$service->recalculate($designer,false,false,'concurrency');elseif($action==='grant')$service->founderAction($designer,'grant',$admin,'concurrent founder grant');else throw new DomainException('invalid worker action');echo "ok\n";exit(0);}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1);}
