#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\AccountMergeService;
use App\Services\AccountSecurityService;

$mode = $argv[1] ?? 'check';
if (!in_array($mode, ['check','execute'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php scripts/phase_13_1_merge_accounts.php check|execute\nPasswords are accepted interactively only.\n");
    exit(2);
}

$service = new AccountMergeService();
try {
    $plan = $service->preflight();
    if ($plan['completed'] ?? false) {
        echo "Phase 13.1 account consolidation is already complete; no changes were made.\n";
        exit(0);
    }
    echo "Preflight passed. Admin, Seller, approved designer, messaging, referral, and credit reconciliation are safe.\n";
    echo 'Source-owned row counts: '.json_encode($plan['counts'], JSON_UNESCAPED_SLASHES)."\n";
    if ($mode === 'check') {
        echo "Read-only check complete. Run with execute to perform the transaction.\n";
        exit(0);
    }

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false || !function_exists('posix_isatty') || !posix_isatty(STDIN)) {
        throw new RuntimeException('Secure no-echo password input is unavailable in this environment.');
    }
    $readSecret = static function (string $prompt): string {
        $output = [];
        $status = 1;
        exec('stty -echo 2>/dev/null', $output, $status);
        if ($status !== 0) throw new RuntimeException('Could not securely disable terminal echo.');
        $state = [];
        $stateStatus = 1;
        exec('stty -a 2>/dev/null', $state, $stateStatus);
        $echoDisabled = $stateStatus === 0 && preg_match('/(?:^|[ ;])-echo(?:[ ;]|$)/', implode(' ', $state)) === 1;
        if (!$echoDisabled) {
            exec('stty echo 2>/dev/null');
            throw new RuntimeException('Terminal echo suppression could not be verified.');
        }
        fwrite(STDOUT, $prompt);
        try { $value = fgets(STDIN); }
        finally { exec('stty echo 2>/dev/null'); fwrite(STDOUT, "\n"); }
        if ($value === false) throw new RuntimeException('Password input was not received.');
        return rtrim($value, "\r\n");
    };
    $password = $readSecret('New canonical password: ');
    $confirmation = $readSecret('Confirm new canonical password: ');
    if (!hash_equals($password, $confirmation)) throw new DomainException('Password confirmation does not match.');
    if (!AccountSecurityService::validPassword($password)) throw new DomainException('Password does not meet the application password policy.');
    $result = $service->merge($password);
    $password = $confirmation = '';
    echo 'Merge completed. Audit ID: '.(int)$result['audit_id'].".\n";
} catch (Throwable $e) {
    if (isset($password)) $password = '';
    if (isset($confirmation)) $confirmation = '';
    fwrite(STDERR, 'STOPPED: '.$e->getMessage()."\n");
    exit(1);
}
