<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use Throwable;

final class PlatformCreditPayoutService
{
    public function settle(int $payoutId, int $adminUserId): array
    {
        DB::begin();
        try {
            $admin = DB::row('select id from users where id=? and role="admin" and status="active" for update', [$adminUserId]);
            if (!$admin) throw new DomainException('Active administrator access is required.');

            $payout = DB::row('select sp.*,o.payment_status,o.status order_status,o.refunded_at,o.partially_refunded_at,o.internally_completed,o.manual_review_required,o.stripe_charge_id,d.user_id seller_user_id,d.stripe_connect_account_id,d.stripe_details_submitted,d.stripe_payouts_enabled,d.status designer_status from seller_payouts sp join orders o on o.id=sp.order_id join designers d on d.id=sp.designer_id where sp.id=? for update', [$payoutId]);
            if (!$payout) throw new DomainException('Platform-credit payout was not found.');
            if ($payout['payout_status'] === 'transferred' && !empty($payout['stripe_transfer_id'])) {
                DB::commit();
                return ['ok'=>true,'replay'=>true,'transfer_id'=>$payout['stripe_transfer_id']];
            }
            if ($payout['payout_status'] !== 'platform_credit_hold') throw new DomainException('Payout is not awaiting platform-credit settlement.');
            if ($payout['payment_status'] !== 'paid' || in_array($payout['order_status'], ['refunded', 'partially_refunded'], true) || !empty($payout['refunded_at']) || !empty($payout['partially_refunded_at']) || empty($payout['internally_completed']) || !empty($payout['manual_review_required']) || !empty($payout['stripe_charge_id'])) {
                throw new DomainException('Order is not eligible for platform-credit settlement.');
            }
            if ($payout['designer_status'] !== 'approved' || empty($payout['stripe_connect_account_id']) || empty($payout['stripe_details_submitted']) || empty($payout['stripe_payouts_enabled'])) {
                throw new DomainException('Seller is not payout-enabled.');
            }
            $amountCents = CreditService::parseCents((string)$payout['seller_payout_amount'], false);
            if ($amountCents <= 0) throw new DomainException('Payout amount must be positive.');
            $key = 'asset_moth_platform_credit_payout_' . (int)$payout['id'] . '_order_' . (int)$payout['order_id'] . '_designer_' . (int)$payout['designer_id'];
            $claim = DB::pdo()->prepare('update seller_payouts set payout_status="platform_credit_processing",platform_credit_attempt_key=?,stripe_transfer_error=null,updated_at=now() where id=? and payout_status="platform_credit_hold"');
            $claim->execute([$key, $payoutId]);
            if ($claim->rowCount() !== 1) {
                throw new DomainException('Payout settlement could not be claimed.');
            }
            try {
                $transfer = StripeService::createTransfer($payout['stripe_connect_account_id'],$amountCents,strtolower((string)($payout['currency'] ?: StripeService::currency())),['order_id'=>(string)$payout['order_id'],'designer_id'=>(string)$payout['designer_id'],'seller_payout_id'=>(string)$payout['id'],'funding'=>'platform_credit'],$key,null,'order_'.(int)$payout['order_id']);
                if (empty($transfer['id'])) throw new DomainException('Stripe transfer did not return an identifier.');
                DB::exec('update seller_payouts set payout_status="transferred",stripe_transfer_id=?,stripe_transfer_error=null,platform_credit_settled_at=now(),platform_credit_settled_by=?,updated_at=now() where id=? and payout_status="platform_credit_processing"',[$transfer['id'],$adminUserId,$payoutId]);
                DB::exec('update order_items set seller_payout_status="transferred",stripe_transfer_id=?,stripe_transfer_error=null where order_id=? and designer_id=? and seller_payout_status="platform_credit_hold"',[$transfer['id'],$payout['order_id'],$payout['designer_id']]);
                $this->log($adminUserId,$payout,$key,'transferred',$transfer['id'],null);
                DB::commit();
                return ['ok'=>true,'replay'=>false,'transfer_id'=>$transfer['id']];
            } catch (Throwable $error) {
                $safe = OperationalErrorSanitizer::sanitize($error->getMessage(),1000);
                DB::exec('update seller_payouts set payout_status="platform_credit_hold",stripe_transfer_error=?,updated_at=now() where id=? and payout_status="platform_credit_processing"',[$safe,$payoutId]);
                $this->log($adminUserId,$payout,$key,'failed',null,$safe);
                DB::commit();
                return ['ok'=>false,'replay'=>false,'error'=>'Platform-balance transfer failed and remains available for retry.'];
            }
        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
    }

    private function log(int $adminId,array $payout,string $key,string $result,?string $transferId,?string $error): void
    {
        DB::exec('insert into admin_logs(admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)',[$adminId,'settled_platform_credit_payout','seller_payout',$payout['id'],json_encode(['payout_id'=>(int)$payout['id'],'order_id'=>(int)$payout['order_id'],'designer_id'=>(int)$payout['designer_id'],'amount'=>(string)$payout['seller_payout_amount'],'currency'=>(string)$payout['currency'],'result'=>$result,'idempotency_key'=>$key,'stripe_transfer_id'=>$transferId,'error'=>$error],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)]);
    }
}
