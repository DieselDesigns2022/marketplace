<?php
namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use Throwable;

final class FullCreditRefundService
{
    public static function authorized(int $orderId): bool
    {
        return (bool)DB::row(
            'select id from admin_logs
             where action="completed_full_credit_refund"
               and entity_type="order" and entity_id=? limit 1',
            [$orderId]
        );
    }

    public function refund(int $orderId, int $adminId): array
    {
        $owns=!DB::pdo()->inTransaction();
        if($owns) DB::begin();
        try {
            $order=DB::row('select * from orders where id=? for update',[$orderId]);
            $actor=DB::row(
                'select id from users where id=? and role="admin"
                 and status="active" for update',[$adminId]
            );
            if(!$actor || !(new AdminPermissionService())->can($adminId,'orders.manage')) {
                throw new DomainException('An active administrator with order-management permission is required.');
            }
            if(!$order || (int)$order['financial_snapshot_version']!==1
                || empty($order['finalization_key'])
                || !in_array($order['payment_status'],['paid','partially_refunded','refunded'],true)) {
                throw new DomainException('This order is not eligible for a full credit refund.');
            }

            $market=CreditService::parseCents((string)$order['credits_applied'],false);
            $prize=CreditService::parseCents((string)$order['prize_credit_applied'],false);
            if($market+$prize<=0) throw new DomainException('This order has no credits to refund.');

            $existing=DB::row(
                'select * from admin_logs
                 where action="completed_full_credit_refund"
                   and entity_type="order" and entity_id=? order by id limit 1',
                [$orderId]
            );
            if($existing) {
                if($order['payment_status']!=='refunded' || $order['status']!=='refunded') {
                    throw new DomainException('Credit refund audit and order status disagree.');
                }
                if($owns) DB::commit();
                return ['replay'=>true,'marketplace_cents'=>0,'prize_cents'=>0];
            }
            if(!empty($order['manual_review_required'])) {
                throw new DomainException('Resolve the order review before completing its credit refund.');
            }

            $observations=DB::rows(
                'select * from marketplace_refund_observations
                 where order_id=? order by id for update',[$orderId]
            );
            $taxRefunded=0;
            $highest=0;
            foreach($observations as $ob) {
                if($ob['allocation_status']!=='reconciled') {
                    throw new DomainException('All cash refund allocations must be reconciled first.');
                }
                $taxRefunded+=(int)$ob['tax_refund_cents'];
                $highest=max($highest,(int)$ob['cumulative_refund_cents']);
            }

            $items=DB::rows(
                'select oi.*,coalesce((
                    select sum(a.merchandise_refund_cents)
                    from marketplace_refund_allocations a
                    where a.order_item_id=oi.id
                ),0) refunded_cents
                 from order_items oi where oi.order_id=? order by oi.id for update',
                [$orderId]
            );
            if(!$items) throw new DomainException('Order items are unavailable.');
            $cashItems=0;
            $marketItems=0;
            $prizeItems=0;
            foreach($items as $item) {
                if($item['cash_merchandise_amount']===null) {
                    throw new DomainException('Order cash snapshots are unavailable.');
                }
                $cash=CreditService::parseCents((string)$item['cash_merchandise_amount'],false);
                if((int)$item['refunded_cents']!==$cash) {
                    throw new DomainException('Refund and reconcile all cash merchandise first.');
                }
                if(CreditService::parseCents((string)$item['seller_payout_amount'])!==0
                    || CreditService::parseCents((string)$item['platform_commission_amount'])!==0) {
                    throw new DomainException('Item earnings must be reconciled to zero first.');
                }
                $cashItems+=$cash;
                $marketItems+=CreditService::parseCents((string)$item['marketplace_credit_applied'],false);
                $prizeItems+=CreditService::parseCents((string)$item['prize_credit_applied'],false);
            }

