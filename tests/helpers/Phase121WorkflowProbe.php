<?php

$environment = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'STRIPE_SECRET_KEY'] as $name) {
    if (($value = getenv($name)) !== false) $environment[$name] = $value;
}
require dirname(__DIR__, 2).'/app/bootstrap.php';
foreach ($environment as $name => $value) $_ENV[$name] = $value;

use App\Controllers\CartController;
use App\Controllers\SellerController;
use App\Services\StripeService;

$action = $argv[1] ?? '';
$userId = (int)($argv[2] ?? 0);
$targetId = (int)($argv[3] ?? 0);
$_SESSION['user'] = ['id' => $userId, 'role' => $action === 'seller-other-product' ? 'designer' : 'buyer'];
$_SERVER['REQUEST_METHOD'] = 'POST';

if ($action === 'add') {
    $_POST = ['license_type' => array_slice($argv, 4)];
    (new CartController())->add($targetId);
}

if ($action === 'resolve-cart') {
    $controller = new CartController();
    $items = (new ReflectionMethod($controller, 'items'))->invoke($controller);
    [$resolved, $subtotal] = (new ReflectionMethod($controller, 'totals'))->invoke($controller, $items);
    echo json_encode(['items' => $resolved, 'subtotal' => $subtotal], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'checkout') {
    StripeService::setTestTransport(static function (string $method, string $path, array $params): array {
        if ($path === '/v1/tax/calculations') {
            $total = array_sum(array_column($params['line_items'] ?? [], 'amount'));
            return ['id' => 'taxcalc_phase121', 'amount_total' => $total, 'tax_amount_exclusive' => 0, 'tax_amount_inclusive' => 0];
        }
        if ($path === '/v1/checkout/sessions') return ['id' => 'cs_phase121', 'url' => 'https://checkout.example.test/phase121'];
        throw new RuntimeException('Unexpected Stripe fixture request: '.$path);
    });
    $_POST = ['billing_line1' => '1 Test St', 'billing_city' => 'Austin', 'billing_state' => 'TX', 'billing_postal_code' => '78701', 'billing_country' => 'US', 'client_license_price' => $argv[4] ?? '9999.99'];
    (new CartController())->checkout();
}

if ($action === 'seller-other-product') {
    (new SellerController())->editProduct($targetId);
}

http_response_code(400);
