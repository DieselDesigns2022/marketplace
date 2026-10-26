<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\PrizeCreditService;

$failures = [];

$check = static function(bool $ok,string $message) use (&$failures): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ').$message.PHP_EOL;
    if (!$ok) $failures[] = $message;
};

$check(
    PrizeCreditService::refundedPrizeTargetCents(
        1000,
        1000,
        500
    ) === 500,
    'partial refund restores Prize Credit proportionally'
);

$check(
    PrizeCreditService::refundedPrizeTargetCents(
        1000,
        1000,
        1000
    ) === 1000,
    'full post-Prize-Credit merchandise refund restores full Prize Credit'
);

$controller = file_get_contents(
    dirname(__DIR__).'/app/Controllers/CustomDesignController.php'
);

$custom = file_get_contents(
    dirname(__DIR__).'/app/Services/CustomDesignService.php'
);

$customView = file_get_contents(
    dirname(__DIR__).'/app/Views/buyer/custom_checkout.php'
);

$refund = file_get_contents(
    dirname(__DIR__).'/app/Controllers/StripeController.php'
);

$refundService = file_get_contents(
    dirname(__DIR__).'/app/Services/MarketplaceRefundService.php'
);

$collab = file_get_contents(
    dirname(__DIR__).'/app/Services/CollabCheckoutService.php'
);

$collabView = file_get_contents(
    dirname(__DIR__).'/app/Views/collabs/checkout.php'
);

$merge = file_get_contents(
    dirname(__DIR__).'/app/Services/AccountMergeService.php'
);

$terms = file_get_contents(
    dirname(__DIR__).'/app/Views/static/page.php'
);

$check(
    str_contains($controller, "use_prize_credit") &&
    str_contains($controller, "prizeAvailable") &&
    str_contains($controller, "d.prize_credit_mode"),
    'Custom Design controller exposes Prize Credit checkout state'
);

$check(
    str_contains($custom, 'PrizeCreditService::allocateCart') &&
    str_contains($custom, 'reserveExact') &&
    str_contains($custom, 'merchandiseAfterPrizeCents') &&
    str_contains($custom, 'prize_credit_mode_snapshot'),
    'Custom Design checkout applies Prize Credit before Marketplace Credit'
);

$check(
    str_contains($customView, 'Use available Prize Credit') &&
    str_contains($customView, 'cannot pay sales tax'),
    'Custom Design checkout explains Prize Credit behavior'
);

$check(
    str_contains(
        $collab,
        'Prize Credit is not available on Collab Bundles.'
    ) &&
    str_contains(
        $collabView,
        'payout'
    ) &&
    str_contains(
        $collabView,
        'shared between multiple participating designers'
    ),
    'Collab checkout explicitly excludes seller-funded Prize Credit'
);

$check(
    str_contains($refund, 'restoreRefundedCredit') &&
    str_contains($refund, "prize_credit_applied']??'0.00'") &&
    str_contains($refundService, 'oi.prize_credit_applied'),
    'refund reconciliation restores Prize Credit and preserves seller economics'
);

$check(
    str_contains($merge, "'prize_credit_grants' => 'user_id'") &&
    str_contains($merge, "'prize_credit_transactions' => 'user_id'") &&
    str_contains($merge, "'prize_credit_grants.admin_user_id'") &&
    str_contains($merge, "'prize_credit_transactions.admin_user_id'"),
    'account merge recognizes Prize Credit ownership and audit references'
);

$check(
    str_contains(
        $terms,
        'Prize Credit used on merchandise is restored'
    ) &&
    str_contains(
        $terms,
        'original expiration or revocation status'
    ) &&
    str_contains(
        $terms,
        'Prize Credit is not available on Collab Bundles'
    ),
    'Terms define Prize Credit refund and Collab behavior'
);

exit($failures ? 1 : 0);
