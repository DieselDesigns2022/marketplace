<?php

namespace App\Services;

class TaxService
{
    public const SELLER_RESPONSIBILITY_COPY = 'I understand I am responsible for knowing whether I need to collect and remit sales tax for my store, unless Asset Moth is legally required to collect and remit tax as a marketplace facilitator.';

    public static function normalizeState(string $state): string
    {
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', trim($state)), 0, 2));
    }

    public static function calculate(array $items, array $discountAllocations = []): array
    {
        $totalTax = 0.0;
        $itemTaxes = [];
        foreach ($items as $idx => $item) {
            $enabled = (int)($item['sales_tax_enabled'] ?? 0) === 1;
            $state = self::normalizeState((string)($item['sales_tax_state'] ?? ''));
            $ratePercent = max(0, (float)($item['sales_tax_rate'] ?? 0));
            $valid = $enabled && preg_match('/^[A-Z]{2}$/', $state) && $ratePercent > 0 && $ratePercent <= 20.00;
            $rate = $valid ? round($ratePercent / 100, 6) : 0.0;
            $taxableBase = max(0, round((float)($item['line_total'] ?? $item['total_price'] ?? 0) - (float)($discountAllocations[$idx] ?? 0), 2));
            $amount = $valid ? round($taxableBase * $rate, 2) : 0.0;
            $itemTaxes[$idx] = [
                'enabled' => $enabled,
                'state' => $state,
                'registration_id' => trim((string)($item['sales_tax_registration_id'] ?? '')),
                'rate' => $rate,
                'rate_percent' => $ratePercent,
                'valid' => $valid,
                'taxable_amount' => $taxableBase,
                'amount' => $amount,
            ];
            $totalTax = round($totalTax + $amount, 2);
        }
        return ['tax' => $totalTax, 'items' => $itemTaxes];
    }
}
