#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\SellerReferralCommissionService;

try {
    $ended = (new SellerReferralCommissionService)->processInactiveReferrals();
    echo "Seller referral inactivity check complete: {$ended} relationship(s) ended.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Seller referral inactivity processing failed: ".$e->getMessage().PHP_EOL);
    exit(1);
}
