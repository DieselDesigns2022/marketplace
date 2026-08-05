<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Services\OperationalErrorSanitizer;
use App\Services\SellerReferralCommissionService;

$period = $argv[1] ?? SellerReferralCommissionService::previousUtcPeriod();
try {
    $result = (new SellerReferralCommissionService())->payMonth($period);
    printf(
        "Seller referral payouts %s UTC: %d paid, %d failed, %d not ready, %d skipped, %d cents transferred.\n",
        $period,
        $result['paid'],
        $result['failed'],
        $result['not_ready'],
        $result['skipped'],
        $result['transferred_cents']
    );
    exit(($result['failed'] + $result['not_ready']) > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Seller referral payout run failed: ' . OperationalErrorSanitizer::sanitize($error->getMessage(), 300) . "\n");
    exit(2);
}
