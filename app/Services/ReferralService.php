<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use Throwable;

final class ReferralService
{
    public const BUYER_REWARD = '1.50';
    public const SELLER_REWARD = '5.00';

    public function __construct(private ?CreditService $credits = null)
    {
        $this->credits ??= new CreditService();
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function validFormat(string $code): bool
    {
        $normalized = self::normalize($code);
        return preg_match('/^[A-Z0-9_-]{8,40}$/', $normalized) === 1 && !ctype_digit($normalized);
    }

    public static function generateCode(): string
    {
        return 'AM' . strtoupper(bin2hex(random_bytes(12)));
    }

    public function referrer(string $code): ?array
    {
        $normalized = self::normalize($code);
        if (!self::validFormat($normalized)) {
            return null;
        }
        return DB::row('select id,referral_code from users where upper(referral_code)=? and status="active"', [$normalized]);
    }

    public function attach(int $userId, string $code, string $intent = 'buyer'): int
    {
        $intent = $intent === 'seller' ? 'seller' : 'buyer';
        $referrer = $this->referrer($code);
        if (!$referrer) {
            throw new DomainException('Invalid referral code.');
        }
        if ((int)$referrer['id'] === $userId) {
            throw new DomainException('Self-referrals are not allowed.');
        }
        if ($intent === 'seller' && DB::row('select id from designers where user_id=?', [$userId])) {
            throw new DomainException('An existing or previously approved seller cannot attach a seller referral.');
        }
        if ($intent === 'buyer' && DB::row('select id from orders where user_id=? limit 1', [$userId])) {
            throw new DomainException('A buyer referral cannot be attached after ordering.');
        }

        $existing = DB::row('select * from referrals where referred_user_id=? for update', [$userId]);
        if ($existing) {
            if ((int)$existing['referrer_user_id'] !== (int)$referrer['id']) {
                throw new DomainException('This account already has a different referrer.');
            }
            if ($intent === 'seller' && empty($existing['seller_intent'])) {
                DB::exec('update referrals set seller_intent=1 where id=?', [$existing['id']]);
            }
            return (int)$existing['id'];
        }

        DB::exec(
            'insert into referrals (referrer_user_id,referred_user_id,referral_type,seller_intent,referral_code_snapshot,status,reward_status,referrer_reward_amount,referred_reward_amount,reward_idempotency_key) values (?,?,?,?,?,"attached","pending",?,?,?)',
            [(int)$referrer['id'], $userId, $intent, $intent === 'seller' ? 1 : 0, $referrer['referral_code'], $intent === 'buyer' ? self::BUYER_REWARD : self::SELLER_REWARD, $intent === 'buyer' ? self::BUYER_REWARD : self::SELLER_REWARD, 'referral:attachment:' . $userId]
        );
        return (int)DB::id();
    }

    public function qualifyBuyer(int $orderId, string $eventKey): bool
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $order = DB::row('select * from orders where id=? for update', [$orderId]);
            if (!$order || $order['payment_status'] !== 'paid' || (int)($order['manual_review_required'] ?? 0) !== 0 || CreditService::parseCents((string)($order['stripe_paid_amount'] ?? $order['total'] ?? '0.00')) <= 0) {
                if ($ownsTransaction) DB::rollBack();
                return false;
            }
            $prior = DB::row('select id from orders where user_id=? and payment_status="paid" and coalesce(stripe_paid_amount,total)>0 and manual_review_required=0 and id<? limit 1', [(int)$order['user_id'], $orderId]);
            $referral = DB::row('select * from referrals where referred_user_id=? for update', [(int)$order['user_id']]);
            if ($prior || !$referral || !empty($referral['buyer_rewarded_at'])) {
                if ($ownsTransaction) DB::rollBack();
                return false;
            }
            $this->grantPair($referral, self::BUYER_REWARD, 'buyer');
            DB::exec('update referrals set buyer_status="rewarded",buyer_referrer_reward_amount=?,buyer_referred_reward_amount=?,buyer_qualifying_order_id=?,buyer_reward_event_key=?,buyer_rewarded_at=now(),status="qualified",reward_status="rewarded",qualified_at=coalesce(qualified_at,now()),rewarded_at=coalesce(rewarded_at,now()) where id=?', [self::BUYER_REWARD, self::BUYER_REWARD, $orderId, $eventKey, $referral['id']]);
            if ($ownsTransaction) DB::commit();
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $error;
        }
    }

    public function qualifySeller(int $orderId, int $designerId, string $eventKey): bool
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $designer = DB::row('select * from designers where id=? and status="approved" for update', [$designerId]);
            $order = DB::row('select * from orders where id=? and payment_status="paid" and status not in ("failed","cancelled","refunded","partially_refunded") and coalesce(manual_review_required,0)=0 and refunded_at is null and partially_refunded_at is null', [$orderId]);
            if (!$designer || !$order) {
                if ($ownsTransaction) DB::rollBack();
                return false;
            }
            $firstItem = DB::row('select min(id) id from order_items where order_id=? and designer_id=?', [$orderId, $designerId]);
            $prior = DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where oi.designer_id=? and o.payment_status="paid" and o.status not in ("failed","cancelled","refunded","partially_refunded") and coalesce(o.manual_review_required,0)=0 and o.refunded_at is null and o.partially_refunded_at is null and o.id<? limit 1', [$designerId, $orderId]);
            $referral = DB::row('select * from referrals where referred_user_id=? and seller_intent=1 for update', [(int)$designer['user_id']]);
            if ($prior || !$referral || !empty($referral['seller_reward_type'])) {
                if ($ownsTransaction) DB::rollBack();
                return false;
            }
            $approvedReferrer=DB::row('select id from designers where user_id=? and status="approved"',[$referral['referrer_user_id']]);
            $rewardType=$approvedReferrer?'lifetime_commission':'store_credit';
            if($rewardType==='store_credit'){
                $this->credits->grant((int)$referral['referrer_user_id'],self::SELLER_REWARD,'referral:'.$referral['id'].':seller:referrer',['referral_id'=>(int)$referral['id'],'description'=>'Seller referral reward']);
            }
            DB::exec('update referrals set seller_reward_type=?,seller_reward_type_selected_at=now(),seller_status="rewarded",seller_referrer_reward_amount=?,seller_referred_reward_amount=0,seller_qualifying_order_id=?,seller_qualifying_order_item_id=?,seller_reward_event_key=?,seller_rewarded_at=now(),status="qualified",reward_status="rewarded",qualified_at=coalesce(qualified_at,now()),rewarded_at=coalesce(rewarded_at,now()) where id=? and seller_reward_type is null', [$rewardType,$rewardType==='store_credit'?self::SELLER_REWARD:'0.00',$orderId,$firstItem['id']??null,$eventKey,$referral['id']]);
            if ($ownsTransaction) DB::commit();
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $error;
        }
    }

    public function dashboard(int $userId): array
    {
        return [
            'code' => (string)(DB::row('select referral_code from users where id=?', [$userId])['referral_code'] ?? ''),
            'made' => DB::rows('select r.*,u.name referred_name,d.display_name referred_store,d.store_slug,
                (select coalesce(sum(l.amount_cents),0) from seller_referral_commission_ledger l where l.referral_id=r.id) lifetime_commission_cents,
                (select greatest(0,coalesce(sum(l.amount_cents),0)) from seller_referral_commission_ledger l where l.referral_id=r.id and l.payout_item_id is null) pending_commission_cents,
                (select coalesce(sum(l.amount_cents),0) from seller_referral_commission_ledger l where l.referral_id=r.id and l.payout_item_id is not null) paid_commission_cents,
                (select coalesce(sum(l.amount_cents),0) from seller_referral_commission_ledger l where l.referral_id=r.id and l.entry_type="recovery_adjustment") recovery_commission_cents
                from referrals r join users u on u.id=r.referred_user_id left join designers d on d.user_id=r.referred_user_id
                where r.referrer_user_id=? order by r.id desc', [$userId]),
            'connected' => DB::row('select * from referrals where referred_user_id=?', [$userId]),
            'payouts' => DB::rows('select period_start,period_end,sequence_no,amount_cents,status,succeeded_at,failure_reason from seller_referral_payout_batches where referrer_user_id=? order by id desc limit 12', [$userId]),
        ];
    }

    private function grantPair(array $referral, string $amount, string $kind): void
    {
        $base = 'referral:' . $referral['id'] . ':' . $kind;
        $meta = ['referral_id' => (int)$referral['id'], 'description' => ucfirst($kind) . ' referral reward'];
        $this->credits->grant((int)$referral['referrer_user_id'], $amount, $base . ':referrer', $meta);
        $this->credits->grant((int)$referral['referred_user_id'], $amount, $base . ':referred', $meta);
    }
}
