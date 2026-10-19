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
        'where d.designer_id=?'
    ),
    'each seller wheel uses only that seller prize inventory'
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
        'oi.coupon_discount'
    )
    &&
    str_contains(
        $service,
        'oi.prize_credit_applied'
    ),
    'qualifying merchandise is calculated after coupon and Prize Credit'
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
        'Needs Wheel Prizes'
    ),
    'admin can see participating sellers and readiness'
);

$check(
    str_contains(
        $seller,
        'your own storefront'
    )
    &&
    str_contains(
        $seller,
        'only prizes donated by your store'
    ),
    'seller participation explains store-specific wheel'
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
