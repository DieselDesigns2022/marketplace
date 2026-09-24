<?php
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\CollabIpRiskWorkflow;
use App\Services\CollabPayoutService;
use App\Services\CollabService;
use App\Services\CollabUploadValidator;
use App\Services\MarketplaceFeeService;

function phase14Check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$payout = new CollabPayoutService();
$estimate = $payout->estimate(1000,5);
phase14Check($estimate['fee_cents']===120 && $estimate['pool_cents']===880 && $estimate['per_designer_cents']===176, 'configured fee service powers the $10 five-designer estimate');
$unevenEstimate = $payout->estimate(1001,3);
phase14Check(array_sum($unevenEstimate['allocations'])===$unevenEstimate['pool_cents'] && $unevenEstimate['remainder_cents']===$unevenEstimate['pool_cents']%3, 'estimate reports deterministic uneven remainder cents');
foreach ([[0,1],[100,-1],[100,0],[100,10001]] as [$price,$count]) { try { $payout->estimate($price,$count); phase14Check(false,'invalid estimate input rejected'); } catch (InvalidArgumentException) {} }
$split = $payout->split(1000,120,[9,2,7,4,1]);
phase14Check($split['pool_cents']===880, 'fee is removed first');
phase14Check(array_sum($split['allocations'])===880, 'allocations conserve cents');
phase14Check(count(array_unique($split['allocations']))===1 && reset($split['allocations'])===176, 'five-way example');
$odd = $payout->split(101,1,[30,10,20]);
phase14Check($odd['allocations']===[10=>34,20=>33,30=>33], 'remainder is deterministic');
$components=$payout->feeComponents([10=>3,20=>2],3,2);
phase14Check(array_sum(array_column($components,'percentage_cents'))===3 && array_sum(array_column($components,'fixed_cents'))===2,'fee components conserve cents');
foreach ([[1000,120,5],[777,100,3],[1,1,4],[0,0,2]] as [$gross,$fee,$count]) {
    $result=$payout->split($gross,$fee,range(1,$count));
    phase14Check($result['fee_cents']+array_sum($result['allocations'])===$gross,'refund split conserves fee plus entitlement');
}
phase14Check(CollabService::safeArchiveName('../../evil.php',4)==='000004-evil.php','path traversal removed');
phase14Check(CollabService::safeArchiveName('same.zip',4)!==CollabService::safeArchiveName('same.zip',5),'collisions prevented');
$collab=['status'=>'ready','snapshot_at'=>'2026-09-01','final_zip_path'=>'collabs/final/a.zip','ip_risk_state'=>'clear','sale_starts_at'=>'2026-09-02 00:00:00','sale_close_date'=>'2026-09-03'];
$service=new CollabService();
phase14Check($service->canSell($collab,new DateTimeImmutable('2026-09-03 23:59:59')),'close date inclusive');
phase14Check(!$service->canSell($collab,new DateTimeImmutable('2026-09-04 00:00:00')),'close date expires immediately');
$collab['final_zip_path']=null;
phase14Check(!$service->canSell($collab,new DateTimeImmutable('2026-09-03')),'zip gates sale');
try { CollabService::validateDates('2026-09-03 00:00:00','2026-09-02 00:00:00','2026-09-04'); phase14Check(false,'invalid sequence rejected'); } catch (DomainException) {}
phase14Check(CollabIpRiskWorkflow::stateAfterScan('approved','same','same',true)==='approved','unchanged fingerprint preserves approval');
phase14Check(CollabIpRiskWorkflow::stateAfterScan('rejected','same','same',true)==='rejected','unchanged fingerprint preserves rejection');
phase14Check(CollabIpRiskWorkflow::stateAfterScan('approved','old','new',true)==='review_required','changed risky fingerprint requires review');
phase14Check(CollabIpRiskWorkflow::stateAfterScan('approved','old','new',false)==='clear','changed clean fingerprint clears review');
phase14Check(CollabService::itemDownloadable('paid',1000,0),'unrefunded collab downloads');
phase14Check(CollabService::itemDownloadable('partially_refunded',1000,999),'partially refunded collab downloads');
phase14Check(!CollabService::itemDownloadable('partially_refunded',1000,1000),'fully refunded collab is denied');
phase14Check(!CollabService::itemDownloadable('refunded',1000,0),'refunded order is denied');
phase14Check(CollabUploadValidator::serverUploadLimitBytes()>0,'upload validation uses configured server limit');
$uploadValidator=new CollabUploadValidator();
phase14Check($uploadValidator->validateMetadata('artwork.svg',10,'contribution','image/svg+xml')['extension']==='svg','SVG contribution files are accepted as protected downloads');
try{$uploadValidator->validateMetadata('license.svg',10,'terms','image/svg+xml');phase14Check(false,'SVG Terms file rejected');}catch(DomainException){}
foreach(['payload.php','payload.phtml','payload.phar'] as $unsafe){try{$uploadValidator->validateMetadata($unsafe,10,'contribution');phase14Check(false,$unsafe.' rejected');}catch(DomainException){}}
phase14Check(CollabUploadValidator::replacementKind('contribution','contribution')==='contribution','matching replacement kind is retained');
try{CollabUploadValidator::replacementKind('contribution','terms');phase14Check(false,'tampered replacement kind rejected');}catch(DomainException){}
$fee=(new MarketplaceFeeService(900,30))->calculate([['id'=>1,'seller_id'=>1,'gross_cents'=>1000]])[1];
phase14Check($fee['fee_cents']===120,'current fee example');
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/2026_09_23_phase_14_collab_bundles.sql');
$schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');
foreach(['UNIQUE KEY collab_participant_unique','eligibility_snapshotted_at','included_in_snapshot','order_item_id BIGINT NOT NULL','final_zip_sha256'] as $token) phase14Check(str_contains($sql,$token),'migration invariant '.$token);
phase14Check(str_contains($schema,'CREATE TABLE collab_events'),'canonical schema contains Phase 14 tables');
$controller=file_get_contents(dirname(__DIR__).'/app/Controllers/CollabController.php');
phase14Check(substr_count($controller,'changesOpen(')>=6,'deadline guard covers participation and file mutations');
phase14Check(str_contains($controller,'DB::begin()')&&str_contains($controller,'accepted_at=now()'),'invitation writes and acceptance are transactional');
phase14Check(str_contains($controller,'replacementKind('),'controller derives replacement kind authoritatively');
phase14Check(substr_count($controller,'CollabIpRiskWorkflow::invalidate(')>=2,'IP-sensitive file and listing mutations invalidate stale decisions before commit');
$ipWorkflow=file_get_contents(dirname(__DIR__).'/app/Services/CollabIpRiskWorkflow.php');
phase14Check(str_contains($ipWorkflow,'for update')&&str_contains($ipWorkflow,'stateAfterScan'),'authoritative IP scans lock and re-read current collab state');
$collabServiceSource=file_get_contents(dirname(__DIR__).'/app/Services/CollabService.php');
phase14Check(str_contains($collabServiceSource,'get_lock(')&&str_contains($collabServiceSource,'release_lock(')&&str_contains($collabServiceSource,'finally'),'ZIP finalization uses a released per-collab mutex');
$downloadController=file_get_contents(dirname(__DIR__).'/app/Controllers/PublicCollabController.php');
$readPosition=strpos($downloadController,'readfile($real)');
$servedPosition=strpos($downloadController,'"served"');
phase14Check($readPosition!==false&&$servedPosition!==false&&$readPosition<$servedPosition,'served download is logged only after delivery succeeds');
phase14Check(str_contains($downloadController,'where oi.id=? and o.user_id=?'),'collab downloads authorize the exact owned order item');
$checkoutSource=file_get_contents(dirname(__DIR__).'/app/Services/CollabCheckoutService.php');
phase14Check(str_contains($checkoutSource,'creative_moth_collab_checkout_')&&str_contains($checkoutSource,'for update'),'checkout serializes buyer/collab attempts and rechecks locked sale state');
$calculatorView=file_get_contents(dirname(__DIR__).'/app/Views/collabs/form.php');
phase14Check(str_contains($calculatorView,'/seller/collabs/payout-estimate')&&!str_contains($calculatorView,'basisPoints')&&!str_contains($calculatorView,'fixedCents')&&!str_contains($calculatorView,'/10000'),'calculator delegates to the server without duplicating the fee formula');
$payoutSource=file_get_contents(dirname(__DIR__).'/app/Services/CollabPayoutService.php');
phase14Check(str_contains($payoutSource,'MarketplaceFeeService::configured()'),'estimate uses the configured marketplace fee service');
$collabControllerSource=file_get_contents(dirname(__DIR__).'/app/Controllers/CollabController.php');
phase14Check(str_contains($collabControllerSource,'function estimatePayout')&&str_contains($collabControllerSource,'H::verifyCsrf()'),'estimate endpoint requires seller authorization and CSRF');
if(class_exists(ZipArchive::class)) {
    $path=tempnam(sys_get_temp_dir(),'p14zip');$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('_creative-moth-snapshot.json','manifest');$zip->close();
    phase14Check($service->archiveMatchesSnapshot($path,'manifest'),'deterministic existing archive can be adopted');
    phase14Check(!$service->archiveMatchesSnapshot($path,'other'),'mismatched archive is rejected');unlink($path);
}
echo "Phase 14 collab bundle tests passed\n";
