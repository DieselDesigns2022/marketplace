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

$stripe=file_get_contents(
    dirname(__DIR__).'/app/Controllers/StripeController.php'
);

$buyer=file_get_contents(
    dirname(__DIR__).'/app/Controllers/BuyerController.php'
);

$success=file_get_contents(
    dirname(__DIR__).'/app/Views/buyer/payment_success.php'
);

$orderView=file_get_contents(
    dirname(__DIR__).'/app/Views/buyer/order.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_wheel_order_entitlements.sql'
);

$check(
    str_contains(
        $migration,
        'campaign_key'
    )
    &&
    str_contains(
        $migration,
        'qualifying_order_id'
    )
    &&
    str_contains(
        $migration,
        'storefront_designer_id'
    ),
    'same order can independently unlock multiple seller wheels'
);

$check(
    str_contains(
        $service,
        'ws.storefront_designer_id=?'
    ),
    'used spin only consumes entitlement for that seller'
);

$check(
    str_contains($service,"':store:'")
    &&
    str_contains($service,"':order:'"),
    'Prize Wheel assignment idempotency includes seller and order'
);

$check(
    str_contains(
        $service,
        'public function unlockedStoresForOrder('
    ),
    'Prize Wheel exposes post-purchase unlocked seller wheels'
);

$check(
    !str_contains(
        $service,
        'oi.total_price
                                - coalesce(
                                    oi.coupon_discount'
    ),
    'qualifying amount does not subtract coupon twice'
);

$check(
    str_contains(
        $service,
        'oi.prize_credit_applied'
    ),
    'seller-funded Prize Credit reduces qualifying purchase amount'
);

$check(
    str_contains(
        $stripe,
        'unlockedStoresForOrder'
    )
    &&
    str_contains(
        $success,
        'Spin Now'
    ),
    'Stripe success page surfaces unlocked wheel spins'
);

$check(
    str_contains(
        $buyer,
        'unlockedStoresForOrder'
    )
    &&
    str_contains(
        $orderView,
        'Prize Wheel Spin Unlocked!'
    ),
    'buyer order page surfaces unlocked wheel spins'
);

exit($failures?1:0);
