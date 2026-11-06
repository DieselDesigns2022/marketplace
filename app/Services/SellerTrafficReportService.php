<?php

namespace App\Services;

use App\Core\Database as DB;
use DateTimeImmutable;
use DomainException;

final class SellerTrafficReportService
{
    /**
     * Returns aggregate traffic for the authenticated seller.
     * Raw visitor identifiers never leave this service.
     */
    public static function forUser(
        int $userId,
        string $from,
        string $to
    ): array {
        if ($userId < 1) {
            throw new DomainException('Login required.');
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $from
        );

        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $to
        );

        if (
            !$start ||
            !$end ||
            $start->format('Y-m-d') !== $from ||
            $end->format('Y-m-d') !== $to ||
            $start > $end ||
            $start->diff($end)->days > 366
        ) {
            throw new DomainException(
                'Invalid analytics date range.'
            );
        }

        $designer=DB::row(
            'SELECT id,store_slug
             FROM designers
             WHERE user_id=? AND status="approved"
             LIMIT 1',
            [$userId]
        );

        if (!$designer) {
            throw new DomainException(
                'Approved seller account required.'
            );
        }

        $designerId=(int)$designer['id'];
        $storePath='/store/'.$designer['store_slug'];

        $store=DB::row(
            'SELECT
                COUNT(*) page_views,
                COUNT(DISTINCT CONCAT(
                    visit_date,":",visitor_hash
                )) daily_unique_visitors
             FROM analytics_page_views
             WHERE visit_date BETWEEN ? AND ?
               AND path=?',
            [$from,$to,$storePath]
        ) ?: [];

        $productTotals=DB::row(
            'SELECT
                COUNT(*) page_views,
                COUNT(DISTINCT CONCAT(
                    v.visit_date,":",v.visitor_hash
                )) daily_unique_visitors
             FROM analytics_page_views v
             JOIN products p
               ON v.path=CONCAT("/product/",p.slug)
             WHERE p.designer_id=?
               AND v.visit_date BETWEEN ? AND ?',
            [$designerId,$from,$to]
        ) ?: [];

        $products=DB::rows(
            'SELECT
                p.id,
                p.title,
                p.slug,
                COUNT(v.id) page_views,
                COUNT(DISTINCT CONCAT(
                    v.visit_date,":",v.visitor_hash
                )) daily_unique_visitors
             FROM products p
             LEFT JOIN analytics_page_views v
               ON v.path=CONCAT("/product/",p.slug)
              AND v.visit_date BETWEEN ? AND ?
             WHERE p.designer_id=?
             GROUP BY p.id,p.title,p.slug
             ORDER BY page_views DESC,p.id DESC
             LIMIT 15',
            [$from,$to,$designerId]
        );

        $days=DB::rows(
            'SELECT
                v.visit_date day,
                COUNT(*) page_views,
                COUNT(DISTINCT v.visitor_hash)
                    daily_unique_visitors
             FROM analytics_page_views v
             WHERE v.visit_date BETWEEN ? AND ?
               AND (
                   v.path=?
                   OR EXISTS (
                       SELECT 1 FROM products p
                       WHERE p.designer_id=?
                         AND v.path=CONCAT(
                            "/product/",p.slug
                         )
                   )
               )
             GROUP BY v.visit_date
             ORDER BY v.visit_date DESC
             LIMIT 31',
            [$from,$to,$storePath,$designerId]
        );

        return [
            'store_views'=>(int)($store['page_views']??0),
            'store_unique'=>(int)(
                $store['daily_unique_visitors']??0
            ),
            'product_views'=>(int)(
                $productTotals['page_views']??0
            ),
            'product_unique'=>(int)(
                $productTotals['daily_unique_visitors']??0
            ),
            'products'=>$products,
            'days'=>$days,
        ];
    }
}
