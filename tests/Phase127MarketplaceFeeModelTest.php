<?php

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\MarketplaceFeeService;
use App\Services\SellerReferralCommissionService;

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) $failures++;
};
$service = new MarketplaceFeeService(900, 30);
$sale = static fn(int $cents): array => $service->calculate([['id'=>1,'seller_id'=>7,'gross_cents'=>$cents]])[7];

foreach ([250=>[23,30,53,197],500=>[45,30,75,425],1000=>[90,30,120,880],2000=>[180,30,210,1790]] as $gross=>$expected) {
    $result=$sale($gross);$check([$result['percentage_fee_cents'],$result['fixed_fee_cents'],$result['fee_cents'],$result['seller_earnings_cents']]===$expected,"exact fee for {$gross} cents");
}
$oneSeller=$service->calculate([['id'=>2,'seller_id'=>1,'gross_cents'=>250],['id'=>1,'seller_id'=>1,'gross_cents'=>250]])[1];
$check($oneSeller['fee_cents']===75&&array_sum(array_column($oneSeller['items'],'fixed_fee_cents'))===30,'multiple items pay one fixed fee with exact deterministic allocation');
$twoSellers=$service->calculate([['id'=>1,'seller_id'=>1,'gross_cents'=>500],['id'=>2,'seller_id'=>2,'gross_cents'=>500]]);
$check($twoSellers[1]['fee_cents']===75&&$twoSellers[2]['fee_cents']===75,'multiple sellers each pay one fixed fee');
$check($sale(800)['fee_cents']===102&&$sale(800)['seller_earnings_cents']===698,'20% coupon and paid license merchandise both produce $8 snapshot');
$check($sale(1000)['fee_cents']===120&&$sale(1000)['seller_earnings_cents']===880,'store credit and tax do not alter $10 merchandise fee basis');
$remaining=$service->recalculateRemaining(500);$check($remaining['fee_cents']===75&&$remaining['seller_earnings_cents']===425,'partial refund recalculates remaining entitlement');
$full=$service->recalculateRemaining(0);$check($full['fee_cents']===0&&$full['seller_earnings_cents']===0,'full refund removes both fee components');
$low=$sale(1);$check($low['fee_cents']===1&&$low['seller_earnings_cents']===0&&$low['capped'],'low-value fee is capped and payout cannot be negative');
$unchanged=$twoSellers[2];$refunded=$service->calculate([['id'=>1,'seller_id'=>1,'gross_cents'=>0],['id'=>2,'seller_id'=>2,'gross_cents'=>500]]);$check($refunded[1]['fee_cents']===0&&$refunded[2]===$unchanged,'one seller refund does not affect another seller');
$check(SellerReferralCommissionService::commissionCents($sale(1000)['seller_earnings_cents'])===9,'referral reward uses actual seller earnings');
$legacyGross=1000;$legacyRate=1800;$legacyFee=intdiv($legacyGross*$legacyRate+5000,10000);$check($legacyFee===180,'historical percentage-only snapshot remains 18% without a fixed fee');

exit($failures === 0 ? 0 : 1);
