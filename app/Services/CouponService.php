<?php

namespace App\Services;

use App\Core\Database as DB;

class CouponService
{
    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9_-]+/', '', strtoupper(trim($code))));
    }

    public static function eligibleSubtotal(array $coupon, array $items): float
    {
        $sellerIds = self::restrictionIds((int)$coupon['id'], 'seller');
        $productIds = self::restrictionIds((int)$coupon['id'], 'product');
        $categoryIds = self::restrictionIds((int)$coupon['id'], 'category');
        $sum = 0.0;
        foreach ($items as $item) {
            if (self::itemEligible($coupon, $item, $sellerIds, $productIds, $categoryIds)) {
                $sum += (float)($item['line_total'] ?? $item['total_price'] ?? 0);
            }
        }
        return round($sum, 2);
    }

    public static function validate(string $code, array $items, int $userId): array
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === '') return ['ok'=>false,'error'=>'Enter a coupon code.'];
        $coupon = DB::row('select * from coupons where code=? limit 1', [$normalized]);
        if (!$coupon) return ['ok'=>false,'error'=>'Coupon code was not found.'];
        if ((int)$coupon['is_active'] !== 1) return ['ok'=>false,'error'=>'Coupon is inactive.'];
        $today = date('Y-m-d');
        $starts = !empty($coupon['starts_at']) ? substr((string)$coupon['starts_at'],0,10) : null;
        $ends = !empty($coupon['ends_at']) ? substr((string)$coupon['ends_at'],0,10) : null;
        if ($starts && $ends && $ends < $starts) return ['ok'=>false,'error'=>'Coupon date window is invalid.'];
        if ($starts && $starts > $today) return ['ok'=>false,'error'=>'Coupon is not active yet.'];
        if ($ends && $ends < $today) return ['ok'=>false,'error'=>'Coupon has expired.'];
        if ($coupon['usage_limit'] !== null && $coupon['usage_limit'] !== '') {
            if ((int)$coupon['usage_limit'] < 1) return ['ok'=>false,'error'=>'Coupon usage limit is invalid.'];
            if ((int)$coupon['usage_count'] >= (int)$coupon['usage_limit']) return ['ok'=>false,'error'=>'Coupon usage limit has been reached.'];
        }
        $userUses = (int)(DB::row('select count(*) c from coupon_usages where coupon_id=? and user_id=?', [$coupon['id'],$userId])['c'] ?? 0);
        if ($coupon['per_user_limit'] !== null && $coupon['per_user_limit'] !== '') {
            if ((int)$coupon['per_user_limit'] < 1) return ['ok'=>false,'error'=>'Coupon per-user limit is invalid.'];
            if ($userUses >= (int)$coupon['per_user_limit']) return ['ok'=>false,'error'=>'You have already used this coupon the maximum number of times.'];
        }
        $eligible = self::eligibleSubtotal($coupon, $items);
        if ($eligible <= 0) return ['ok'=>false,'error'=>'Coupon does not apply to the items in your cart.'];
        if ((float)$coupon['min_cart_amount'] > 0 && $eligible < (float)$coupon['min_cart_amount']) return ['ok'=>false,'error'=>'Cart does not meet this coupon minimum.'];
        $discount = self::discountAmount($coupon, $eligible);
        if ($discount <= 0) return ['ok'=>false,'error'=>'Coupon does not reduce this cart.'];
        return ['ok'=>true,'coupon'=>$coupon,'eligible_subtotal'=>$eligible,'discount'=>$discount,'code'=>$normalized];
    }

    public static function discountAmount(array $coupon, float $eligibleSubtotal): float
    {
        if (($coupon['discount_type'] ?? '') === 'percent') $discount = $eligibleSubtotal * ((float)$coupon['discount_value'] / 100);
        else $discount = (float)$coupon['discount_value'];
        return round(max(0, min($eligibleSubtotal, $discount)), 2);
    }

    public static function allocateDiscount(array $coupon, array $items, float $discount): array
    {
        $sellerIds = self::restrictionIds((int)$coupon['id'], 'seller');
        $productIds = self::restrictionIds((int)$coupon['id'], 'product');
        $categoryIds = self::restrictionIds((int)$coupon['id'], 'category');
        $eligibleTotal = self::eligibleSubtotal($coupon, $items);
        $allocations = []; $remaining = $discount; $eligibleKeys = [];
        foreach ($items as $i => $item) if (self::itemEligible($coupon, $item, $sellerIds, $productIds, $categoryIds)) $eligibleKeys[] = $i;
        foreach ($eligibleKeys as $pos => $i) {
            $line = (float)($items[$i]['line_total'] ?? $items[$i]['total_price'] ?? 0);
            $amount = ($pos === count($eligibleKeys) - 1) ? $remaining : round($discount * ($line / max($eligibleTotal, 0.01)), 2);
            $amount = max(0, min($line, $amount));
            $allocations[$i] = $amount; $remaining = round($remaining - $amount, 2);
        }
        return $allocations;
    }

    public static function recordUsage(int $orderId): void
    {
        $order = DB::row('select * from orders where id=?', [$orderId]);
        if (!$order || empty($order['coupon_id']) || (float)($order['coupon_discount'] ?? 0) <= 0) return;
        DB::exec('insert ignore into coupon_usages (coupon_id,user_id,order_id,code_snapshot,discount_amount) values (?,?,?,?,?)', [$order['coupon_id'],$order['user_id'],$orderId,$order['coupon_code'],$order['coupon_discount']]);
        DB::exec('update coupons set usage_count=(select count(*) from coupon_usages where coupon_id=?) where id=?', [$order['coupon_id'],$order['coupon_id']]);
    }

    private static function restrictionIds(int $couponId, string $type): array
    {
        return array_map('intval', array_column(DB::rows('select restrictable_id from coupon_restrictions where coupon_id=? and restrictable_type=?', [$couponId,$type]), 'restrictable_id'));
    }

    private static function itemEligible(array $coupon, array $item, array $sellerIds, array $productIds, array $categoryIds): bool
    {
        if (($coupon['scope'] ?? '') === 'seller' && (int)($item['designer_id'] ?? 0) !== (int)$coupon['seller_id']) return false;
        if ($sellerIds && !in_array((int)($item['designer_id'] ?? 0), $sellerIds, true)) return false;
        if ($productIds && !in_array((int)($item['id'] ?? $item['product_id'] ?? 0), $productIds, true)) return false;
        if ($categoryIds && !in_array((int)($item['category_id'] ?? 0), $categoryIds, true)) return false;
        return true;
    }
}
