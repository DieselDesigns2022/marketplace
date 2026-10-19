<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\PrizeCreditService;

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS':'FAIL').": $label\n";
    if(!$ok)$failures++;
};

$items=[
    [
        'designer_id'=>1,
        'line_total'=>'20.00',
        'prize_credit_mode'=>'fixed_5',
    ],
    [
        'designer_id'=>1,
        'line_total'=>'10.00',
        'prize_credit_mode'=>'fixed_5',
    ],
    [
        'designer_id'=>2,
        'line_total'=>'40.00',
        'prize_credit_mode'=>'percent_25',
    ],
    [
        'designer_id'=>3,
        'line_total'=>'30.00',
        'prize_credit_mode'=>'none',
    ],
];

$allocation=PrizeCreditService::allocateCart(
    $items,
    [
        0=>'2.00',
        1=>'0.00',
        2=>'0.00',
        3=>'0.00',
    ],
    5000,
    true
);

$check(
    $allocation['seller_cents'][1]===500,
    '$5 seller cap applies once across all seller items'
);

$check(
    $allocation['seller_cents'][2]===1000,
    '25 percent seller cap applies to seller merchandise'
);

$check(
    !isset($allocation['seller_cents'][3]),
    'seller declining Prize Credit receives no allocation'
);

$check(
    $allocation['total_cents']===1500,
    'cart Prize Credit total equals seller-limited allocations'
);

$limited=PrizeCreditService::allocateCart(
    $items,
    [],
    700,
    true
);

$check(
    $limited['total_cents']===700,
    'buyer available Prize Credit caps total allocation'
);

$disabled=PrizeCreditService::allocateCart(
    $items,
    [],
    5000,
    false
);

$check(
    $disabled['total_cents']===0,
    'Prize Credit is not applied unless buyer chooses it'
);

$cart=file_get_contents(
    dirname(__DIR__).'/app/Controllers/CartController.php'
);

$finalizer=file_get_contents(
    dirname(__DIR__).'/app/Services/OrderFinalizationService.php'
);

$view=file_get_contents(
    dirname(__DIR__).'/app/Views/buyer/checkout.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_credit_checkout.sql'
);

$check(
    str_contains(
        $cart,
        'Prize Credit is seller-funded and merchandise-only.'
    )
    &&
    strpos($cart,'$prizeCreditCents')
        < strpos($cart,'CreditService::checkoutBreakdown'),
    'Prize Credit is calculated before Marketplace Credit'
);

$check(
    str_contains(
        $cart,
        '$merchandiseAfterPrizeCents'
    )
    &&
    str_contains(
        $cart,
        '$grossCents'
    ),
    'Prize Credit reduces merchandise before Marketplace Credit'
);

$check(
    str_contains(
        $view,
        'Prize Credit applies only to merchandise'
    )
    &&
    str_contains(
        $view,
        'It cannot pay sales tax'
    ),
    'checkout explicitly states Prize Credit cannot pay tax'
);

$check(
    str_contains(
        $finalizer,
        '$platformFunded = $creditCents > 0'
    ),
    'Prize Credit never creates platform-funded seller payout'
);

$check(
    str_contains(
        $finalizer,
        'total_price-coalesce(oi.prize_credit_applied,0)'
    ),
    'seller financial ledgers subtract seller-funded Prize Credit'
);

$check(
    str_contains(
        $migration,
        'prize_credit_applied'
    )
    &&
    str_contains(
        $migration,
        'prize_credit_mode_snapshot'
    ),
    'orders snapshot Prize Credit redemption and seller participation'
);

exit($failures?1:0);
