<?php

namespace App\Services;

use DomainException;

/**
 * Quotes competing product discounts without changing stored prices.
 *
 * Percentage individual sales reduce the product and paid licenses.
 * Fixed individual sales reduce the product base price only.
 * Grand Opening and individual sales do not stack with each other.
 * An eligible coupon is applied separately by the existing checkout.
 */
final class ProductSaleQuoteService
{
    private static function cents(string $amount): int
    {
        return CreditService::parseCents($amount, false);
    }

    public static function quote(
        string $regularBase,
        array $licenses,
        int $grandOpeningPoints,
        ?array $individualSale
    ): array {
        if ($grandOpeningPoints < 0 || $grandOpeningPoints > 9999) {
            throw new DomainException('Invalid Grand Opening discount.');
        }

        $base = self::cents($regularBase);

        $licenseCents = [];

        foreach ($licenses as $license) {
            $licenseCents[] = self::cents(
                (string)($license['price'] ?? '0.00')
            );
        }

        $regularLine = $base + array_sum($licenseCents);

        $grandBase = GrandOpeningPricingService::component(
            $base,
            $grandOpeningPoints
        );

        $grandLicenses = [];

        foreach ($licenseCents as $amount) {
            $grandLicenses[] =
                GrandOpeningPricingService::component(
                    $amount,
                    $grandOpeningPoints
                );
        }

        $grandLine = $grandBase + array_sum($grandLicenses);

        $best = [
            'source' => $grandLine < $regularLine
                ? 'grand_opening'
                : 'none',
            'base_cents' => $grandBase,
            'license_cents' => $grandLicenses,
            'line_cents' => $grandLine,
            'regular_line_cents' => $regularLine,
            'savings_cents' => $regularLine - $grandLine,
            'grand_opening_points' => $grandOpeningPoints,
        ];

        if (!$individualSale) {
            return $best;
        }

        $type = (string)($individualSale['discount_type'] ?? '');
        $value = (string)($individualSale['discount_value'] ?? '');

        $individualBase = ProductSaleService::discountedBaseCents(
            $base,
            $type,
            $value
        );

        $individualLicenses = [];

        foreach ($licenseCents as $amount) {
            $individualLicenses[] = $type === 'percent'
                ? ProductSaleService::discountedBaseCents(
                    $amount,
                    $type,
                    $value
                )
                : $amount;
        }

        $individualLine =
            $individualBase + array_sum($individualLicenses);

        // Never increase the customer's total.
        // Equal discounts retain Grand Opening as the existing winner.
        if ($individualLine >= $best['line_cents']) {
            return $best;
        }

        return [
            'source' => 'individual',
            'base_cents' => $individualBase,
            'license_cents' => $individualLicenses,
            'line_cents' => $individualLine,
            'regular_line_cents' => $regularLine,
            'savings_cents' => $regularLine - $individualLine,
            'grand_opening_points' => 0,
            'individual_type' => $type,
            'individual_value' => $value,
        ];
    }

    /**
     * Database expression for an individual sale's base price.
     * Uses UTC dates so no cron job is needed to start or end sales.
     *
     * This is prepared for later integration with browse filters.
     * It is not currently used to change live prices.
     */
    public static function individualPriceSql(string $alias = 'p'): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $alias)) {
            throw new DomainException('Invalid product alias.');
        }

        $regular = $alias.'.price';

        $discounted = 'CASE
            WHEN ps.discount_type="percent" THEN
                GREATEST(
                    0.01,
                    '.$regular.' -
                    ROUND('.$regular.' * ps.discount_value / 100, 2)
                )
            WHEN ps.discount_type="fixed" THEN
                GREATEST(0.01,'.$regular.' - ps.discount_value)
            ELSE '.$regular.'
        END';

        return '(COALESCE((
            SELECT
                CASE
                    WHEN '.$regular.' > 0 THEN '.$discounted.'
                    ELSE 0
                END
            FROM product_sales ps
            JOIN designers sd ON sd.id=ps.designer_id
            WHERE ps.product_id='.$alias.'.id
              AND ps.designer_id='.$alias.'.designer_id
              AND ps.is_active=1
              AND ps.starts_at<=UTC_TIMESTAMP()
              AND ps.ends_at>UTC_TIMESTAMP()
              AND '.$alias.'.status="approved"
              AND sd.status="approved"
            LIMIT 1
        ),'.$regular.'))';
    }
}
