<?php

namespace App\Services;

use InvalidArgumentException;

final class CollabPayoutService
{
    public function estimate(int $priceCents, int $designers): array
    {
        if ($priceCents < 1 || $priceCents > CreditService::MAX_CENTS) {
            throw new InvalidArgumentException('Enter a valid positive sale price.');
        }
        if ($designers < 1 || $designers > 10000) {
            throw new InvalidArgumentException('Designer count must be between 1 and 10,000.');
        }
        $fee = MarketplaceFeeService::configured()->calculate([
            ['id'=>1, 'seller_id'=>1, 'gross_cents'=>$priceCents],
        ])[1];
        return $this->split($priceCents, (int)$fee['fee_cents'], range(1, $designers));
    }

    /** Stable ascending designer IDs receive remainder cents first. */
    public function distribute(int $cents, array $designerIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $designerIds)));
        sort($ids, SORT_NUMERIC);
        if (!$ids) throw new InvalidArgumentException('At least one eligible designer is required.');
        $cents = max(0, $cents);
        $base = intdiv($cents, count($ids));
        $remainder = $cents % count($ids);
        $allocations = [];
        foreach ($ids as $index => $id) $allocations[$id] = $base + ($index < $remainder ? 1 : 0);
        return $allocations;
    }

    public function split(int $grossCents, int $feeCents, array $designerIds): array
    {
        $fee = max(0, min($grossCents, $feeCents));
        $pool = $grossCents - $fee;
        $allocations = $this->distribute($pool, $designerIds);
        return [
            'gross_cents'=>$grossCents,
            'fee_cents'=>$fee,
            'pool_cents'=>$pool,
            'designer_count'=>count($allocations),
            'per_designer_cents'=>intdiv($pool, count($allocations)),
            'remainder_cents'=>$pool % count($allocations),
            'allocations'=>$allocations,
        ];
    }

    public function feeComponents(array $totalFeeShares, int $percentageCents, int $fixedCents): array
    {
        ksort($totalFeeShares, SORT_NUMERIC);
        if (array_sum($totalFeeShares) !== $percentageCents + $fixedCents) {
            throw new InvalidArgumentException('Fee components must equal the total fee shares.');
        }
        $fixedLeft = $fixedCents;
        $components = [];
        foreach ($totalFeeShares as $designerId => $total) {
            $fixed = min($total, $fixedLeft);
            $fixedLeft -= $fixed;
            $components[$designerId] = ['percentage_cents'=>$total-$fixed, 'fixed_cents'=>$fixed];
        }
        if ($fixedLeft !== 0) throw new InvalidArgumentException('Fixed fee could not be allocated safely.');
        return $components;
    }
}
