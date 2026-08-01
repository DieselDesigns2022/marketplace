<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\CreditService;
use App\Services\NotificationService;
use App\Services\PlatformCreditPayoutService;
use App\Services\SellerReferralCommissionService;
use InvalidArgumentException;
use Throwable;

final class AdminCreditController
{
    public function index(): void
    {
        H::requireRole('admin');
        $query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 190);
        $selectedId = max(0, (int)($_GET['user_id'] ?? 0));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * 25;
        $params = [];
        $where = '';
        if ($query !== '') {
            $where = ' where u.name like ? or u.email like ?';
            $params = ['%' . $query . '%', '%' . $query . '%'];
        }
        $users = DB::rows("select u.id,u.name,u.email,coalesce(c.total_balance,0.00) total_balance,coalesce(c.reserved_balance,0.00) reserved_balance,coalesce(c.total_balance,0.00)-coalesce(c.reserved_balance,0.00) available_balance from users u left join marketplace_credits c on c.user_id=u.id$where order by u.id desc limit 25 offset $offset", $params);
        $selected = $selectedId ? DB::row('select u.id,u.name,u.email,coalesce(c.total_balance,0.00) total_balance,coalesce(c.reserved_balance,0.00) reserved_balance,coalesce(c.total_balance,0.00)-coalesce(c.reserved_balance,0.00) available_balance from users u left join marketplace_credits c on c.user_id=u.id where u.id=?', [$selectedId]) : null;
        $ledger = $selected ? DB::rows('select ct.*,a.name admin_name from credit_transactions ct left join users a on a.id=ct.admin_user_id where ct.user_id=? order by ct.id desc limit 100', [$selectedId]) : [];
        $referrals = DB::rows('select r.*,a.name referrer_name,b.name referred_name from referrals r join users a on a.id=r.referrer_user_id join users b on b.id=r.referred_user_id where (?=0 or r.referrer_user_id=? or r.referred_user_id=?) order by r.id desc limit 100', [$selectedId, $selectedId, $selectedId]);
        $payoutStatus = in_array($_GET['payout_status'] ?? '', ['processing','paid','failed','not_ready'], true) ? $_GET['payout_status'] : '';
        $payouts = DB::rows(
            'select b.*,u.name referrer_name,d.display_name,d.store_slug,d.stripe_details_submitted,d.stripe_payouts_enabled,
                    (select count(*) from seller_referral_transfer_attempts a where a.batch_id=b.id) attempt_count
             from seller_referral_payout_batches b join users u on u.id=b.referrer_user_id
             left join designers d on d.user_id=b.referrer_user_id
             where (?="" or b.status=?) order by b.id desc limit 100',
            [$payoutStatus, $payoutStatus]
        );
        $attempts = DB::rows('select * from seller_referral_transfer_attempts where batch_id in (select id from seller_referral_payout_batches order by id desc) order by id desc limit 300');
        $commissionTotals = DB::rows(
            'select r.id referral_id,ru.name referrer_name,du.display_name referred_store,r.commission_ended_at,r.commission_end_reason,
                    coalesce(sum(case when l.payout_item_id is null then l.amount_cents else 0 end),0) unpaid_cents,
                    coalesce(sum(case when l.payout_item_id is not null then l.amount_cents else 0 end),0) paid_cents,
                    coalesce(sum(case when l.entry_type="recovery_adjustment" then l.amount_cents else 0 end),0) recovery_cents,
                    coalesce(sum(l.amount_cents),0) lifetime_cents
             from referrals r join users ru on ru.id=r.referrer_user_id left join designers du on du.user_id=r.referred_user_id
             left join seller_referral_commission_ledger l on l.referral_id=r.id where r.seller_reward_type="lifetime_commission"
             group by r.id,ru.name,du.display_name,r.commission_ended_at,r.commission_end_reason order by r.id desc'
        );
        H::view('admin/credits', compact('users', 'selected', 'ledger', 'referrals', 'query', 'page', 'payoutStatus', 'payouts', 'attempts', 'commissionTotals'));
    }

    public function retrySellerReferralPayout($id): void
    {
        H::requireRole('admin');
        H::verifyCsrf();
        $adminId = (int)H::user()['id'];
        $active = DB::row('select id from users where id=? and role="admin" and status="active"', [$adminId]);
        if (!$active) {
            H::abort(403);
        }
        $batch = DB::row('select * from seller_referral_payout_batches where id=?', [(int)$id]);
        if (!$batch) {
            H::abort(404);
        }
        try {
            $result = (new SellerReferralCommissionService())->retryBatch((int)$id);
            $fresh = DB::row('select * from seller_referral_payout_batches where id=?', [(int)$id]);
            DB::exec(
                'insert into seller_referral_admin_audits(admin_user_id,batch_id,action,amount_cents,result_status,reason,stripe_transfer_id)
                 values (?, ?, "retry", ?, ?, ?, ?)',
                [$adminId, $id, $batch['amount_cents'], $result['status'], $fresh['failure_reason'] ?? null, $fresh['stripe_transfer_id'] ?? null]
            );
            H::flash($result['status'] === 'paid' ? 'success' : 'warning', 'Referral payout retry result: ' . str_replace('_', ' ', $result['status']) . '.');
        } catch (Throwable $error) {
            $safe = \App\Services\OperationalErrorSanitizer::sanitize($error->getMessage(), 500);
            DB::exec(
                'insert into seller_referral_admin_audits(admin_user_id,batch_id,action,amount_cents,result_status,reason)
                 values (?, ?, "retry", ?, "rejected", ?)',
                [$adminId, $id, $batch['amount_cents'], $safe]
            );
            H::flash('error', 'Referral payout retry was rejected.');
        }
        H::redirect('/admin/referrals?payout_status=' . rawurlencode((string)($batch['status'] ?? '')));
    }

    public function adjust(): void
    {
        H::requireRole('admin');
        H::verifyCsrf();
        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = trim((string)($_POST['amount'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $target = DB::row('select id,status from users where id=?', [$userId]);
        if (!$target || $target['status'] !== 'active' || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            H::flash('error', 'Select an active user and provide a reason between 3 and 500 characters.');
            H::redirect('/admin/referrals?user_id=' . $userId);
        }
        try {
            $cents = CreditService::parseCents($amount);
            if ($cents === 0) throw new InvalidArgumentException('Zero adjustment.');
            $key = 'admin-credit:' . (int)H::user()['id'] . ':' . bin2hex(random_bytes(16));
            DB::begin();
            (new CreditService)->adjust($userId, $amount, $key, [
                'admin_user_id' => (int)H::user()['id'],
                'description' => mb_substr($reason, 0, 500),
            ]);
            DB::exec('insert into admin_logs (admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)', [(int)H::user()['id'], 'store_credit_adjustment', 'user', $userId, json_encode(['amount' => CreditService::formatCents($cents), 'reason' => $reason], JSON_THROW_ON_ERROR)]);
            NotificationService::adminCreditAdjustment($userId, $key . ':notification', CreditService::formatCents($cents));
            DB::commit();
            H::flash('success', 'Credit adjustment recorded.');
        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            error_log('Admin credit adjustment failed: ' . $error->getMessage());
            H::flash('error', 'Credit adjustment could not be applied. Check the amount and available balance.');
        }
        H::redirect('/admin/referrals?user_id=' . $userId);
    }

    public function settlePlatformCreditPayout($id): void
    {
        H::requireRole('admin');
        H::verifyCsrf();
        try {
            $result = (new PlatformCreditPayoutService())->settle((int)$id,(int)H::user()['id']);
            H::flash($result['ok'] ? 'success' : 'error',$result['ok'] ? ($result['replay'] ? 'This platform-credit payout was already settled.' : 'Platform-credit payout transferred successfully.') : $result['error']);
        } catch (Throwable $error) {
            error_log('Platform-credit payout settlement rejected: '.\App\Services\OperationalErrorSanitizer::sanitize($error->getMessage(),240));
            H::flash('error','That platform-credit payout is not eligible for settlement.');
        }
        H::redirect('/admin/payment-logs?issue=platform_credit_holds');
    }
}
