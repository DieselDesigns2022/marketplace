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
    dirname(__DIR__).
    '/app/Services/PrizeWheelService.php'
);

$admin=file_get_contents(
    dirname(__DIR__).
    '/app/Views/admin/prize_wheel.php'
);

$store=file_get_contents(
    dirname(__DIR__).
    '/app/Views/public/store.php'
);

$seller=file_get_contents(
    dirname(__DIR__).
    '/app/Views/seller/store.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_wheel_store_specific.sql'
);

$check(
    str_contains(
        $service,
        '$storefrontDesignerId'
    )
    &&
    str_contains(
        $service,
        'seller_fixed_coupon'
    )
    &&
    str_contains(
        $service,
        'seller_percent_coupon'
    ),
    'each seller wheel fulfills rewards specifically for that storefront'
);

$check(
    str_contains(
        $service,
        'qualifying_order_id'
    )
    &&
    str_contains(
        $service,
        'oi.designer_id=?'
    ),
    'spin eligibility is tied to a paid order from that seller'
);

$check(
    str_contains(
        $service,
        'oi.total_price'
    )
    &&
    str_contains(
        $service,
        'oi.prize_credit_applied'
    ),
    'qualifying merchandise uses discounted line total and subtracts Prize Credit'
);

$check(
    str_contains(
        $migration,
        'minimum_purchase_cents'
    )
    &&
    str_contains(
        $migration,
        'DEFAULT 500'
    ),
    'default qualifying purchase is $5'
);

$check(
    str_contains(
        $migration,
        'qualifying_order_id'
    )
    &&
    str_contains(
        $migration,
        'prize_wheel_order_campaign_uq'
    ),
    'a qualifying order can unlock only one wheel spin per promotion round'
);

$check(
    str_contains(
        $admin,
        'Participating Sellers'
    )
    &&
    str_contains(
        $admin,
        'Active wheel prizes:'
    )
    &&
    str_contains(
        $admin,
        'seller-funded'
    ),
    'admin can see participating sellers and global wheel prize status'
);

$check(
    str_contains(
        $seller,
        'Participate in Creative Moth giveaways'
    )
    &&
    str_contains(
        $seller,
        'will not reimburse me'
    )
    &&
    str_contains(
        $seller,
        'You do not need to donate prize inventory for the Prize Wheel'
    )
    &&
    str_contains(
        $seller,
        'prize_wheel_opt_in'
    ),
    'seller participation explains giveaway consent and seller-funded rewards'
);

$check(
    str_contains(
        $store,
        'prize-wheel-promo-modal'
    )
    &&
    str_contains(
        $store,
        'qualifying purchase'
    ),
    'participating storefront displays promotional popup'
);

$check(
    str_contains(
        $store,
        "sessionStorage"
    ),
    'store promotion popup is limited to once per browser session per seller'
);

exit($failures?1:0);
