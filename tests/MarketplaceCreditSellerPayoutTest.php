<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CreditService;
use App\Services\MarketplaceFeeService;

$fees = new MarketplaceFeeService(900, 30);
$failures = 0;
$check = function ($ok, $name) use (&$failures) {
    echo ($ok ? "PASS " : "FAIL ") . $name . PHP_EOL;
    if (!$ok) $failures++;
};

foreach ([
    [595, 150, 445, 70, 375],
    [555, 150, 405, 66, 339],
    [1000, 0, 1000, 120, 880],
    [1000, 1100, 0, 0, 0]
] as [$gross, $credit, $basis, $fee, $seller]) {
    $applied = CreditService::allocateMerchandiseCredit(
        [0 => $gross], $credit
    )[0];
    $result = $fees->calculate([
        ['id' => 1, 'seller_id' => 1, 'gross_cents' => $gross - $applied]
    ])[1];
    $check(
        $gross - $applied === $basis
        && $result['fee_cents'] === $fee
        && $result['seller_earnings_cents'] === $seller,
        "seller payout on $gross cents with $credit credit"
    );
}

$split = CreditService::allocateMerchandiseCredit(
    [0 => 500, 1 => 500], 151
);
$check($split === [0 => 76, 1 => 75], 'multiple sellers allocate credit exactly');

$check(
    array_sum(CreditService::allocateMerchandiseCredit(
        [0 => 300, 1 => 0], 500
    )) === 300,
    'credit above merchandise does not reduce sellers beyond merchandise'
);

exit($failures ? 1 : 0);
