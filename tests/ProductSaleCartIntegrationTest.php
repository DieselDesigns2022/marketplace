<?php

$dsn = (string)getenv('CM_SALE_TEST_DSN');

if (
    PHP_SAPI !== 'cli' ||
    !preg_match(
        '/^mysql:host=localhost;dbname=cm_sale_test_[0-9]{14}$/D',
        $dsn
    )
) {
    throw new RuntimeException(
        'STOP: An isolated test database is required.'
    );
}

/*
 * Run the existing financial integration suite first.
 * This creates its own fake buyers, products, licenses,
 * coupons and Grand Opening event in the test database.
 */
require __DIR__.'/GrandOpeningPricingIntegrationTest.php';

use App\Core\Database as DB;
use App\Services\GrandOpeningPricingService as Pricing;
use App\Services\CreditService;

echo "\n=== INDIVIDUAL PRODUCT SALE CART TESTS ===\n";

$checks = 0;

$checkSale = static function(
    bool $ok,
    string $description
) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException(
            'FAIL: '.$description
        );
    }

    $checks++;
    echo "PASS: ".$description."\n";
};

$feature = new ReflectionMethod(
    Pricing::class,
    'individualSalesEnabled'
);

$checkSale(
    $feature->invoke(null) === false,
    'Individual discounts OFF by default'
);

/*
 * Restore a known Grand Opening state after the original
 * regression suite, without touching any live records.
 */
DB::exec(
    'UPDATE designers SET status="approved" WHERE id=1'
);

DB::exec(
    'UPDATE products
     SET status="approved",price=10.00,
         commercial_license_price=5.00
     WHERE id=1'
);

DB::exec(
    'UPDATE grand_opening_events
     SET is_enabled=1,
         starts_at=UTC_TIMESTAMP()-INTERVAL 1 DAY,
         ends_at=UTC_TIMESTAMP()+INTERVAL 7 DAY
     WHERE id=1'
);

DB::exec(
    'UPDATE grand_opening_sales
     SET is_participating=1,
         discount_basis_points=2000,
         product_scope="shop"
     WHERE event_id=1 AND designer_id=1'
);

DB::exec(
    'INSERT INTO product_sales
       (product_id,designer_id,discount_type,
        discount_value,starts_at,ends_at,is_active)
     VALUES
       (1,1,"percent",30.00,
        UTC_TIMESTAMP()-INTERVAL 1 HOUR,
        UTC_TIMESTAMP()+INTERVAL 1 DAY,1)'
);

$input = [
    [
        'id' => 1,
        'license_type' => 'personal,commercial'
    ]
];

$engine = new Pricing();

$before = $engine->cart($input);

$checkSale(
    $before['subtotal_cents'] === 1200,
    'Live-disabled price remains Grand Opening $12.00'
);

putenv('CM_INDIVIDUAL_SALES_TEST=1');

$checkSale(
    $feature->invoke(null) === true,
    'Individual pricing enabled only for isolated test'
);

$percent = $engine->cart($input);

$checkSale(
    $percent['subtotal_cents'] === 1050,
    '30% individual sale beats 20% Grand Opening'
);

$checkSale(
    (string)$percent['items'][0]['price'] === '7.00',
    'Product base price becomes $7.00'
);

$checkSale(
    (float)$percent['items'][0]['license_price'] === 3.50,
    'Paid commercial license becomes $3.50'
);

$checkSale(
    ($percent['items'][0]['go_sale_source'] ?? '') === 'individual',
    'Individual discount correctly identified as winner'
);

$checkSale(
    $percent['sale_savings_cents'] === 450,
    'Customer saves $4.50 against regular $15.00'
);

$withCoupon = $engine->cart($input,'HIGH',1);

$checkSale(
    !empty($withCoupon['coupon_result']['ok']),
    'Eligible coupon remains valid on sale'
);

$checkSale(
    $withCoupon['subtotal_cents'] === 1050,
    'Sale price remains $10.50 before coupon'
);

$checkSale(
    abs(
        (float)$withCoupon['coupon_result']['discount']
        - 3.15
    ) < 0.001,
    '30% coupon adds $3.15 savings'
);

$couponCents = CreditService::parseCents(
    (string)$withCoupon['coupon_result']['discount'],
    false
);

$checkSale(
    $withCoupon['subtotal_cents'] - $couponCents === 735,
    'Sale plus coupon totals $7.35 before tax'
);

DB::exec(
    'UPDATE product_sales
     SET discount_type="fixed",
         discount_value=4.00
     WHERE product_id=1'
);

$fixed = $engine->cart($input);

$checkSale(
    $fixed['subtotal_cents'] === 1100,
    '$4 fixed discount gives $11.00 total'
);

$checkSale(
    (string)$fixed['items'][0]['price'] === '6.00'
    && (float)$fixed['items'][0]['license_price'] === 5.00,
    'Fixed discount leaves paid license at regular price'
);

DB::exec(
    'UPDATE product_sales
     SET discount_value=1.00
     WHERE product_id=1'
);

$weaker = $engine->cart($input);

$checkSale(
    $weaker['subtotal_cents'] === 1200,
    'Better Grand Opening discount wins'
);

DB::exec(
    'UPDATE product_sales
     SET discount_type="percent",
         discount_value=30.00,
         starts_at=UTC_TIMESTAMP()+INTERVAL 1 DAY,
         ends_at=UTC_TIMESTAMP()+INTERVAL 2 DAY
     WHERE product_id=1'
);

$future = $engine->cart($input);

$checkSale(
    $future['subtotal_cents'] === 1200,
    'Future individual sale does not begin early'
);

DB::exec(
    'UPDATE product_sales
     SET starts_at=UTC_TIMESTAMP()-INTERVAL 2 DAY,
         ends_at=UTC_TIMESTAMP()-INTERVAL 1 DAY
     WHERE product_id=1'
);

$expired = $engine->cart($input);

$checkSale(
    $expired['subtotal_cents'] === 1200,
    'Expired individual sale stops automatically'
);

DB::exec(
    'UPDATE product_sales
     SET starts_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR,
         ends_at=UTC_TIMESTAMP()+INTERVAL 1 DAY,
         is_active=0
     WHERE product_id=1'
);

$disabled = $engine->cart($input);

$checkSale(
    $disabled['subtotal_cents'] === 1200,
    'Disabled individual sale has no effect'
);

DB::exec(
    'UPDATE product_sales
     SET is_active=1
     WHERE product_id=1'
);

$quote = $engine->cart($input);

$sql = DB::row(
    'SELECT '.Pricing::priceSql('p').' AS amount
     FROM products p WHERE p.id=1'
);

$checkSale(
    abs((float)$sql['amount'] - 7.00) < 0.001,
    'Browse SQL matches winning product base price'
);

$checkSale(
    (string)$quote['items'][0]['price'] === '7.00',
    'Cart matches browse product base price'
);

putenv('CM_INDIVIDUAL_SALES_TEST=0');

$checkSale(
    $feature->invoke(null) === false,
    'Individual discounts return to OFF after test'
);

$again = $engine->cart($input);

$checkSale(
    $again['subtotal_cents'] === 1200,
    'Original Grand Opening behavior restored'
);

echo "\nPASS: $checks additional integrated cart checks\n";
echo "No customer products, carts, orders or charges touched.\n";
