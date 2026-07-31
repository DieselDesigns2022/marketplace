<?php

// bootstrap.php loads the application's .env file into $_ENV. Preserve the
// disposable database and Stripe settings supplied by the parent integration
// test so this subprocess cannot silently reconnect to the developer/VPS
// database named in that file.
$processEnvironment = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'STRIPE_SECRET_KEY'] as $name) {
    $value = getenv($name);
    if ($value !== false) {
        $processEnvironment[$name] = $value;
    }
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';

foreach ($processEnvironment as $name => $value) {
    $_ENV[$name] = $value;
}

use App\Controllers\AdminCreditController;
use App\Services\StripeService;

$action = $argv[1] ?? '';
$userId = (int)($argv[2] ?? 0);
$role = $argv[3] ?? 'buyer';
$sessionToken = $argv[4] ?? '';
$submittedToken = $argv[5] ?? '';
$targetId = (int)($argv[6] ?? 0);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user'] = ['id'=>$userId, 'role'=>$role];
$_SESSION['_csrf'] = $sessionToken;
$_POST = ['_csrf'=>$submittedToken];

register_shutdown_function(static function (): void {
    echo "\nPROBE_STATUS:" . http_response_code() . "\n";
});

$controller = new AdminCreditController();
if ($action === 'settle') {
    StripeService::setTestTransport(static fn(): array => ['id'=>'tr_controller_fixture']);
    $controller->settlePlatformCreditPayout($targetId);
}
if ($action === 'adjust') {
    $_POST += ['user_id'=>$targetId, 'amount'=>$argv[7] ?? '', 'reason'=>$argv[8] ?? ''];
    $controller->adjust();
}

http_response_code(400);
