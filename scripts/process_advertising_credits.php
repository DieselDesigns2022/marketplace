<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\AdvertisingCreditService;
use App\Services\OperationalErrorSanitizer;

try {
    $limit=max(1,min(500,(int)($argv[1]??100)));
    $processed=(new AdvertisingCreditService())->processDue($limit);
    echo "Advertising Credit recurring grants processed: ".$processed.PHP_EOL;
} catch(Throwable $e){
    fwrite(
        STDERR,
        'Advertising Credit worker failed: '.
        OperationalErrorSanitizer::sanitize($e->getMessage(),300).
        PHP_EOL
    );
    exit(1);
}
