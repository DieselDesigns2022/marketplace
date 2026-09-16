<?php
$db=(string)(getenv('PHASE127_FIXTURE_DB')?:'');if($db===''){fwrite(STDERR,"missing fixture DB\n");exit(2);}$_ENV['DB_NAME']=$db;putenv('DB_NAME='.$db);require dirname(__DIR__,2).'/app/bootstrap.php';
use App\Core\Database as DB;use App\Services\MarketplaceRefundService;
try{$action=$argv[1]??'';$service=new MarketplaceRefundService();if($action==='observe'){$row=$service->observe((int)$argv[2],(string)$argv[3],(int)$argv[4]);$service->ensureAllocationReview($row);echo json_encode(['id'=>(int)$row['id'],'replay'=>(bool)($row['replay']??false)]);}
elseif($action==='claim'){$row=$service->claimPayout((int)$argv[2]);echo json_encode($row);}
elseif($action==='clear_race'){$order=(int)$argv[2];$barrier=(string)$argv[3];DB::begin();DB::row('select id from orders where id=? for update',[$order]);file_put_contents($barrier,'locked');usleep(500000);$service->clearReviewWhenFullyReconciled($order);DB::commit();echo 'cleared';}
else throw new RuntimeException('unknown action');}catch(Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
