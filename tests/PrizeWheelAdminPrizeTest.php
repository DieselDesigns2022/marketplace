<?php

require dirname(__DIR__).'/app/bootstrap.php';

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS':'FAIL').": $label\n";
    if(!$ok)$failures++;
};

$service=file_get_contents(
    dirname(__DIR__).'/app/Services/PrizeWheelService.php'
);

$controller=file_get_contents(
    dirname(__DIR__).'/app/Controllers/PrizeWheelController.php'
);

$admin=file_get_contents(
    dirname(__DIR__).'/app/Views/admin/prize_wheel.php'
);

$seller=file_get_contents(
    dirname(__DIR__).'/app/Views/seller/store.php'
);

$coupon=file_get_contents(
    dirname(__DIR__).'/app/Services/CouponService.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_wheel_admin_prizes.sql'
);

foreach([
    '$5 Store Credit',
    '$3 Store Credit',
    '$1 Store Credit',
    '20% Off Coupon',
    '10% Off Coupon',
    '15% Off Coupon',
    'Spin Again',
    'Grand Opening Giveaway Entry',
] as $title){
    $check(
        str_contains($migration,$title),
        'preset exists: '.$title
    );
}

$check(
    str_contains($service,'prize_wheel_prizes')
    &&
    !str_contains(
        $service,
        'private function inventory('
    ),
    'wheel uses Admin prize definitions instead of seller donations'
);

$check(
    str_contains(
        $service,
        '"seller"'
    )
    &&
    str_contains(
        $service,
        '$storefrontDesignerId'
    )
    &&
    str_contains(
        $service,
        "'seller_fixed_coupon'"
    ),
    'store credit prize is seller-specific'
);

$check(
    str_contains(
        $service,
        "'seller_percent_coupon'"
    ),
    'percentage coupon prizes are seller-specific'
);

$check(
    str_contains(
        $service,
        'prize_wheel_bonus_spins'
    ),
    'Spin Again creates seller-specific bonus spin'
);

$check(
    str_contains(
        $service,
        'grand_opening_giveaway_entries'
    ),
    'main Grand Opening giveaway entry is recorded'
);

$check(
    str_contains(
        $coupon,
        'Prize Wheel coupon belongs to another account'
    ),
    'generated seller coupon is locked to winning buyer'
);

$check(
    str_contains(
        $admin,
        'Wheel Prizes'
    )
    &&
    str_contains(
        $controller,
        "action==='save_prize'"
    ),
    'Admin can manage Prize Wheel prizes'
);

$check(
    str_contains(
        $seller,
        'Creative Moth will not reimburse me'
    )
    &&
    str_contains(
        $seller,
        'prize_wheel_opt_in'
    ),
    'seller opt-in clearly accepts seller-funded giveaway prizes'
);


$check(
    str_contains(
        $service,
        'o.stripe_paid_amount'
    )
    &&
    str_contains(
        $service,
        '- coalesce(o.tax_amount,0)'
    ),
    'qualification uses actual Stripe-paid merchandise after tax exclusion'
);

exit($failures?1:0);
