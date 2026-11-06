<?php

require __DIR__.'/../app/bootstrap.php';

use App\Services\ProductSaleQuoteService as Quote;
use App\Services\CreditService;

$checks = 0;

$check = static function(
    bool $condition,
    string $label
) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: '.$label);
    }

    $checks++;
};

$license = [
    ['price' => '5.00']
];

$none = Quote::quote('10.00',$license,0,null);

$check($none['source']==='none', 'No sale stays regular');
$check($none['line_cents']===1500, 'Regular total');

$grand = Quote::quote('10.00',$license,2000,null);

$check($grand['source']==='grand_opening','Grand Opening selected');
$check($grand['base_cents']===800,'Grand Opening base');
$check($grand['license_cents'][0]===400,'Grand Opening license');
$check($grand['line_cents']===1200,'Grand Opening total');

$individualPercent = [
    'discount_type' => 'percent',
    'discount_value' => '30.00'
];

$percent = Quote::quote(
    '10.00',
    $license,
    2000,
    $individualPercent
);

$check($percent['source']==='individual','Better individual sale wins');
$check($percent['base_cents']===700,'Percentage base');
$check($percent['license_cents'][0]===350,'Percentage license');
$check($percent['line_cents']===1050,'Percentage total');
$check($percent['savings_cents']===450,'Percentage savings');

$fixed = Quote::quote(
    '10.00',
    $license,
    2000,
    [
        'discount_type'=>'fixed',
        'discount_value'=>'4.00'
    ]
);

$check($fixed['source']==='individual','Fixed discount wins');
$check($fixed['base_cents']===600,'Fixed amount base');
$check($fixed['license_cents'][0]===500,'Fixed leaves license alone');
$check($fixed['line_cents']===1100,'Fixed total');

$smaller = Quote::quote(
    '10.00',
    $license,
    2000,
    [
        'discount_type'=>'fixed',
        'discount_value'=>'1.00'
    ]
);

$check($smaller['source']==='grand_opening','Grand Opening beats smaller sale');

$tie = Quote::quote(
    '10.00',
    $license,
    2000,
    [
        'discount_type'=>'percent',
        'discount_value'=>'20.00'
    ]
);

$check($tie['source']==='grand_opening','Tie preserves Grand Opening');

$free = Quote::quote(
    '0.00',
    [['price'=>'0.00']],
    2000,
    $individualPercent
);

$check($free['line_cents']===0,'Free products stay free');

$nearZero = Quote::quote(
    '0.01',
    [],
    9999,
    [
        'discount_type'=>'fixed',
        'discount_value'=>'20.00'
    ]
);

$check($nearZero['line_cents']===1,'Paid products stay at least one cent');

$oneHundredPercent = Quote::quote(
    '10.00',
    $license,
    0,
    [
        'discount_type'=>'percent',
        'discount_value'=>'100.00'
    ]
);

$check(
    $oneHundredPercent['line_cents']===2,
    '100% sale preserves one cent per paid component'
);

$noGrand = Quote::quote(
    '10.00',
    $license,
    0,
    [
        'discount_type'=>'fixed',
        'discount_value'=>'2.00'
    ]
);

$check($noGrand['line_cents']===1300,'Individual sale without event');

$negativeRejected = false;

try {
    Quote::quote(
        '10.00',
        [],
        0,
        [
            'discount_type'=>'fixed',
            'discount_value'=>'-1.00'
        ]
    );
} catch (Throwable $e) {
    $negativeRejected = true;
}

$check($negativeRejected,'Negative discount rejected');

$badAliasRejected = false;

try {
    Quote::individualPriceSql('p;DROP');
} catch (DomainException $e) {
    $badAliasRejected = true;
}

$check($badAliasRejected,'Unsafe SQL alias rejected');

$check(
    str_contains(
        Quote::individualPriceSql(),
        'UTC_TIMESTAMP()'
    ),
    'Automatic UTC schedule in SQL'
);

echo "PASS: $checks individual sale-pricing checks\n";
