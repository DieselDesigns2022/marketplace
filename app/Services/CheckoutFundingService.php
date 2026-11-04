<?php
namespace App\Services;

/**
 * New-order funding calculations only.
 * Inputs are integer cents after coupon/sale discounts.
 * Prize Credit precedes Marketplace Credit.
 * Promotional Marketplace Credit covers merchandise only.
 * No database, network, or historical-ledger changes occur here.
 */
final class CheckoutFundingService
{
    private const MAX_CENTS = 1000000000;

    private static function amount(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > self::MAX_CENTS) {
            throw new \InvalidArgumentException('Invalid integer-cent funding amount.');
        }
        return $value;
    }

    public static function allocate(array $weights, int $amount): array
    {
        self::amount($amount);
        $total = 0;
        foreach ($weights as $weight) $total += self::amount($weight);
        self::amount($total);
        if ($amount > $total) {
            throw new \InvalidArgumentException('Allocation exceeds available merchandise.');
        }
        $result = array_fill_keys(array_keys($weights), 0);
        if ($amount === 0) return $result;
        $ranked = [];
        $used = 0;
        $position = 0;
        foreach ($weights as $key => $weight) {
            $numerator = $amount * $weight;
            $result[$key] = intdiv($numerator, $total);
            $used += $result[$key];
            $ranked[] = [
                'key'=>$key,'remainder'=>$numerator % $total,'position'=>$position++
            ];
        }
        usort($ranked, static fn($a,$b) =>
            $b['remainder'] <=> $a['remainder']
            ?: $a['position'] <=> $b['position']);
        for ($i=0; $i<$amount-$used; $i++) {
            $result[$ranked[$i]['key']]++;
        }
        return $result;
    }

    public static function calculate(
        array $items, int $taxCents, int $creditCents,
        int $basisPoints=900, int $fixedCents=30
    ): array {
        self::amount($taxCents);
        self::amount($creditCents);
        if ($basisPoints<0 || $basisPoints>10000 || $fixedCents<0) {
            throw new \InvalidArgumentException('Invalid fee configuration.');
        }
        $normalized = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $seller = $item['seller_id'] ?? null;
            if (!is_int($id) || $id<1 || isset($normalized[$id])
                || !is_int($seller) || $seller<1) {
                throw new \InvalidArgumentException('Invalid or duplicate item identity.');
            }
            $gross = self::amount($item['merchandise_cents'] ?? null);
            $prize = self::amount($item['prize_credit_cents'] ?? 0);
            if ($prize>$gross) {
                throw new \InvalidArgumentException('Prize Credit exceeds merchandise.');
            }
            $normalized[$id] = [
                'id'=>$id,'seller_id'=>$seller,
                'merchandise_cents'=>$gross,'prize_credit_cents'=>$prize,
                'post_prize_cents'=>$gross-$prize
            ];
        }
        ksort($normalized,SORT_NUMERIC);
        $weights = [];
        foreach ($normalized as $id=>$item) $weights[$id]=$item['post_prize_cents'];
        $merchandise = array_sum($weights);
        self::amount($merchandise+$taxCents);
        if ($creditCents>$merchandise) {
            throw new \InvalidArgumentException('Reserved promotional credit exceeds merchandise balance.');
        }
        $merchandiseCredit = min($creditCents,$merchandise);
        $taxCredit = 0;
        $allocation = self::allocate($weights,$merchandiseCredit);
        $feeInput = [];
        foreach ($normalized as $id=>$item) {
            $feeInput[] = [
                'id'=>$id,'seller_id'=>$item['seller_id'],
                'gross_cents'=>$item['post_prize_cents']-$allocation[$id]
            ];
        }
        $sellers = (new MarketplaceFeeService($basisPoints,$fixedCents))
            ->calculate($feeInput);
        foreach ($normalized as $id=>&$item) {
            $item['marketplace_credit_cents']=$allocation[$id];
            $item += $sellers[$item['seller_id']]['items'][$id];
        }
        unset($item);
        return [
            'version'=>1,
            'marketplace_credit_cents'=>$creditCents,
            'merchandise_credit_cents'=>$merchandiseCredit,
            'tax_credit_cents'=>$taxCredit,
            'tax_cents'=>$taxCents,
            'cash_tax_cents'=>$taxCents-$taxCredit,
            'cash_merchandise_cents'=>$merchandise-$merchandiseCredit,
            'cash_total_cents'=>$merchandise+$taxCents-$creditCents,
            'fee_cents'=>array_sum(array_column($sellers,'fee_cents')),
            'seller_payout_cents'=>array_sum(array_column($sellers,'seller_earnings_cents')),
            'items'=>$normalized,'sellers'=>$sellers
        ];
    }
}
