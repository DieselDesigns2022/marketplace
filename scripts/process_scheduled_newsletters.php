#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\EmailNewsletterSchedulerService;
use App\Services\OperationalErrorSanitizer;

try {
    $limit=
        isset($argv[1])
            ? (int)$argv[1]
            : 20;

    $result=
        EmailNewsletterSchedulerService::run(
            $limit
        );

    fwrite(
        STDOUT,
        json_encode(
            $result,
            JSON_UNESCAPED_SLASHES
        ).PHP_EOL
    );

    exit(0);
} catch(\Throwable $e) {
    fwrite(
        STDERR,
        'Newsletter scheduler failure: '.
        OperationalErrorSanitizer::sanitize(
            $e->getMessage(),
            300
        ).
        PHP_EOL
    );

    exit(1);
}
