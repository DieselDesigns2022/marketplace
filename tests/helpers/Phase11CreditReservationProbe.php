<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database as DB;
use App\Services\CreditService;

// CLI installations can exclude environment variables from $_ENV. The parent
// integration test supplies the disposable fixture through the process
// environment, so copy those values after bootstrap has read any local .env.
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'] as $name) {
    $value = getenv($name);
    if ($value !== false) {
        $_ENV[$name] = $value;
    }
}

DB::begin();
try {
    $service=new CreditService();
    $result=match($argv[5]??'reserve'){
        'finalize'=>$service->finalizeReservation((int)$argv[1],(int)$argv[3],$argv[4]),
        'release'=>$service->releaseReservation((int)$argv[1],(int)$argv[3],$argv[4]),
        default=>$service->reserve((int)$argv[1],$argv[2],(int)$argv[3],$argv[4]),
    };
    DB::commit();
    echo is_bool($result)?($result?'true':'false'):$result;
} catch (Throwable $error) {
    if(DB::pdo()->inTransaction())DB::rollBack();
    echo 'ERROR:'.$error::class;
    exit(1);
}
