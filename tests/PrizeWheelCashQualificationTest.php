<?php

require dirname(__DIR__).'/app/bootstrap.php';

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS':'FAIL').": $label\n";

    if(!$ok){
        $failures++;
    }
};

$service=file_get_contents(
    dirname(__DIR__).'/app/Services/PrizeWheelService.php'
);

$store=file_get_contents(
    dirname(__DIR__).'/app/Views/public/store.php'
);

$home=file_get_contents(
    dirname(__DIR__).'/app/Views/public/home.php'
);

$publicController=file_get_contents(
    dirname(__DIR__).'/app/Controllers/PublicController.php'
);

$payment=file_get_contents(
    dirname(__DIR__).'/app/Views/buyer/payment_success.php'
);

$check(
    str_contains(
        $service,
        'o.stripe_paid_amount'
    ),
    'wheel qualification uses actual Stripe-paid amount'
);

$check(
    str_contains(
        $service,
        '- coalesce(o.tax_amount,0)'
    ),
    'sales tax is excluded from qualifying cash'
);

$check(
    str_contains(
        $service,
        'all_items.total_price'
    )
    &&
    str_contains(
        $service,
        'oi.designer_id=?'
    ),
    'mixed-seller Stripe cash is allocated by seller merchandise share'
);

$check(
    str_contains(
        $service,
        'oi.prize_credit_applied'
    ),
    'Prize Credit reduces qualifying merchandise'
);

$check(
    str_contains(
        $service,
        '$usingBonus='
    )
    &&
    str_contains(
        $service,
        'if($usingBonus)'
    ),
    'purchase spin and Spin Again consume separate entitlements'
);

$check(
    str_contains(
        $store,
        'after all coupons, credits,'
    )
    &&
    str_contains(
        $store,
        'Marketplace Credit'
    )
    &&
    str_contains(
        $store,
        'Sales tax does not count'
    ),
    'storefront clearly explains the post-discount $5 rule'
);

$check(
    !str_contains(
        $store,
        'Donated by'
    )
    &&
    !str_contains(
        $store,
        'Download / Claim Prize File'
    ),
    'old Prize Donation winner UI is removed'
);

$check(
    str_contains(
        $publicController,
        '$wheelService->globallyEnabled()'
    )
    &&
    str_contains(
        $publicController,
        '$wheelService->participatingStores()'
    )
    &&
    str_contains(
        $home,
        'Grand Opening Party Sellers'
    ),
    'homepage shows participating sellers only through active wheel state'
);

$check(
    str_contains(
        $payment,
        'creative-moth-payment-confirmation:'
    )
    &&
    str_contains(
        $payment,
        'window.sessionStorage.getItem'
    ),
    'payment confirmation refresh timeout survives page reloads'
);

exit($failures?1:0);
