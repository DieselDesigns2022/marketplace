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
        'find_in_set('
    ) &&
    str_contains(
        $service,
        '"prize_wheel"'
    ),
    'Prize Wheel reads comma-separated authorized prize inventory correctly'
);

$check(
    str_contains(
        $service,
        'random_int(1,$total)'
    ) &&
    str_contains(
        $service,
        'quantity_remaining'
    ),
    'Prize selection is weighted by remaining prize units'
);

$check(
    str_contains(
        $service,
        "'prize_wheel'"
    ) &&
    str_contains(
        $service,
        '->assign('
    ) &&
    str_contains(
        $service,
        '->award('
    ),
    'spin uses existing Prize Donation assignment and award workflow'
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
        $controller,
        'prize_wheel_result'
    ) &&
    str_contains(
        $controller,
        'winnerFile'
    ),
    'winner result and protected prize file delivery are wired'
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
        "/prize-wheel/spin"
    ) &&
    str_contains(
        $routes,
        "/prize-wheel/spins/{id}/file"
    ),
    'public spin and winner file routes are registered'
);

$check(
    str_contains(
        $merge,
        "'prize_wheel_spins' => 'user_id'"
    ),
    'Prize Wheel winners participate in account merge'
);

exit($failures?1:0);
