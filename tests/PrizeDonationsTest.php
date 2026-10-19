<?php

$root=dirname(__DIR__);
$failures=[];

$check=function(
    bool $ok,
    string $message
)use(&$failures):void{
    echo ($ok?'PASS: ':'FAIL: ').$message.PHP_EOL;

    if(!$ok){
        $failures[]=$message;
    }
};

$service=file_get_contents(
    "$root/app/Services/PrizeDonationService.php"
);

$controller=file_get_contents(
    "$root/app/Controllers/PrizeDonationController.php"
);

$routes=file_get_contents(
    "$root/public/index.php"
);

$permissions=file_get_contents(
    "$root/app/Services/AdminPermissionService.php"
);

$sellerView=file_get_contents(
    "$root/app/Views/seller/prize_donations.php"
);

$sellerText=preg_replace(
    '/\\s+/',
    ' ',
    strip_tags($sellerView)
);

$sellerNav=file_get_contents(
    "$root/app/Views/partials/dashboard_nav.php"
);

$adminView=file_get_contents(
    "$root/app/Views/admin/prize_donations.php"
);

$merge=file_get_contents(
    "$root/app/Services/AccountMergeService.php"
);

$check(
    str_contains($service,"'finished_product'") &&
    str_contains($service,"'bundle'") &&
    str_contains($service,"'custom_design'") &&
    str_contains($service,"'store_credit'") &&
    str_contains($service,"'exclusive'") &&
    str_contains($service,"'service'") &&
    str_contains($service,"'other'"),
    'all approved seller prize types are supported'
);

$check(
    str_contains($service,"'prize_wheel'") &&
    str_contains($service,"'giveaway'") &&
    str_contains($service,"'social_game'") &&
    str_contains($service,"'battle'"),
    'approved Grand Opening usage designations are supported'
);

$check(
    str_contains($service,'for update') &&
    str_contains($service,'quantity_remaining=quantity_remaining-?') &&
    str_contains($service,'idempotency_key'),
    'assignment uses row locking, finite inventory, and idempotency'
);

$check(
    str_contains($routes,"/seller/prize-donations") &&
    str_contains($routes,"/admin/prize-donations") &&
    str_contains($routes,"/admin/prize-assignments/{id}/award") &&
    str_contains($routes,"/admin/prize-assignments/{id}/fulfill"),
    'seller and admin prize routes are registered'
);

$check(
    str_contains($permissions,"'prizes.view'") &&
    str_contains($permissions,"'prizes.manage'"),
    'Prize Donations has granular admin permissions'
);

$check(
    str_contains($sellerView,'Submitting more than one prize?') &&
    str_contains($sellerView,'Please submit this form separately for each prize.') &&
    str_contains($sellerView,'Do not combine') &&
    str_contains($sellerView,'seller_authorized') &&
    str_contains($sellerView,'usage_designations[]'),
    'seller UI clearly requires a separate form for every individual prize'
);

$check(
    str_contains($sellerNav,"'Promotions / Ads'") &&
    str_contains($sellerNav,"'/seller/prize-donations'") &&
    str_contains($sellerNav,"'Prize Donations'"),
    'seller main navigation lists Prize Donations under Promotions / Ads'
);


$check(
    str_contains($sellerNav,"'Marketplace'") &&
    str_contains($sellerNav,"'/admin/prize-donations'") &&
    str_contains($sellerNav,"'Prize Donations'"),
    'admin main navigation lists Prize Donations under Marketplace'
);

$check(
    str_contains($adminView,'Prize Inventory') &&
    str_contains($adminView,'Assignments / Winners / Fulfillment') &&
    str_contains($adminView,'Return to Inventory'),
    'admin UI covers inventory, assignment, award, fulfillment, and release'
);

$check(
    str_contains($merge,"'prize_assignments' => 'winner_user_id'") &&
    str_contains($merge,'prize_donations.approved_by_admin_id'),
    'account merge knows Prize Donation user references'
);

exit($failures?1:0);
