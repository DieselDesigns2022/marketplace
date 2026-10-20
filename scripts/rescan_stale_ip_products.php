#!/usr/bin/env php
<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database as DB;
use App\Services\OperationalErrorSanitizer;
use App\Services\ProductIpRiskWorkflow;

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
        'select
            p.id,
            p.title,
            d.user_id seller_user_id,
            s.review_status,
            sc.scanned_at
         from products p
         join designers d on d.id=p.designer_id
         left join product_ip_risk_states s on s.product_id=p.id
         left join product_ip_risk_scans sc on sc.id=s.latest_scan_id
         where p.status="approved"
           and (
                sc.id is null
                or sc.scanned_at < ?
           )
         order by p.id
         limit '.$limit,
        [$catalogChangedAt]
    );

    echo 'IP catalog changed at: '.$catalogChangedAt.PHP_EOL;
    echo 'Stale approved products found: '.count($rows).PHP_EOL;

    if ($dryRun) {
        foreach ($rows as $row) {
            echo '#'.$row['id'].' '.$row['title']
                .' | previous='.(($row['review_status'] ?? '') ?: 'NO STATE')
                .' | scanned='.(($row['scanned_at'] ?? '') ?: 'NEVER')
                .PHP_EOL;
        }
        exit(0);
    }

    $workflow = new ProductIpRiskWorkflow();
    $processed = 0;

    foreach ($rows as $row) {
        $workflow->scanProduct(
            (int)$row['id'],
            (int)$row['seller_user_id'],
            false
        );
        $processed++;
    }

    echo 'Products rescanned: '.$processed.PHP_EOL;
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'IP rescan worker failed: '.
        OperationalErrorSanitizer::sanitize($e->getMessage(), 300).
        PHP_EOL
    );
    exit(1);
}
