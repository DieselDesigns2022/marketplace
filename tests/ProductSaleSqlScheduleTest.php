<?php

require __DIR__.'/../app/bootstrap.php';

use App\Services\ProductSaleQuoteService as Quote;
use App\Services\GrandOpeningPricingService as Opening;

$dsn=getenv('CM_SALE_TEST_DSN');

if (
    !$dsn ||
    !preg_match(
        '/^mysql:host=localhost;dbname=cm_sale_test_[a-z0-9_]+$/D',
        $dsn
    )
) {
    throw new RuntimeException(
        'This test requires an isolated cm_sale_test database.'
    );
}

$pdo=new PDO(
    $dsn,
    'root',
    '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);

$pdo->exec('CREATE TEMPORARY TABLE designers (
    id BIGINT PRIMARY KEY,
    status VARCHAR(30) NOT NULL
)');

$pdo->exec('CREATE TEMPORARY TABLE product_sales (
    product_id BIGINT PRIMARY KEY,
    designer_id BIGINT NOT NULL,
    discount_type VARCHAR(20) NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    is_active TINYINT NOT NULL
)');

$pdo->exec('CREATE TEMPORARY TABLE grand_opening_events (
    id BIGINT PRIMARY KEY,
    is_enabled TINYINT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL
)');

$pdo->exec('CREATE TEMPORARY TABLE grand_opening_sales (
    event_id BIGINT NOT NULL,
    designer_id BIGINT NOT NULL,
    discount_basis_points INT NOT NULL,
    is_participating TINYINT NOT NULL,
    product_scope VARCHAR(20) NOT NULL
)');

$pdo->exec('CREATE TEMPORARY TABLE grand_opening_sale_products (
    event_id BIGINT,
    designer_id BIGINT,
    product_id BIGINT
)');

$pdo->exec(
    "INSERT INTO designers VALUES (501,'approved')"
);

$pdo->exec(
    "INSERT INTO grand_opening_events VALUES
     (1,0,UTC_TIMESTAMP()-INTERVAL 1 DAY,
          UTC_TIMESTAMP()+INTERVAL 1 DAY)"
);

$pdo->exec(
    "INSERT INTO product_sales VALUES
     (701,501,'percent',25.00,
      UTC_TIMESTAMP()-INTERVAL 1 HOUR,
      UTC_TIMESTAMP()+INTERVAL 1 HOUR,1)"
);

$expression=Quote::individualPriceSql('p');

$sql="SELECT ".$expression." AS sale_price
      FROM (
        SELECT 701 AS id,501 AS designer_id,
               20.00 AS price,'approved' AS status
      ) p";

$checks=0;

$assert=function(
    float $expected,
    string $label
) use ($pdo,$sql,&$checks):void {
    $actual=(float)$pdo->query($sql)->fetchColumn();

    if (abs($expected-$actual)>0.001) {
        throw new RuntimeException(
            "FAIL: $label expected $expected, got $actual"
        );
    }

    $checks++;
    echo "PASS: $label\n";
};

$assert(15.00,'Active 25% sale');

$pdo->exec(
    "UPDATE product_sales
     SET starts_at=UTC_TIMESTAMP()+INTERVAL 1 DAY"
);

$assert(20.00,'Future sale does not start early');

$pdo->exec(
    "UPDATE product_sales
     SET starts_at=UTC_TIMESTAMP()-INTERVAL 2 DAY,
         ends_at=UTC_TIMESTAMP()-INTERVAL 1 DAY"
);

$assert(20.00,'Expired sale ends automatically');

$pdo->exec(
    "UPDATE product_sales
     SET starts_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR,
         ends_at=UTC_TIMESTAMP()+INTERVAL 1 HOUR,
         is_active=0"
);

$assert(20.00,'Disabled sale is inactive');

$pdo->exec(
    "UPDATE product_sales
     SET is_active=1,
         discount_type='fixed',
         discount_value=6.00"
);

$assert(14.00,'Active fixed-dollar discount');

$pdo->exec(
    "UPDATE designers SET status='disabled' WHERE id=501"
);

$assert(20.00,'Unapproved seller cannot offer sale');

$pdo->exec(
    "UPDATE designers SET status='approved' WHERE id=501"
);

$pdo->exec(
    "UPDATE product_sales SET designer_id=502"
);

$assert(20.00,'Sale seller ownership is enforced');

$pdo->exec(
    "UPDATE product_sales SET designer_id=501"
);

$pdo->exec(
    "UPDATE product_sales SET discount_value=99.00"
);

$assert(0.01,'Fixed sale cannot make paid base free');

$pdo->exec(
    "UPDATE product_sales
     SET discount_type='percent',discount_value=100.00"
);

$assert(0.01,'100% discount retains minimum cent');

$pdo->exec(
    "UPDATE product_sales
     SET discount_type='percent',discount_value=25.00"
);

$grand=Opening::priceSql('p');

$grandSql="SELECT ".$grand." AS sale_price
      FROM (
        SELECT 701 AS id,501 AS designer_id,
               20.00 AS price,'approved' AS status
      ) p";

$actual=(float)$pdo->query($grandSql)->fetchColumn();

if (abs($actual-20.00)>0.001) {
    throw new RuntimeException(
        'Live pricing changed while individual sales are disabled.'
    );
}

$checks++;
echo "PASS: Live pricing ignores inactive individual-sale feature\n";

$reflection=new ReflectionMethod(
    Opening::class,
    'individualSalesEnabled'
);

if ($reflection->invoke(null)!==false) {
    throw new RuntimeException(
        'Individual sale feature switch unexpectedly enabled.'
    );
}

$checks++;
echo "PASS: Live feature switch remains OFF\n";

echo "PASS: $checks isolated sale schedule and SQL checks\n";
