<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';

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
