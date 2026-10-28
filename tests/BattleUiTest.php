<?php

require dirname(__DIR__).'/app/bootstrap.php';

$root=dirname(__DIR__);
$fail=[];

$check=function(
    bool $ok,
    string $name
)use(&$fail):void{
    echo ($ok?'PASS: ':'FAIL: ').
        $name.
        PHP_EOL;

    if(!$ok){
        $fail[]=$name;
    }
};

$routes=file_get_contents(
    $root.'/public/index.php'
);

$controller=file_get_contents(
    $root.'/app/Controllers/BattleController.php'
);

$service=file_get_contents(
    $root.'/app/Services/BattleService.php'
);

$public=file_get_contents(
    $root.'/app/Views/battles/show.php'
);

$seller=file_get_contents(
    $root.'/app/Views/seller/battles.php'
);

$admin=file_get_contents(
    $root.'/app/Views/admin/battle.php'
);

$nav=file_get_contents(
    $root.'/app/Views/partials/dashboard_nav.php'
);

$check(
    str_contains(
        $routes,
        "'/battle-of-the-designers'"
    ) &&
    str_contains(
        $routes,
        "'/battle-of-the-designers/{slug}'"
    ) &&
    str_contains(
        $routes,
        "'/battle-of-the-designers/{slug}/matchups/{matchupId}/vote'"
    ),
    'Battle public bracket and matchup vote routes are registered'
);

$check(
    str_contains(
        $routes,
        "'/seller/battle-of-the-designers'"
    ) &&
    str_contains(
        $seller,
        'Your store logo'
    ),
    'seller Battle signup route requires an uploaded logo'
);

$check(
    str_contains(
        $controller,
        'H::requireLogin();'
    ) &&
    str_contains(
        $controller,
        "H::verifyCsrf();"
    ) &&
    str_contains(
        $controller,
        "REMOTE_ADDR"
    ) &&
    str_contains(
        $controller,
        "HTTP_USER_AGENT"
    ),
    'Battle voting requires login, CSRF, and records request audit signals'
);

$check(
    str_contains(
        $service,
        'battle_vote_one_per_matchup_uq'
    ) ||
    str_contains(
        file_get_contents(
            $root.
            '/database/migrations/2026_10_26_battle_of_designers.sql'
        ),
        'battle_vote_one_per_matchup_uq'
    ),
    'one vote per user is enforced per matchup'
);

$check(
    str_contains(
        $service,
        "'fingerprint_burst'"
    ) &&
    str_contains(
        $service,
        "'ip_burst'"
    ),
    'Battle anti-abuse system tracks both IP and browser-pattern evidence'
);

$check(
    str_contains(
        $admin,
        'Close Signup &amp; Generate Randomized Bracket'
    ) &&
    str_contains(
        $admin,
        'Schedule Round'
    ) &&
    str_contains(
        $admin,
        'Round start — Eastern Time'
    ) &&
    str_contains(
        $admin,
        'Close Round &amp; Count Votes'
    ) &&
    str_contains(
        $controller,
        "action==='schedule_round'"
    ),
    'admin Battle UI manages bracket scheduling and rounds'
);

$check(!str_contains($admin,'value="resolve_tie"') && str_contains($admin,'battle_phase.php'), 'Admin shows automatic tie phases without a manual winner picker');

$check(
    str_contains(
        $admin,
        '24 hours exactly'
    ) &&
    !str_contains(
        $admin,
        'Voting duration per round'
    ),
    'admin Battle UI uses fixed 24-hour rounds'
);

$check(
    substr_count(
        $public,
        '$100 Creative Moth Advertising Credit'
    )>=1 &&
    substr_count(
        $seller,
        '$100 Creative Moth Advertising Credit'
    )>=1 &&
    substr_count(
        $admin,
        '$100 Creative Moth Advertising Credit'
    )>=1,
    'Battle UI consistently shows the locked $100 Advertising Credit prize'
);

$check(
    !str_contains(
        $service,
        'PrizeDonationService'
    ) &&
    !str_contains(
        $service,
        'PrizeCreditService'
    ) &&
    !str_contains(
        $admin,
        'Donated Prize'
    ) &&
    !str_contains(
        $admin,
        'Prize Credit'
    ),
    'Battle does not expose donated-prize or Prize Credit rewards'
);

$check(
    str_contains(
        $nav,
        'Battle of the Designers'
    ),
    'seller navigation exposes Battle of the Designers'
);


$check(
    str_contains(
        $public,
        'battle-head-to-head'
    ) &&
    str_contains(
        $public,
        'Previous Round Winners'
    ) &&
    str_contains(
        $public,
        'Visit Their Store'
    ),
    'Battle presents designers head-to-head and promotes previous-round winners'
);

$check(
    str_contains(
        $admin,
        'Registered Voter Audit'
    ) &&
    str_contains(
        $admin,
        'Account created'
    ) &&
    str_contains(
        $admin,
        'IP address'
    ) &&
    str_contains(
        $admin,
        'Browser / device evidence'
    ),
    'admin Battle UI exposes registered voter audit evidence'
);


$sellerBattle=file_get_contents(
    dirname(__DIR__).
    '/app/Views/seller/battles.php'
);

$check(
    str_contains(
        $sellerBattle,
        'How Battle of the Designers Works'
    ) &&
    str_contains(
        $sellerBattle,
        'Designers Are Randomly Paired'
    ) &&
    str_contains(
        $sellerBattle,
        'Each Battle round lasts exactly'
    ) &&
    str_contains(
        $sellerBattle,
        'registered Creative Moth'
    ) &&
    str_contains(
        $sellerBattle,
        'Winners Advance'
    ) &&
    str_contains(
        $sellerBattle,
        '$100 Creative Moth Advertising Credit'
    ) &&
    str_contains(
        $sellerBattle,
        'promote your'
    ),
    'seller Battle signup explains the tournament and advertising-credit prize'
);

exit($fail?1:0);
