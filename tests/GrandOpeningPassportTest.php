<?php

$failures=0;

$check=function(bool $ok,string $label)use(&$failures):void{
    echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL;
    if(!$ok)$failures++;
};

$root=dirname(__DIR__);

$service=file_get_contents(
    $root.'/app/Services/GrandOpeningPassportService.php'
);

$controller=file_get_contents(
    $root.'/app/Controllers/PublicController.php'
);

$buyer=file_get_contents(
    $root.'/app/Controllers/BuyerController.php'
);

$view=file_get_contents(
    $root.'/app/Views/buyer/passport.php'
);

$migration=file_get_contents(
    $root.'/database/migrations/2026_10_27_grand_opening_passport.sql'
);

$check(
    substr_count($service,"'title' =>")===8,
    'Passport defines exactly 8 Grand Opening stamps'
);

$check(
    str_contains($service,'follow_3_stores') &&
    str_contains($service,'wishlist_item') &&
    str_contains($service,'battle_vote') &&
    str_contains($service,'purchase') &&
    str_contains($service,'refer_friend') &&
    str_contains($service,'visit_5_stores') &&
    str_contains($service,'leave_review'),
    'Passport includes every required activity'
);

$check(
    str_contains(
        $controller,
        'GrandOpeningPassportService'
    ),
    'public seller-store visits are tracked for Passport progress'
);

$check(
    str_contains(
        $service,
        'seller_reviews'
    ),
    'Passport uses the live seller review system'
);

$check(
    str_contains(
        $migration,
        "ENUM('prize_wheel','passport')"
    ) &&
    str_contains(
        $service,
        'source_type="passport"'
    ),
    'Passport giveaway entries are separate from Prize Wheel entries'
);

$check(
    str_contains(
        $buyer,
        'public function passport()'
    ) &&
    str_contains(
        $view,
        'Grand Opening Passport'
    ),
    'buyer dashboard exposes the Grand Opening Passport'
);

$check(
    str_contains(
        $service,
        'passport-complete'
    ) &&
    str_contains(
        $service,
        'insert ignore into grand_opening_giveaway_entries'
    ),
    'completing all Passport stamps awards one idempotent giveaway entry'
);


$merge=file_get_contents(
    $root.'/app/Services/AccountMergeService.php'
);

$schema=file_get_contents(
    $root.'/database/schema.sql'
);

$check(
    str_contains(
        $merge,
        'moveGrandOpeningPassport'
    ) &&
    str_contains(
        $merge,
        'grand_opening_passport_stamps'
    ) &&
    str_contains(
        $merge,
        'grand_opening_store_visits'
    ) &&
    str_contains(
        $merge,
        'grand_opening_giveaway_entries'
    ),
    'Passport records survive account merges safely'
);

$check(
    str_contains(
        $schema,
        'grand_opening_entry_source_unique'
    ) &&
    str_contains(
        $schema,
        "'prize_wheel','passport'"
    ),
    'canonical schema preserves Passport giveaway-entry uniqueness'
);

exit($failures?1:0);
