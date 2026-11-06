<?php

require __DIR__.'/../app/bootstrap.php';

use App\Core\Database as DB;
use App\Services\SellerTrafficReportService as Traffic;

$checks=0;

$check=static function(
    bool $condition,
    string $description
) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: '.$description);
    }

    $checks++;
    echo "PASS: $description\n";
};

$end=gmdate('Y-m-d');
$start=gmdate('Y-m-d',strtotime('-29 days'));

foreach ([
    [0,$start,$end],
    [-1,$start,$end],
    [1,'invalid',$end],
    [1,'2026-02-30',$end],
    [1,'2026-12-31','2026-01-01'],
    [1,'2020-01-01',$end],
] as $case) {
    $rejected=false;

    try {
        Traffic::forUser(...$case);
    } catch (\DomainException $e) {
        $rejected=true;
    }

    $check($rejected,'Invalid access/date request rejected');
}

$sellers=DB::rows(
    'SELECT id,user_id,store_slug
     FROM designers
     WHERE status="approved"
       AND user_id IS NOT NULL
       AND store_slug IS NOT NULL
       AND store_slug<>""
     ORDER BY id
     LIMIT 12'
);

$check(count($sellers)>0,'Approved seller available for tests');

$totalReports=0;

foreach ($sellers as $seller) {
    $sellerId=(int)$seller['id'];
    $userId=(int)$seller['user_id'];

    $report=Traffic::forUser($userId,$start,$end);

    $metrics=[
        'store_views','store_unique',
        'product_views','product_unique'
    ];

    foreach ($metrics as $metric) {
        $check(
            isset($report[$metric])
            && is_int($report[$metric])
            && $report[$metric]>=0,
            'Valid nonnegative '.$metric
        );
    }

    $check(
        $report['store_unique'] <= $report['store_views'],
        'Store unique visitors do not exceed store views'
    );

    $check(
        $report['product_unique'] <= $report['product_views'],
        'Product unique visitors do not exceed product views'
    );

    $expected=DB::row(
        'SELECT COUNT(*) AS views
         FROM analytics_page_views
         WHERE visit_date BETWEEN ? AND ?
           AND path=?',
        [$start,$end,'/store/'.$seller['store_slug']]
    );

    $check(
        $report['store_views']===(int)$expected['views'],
        'Store view total matches authoritative records'
    );

    $ids=array_map(
        'intval',
        array_column($report['products'],'id')
    );

    if ($ids) {
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $parameters=array_merge([$sellerId],$ids);

        $owned=DB::row(
            'SELECT COUNT(*) AS total
             FROM products
             WHERE designer_id=?
               AND id IN ('.$marks.')',
            $parameters
        );

        $check(
            (int)$owned['total']===count($ids),
            'Every reported product belongs to its seller'
        );
    } else {
        $check(true,'Empty product report exposes no products');
    }

    $check(
        count($report['products'])<=15,
        'Most-viewed product limit enforced'
    );

    $check(
        count($report['days'])<=31,
        'Daily traffic report limit enforced'
    );

    foreach ($report['days'] as $day) {
        $check(
            $day['day'] >= $start &&
            $day['day'] <= $end,
            'Daily result within selected dates'
        );
    }

    $totalReports++;
}

$service=file_get_contents(
    __DIR__.'/../app/Services/SellerTrafficReportService.php'
);

$view=file_get_contents(
    __DIR__.'/../app/Views/seller/analytics.php'
);

$check(
    str_contains($service,'WHERE user_id=? AND status="approved"'),
    'Seller identity checked on every report'
);

$check(
    str_contains($view,'SellerTrafficReportService::forUser'),
    'Dashboard calls authenticated seller report'
);

$check(
    str_contains($view,'Shop &amp; Product Traffic'),
    'Traffic dashboard section installed'
);

$check(
    !str_contains($view,'visitor_hash'),
    'Dashboard does not expose visitor identifiers'
);

echo "\nPASS: $checks seller traffic checks\n";
echo "Approved seller reports verified: $totalReports\n";
echo "Only aggregate counts shown; no personal data printed.\n";
