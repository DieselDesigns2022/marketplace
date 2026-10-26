<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\PrizeCreditService;

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS':'FAIL').": $label\n";
    if(!$ok)$failures++;
};

$check(
    PrizeCreditService::sellerLimitCents('none',2000)===0,
    'seller can decline Prize Credit'
);

$check(
    PrizeCreditService::sellerLimitCents('full',2000)===2000,
    'full participation accepts full eligible amount'
);

$check(
    PrizeCreditService::sellerLimitCents('fixed_5',2000)===500 &&
    PrizeCreditService::sellerLimitCents('fixed_5',300)===300,
    '$5 seller cap is enforced'
);

$check(
    PrizeCreditService::sellerLimitCents('fixed_10',2000)===1000 &&
    PrizeCreditService::sellerLimitCents('fixed_10',700)===700,
    '$10 seller cap is enforced'
);

$check(
    PrizeCreditService::sellerLimitCents('percent_25',1000)===250,
    '25 percent seller cap is exact'
);

$check(
    PrizeCreditService::sellerLimitCents('percent_50',999)===499,
    '50 percent seller cap uses safe whole cents'
);

$service=file_get_contents(
    dirname(__DIR__).'/app/Services/PrizeCreditService.php'
);

$seller=file_get_contents(
    dirname(__DIR__).'/app/Views/seller/store.php'
);

$admin=file_get_contents(
    dirname(__DIR__).'/app/Views/admin/credits.php'
);

$controller=file_get_contents(
    dirname(__DIR__).'/app/Controllers/AdminCreditController.php'
);

$terms=file_get_contents(
    dirname(__DIR__).'/app/Views/static/page.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_credits.sql'
);

$check(
    str_contains($migration,'prize_credit_grants') &&
    str_contains($migration,'prize_credit_transactions') &&
    str_contains(
        $migration,
        'prize_credit_transaction_allocations'
    ),
    'Prize Credit has separate grant and immutable ledger storage'
);

$check(
    str_contains($seller,'Prize Credit Participation') &&
    str_contains($seller,'prize_credit_mode') &&
    str_contains($seller,'completely optional'),
    'seller Store settings expose voluntary Prize Credit participation'
);

$check(
    str_contains($admin,'Prize Credit Ledger') &&
    str_contains($admin,'value="prize"') &&
    str_contains($admin,'expires_on'),
    'admin Credits page can issue and review Prize Credit'
);

$check(
    str_contains($controller,'PrizeCreditService') &&
    str_contains($controller,'prize_credit_adjustment'),
    'admin Prize Credit adjustments use the dedicated service'
);

$check(
    str_contains($terms,'Prize Credits may carry a stated expiration date') &&
    str_contains($terms,'Prize Credit used on merchandise is restored') &&
    str_contains($terms,'original expiration or revocation status'),
    'Terms define Prize Credit expiration and refund restoration'
);

$check(
    !str_contains(
        $service,
        'marketplace_credits'
    ) &&
    !str_contains(
        $service,
        'advertising_credits'
    ),
    'Prize Credit storage remains financially separate'
);

exit($failures?1:0);
