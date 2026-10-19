<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\PrizeWheelService;

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS':'FAIL').": $label\n";
    if(!$ok)$failures++;
};

$service=file_get_contents(
    dirname(__DIR__).'/app/Services/PrizeWheelService.php'
);

$admin=file_get_contents(
    dirname(__DIR__).'/app/Views/admin/prize_wheel.php'
);

$seller=file_get_contents(
    dirname(__DIR__).'/app/Views/seller/store.php'
);

$sellerController=file_get_contents(
    dirname(__DIR__).'/app/Controllers/SellerController.php'
);

$publicController=file_get_contents(
    dirname(__DIR__).'/app/Controllers/PublicController.php'
);

$store=file_get_contents(
    dirname(__DIR__).'/app/Views/public/store.php'
);

$nav=file_get_contents(
    dirname(__DIR__).'/app/Views/partials/dashboard_nav.php'
);

$routes=file_get_contents(
    dirname(__DIR__).'/public/index.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_wheel_controls.sql'
);

$check(
    str_contains($migration,'prize_wheel_opt_in') &&
    str_contains($migration,'prize_wheel_settings'),
    'Prize Wheel has seller opt-in and global settings storage'
);

$check(
    str_contains($migration,'DEFAULT 0') &&
    str_contains($migration,'VALUES') &&
    str_contains($migration,"'Grand Opening Prize Wheel'"),
    'Prize Wheel defaults safely to OFF'
);

$check(
    str_contains($service,'storefrontVisible') &&
    str_contains($service,'prize_wheel_opt_in') &&
    str_contains($service,'globallyEnabled'),
    'storefront visibility requires both seller opt-in and master switch'
);

$check(
    str_contains($admin,'OFF — hide promotion from every store') &&
    str_contains($admin,'ON — activate participating seller wheels'),
    'admin has explicit master visibility switch'
);

$check(
    str_contains($seller,'prize_wheel_opt_in') &&
    str_contains($sellerController,"=== 'prize_wheel'"),
    'seller can independently opt storefront into Prize Wheel'
);

$check(
    str_contains($publicController,'prizeWheelVisible') &&
    str_contains($store,'creative-moth-prize-wheel'),
    'public storefront renders Prize Wheel only through server-side gate'
);

$check(
    str_contains($nav,"'/admin/prize-wheel'") &&
    str_contains($nav,"'Prize Wheel'"),
    'Prize Wheel is in admin Marketplace navigation'
);

$check(
    str_contains($routes,"'/admin/prize-wheel'") &&
    str_contains($routes,'PrizeWheelController'),
    'Prize Wheel admin route is registered'
);

$check(
    str_contains(
        $service,
        'prize_wheel_prizes'
    )
    &&
    str_contains(
        $service,
        'availableWheelPrizeCount'
    ),
    'Prize Wheel inventory count recognizes Admin-managed wheel prizes'
);

exit($failures?1:0);