            $tax=CreditService::parseCents((string)$order['tax_amount'],false);
            $taxCredit=CreditService::parseCents((string)$order['marketplace_credit_tax'],false);
            $total=CreditService::parseCents((string)$order['total'],false);
            if($taxCredit>$tax || $taxRefunded!==$tax-$taxCredit
                || $cashItems+$tax-$taxCredit!==$total || $highest!==$total
                || $marketItems+$taxCredit!==$market || $prizeItems!==$prize) {
                throw new DomainException('Cash refunds and funding snapshots do not reconcile.');
            }
            if($total===0 && CreditService::parseCents((string)$order['stripe_paid_amount'])!==0) {
                throw new DomainException('Unexpected Stripe payment requires review.');
            }

            foreach(DB::rows(
                'select * from seller_payouts where order_id=? order by id for update',
                [$orderId]
            ) as $payout) {
                if(CreditService::parseCents((string)$payout['seller_payout_amount'])!==0
                    || CreditService::parseCents((string)$payout['recovery_reserved_amount'])!==0
                    || $payout['payout_execution_status']==='attempting') {
                    throw new DomainException('Seller payout reconciliation must finish first.');
                }
            }

            // The order lock serializes requests. This audit and all restoration
            // changes commit together, or are rolled back together.
            DB::exec(
                'insert into admin_logs(admin_user_id,action,entity_type,entity_id,metadata)
                 values (?,"completed_full_credit_refund","order",?,?)',
                [$adminId,$orderId,'{}']
            );
            $auditId=(int)DB::id();
            $marketRestored=(new CreditService())->restoreRefundedCredit(
                (int)$order['user_id'],$orderId
            );
            $prizeRestored=(new PrizeCreditService())->restoreRefundedCredit(
                (int)$order['user_id'],$orderId
            );
            if($prize>0 && !DB::row(
                'select id from prize_credit_transactions
                 where order_id=? and user_id=? and type="finalize"
                   and status="finalized" limit 1',
                [$orderId,$order['user_id']]
            )) {
                throw new DomainException('Original Prize Credit redemption is unavailable.');
            }

            DB::exec(
                'update orders set status="refunded",payment_status="refunded",
                 refunded_at=coalesce(refunded_at,now()) where id=?',[$orderId]
            );
            DB::exec(
                'update order_items set manual_delivery_status="cancelled_refunded"
                 where order_id=? and fulfillment_type="google_drive"',[$orderId]
            );
            // Same protected delivery transition used by the existing refund path.
            (new CustomDesignService())->systemTransitionByOrder(
                $orderId,'refunded','stripe_refund'
            );
            DB::exec('update admin_logs set metadata=? where id=?',[
                json_encode([
                    'order_id'=>$orderId,'cash_refunded_cents'=>$total,
                    'marketplace_restored_cents'=>$marketRestored,
                    'prize_restored_cents'=>$prizeRestored,
                    'reason'=>'Administrator completed full order credit refund'
                ],JSON_THROW_ON_ERROR),$auditId
            ]);
            NotificationService::create(
                (int)$order['user_id'],'credit_refund','buyer',
                'Your order has been refunded',
                'Order #'.$orderId.' is fully refunded. All credit used on this order has been restored: '
                    .'Marketplace Credit $'.CreditService::formatCents($market)
                    .' and Prize Credit $'.CreditService::formatCents($prize)
                    .'. Download access and further delivery for this order are closed.',
                'order:'.$orderId.':full-credit-refund:notification',
                '/dashboard/order/'.$orderId
            );
            $buyer=DB::row('select email,name from users where id=?',[$order['user_id']]);
            if($buyer) EmailQueueService::queue(
                'transactional',$buyer['email'],'Your order credit refund is complete',
                'full_credit_refund',[
                    'name'=>$buyer['name'],'order_id'=>$orderId,
                    'marketplace_credit'=>CreditService::formatCents($market),
                    'prize_credit'=>CreditService::formatCents($prize)
                ],'order:'.$orderId.':full-credit-refund:email'
            );
            if($owns) DB::commit();
            return [
                'replay'=>false,'marketplace_cents'=>$marketRestored,
                'prize_cents'=>$prizeRestored
            ];
        } catch(Throwable $error) {
            if($owns && DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
    }
}
