<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;

final class CollabCheckoutService
{
    public function create(int $collabId, int $buyerId, array $input, ?int $storefrontDesignerId = null): array
    {
        $lockName = sprintf('creative_moth_collab_checkout_%d_%d', $collabId, $buyerId);
        $lock = DB::row('select get_lock(?, 0) acquired', [$lockName]);
        if ((int)($lock['acquired'] ?? 0) !== 1) {
            throw new DomainException('A checkout for this collab is already in progress. Please try again.');
        }

        try {
            return $this->createLocked($collabId, $buyerId, $input, $storefrontDesignerId);
        } finally {
            DB::row('select release_lock(?) released', [$lockName]);
        }
    }

    private function createLocked(int $collabId, int $buyerId, array $input, ?int $storefrontDesignerId): array
    {
        $collab = DB::row('select c.*,d.display_name host_name from collab_events c join designers d on d.id=c.host_designer_id where c.id=?', [$collabId]);
        if (!$collab || !(new CollabService())->canSell($collab)) throw new DomainException('This collab is not available for purchase.');
        if ($this->hasBlockingPurchase($collabId, $buyerId)) {
            throw new DomainException('You already own this collab or have a checkout in progress.');
        }
        $eligible = DB::rows('select cp.designer_id,cp.qualifying_file_count from collab_participants cp where cp.collab_id=? and cp.eligibility="eligible" order by cp.designer_id', [$collabId]);
        if (count($eligible) !== (int)$collab['eligible_count']) throw new DomainException('The contributor snapshot is unavailable.');
        $eligibleIds = array_map('intval', array_column($eligible, 'designer_id'));
        if ($storefrontDesignerId !== null && !in_array($storefrontDesignerId, $eligibleIds, true)) $storefrontDesignerId = null;
        $subtotalCents = (int)$collab['price_cents'];
        $coupon = null; $discountCents = 0;
        $code = CouponService::normalizeCode((string)($input['coupon_code'] ?? ''));
        if ($code !== '') {
            $result = CouponService::validate($code, [['id'=>0,'designer_id'=>0,'line_total'=>CreditService::formatCents($subtotalCents)]], $buyerId);
            if (!$result['ok'] || ($result['coupon']['scope'] ?? '') !== 'platform') throw new DomainException($result['error'] ?? 'Only platform coupons apply to collab bundles.');
            $coupon = $result['coupon']; $discountCents = CreditService::parseCents((string)$result['discount'], false);
        }
        $merchandiseCents = max(0, $subtotalCents - $discountCents);
        $billing = StripeService::normalizeBillingAddress([
            'line1'=>trim((string)($input['billing_line1']??'')), 'line2'=>trim((string)($input['billing_line2']??'')),
            'city'=>trim((string)($input['billing_city']??'')), 'state'=>trim((string)($input['billing_state']??'')),
            'postal_code'=>trim((string)($input['billing_postal_code']??'')), 'country'=>'US',
        ]);
        $checkout = new CheckoutOrderService();
        $tax = $checkout->calculateTax([['id'=>$collabId,'total_price'=>CreditService::formatCents($merchandiseCents)]], $billing, 'collab-tax:'.$buyerId.':'.$collabId.':'.hash('sha256',json_encode($billing)));
        $available = (new CreditService())->balances($buyerId)['available'];
        $breakdown = CreditService::checkoutBreakdown(CreditService::formatCents($subtotalCents), CreditService::formatCents($discountCents), CreditService::formatCents((int)$tax['tax_cents']), $available, ($input['use_credits']??'')==='1');
        $fee = MarketplaceFeeService::configured()->calculate([['id'=>1,'seller_id'=>(int)$collab['host_designer_id'],'gross_cents'=>$merchandiseCents]])[(int)$collab['host_designer_id']];
        DB::begin();
        try {
            $collab = DB::row(
                'select c.*, d.display_name host_name
                   from collab_events c
                   join designers d on d.id = c.host_designer_id
                  where c.id = ?
                  for update',
                [$collabId]
            );
            if (!$collab || !(new CollabService())->canSell($collab)) {
                throw new DomainException('This collab is no longer available for purchase.');
            }

            $eligible = DB::rows(
                'select designer_id, qualifying_file_count
                   from collab_participants
                  where collab_id = ? and eligibility = "eligible"
                  order by designer_id',
                [$collabId]
            );
            if (empty($collab['snapshot_at'])
                || empty($collab['final_zip_path'])
                || count($eligible) !== (int)$collab['eligible_count']) {
                throw new DomainException('The contributor snapshot is unavailable.');
            }
            if ($this->hasBlockingPurchase($collabId, $buyerId)) {
                throw new DomainException('You already own this collab or have a checkout in progress.');
            }

            $orderId = $checkout->createOrder([
                'user_id'=>$buyerId, 'subtotal'=>CreditService::formatCents($subtotalCents), 'tax_amount'=>CreditService::formatCents((int)$tax['tax_cents']),
                'tax_snapshot'=>$tax['snapshot'], 'tax_calculation_id'=>$tax['id'], 'billing_snapshot'=>json_encode($billing,JSON_THROW_ON_ERROR),
                'credits'=>CreditService::formatCents($breakdown['credit_cents']), 'coupon_discount'=>CreditService::formatCents($discountCents),
                'coupon_id'=>$coupon['id']??null, 'coupon_code'=>$coupon['code']??null, 'coupon_snapshot'=>$coupon?json_encode($coupon,JSON_THROW_ON_ERROR):null,
                'total'=>CreditService::formatCents($breakdown['final_cents']), 'currency'=>StripeService::currency(), 'amount_cents'=>$breakdown['final_cents'],
                'commission_total'=>CreditService::formatCents((int)$fee['fee_cents']),
            ]);
            DB::exec('update orders set marketplace_fee_model="percentage_plus_fixed",marketplace_fee_basis_points=?,marketplace_fixed_fee_cents=? where id=?', [StripeService::commissionBasisPoints(),StripeService::commissionFixedCents(),$orderId]);
            if ($breakdown['credit_cents'] > 0) (new CreditService())->reserve($buyerId, CreditService::formatCents($breakdown['credit_cents']), $orderId, 'order:'.$orderId.':credit:reserve');
            $itemId = $checkout->addItem([
                'order_id'=>$orderId, 'collab_id'=>$collabId, 'title'=>$collab['title'], 'slug'=>$collab['slug'],
                'designer_id'=>$collab['host_designer_id'], 'seller_name'=>'Collab contributors', 'license_type'=>'collab_bundle',
                'license_name'=>'Collab Bundle License', 'license_description'=>'Contributor Terms & Conditions are included in the protected ZIP.',
                'fulfillment_type'=>'downloadable', 'unit_price'=>CreditService::formatCents($subtotalCents),
                'total_price'=>CreditService::formatCents($merchandiseCents), 'commission_rate'=>StripeService::commissionRate(),
            ]);
            DB::exec('update order_items set collab_storefront_designer_id=? where id=?', [$storefrontDesignerId,$itemId]);
            DB::exec('update order_items set coupon_id=?,coupon_code=?,coupon_discount=?,platform_commission_amount=?,marketplace_percentage_fee_amount=?,marketplace_fixed_fee_amount=?,seller_payout_amount=? where id=?', [
                $coupon['id']??null,$coupon['code']??null,CreditService::formatCents($discountCents),CreditService::formatCents((int)$fee['fee_cents']),
                CreditService::formatCents((int)$fee['percentage_fee_cents']),CreditService::formatCents((int)$fee['fixed_fee_cents']),CreditService::formatCents((int)$fee['seller_earnings_cents']),$itemId,
            ]);
            $order = DB::row('select * from orders where id=?', [$orderId]);
            if ($breakdown['final_cents'] === 0) {
                $finalizer = new OrderFinalizationService();
                $finalizer->finalize($orderId, 'internal-credit-order:'.$orderId, true);
                DB::commit();
                $finalizer->communicate($orderId);
                return ['order_id'=>$orderId,'url'=>'/dashboard/order/'.$orderId,'internal'=>true];
            }
            $session = $checkout->checkout($order, DB::rows('select * from order_items where order_id=?', [$orderId]));
            DB::exec('update orders set stripe_checkout_session_id=?,stripe_payment_status="pending" where id=?', [$session['id']??null,$orderId]);
            DB::commit();
            return ['order_id'=>$orderId,'url'=>$session['url'],'internal'=>false];
        } catch (\Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
    }

    private function hasBlockingPurchase(int $collabId, int $buyerId): bool
    {
        return (bool)DB::row(
            'select oi.id
               from order_items oi
               join orders o on o.id = oi.order_id
              where oi.collab_id = ?
                and o.user_id = ?
                and (
                    o.payment_status in ("pending", "captured_pending_finalization", "manual_review")
                    or (
                        o.payment_status in ("paid", "partially_refunded")
                        and coalesce((
                            select sum(a.merchandise_refund_cents)
                              from marketplace_refund_allocations a
                             where a.order_item_id = oi.id
                        ), 0) < round(oi.total_price * 100)
                    )
                )
              limit 1',
            [$collabId, $buyerId]
        );
    }
}
