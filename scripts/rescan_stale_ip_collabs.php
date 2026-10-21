#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database as DB;
use App\Services\CollabIpRiskWorkflow;
use App\Services\OperationalErrorSanitizer;

try {
    $dryRun = in_array('--dry-run', $argv, true);

    $limit = 50;
    foreach ($argv as $arg) {
        if (ctype_digit($arg)) {
            $limit = max(1, min(500, (int)$arg));
            break;
        }
    }

    $catalogChangedAt = DB::row(
        'select greatest(
            coalesce((select max(updated_at) from ip_risk_terms), "1970-01-01 00:00:00"),
            coalesce((select max(updated_at) from ip_risk_term_aliases), "1970-01-01 00:00:00"),
            coalesce((select max(updated_at) from ip_risk_context_rules), "1970-01-01 00:00:00")
        ) changed_at'
    )['changed_at'] ?? '1970-01-01 00:00:00';

    $rows = DB::rows(
        'select id,title,status,ip_risk_state,ip_risk_scanned_at
         from collab_events
         where status in ("collecting","processing","ready")
           and (
                ip_risk_scanned_at is null
                or ip_risk_scanned_at < ?
           )
         order by id
         limit '.$limit,
        [$catalogChangedAt]
    );

    echo 'IP catalog changed at: '.$catalogChangedAt.PHP_EOL;
    echo 'Stale active collabs found: '.count($rows).PHP_EOL;

    if ($dryRun) {
        foreach ($rows as $row) {
            echo '#'.$row['id'].' '.$row['title']
                .' | status='.$row['status']
                .' | ip='.$row['ip_risk_state']
                .' | scanned='.(($row['ip_risk_scanned_at'] ?? '') ?: 'NEVER')
                .PHP_EOL;
        }
        exit(0);
    }

    $workflow = new CollabIpRiskWorkflow();
    $processed = 0;

    foreach ($rows as $row) {
        $workflow->scan(
            (int)$row['id'],
            false,
            true
        );
        $processed++;
    }

    echo 'Collabs rescanned: '.$processed.PHP_EOL;
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'Collab IP rescan worker failed: '.
        OperationalErrorSanitizer::sanitize($e->getMessage(), 300).
        PHP_EOL
    );
    exit(1);
}
