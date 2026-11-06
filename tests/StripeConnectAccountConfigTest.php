<?php

require __DIR__.'/../app/bootstrap.php';

use App\Services\StripeService;

$source = file_get_contents(
    __DIR__.'/../app/Services/StripeService.php'
);

$start = strpos(
    $source,
    'public static function createConnectedAccount('
);

$end = strpos(
    $source,
    'public static function createAccountLink(',
    $start
);

if ($start === false || $end === false) {
    throw new RuntimeException(
        'Stripe Connect method unavailable.'
    );
}

$method = substr($source,$start,$end-$start);

$checks = [
    'Express account type retained' =>
        str_contains($method,"'type' => 'express'"),

    'Country passed from seller' =>
        str_contains($method,"'country' => \$country"),

    'Card payments capability requested' =>
        str_contains(
            $method,
            "'card_payments' => ['requested' => 'true']"
        ),

    'Transfers capability requested' =>
        str_contains(
            $method,
            "'transfers' => ['requested' => 'true']"
        ),

    'Full service agreement specified' =>
        str_contains(
            $method,
            "'service_agreement' => 'full'"
        ),

    'Stripe Accounts API used' =>
        str_contains($method,"'/v1/accounts'"),

    'Existing designer identity retained' =>
        str_contains(
            $method,
            "'designer_id' => (string)\$designer['id']"
        ),

    'Existing seller user identity retained' =>
        str_contains(
            $method,
            "'user_id' => (string)\$user['id']"
        ),

    'Country GB recognized' =>
        StripeService::normalizeCountryCode('GB') === 'GB',

    'United Kingdom normalized correctly' =>
        StripeService::normalizeCountryCode(
            'United Kingdom'
        ) === 'GB',

    'US still recognized' =>
        StripeService::normalizeCountryCode('US') === 'US',
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        throw new RuntimeException(
            'FAIL: '.$label
        );
    }
}

echo 'PASS: '.count($checks).
    " Stripe Connect configuration checks\n";

echo "No Stripe API account creation attempted.\n";
