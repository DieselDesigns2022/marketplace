<?php
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\LicenseService;

$fail = 0;
$check = function (bool $ok, string $name) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL').": $name\n";
    if (!$ok) $fail++;
};

$digital = [
    'license_key' => LicenseService::DIGITAL_PRODUCT_KEY,
    'name' => 'Digital Product License',
    'description' => 'Finished digital product permission; original source file remains protected.',
    'price' => 12.50,
];
$personal = ['license_key' => 'personal', 'name' => 'Personal', 'description' => 'Personal use.', 'price' => 0.00];
$pod = ['license_key' => 'pod', 'name' => 'POD', 'description' => 'POD use.', 'price' => 3.25];

$check(LicenseService::DIGITAL_PRODUCT_KEY === 'digital-product', 'stable normalized Digital Product key');
$check(LicenseService::normalizeLicenseKeys(['pod', 'digital-product', 'pod']) === ['personal', 'pod', 'digital-product'], 'multi-license keys are normalized and de-duplicated with Personal included');
$check(LicenseService::priceTotal([$personal, $digital]) === 12.50, 'paid Digital Product add-on contributes authoritative price');
$free = $digital;
$free['price'] = 0.00;
$check(LicenseService::priceTotal([$personal, $free]) === 0.00, 'free Digital Product add-on remains zero');
$check(LicenseService::priceTotal([$personal, $digital, $pod]) === 15.75, 'multiple add-on prices aggregate correctly');
$snapshot = json_decode(LicenseService::snapshot([$personal, $digital, $pod]), true);
$check(array_column($snapshot, 'key') === ['personal', 'digital-product', 'pod'], 'snapshot preserves every normalized purchased permission');
$check($snapshot[1]['name'] === 'Digital Product License' && $snapshot[1]['description'] === $digital['description'] && $snapshot[1]['price'] === 12.5, 'snapshot preserves Digital Product name, description, and paid price');

$migration = file_get_contents(dirname(__DIR__).'/database/migrations/2026_08_10_phase_12_1_digital_product_license.sql');
$schema = file_get_contents(dirname(__DIR__).'/database/schema.sql');
$help = file_get_contents(dirname(__DIR__).'/app/Views/static/page.php');
$cart = file_get_contents(dirname(__DIR__).'/app/Controllers/CartController.php');
$seller = file_get_contents(dirname(__DIR__).'/app/Controllers/SellerController.php');
$check(str_contains($migration, "'digital-product','Digital Product License'") && str_contains($schema, "'digital-product','Digital Product License'"), 'migration and canonical schema seed the type');
foreach (['modified or incorporated', 'resell', 'redistribute', 'share', 'sublicense', 'give it away', 'extractable file', 'standalone digital file'] as $rule) {
    $check(str_contains(strtolower($migration), strtolower($rule)), "license text covers $rule restriction");
}
$check(str_contains($help, '<strong>Digital Product License:</strong>') && str_contains($help, 'original/source file'), 'Licensing Help provides focused buyer and seller guidance');
$check(str_contains($cart, 'LicenseService::purchasableLicenses') && str_contains($cart, 'LicenseService::priceTotal') && str_contains($cart, 'LicenseService::snapshot'), 'source contract retains calls to shared availability, pricing, and snapshot services');
$check(str_contains($seller, "select * from products where id=? and designer_id=?") && str_contains($seller, "where id=? and designer_id=?"), 'source contract retains ownership-scoped seller product queries');

exit($fail ? 1 : 0);
