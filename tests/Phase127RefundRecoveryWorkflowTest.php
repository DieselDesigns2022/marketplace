<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\MarketplaceRefundService;
$fail=0;$check=static function(bool $ok,string $message)use(&$fail){echo($ok?'PASS: ':'FAIL: ').$message.PHP_EOL;if(!$ok)$fail++;};
$items=[['id'=>1,'seller_id'=>10,'gross_cents'=>500],['id'=>2,'seller_id'=>20,'gross_cents'=>500]];$isolated=MarketplaceRefundService::sellerEntitlements($items,[1=>500]);
$check($isolated[10]['gross_cents']===0&&$isolated[10]['fee_cents']===0&&$isolated[10]['seller_earnings_cents']===0,'seller A full item refund reaches zero');
$check($isolated[20]['gross_cents']===500&&$isolated[20]['fee_cents']===75&&$isolated[20]['seller_earnings_cents']===425,'seller B remains unchanged');
$check(MarketplaceRefundService::outstandingDelta(200,0)===200&&MarketplaceRefundService::outstandingDelta(500,200)===300&&MarketplaceRefundService::outstandingDelta(500,500)===0,'refund observations expose only the new cumulative delta');
$check(MarketplaceRefundService::allocationIsExact(300,[1=>200],100)&&!MarketplaceRefundService::allocationIsExact(300,[1=>200],0)&&!MarketplaceRefundService::allocationIsExact(0,[],0),'merchandise plus tax must exactly reconcile a positive observation');
$first=MarketplaceRefundService::sellerEntitlements([['id'=>1,'seller_id'=>10,'gross_cents'=>1000]],[1=>200])[10];$second=MarketplaceRefundService::sellerEntitlements([['id'=>1,'seller_id'=>10,'gross_cents'=>1000]],[1=>500])[10];
$check($first['seller_earnings_cents']===698&&$second['seller_earnings_cents']===425,'successive item refunds recalculate current entitlement');
$check(MarketplaceRefundService::recoveryGrowth(455,182)===273&&MarketplaceRefundService::recoveryGrowth(455,455)===0,'later recovery growth creates only the new delta after a prior waiver/application');
$check(MarketplaceRefundService::recoveryCanClose(0)&&!MarketplaceRefundService::recoveryCanClose(1),'reserved recovery cannot be waived or resolved');
$full=MarketplaceRefundService::recoveryPlan(600,455);$check($full['applied_cents']===455&&$full['transfer_cents']===145,'next payout can fully satisfy recovery');
$large=MarketplaceRefundService::recoveryPlan(425,600);$check($large['applied_cents']===425&&$large['transfer_cents']===0&&$large['remaining_recovery_cents']===175,'recovery cannot produce a negative transfer');
$next=MarketplaceRefundService::recoveryPlan(100,$large['remaining_recovery_cents']);$last=MarketplaceRefundService::recoveryPlan(100,$next['remaining_recovery_cents']);$check($next['transfer_cents']===0&&$last['applied_cents']===75&&$last['transfer_cents']===25,'multiple payouts satisfy recovery incrementally');
exit($fail?1:0);
