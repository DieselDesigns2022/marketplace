<?php

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CreditService;
use App\Services\ReferralService;
use App\Services\SellerReferralCommissionService;
use App\Services\StripeService;

$failures = [];
$check = function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ": $message\n";
    if (!$condition) $failures[] = $message;
};

$valid = ['0' => 0, '0.00' => 0, '1.5' => 150, '1.50' => 150, '-1.50' => -150, '9999999999.99' => 999999999999];
foreach ($valid as $amount => $expected) {
    $check(CreditService::parseCents($amount) === $expected, "strict decimal parsing: $amount");
}
foreach (['', ' 1.00x', '1e2', 'INF', 'NaN', '01.00', '.50', '1.234', '--1', '10000000000.00'] as $invalid) {
    try {
        CreditService::parseCents($invalid);
        $rejected = false;
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $check($rejected, "invalid money rejected: $invalid");
}
$check(CreditService::formatCents(-150) === '-1.50', 'signed cents format exactly');
$partial = CreditService::checkoutBreakdown('10.00', '2.00', '0.80', '3.00', true);
$check($partial === ['subtotal_cents'=>1000,'discount_cents'=>200,'tax_cents'=>80,'credit_cents'=>300,'final_cents'=>580], 'tax is included before partial credit');
$full = CreditService::checkoutBreakdown('10.00', '2.00', '0.80', '20.00', true);
$check($full['credit_cents'] === 880 && $full['final_cents'] === 0, 'credit can cover merchandise and tax exactly');
$check(ReferralService::validFormat('AM12_ABCD') && !ReferralService::validFormat('12345678') && !ReferralService::validFormat('bad!'), 'referral codes are URL-safe and numeric IDs are rejected');
$check(SellerReferralCommissionService::commissionCents(0)===0 && SellerReferralCommissionService::commissionCents(49)===0 && SellerReferralCommissionService::commissionCents(50)===1 && SellerReferralCommissionService::commissionCents(149)===1 && SellerReferralCommissionService::commissionCents(150)===2, 'seller referral 1% uses exact integer cents with half-up rounding');
$check(SellerReferralCommissionService::periodBounds('2024-02')===['2024-02-01 00:00:00','2024-03-01 00:00:00'], 'monthly payout uses exact UTC calendar boundaries including leap months');
foreach (['2024-00','2024-13','2024-1','not-a-month'] as $invalidPeriod) {
    try { SellerReferralCommissionService::periodBounds($invalidPeriod); $periodRejected=false; } catch (DomainException) { $periodRejected=true; }
    $check($periodRejected, 'invalid payout period rejected: '.$invalidPeriod);
}
$check(ReferralService::BUYER_REWARD === '1.50' && ReferralService::SELLER_REWARD === '5.00', 'reward constants are exact');
$address = ['line1'=>' 1 main st ','city'=>'Town','state'=>'ca','postal_code'=>'90210','country'=>'us'];
$check(StripeService::billingAddressMatches(StripeService::normalizeBillingAddress($address), ['line1'=>'1 MAIN ST','city'=>'TOWN','state'=>'CA','postal_code'=>'90210','country'=>'US']), 'normalized authoritative billing address matches Stripe response');
$check(!StripeService::billingAddressMatches(StripeService::normalizeBillingAddress($address), ['line1'=>'2 MAIN ST','city'=>'TOWN','state'=>'CA','postal_code'=>'90210','country'=>'US']), 'material billing address mismatch is rejected');

$oldStripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
$_ENV['STRIPE_SECRET_KEY'] = 'sk_test_deterministic';
$requests = [];
$responses = [];
StripeService::setTestTransport(function (string $method, string $path, array $params, ?string $key) use (&$requests, &$responses): array {
    $requests[] = compact('method', 'path', 'params', 'key');
    if (isset($responses[$key])) return $responses[$key];
    if ($path === '/v1/tax/calculations') {
        return $responses[$key] = ['id'=>'taxcalc_TEST1','tax_amount_exclusive'=>80,'tax_amount_inclusive'=>0,'amount_total'=>1080];
    }
    if ($path === '/v1/tax/transactions/create_from_calculation') {
        return $responses[$key] = ['id'=>'tax_1_test'];
    }
    if ($path === '/v1/transfers') {
        return $responses[$key] = ['id'=>'tr_platform_test'];
    }
    throw new RuntimeException('Unexpected deterministic Stripe request.');
});
$tax = StripeService::calculateTax([['id'=>1,'total_price'=>'10.00']], ['line1'=>'1 Main St','city'=>'Town','state'=>'CA','postal_code'=>'90210','country'=>'US'], 'tax-calc-test');
$check($tax['tax_cents'] === 80 && $tax['total_cents'] === 1080, 'deterministic transport calculates authoritative tax in cents');
$transaction = StripeService::createTaxTransaction($tax['id'], 42);
$check($transaction['id'] === 'tax_1_test', 'deterministic transport creates Tax Transaction');
$transfer = StripeService::createTransfer('acct_test', 850, 'usd', ['order_id'=>'42'], 'payout-test', null, 'order_42');
$transferReplay = StripeService::createTransfer('acct_test', 850, 'usd', ['order_id'=>'42'], 'payout-test', null, 'order_42');
$lastTransfer = array_values(array_filter($requests, fn(array $request): bool => $request['path'] === '/v1/transfers'))[0];
$check($transfer === $transferReplay && $transfer['id'] === 'tr_platform_test', 'deterministic transfer replay uses its stable idempotency key');
$check(!isset($lastTransfer['params']['source_transaction']) && $lastTransfer['params']['transfer_group'] === 'order_42', 'platform-credit transfer omits buyer source and retains order transfer group');
$taxFailure = false;
StripeService::setTestTransport(static function (): array { throw new RuntimeException('deterministic tax failure'); });
try { StripeService::createTaxTransaction('taxcalc_TEST1', 43); } catch (RuntimeException) { $taxFailure = true; }
$check($taxFailure, 'deterministic Stripe transport exercises Tax Transaction failure');
$transferFailure = false;
try { StripeService::createTransfer('acct_test', 850, 'usd', [], 'payout-failure', null, 'order_43'); } catch (RuntimeException) { $transferFailure = true; }
$check($transferFailure, 'deterministic Stripe transport exercises platform-balance transfer failure');
StripeService::setTestTransport(null);
if ($oldStripeKey === null) unset($_ENV['STRIPE_SECRET_KEY']); else $_ENV['STRIPE_SECRET_KEY'] = $oldStripeKey;

try {
    App\Core\Database::pdo();
    echo "INFO: Database-backed service behavior is exercised by Phase11DatabaseIntegrationTest.php.\n";
} catch (Throwable $error) {
    echo "SKIP: Database service cases require configured disposable MariaDB: " . $error->getMessage() . "\n";
}
exit($failures ? 1 : 0);
