<?php

namespace App\Services;

/**
 * Authoritative Phase 12.7 marketplace-fee calculator.
 *
 * Every input and output amount is an integer number of cents. Items are grouped
 * by seller before either fee component is calculated; allocations use stable
 * largest-remainder ordering (remainder, then item id).
 */
final class MarketplaceFeeService
{
    public const DEFAULT_BASIS_POINTS = 900;
    public const DEFAULT_FIXED_CENTS = 30;

    public function __construct(
        private readonly int $basisPoints = self::DEFAULT_BASIS_POINTS,
        private readonly int $fixedCents = self::DEFAULT_FIXED_CENTS,
    ) {}

    public static function configured(): self
    {
        return new self(StripeService::commissionBasisPoints(), StripeService::commissionFixedCents());
    }

    /** @param array<int,array{id:int|string,seller_id:int|string,gross_cents:int}> $items */
    public function calculate(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $seller = (int)$item['seller_id'];
            $id = (int)$item['id'];
            $gross = max(0, (int)$item['gross_cents']);
            $groups[$seller][] = ['id' => $id, 'gross_cents' => $gross];
        }
        ksort($groups, SORT_NUMERIC);

        $result = [];
        foreach ($groups as $seller => $sellerItems) {
            usort($sellerItems, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
            $gross = array_sum(array_column($sellerItems, 'gross_cents'));
            $percentage = $gross > 0 ? intdiv($gross * $this->basisPoints + 5000, 10000) : 0;
            $fixed = $gross > 0 ? max(0, $this->fixedCents) : 0;
            $uncapped = $percentage + $fixed;
            $fee = min($gross, $uncapped);
            // Preserve percentage first and cap the fixed component to the remaining gross.
            $percentage = min($percentage, $fee);
            $fixed = $fee - $percentage;
            $percentageAllocations = self::allocate($percentage, $sellerItems);
            $fixedAllocations = self::allocate($fixed, $sellerItems);
            $allocatedItems = [];
            foreach ($sellerItems as $item) {
                $id = $item['id'];
                $itemFee = $percentageAllocations[$id] + $fixedAllocations[$id];
                $allocatedItems[$id] = [
                    'gross_cents' => $item['gross_cents'],
                    'percentage_fee_cents' => $percentageAllocations[$id],
                    'fixed_fee_cents' => $fixedAllocations[$id],
                    'fee_cents' => $itemFee,
                    'seller_earnings_cents' => max(0, $item['gross_cents'] - $itemFee),
                ];
            }
            $result[$seller] = [
                'seller_id' => $seller,
                'gross_cents' => $gross,
                'basis_points' => $this->basisPoints,
                'percentage_fee_cents' => $percentage,
                'fixed_fee_cents' => $fixed,
                'fee_cents' => $fee,
                'seller_earnings_cents' => max(0, $gross - $fee),
                'capped' => $uncapped > $gross,
                'items' => $allocatedItems,
            ];
        }
        return $result;
    }

    public function recalculateRemaining(int $remainingGrossCents): array
    {
        $calculated = $this->calculate([['id' => 1, 'seller_id' => 1, 'gross_cents' => max(0, $remainingGrossCents)]]);
        return $calculated[1];
    }

    /** @param array<int,array{id:int,gross_cents:int}> $items @return array<int,int> */
    private static function allocate(int $amount, array $items): array
    {
        $result = array_fill_keys(array_column($items, 'id'), 0);
        $gross = array_sum(array_column($items, 'gross_cents'));
        if ($amount <= 0 || $gross <= 0) return $result;
        $ranked = [];
        $allocated = 0;
        foreach ($items as $item) {
            $numerator = $amount * $item['gross_cents'];
            $floor = intdiv($numerator, $gross);
            $result[$item['id']] = $floor;
            $allocated += $floor;
            $ranked[] = ['id' => $item['id'], 'remainder' => $numerator % $gross];
        }
        usort($ranked, static fn(array $a, array $b): int => $b['remainder'] <=> $a['remainder'] ?: $a['id'] <=> $b['id']);
        for ($left = $amount - $allocated, $i = 0; $i < $left; $i++) $result[$ranked[$i % count($ranked)]['id']]++;
        ksort($result, SORT_NUMERIC);
        return $result;
    }
}
