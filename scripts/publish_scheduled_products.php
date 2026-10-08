<?php
require dirname(__DIR__).'/app/bootstrap.php';
try {
    echo json_encode((new \App\Services\ProductScheduleService())->processDue(), JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Scheduled product processing failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
