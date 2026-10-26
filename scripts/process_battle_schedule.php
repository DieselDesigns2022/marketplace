#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\BattleService;
use App\Services\OperationalErrorSanitizer;

try{
    $limit=max(
        1,
        min(
            200,
            (int)($argv[1]??50)
        )
    );

    $result=(new BattleService())
        ->processSchedule($limit);

    fwrite(
        STDOUT,
        json_encode(
            $result,
            JSON_UNESCAPED_SLASHES
        ).PHP_EOL
    );

    exit(
        empty($result['errors'])
            ? 0
            : 1
    );

}catch(Throwable $error){
    $safe=OperationalErrorSanitizer::sanitize(
        $error->getMessage(),
        300
    );

    fwrite(
        STDERR,
        'Battle schedule operational failure: '.
        $safe.
        PHP_EOL
    );

    exit(1);
}
