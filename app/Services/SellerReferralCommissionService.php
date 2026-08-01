<?php

namespace App\Services;

use App\Core\Database as DB;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class SellerReferralCommissionService
{
    private const LEASE_SECONDS = 900;

    public static function commissionCents(int $sellerEarningCents): int
    {
        return $sellerEarningCents <= 0 ? 0 : intdiv($sellerEarningCents + 50, 100);
    }

    public static function previousUtcPeriod(): string
    {
        return (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')))
            ->modify('-1 month')->format('Y-m');
    }

    public static function periodBounds(string $period): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            throw new DomainException('Payout period must use YYYY-MM.');
        }
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        if ($year < 2000 || !checkdate($month, 1, $year)) {
            throw new DomainException('Payout period is not a real calendar month.');
        }
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new DateTimeZone('UTC'));
        return [$start->format('Y-m-d H:i:s'), $start->modify('+1 month')->format('Y-m-d H:i:s')];
    }

    public function accrueOrder(int $orderId, int $designerId): int
    {
        $referral = DB::row(
            'select r.* from referrals r join designers d on d.user_id=r.referred_user_id
             where d.id=? and r.seller_reward_type="lifetime_commission" and r.commission_ended_at is null',
            [$designerId]
        );
        $order = DB::row(
            'select id from orders where id=? and payment_status="paid" and status not in ("failed","cancelled","refunded","partially_refunded")
             and coalesce(manual_review_required,0)=0 and refunded_at is null and partially_refunded_at is null',
            [$orderId]
        );
        if (!$referral || !$order) {
            return 0;
        }
        $total = 0;
        foreach (DB::rows('select id,seller_payout_amount from order_items where order_id=? and designer_id=?', [$orderId, $designerId]) as $item) {
            $earning = CreditService::parseCents((string)$item['seller_payout_amount']);
            $amount = self::commissionCents($earning);
            if ($amount === 0) {
                continue;
            }
            $inserted = DB::exec(
                'insert ignore into seller_referral_commission_ledger
                 (referral_id,order_id,order_item_id,entry_type,amount_cents,seller_earning_cents,event_key)
                 values (?,?,?,"accrual",?,?,?)',
                [$referral['id'], $orderId, $item['id'], $amount, $earning, 'seller-referral:accrual:item:' . $item['id']]
            );
            if ($inserted) {
                $total += $amount;
            }
        }
        return $total;
    }

    public function reconcileRefund(int $orderId, int $cumulativeRefundCents): void
    {
        $rows = DB::rows(
            'select a.id accrual_id,a.referral_id,a.order_item_id,a.seller_earning_cents,oi.seller_payout_amount,
                    coalesce(sum(adj.amount_cents),0) adjusted_cents,
                    exists(select 1 from seller_referral_payout_items pi where pi.ledger_entry_id=a.id) accrual_paid
             from seller_referral_commission_ledger a
             join order_items oi on oi.id=a.order_item_id
             left join seller_referral_commission_ledger adj on adj.related_entry_id=a.id
             where a.order_id=? and a.entry_type="accrual"
             group by a.id,a.referral_id,a.order_item_id,a.seller_earning_cents,oi.seller_payout_amount',
            [$orderId]
        );
        foreach ($rows as $row) {
            $original = self::commissionCents((int)$row['seller_earning_cents']);
            $desired = self::commissionCents(CreditService::parseCents((string)$row['seller_payout_amount']));
            $alreadyAdjusted = abs((int)$row['adjusted_cents']);
            $requiredReduction = max(0, $original - $desired);
            $delta = $requiredReduction - $alreadyAdjusted;
            if ($delta <= 0) {
                continue;
            }
            $type = !empty($row['accrual_paid']) ? 'recovery_adjustment' : 'refund_adjustment';
            DB::exec(
                'insert ignore into seller_referral_commission_ledger
                 (referral_id,order_id,order_item_id,entry_type,amount_cents,seller_earning_cents,related_entry_id,event_key)
                 values (?,?,?,?,?,0,?,?)',
                [$row['referral_id'], $orderId, $row['order_item_id'], $type, -$delta, $row['accrual_id'],
                 'seller-referral:refund:' . $orderId . ':' . $cumulativeRefundCents . ':item:' . $row['order_item_id']]
            );
        }
    }

    public function permanentlyStop(int $referredUserId, string $reason): bool
    {
        if (!in_array($reason, ['store_disabled', 'store_inactive', 'store_deleted'], true)) {
            throw new DomainException('Invalid permanent commission stop reason.');
        }
        return DB::exec(
            'update referrals set commission_end_reason=?,commission_ended_at=utc_timestamp()
             where referred_user_id=? and seller_reward_type="lifetime_commission" and commission_ended_at is null',
            [$reason, $referredUserId]
        ) > 0;
    }

    public function notifyPermanentStop(int $referredUserId): void
    {
        foreach (DB::rows('select id,referrer_user_id,commission_end_reason from referrals where referred_user_id=? and commission_ended_at is not null', [$referredUserId]) as $referral) {
            try {
                NotificationService::sellerReferralEnded(
                    (int)$referral['referrer_user_id'],
                    'seller-referral:' . $referral['id'] . ':permanent-stop',
                    (string)$referral['commission_end_reason']
                );
            } catch (Throwable $error) {
                NotificationService::reportFailure('seller_referral_permanent_stop', $error);
            }
        }
    }

    public function payMonth(string $period): array
    {
        [$start, $end] = self::periodBounds($period);
        $summary = ['paid' => 0, 'failed' => 0, 'not_ready' => 0, 'skipped' => 0, 'transferred_cents' => 0];
        $users = DB::rows(
            'select distinct r.referrer_user_id from referrals r
             where r.seller_reward_type="lifetime_commission"
             order by r.referrer_user_id'
        );
        foreach ($users as $user) {
            $result = $this->paySeller((int)$user['referrer_user_id'], $start, $end);
            $summary[$result['status']]++;
            $summary['transferred_cents'] += $result['transferred_cents'];
        }
        return $summary;
    }

    public function retryBatch(int $batchId): array
    {
        $batch = DB::row('select * from seller_referral_payout_batches where id=?', [$batchId]);
        if (!$batch || !in_array($batch['status'], ['failed', 'not_ready', 'processing'], true)) {
            throw new DomainException('That referral payout batch is not retryable.');
        }
        return $this->processBatch($batchId, true);
    }

    private function paySeller(int $userId, string $start, string $end): array
    {
        DB::begin();
        try {
            $paid = DB::row(
                'select id from seller_referral_payout_batches
                 where referrer_user_id=? and period_start=? and period_end=? and status="paid" order by sequence_no desc limit 1',
                [$userId, substr($start, 0, 10), substr($end, 0, 10)]
            );
            $entries = DB::rows(
                'select l.* from seller_referral_commission_ledger l
                 join referrals r on r.id=l.referral_id
                 where r.referrer_user_id=? and l.created_at<? and l.claimed_batch_id is null and l.payout_item_id is null
                 order by l.id for update',
                [$userId, $end]
            );
            $amount = array_sum(array_map(static fn(array $row): int => (int)$row['amount_cents'], $entries));
            if ($amount <= 0) {
                DB::commit();
                return ['status' => 'skipped', 'transferred_cents' => 0, 'already_paid' => (bool)$paid];
            }
            $latestBatch = DB::row(
                'select sequence_no from seller_referral_payout_batches
                 where referrer_user_id=? and period_start=? and period_end=?
                 order by sequence_no desc limit 1 for update',
                [$userId, substr($start, 0, 10), substr($end, 0, 10)]
            );
            $sequence = 1 + (int)($latestBatch['sequence_no'] ?? 0);
            $key = sprintf('seller-referral:%d:%s:%d', $userId, substr($start, 0, 7), $sequence);
            $claim = bin2hex(random_bytes(16));
            DB::exec(
                'insert into seller_referral_payout_batches
                 (referrer_user_id,period_start,period_end,sequence_no,amount_cents,status,idempotency_key,claim_token,processing_started_at,attempted_at)
                 values (?,?,?,?,?,"processing",?,?,utc_timestamp(),utc_timestamp())',
                [$userId, substr($start, 0, 10), substr($end, 0, 10), $sequence, $amount, $key, $claim]
            );
            $batchId = (int)DB::id();
            foreach ($entries as $entry) {
                DB::exec('update seller_referral_commission_ledger set claimed_batch_id=? where id=? and claimed_batch_id is null', [$batchId, $entry['id']]);
            }
            DB::commit();
            return $this->processBatch($batchId, false);
        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $error;
        }
    }

    private function processBatch(int $batchId, bool $allowStaleRecovery): array
    {
        $claim = bin2hex(random_bytes(16));
        DB::begin();
        try {
            $batch = DB::row('select * from seller_referral_payout_batches where id=? for update', [$batchId]);
            if (!$batch) {
                throw new DomainException('Referral payout batch was not found.');
            }
            if ($batch['status'] === 'paid') {
                DB::commit();
                return ['status' => 'skipped', 'transferred_cents' => 0, 'already_paid' => true];
            }
            $stale = !empty($batch['processing_started_at'])
                && strtotime((string)$batch['processing_started_at']) <= time() - self::LEASE_SECONDS;
            if ($batch['status'] === 'processing' && !$stale && $allowStaleRecovery) {
                DB::commit();
                return ['status' => 'skipped', 'transferred_cents' => 0, 'already_paid' => false];
            }
            DB::exec(
                'update seller_referral_payout_batches set status="processing",claim_token=?,processing_started_at=utc_timestamp(),attempted_at=utc_timestamp(),failure_reason=null where id=?',
                [$claim, $batchId]
            );
            DB::exec('insert into seller_referral_transfer_attempts(batch_id,status,attempted_at) values (?,"attempted",utc_timestamp())', [$batchId]);
            $attemptId = (int)DB::id();
            $seller = DB::row('select * from designers where user_id=? and status="approved"', [$batch['referrer_user_id']]);
            if (!$seller || empty($seller['stripe_connect_account_id']) || empty($seller['stripe_details_submitted']) || empty($seller['stripe_payouts_enabled'])) {
                $reason = 'Your seller payout account is not ready to receive referral commission transfers.';
                DB::exec('update seller_referral_payout_batches set status="not_ready",failure_reason=?,claim_token=null,processing_started_at=null where id=?', [$reason, $batchId]);
                DB::exec('insert into seller_referral_transfer_attempts(batch_id,status,failure_reason,attempted_at) values (?,"not_ready",?,utc_timestamp())', [$batchId, $reason]);
                DB::commit();
                $this->notifyFailure((int)$batch['referrer_user_id'], $batchId, 'not_ready');
                return ['status' => 'not_ready', 'transferred_cents' => 0, 'already_paid' => false];
            }
            DB::commit();

            try {
                $transfer = StripeService::createTransfer(
                    (string)$seller['stripe_connect_account_id'],
                    (int)$batch['amount_cents'],
                    StripeService::currency(),
                    ['referrer_user_id' => (string)$batch['referrer_user_id'], 'payout_batch_id' => (string)$batchId],
                    (string)$batch['idempotency_key'],
                    null,
                    'seller_referral_' . str_replace('-', '', (string)$batch['period_start']) . '_' . $batch['sequence_no']
                );
            } catch (Throwable $error) {
                $reason = OperationalErrorSanitizer::sanitize($error->getMessage(), 500);
                DB::begin();
                DB::exec('update seller_referral_payout_batches set status="failed",failure_reason=?,claim_token=null,processing_started_at=null where id=? and claim_token=?', [$reason, $batchId, $claim]);
                DB::exec('insert into seller_referral_transfer_attempts(batch_id,status,failure_reason,attempted_at) values (?,"failed",?,utc_timestamp())', [$batchId, $reason]);
                DB::commit();
                $this->notifyFailure((int)$batch['referrer_user_id'], $batchId, 'failed');
                return ['status' => 'failed', 'transferred_cents' => 0, 'already_paid' => false];
            }

            DB::begin();
            $locked = DB::row('select * from seller_referral_payout_batches where id=? for update', [$batchId]);
            if ($locked['status'] !== 'paid') {
                foreach (DB::rows('select * from seller_referral_commission_ledger where claimed_batch_id=? and payout_item_id is null order by id for update', [$batchId]) as $entry) {
                    DB::exec('insert into seller_referral_payout_items(batch_id,ledger_entry_id,amount_cents) values (?,?,?)', [$batchId, $entry['id'], $entry['amount_cents']]);
                    DB::exec('update seller_referral_commission_ledger set payout_item_id=? where id=?', [(int)DB::id(), $entry['id']]);
                }
                DB::exec('update seller_referral_payout_batches set status="paid",stripe_transfer_id=?,succeeded_at=utc_timestamp(),claim_token=null,processing_started_at=null where id=?', [$transfer['id'], $batchId]);
                DB::exec('insert into seller_referral_transfer_attempts(batch_id,status,stripe_transfer_id,attempted_at,succeeded_at) values (?,"succeeded",?,utc_timestamp(),utc_timestamp())', [$batchId, $transfer['id']]);
            }
            DB::commit();
            $this->notifySuccess((int)$batch['referrer_user_id'], $batchId, (int)$batch['amount_cents']);
            return ['status' => 'paid', 'transferred_cents' => (int)$batch['amount_cents'], 'already_paid' => false];
        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $error;
        }
    }

    private function notifySuccess(int $userId, int $batchId, int $amount): void
    {
        try {
            NotificationService::sellerReferralPayoutSuccess($userId, 'seller-referral:payout:' . $batchId . ':paid', CreditService::formatCents($amount));
        } catch (Throwable $error) {
            NotificationService::reportFailure('seller_referral_payout_success', $error);
        }
    }

    private function notifyFailure(int $userId, int $batchId, string $status): void
    {
        try {
            NotificationService::sellerReferralPayoutProblem($userId, 'seller-referral:payout:' . $batchId . ':' . $status, $status);
        } catch (Throwable $error) {
            NotificationService::reportFailure('seller_referral_payout_problem', $error);
        }
    }
}
