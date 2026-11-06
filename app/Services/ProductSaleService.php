<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;

final class ProductSaleService
{
    /**
     * Pure calculation. All amounts use integer cents.
     * Never changes the stored regular product price.
     */
    public static function discountedBaseCents(
        int $regularCents,
        string $type,
        string $value
    ): int {
        if ($regularCents < 0) {
            throw new DomainException('Invalid product price.');
        }

        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
            throw new DomainException('Invalid sale discount.');
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $units = (int)$whole * 100
            + (int)str_pad($fraction, 2, '0');

        if ($units <= 0) {
            throw new DomainException('Sale discount must be positive.');
        }

        if ($type === 'percent') {
            if ($units > 10000) {
                throw new DomainException('Percentage exceeds 100%.');
            }

            $savings = intdiv(
                $regularCents * $units + 5000,
                10000
            );
        } elseif ($type === 'fixed') {
            $savings = $units;
        } else {
            throw new DomainException('Invalid sale discount type.');
        }

        if ($regularCents === 0) {
            return 0;
        }

        // A paid product cannot become free through a sale.
        return max(1, $regularCents - $savings);
    }

    /**
     * Returns a sale only while its UTC window is active.
     */
    public static function activeForProduct(int $productId): ?array
    {
        if ($productId < 1) {
            return null;
        }

        return DB::row(
            'SELECT ps.*
             FROM product_sales ps
             JOIN products p
               ON p.id=ps.product_id
              AND p.designer_id=ps.designer_id
             JOIN designers d
               ON d.id=ps.designer_id
             WHERE ps.product_id=?
               AND ps.is_active=1
               AND ps.starts_at<=UTC_TIMESTAMP()
               AND ps.ends_at>UTC_TIMESTAMP()
               AND p.status="approved"
               AND d.status="approved"
             LIMIT 1',
            [$productId]
        );
    }
}
