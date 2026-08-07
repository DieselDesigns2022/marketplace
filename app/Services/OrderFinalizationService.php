<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use Throwable;

final class OrderFinalizationService
{
    public function __construct(private ?CreditService $credits = null, private ?ReferralService $referrals = null)
    {
        $this->credits ??= new CreditService();
        $this->referrals ??= new ReferralService($this->credits);
    }

    public function finalize(int $orderId, string $eventKey, bool $internal = false): bool
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $order = DB::row('select * from orders where id=? for update', [$orderId]);
            if (!$order) throw new DomainException('Order not found.');
            if (!empty($order['finalization_key'])) {
                if ($order['finalization_key'] !== $eventKey && ($order['payment_status'] ?? '') !== 'paid') {
                    throw new DomainException('Finalization key conflict.');
                }
                if ($ownsTransaction) DB::rollBack();
                return false;
            }
            if (!$internal && (($order['payment_status'] ?? '') !== 'captured_pending_finalization' || !empty($order['manual_review_required']))) {
                throw new DomainException('Only a verified, non-review payment may finalize.');
            }
            if ($internal && (CreditService::parseCents((string)$order['total']) !== 0 || ($order['tax_status'] ?? '') !== 'calculated' || empty($order['tax_calculation_id']))) {
                throw new DomainException('Internal completion requires a zero total and completed tax calculation.');
            }
            $this->createTaxTransaction($order);
            if (CreditService::parseCents((string)$order['credits_applied']) > 0) {
                $this->credits->finalizeReservation((int)$order['user_id'], $orderId, 'order:' . $orderId . ':credit:finalize');
            }
            DB::exec('update orders set status="paid",payment_status="paid",manual_review_required=0,credit_payment_status=case when credits_applied>0 then "finalized" else "none" end,internally_completed=?,payment_provider=?,payment_processor=?,payment_mode=?,finalization_key=?,finalized_at=now(),paid_at=coalesce(paid_at,now()) where id=?', [$internal ? 1 : 0, $internal ? 'store_credit' : 'stripe', $internal ? 'internal' : 'stripe', $internal ? 'credit' : 'checkout', $eventKey, $orderId]);
            DB::exec('update order_items set paid_at=coalesce(paid_at,now()),payout_ready_at=coalesce(payout_ready_at,now()),manual_delivery_status=case when fulfillment_type="google_drive" and manual_delivery_status in ("pending_delivery","buyer_email_needed") then "ready_for_seller_delivery" else manual_delivery_status end where order_id=?', [$orderId]);
            DB::exec('update seller_earnings set status="paid_pending_payout" where order_id=?', [$orderId]);
            CouponService::recordUsage($orderId);
            $this->prepareFinancialLedgers($orderId, (string)($order['stripe_currency'] ?: StripeService::currency()), $internal);
            $this->referrals->qualifyBuyer($orderId, $eventKey . ':buyer');
            foreach (DB::rows('select distinct designer_id from order_items where order_id=?', [$orderId]) as $seller) {
                $this->referrals->qualifySeller($orderId, (int)$seller['designer_id'], $eventKey . ':seller:' . $seller['designer_id']);
                (new SellerReferralCommissionService)->accrueOrder($orderId, (int)$seller['designer_id']);
            }
            if ($ownsTransaction) {
                DB::commit();
                $this->communicate($orderId);
            }
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) DB::rollBack();
            if ($ownsTransaction && str_contains($error->getMessage(), 'Tax Transaction')) {
                DB::exec('update orders set status="pending",payment_status="manual_review",manual_review_required=1,manual_review_reason="Stripe payment captured; Tax Transaction creation failed. Replay webhook or recover from admin payment review.",tax_transaction_status="failed" where id=? and finalization_key is null', [$orderId]);
            }
            throw $error;
        }
    }

    public function release(int $orderId, string $eventKey): bool
    {
        $order = DB::row('select * from orders where id=?', [$orderId]);
        if (!$order || CreditService::parseCents((string)($order['credit_reserved'] ?? '0.00')) <= 0 || ($order['credit_payment_status'] ?? '') !== 'reserved') return false;
        DB::begin();
        try {
            $released = $this->credits->releaseReservation((int)$order['user_id'], $orderId, $eventKey);
            DB::exec('update orders set credit_payment_status="released",credits_applied=0.00,total=subtotal-coupon_discount+tax_amount,stripe_amount_total=round((subtotal-coupon_discount+tax_amount)*100) where id=? and credit_payment_status="reserved"', [$orderId]);
            DB::commit();
            return $released;
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }

    private function prepareFinancialLedgers(int $orderId, string $currency, bool $platformFunded): void
    {
        $items = DB::rows('select order_id,product_id,designer_id,sum(total_price) total_price,sum(total_price*commission_rate) commission_amount from order_items where order_id=? group by order_id,product_id,designer_id', [$orderId]);
        foreach ($items as $item) {
            DB::exec('insert into platform_commissions (order_id,product_id,designer_id,gross_sale,commission_amount) select ?,?,?,?,? where not exists (select 1 from platform_commissions where order_id=? and product_id=? and designer_id=?)', [$orderId, $item['product_id'], $item['designer_id'], $item['total_price'], round((float)$item['commission_amount'], 2), $orderId, $item['product_id'], $item['designer_id']]);
        }
        foreach (DB::rows('select oi.designer_id,sum(oi.total_price) gross,sum(oi.total_price*oi.commission_rate) commission,d.stripe_connect_account_id,d.stripe_details_submitted,d.stripe_payouts_enabled from order_items oi join designers d on d.id=oi.designer_id where oi.order_id=? group by oi.designer_id,d.stripe_connect_account_id,d.stripe_details_submitted,d.stripe_payouts_enabled', [$orderId]) as $row) {
            $gross = round((float)$row['gross'], 2);
            $commission = round((float)$row['commission'], 2);
            $payout = max(0, round($gross - $commission, 2));
            $status = $platformFunded
                ? 'platform_credit_hold'
                : (!empty($row['stripe_connect_account_id']) && !empty($row['stripe_details_submitted']) && !empty($row['stripe_payouts_enabled']) ? 'pending_transfer' : 'pending_stripe_onboarding');
            DB::exec('insert into seller_payouts (order_id,designer_id,gross_amount,platform_commission_amount,seller_payout_amount,currency,payout_status) values (?,?,?,?,?,?,?) on duplicate key update gross_amount=values(gross_amount),platform_commission_amount=values(platform_commission_amount),seller_payout_amount=values(seller_payout_amount),currency=values(currency),payout_status=case when payout_status="transferred" then payout_status else values(payout_status) end', [$orderId, $row['designer_id'], $gross, $commission, $payout, $currency, $status]);
            DB::exec('update order_items set platform_commission_amount=round(total_price*commission_rate,2),seller_payout_amount=round(total_price-(total_price*commission_rate),2),seller_payout_status=case when seller_payout_status="transferred" then seller_payout_status else ? end where order_id=? and designer_id=?', [$status, $orderId, $row['designer_id']]);
        }
    }

    public function communicate(int $orderId): void
    {
        foreach(DB::rows('select distinct designer_id from order_items where order_id=?',[$orderId]) as $seller){$designerId=(int)$seller['designer_id'];$this->communicationAttempt('creator_recognition_payment',fn()=>(new CreatorRecognitionService)->recalculate($designerId,false,true,'payment',null,'recognition:paid:order:'.$orderId.':seller:'.$designerId));}
        $this->communicationAttempt('paid_order_communications', function () use ($orderId): void {
            $this->queueCommunications($orderId);
        });
        try {
            $rewardedReferrals = DB::rows('select * from referrals where (buyer_rewarded_at is not null or seller_rewarded_at is not null) and (buyer_qualifying_order_id=? or seller_qualifying_order_id=?)', [$orderId, $orderId]);
        } catch (Throwable $error) {
            NotificationService::reportFailure('referral_reward_communications', $error);
            return;
        }
        foreach ($rewardedReferrals as $referral) {
            if (!empty($referral['buyer_rewarded_at'])) {
                $this->communicationAttempt('buyer_referral_reward', fn() => NotificationService::buyerReferralReward((int)$referral['referrer_user_id'], 'referral:' . $referral['id'] . ':buyer:notify:referrer'));
                $this->communicationAttempt('buyer_referral_reward', fn() => NotificationService::buyerReferralReward((int)$referral['referred_user_id'], 'referral:' . $referral['id'] . ':buyer:notify:referred'));
            }
            if (!empty($referral['seller_rewarded_at'])) {
                if (($referral['seller_reward_type'] ?? '') === 'store_credit') {
                    $this->communicationAttempt('seller_referral_credit', fn() => NotificationService::sellerReferralCredit((int)$referral['referrer_user_id'], 'referral:' . $referral['id'] . ':seller-credit:notify'));
                } elseif (($referral['seller_reward_type'] ?? '') === 'lifetime_commission') {
                    $this->communicationAttempt('seller_referral_lifetime', fn() => NotificationService::sellerReferralLifetimeQualified((int)$referral['referrer_user_id'], 'referral:' . $referral['id'] . ':seller-lifetime:notify'));
                }
            }
        }
    }

    private function queueCommunications(int $orderId): void
    {
        $order = DB::row('select * from orders where id=?', [$orderId]);
        EmailQueueService::paidOrder($orderId);
        NotificationService::create((int)$order['user_id'], 'purchase_receipt', 'buyer', 'Purchase complete', 'Your order #' . $orderId . ' is complete.', 'order:' . $orderId . ':buyer:paid', '/dashboard/order/' . $orderId);
        NotificationService::create((int)$order['user_id'], 'download_ready', 'buyer', 'Downloads ready', 'Your files for order #' . $orderId . ' are ready.', 'order:' . $orderId . ':buyer:download-ready', '/dashboard/order/' . $orderId);
        $coupon = !empty($order['coupon_id']) ? DB::row('select id,scope,seller_id,code from coupons where id=?', [$order['coupon_id']]) : null;
        foreach (DB::rows('select d.user_id,oi.designer_id,u.email,u.name,sum(coalesce(oi.coupon_discount,0)) coupon_discount from order_items oi join designers d on d.id=oi.designer_id join users u on u.id=d.user_id where oi.order_id=? group by d.user_id,oi.designer_id,u.email,u.name', [$orderId]) as $seller) {
            $key = 'order:' . $orderId . ':seller:' . $seller['designer_id'];
            NotificationService::create((int)$seller['user_id'], 'new_sale', 'designer', 'New sale', 'You made a sale in order #' . $orderId . '.', $key, '/seller/sales');
            EmailQueueService::foundationSellerEmail($seller['email'], 'new_sale', ['name' => $seller['name'], 'title' => 'New sale', 'message' => 'You made a sale in order #' . $orderId . '.', 'action_url' => '/seller/sales'], $key . ':email');
            $couponAffected = $coupon && (float)$seller['coupon_discount'] > 0 && ($coupon['scope'] === 'platform' || (int)$coupon['seller_id'] === (int)$seller['designer_id']);
            if ($couponAffected) {
                $couponKey = 'order:' . $orderId . ':coupon:' . $coupon['id'] . ':seller:' . $seller['designer_id'];
                NotificationService::create((int)$seller['user_id'], 'coupon_used', 'designer', 'Coupon used', 'Coupon ' . $coupon['code'] . ' affected your items in order #' . $orderId . '.', $couponKey, '/seller/sales');
                EmailQueueService::foundationSellerEmail($seller['email'], 'coupon_used', ['name' => $seller['name'], 'title' => 'Coupon used', 'message' => 'Coupon ' . $coupon['code'] . ' affected your items in order #' . $orderId . '.', 'action_url' => '/seller/sales'], $couponKey . ':email');
            }
        }
    }

    private function createTaxTransaction(array $order): void
    {
        if (($order['tax_transaction_status'] ?? '') === 'created' && !empty($order['tax_transaction_id'])) return;
        try {
            $transaction = StripeService::createTaxTransaction((string)$order['tax_calculation_id'], (int)$order['id']);
        } catch (Throwable $error) {
            throw new DomainException('Stripe Tax Transaction creation failed: ' . $error->getMessage(), 0, $error);
        }
        if (empty($transaction['id'])) throw new DomainException('Stripe Tax Transaction creation failed: missing transaction identifier.');
        DB::exec('update orders set tax_transaction_id=?,tax_transaction_status="created",tax_status="complete",tax_collected_at=now() where id=?', [$transaction['id'], $order['id']]);
    }

    private function communicationAttempt(string $context, callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable $error) {
            NotificationService::reportFailure($context, $error);
        }
    }
}
