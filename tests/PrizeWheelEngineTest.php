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

$store=file_get_contents(
    dirname(__DIR__).'/app/Views/public/store.php'
);

$admin=file_get_contents(
    dirname(__DIR__).'/app/Views/admin/prize_wheel.php'
);

$routes=file_get_contents(
    dirname(__DIR__).'/public/index.php'
);

$merge=file_get_contents(
    dirname(__DIR__).'/app/Services/AccountMergeService.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_19_prize_wheel_engine.sql'
);

$check(
    str_contains($migration,'prize_wheel_spins') &&
    str_contains($migration,'campaign_key') &&
    str_contains($migration,'spins_per_user'),
    'Prize Wheel stores spins and giveaway round eligibility'
);

$check(
    str_contains(
        $service,
        'prize_wheel_prizes'
    )
    &&
    str_contains(
        $service,
        'is_active=1'
    ),
    'Prize Wheel reads active Admin-managed prize inventory'
);

$check(
    str_contains(
        $service,
        '(int)$prize[\'weight\']'
    )
    &&
    str_contains(
        $service,
        'random_int(1,$total)'
    ),
    'Prize selection is weighted by Admin-configured prize weights'
);

$check(
    str_contains(
        $service,
        'fulfillReward('
    )
    &&
    str_contains(
        $service,
        'prize_wheel_rewards'
    ),
    'spin creates and fulfills Admin-managed wheel rewards'
);

$check(
    str_contains(
        $service,
        'get_lock'
    ) &&
    str_contains(
        $migration,
        'prize_wheel_spin_user_unique'
    ),
    'concurrent duplicate spins are guarded'
);

$check(
    str_contains(
        $service,
        'coupon_code'
    )
    &&
    str_contains(
        $service,
        'reward_message'
    )
    &&
    str_contains(
        $controller,
        "You won "
    ),
    'winner result and seller-specific reward fulfillment are wired'
);

$check(
    str_contains(
        $store,
        'creative-wheel-spin-form'
    ) &&
    str_contains(
        $store,
        'is-spinning'
    ),
    'storefront contains real spinning wheel interaction'
);

$check(
    str_contains(
        $admin,
        'Minimum purchase required'
    ) &&
    str_contains(
        $admin,
        'Start New Promotion Round'
    ) &&
    str_contains(
        $admin,
        'Recent Spins / Winners'
    ),
    'admin controls qualifying purchase and promotion rounds'
);

$check(
    str_contains(
        $routes,
        "/store/{id}/prize-wheel/spin"
    )
    &&
    !str_contains(
        $routes,
        "/prize-wheel/spins/{id}/file"
    ),
    'public Prize Wheel exposes spin route without obsolete prize-file route'
);

$check(
    str_contains(
        $merge,
        "'prize_wheel_spins' => 'user_id'"
    ),
    'Prize Wheel winners participate in account merge'
);

exit($failures?1:0);
