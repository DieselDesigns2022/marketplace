<?php
namespace App\Services;
use App\Core\Database as DB;

/** Synchronizes current refund balances for versioned orders only. */
final class RefundLedgerService
{
    public static function sync(int $orderId): void
    {
        if(!DB::pdo()->inTransaction()){
            throw new \DomainException('Refund ledger synchronization requires a transaction.');
        }
        $order=DB::row('select * from orders where id=? for update',[$orderId]);
        if(!$order || (int)($order['financial_snapshot_version']??0)!==1)return;

        $items=DB::rows(
            'select oi.*,coalesce((
                select sum(a.merchandise_refund_cents)
                from marketplace_refund_allocations a where a.order_item_id=oi.id
             ),0) refunded_cents
             from order_items oi where oi.order_id=? order by oi.id for update',
            [$orderId]
        );
        $groups=[];$orderFee=0;
        foreach($items as $item){
            $fee=CreditService::parseCents((string)$item['platform_commission_amount'],false);
            $orderFee+=$fee;
            if($item['collab_id']!==null)continue;
            if($item['cash_merchandise_amount']===null){
                throw new \DomainException('Versioned order has no cash funding snapshot.');
            }
            $gross=max(0,CreditService::parseCents(
                (string)$item['cash_merchandise_amount'],false
            )-(int)$item['refunded_cents']);
            $net=CreditService::parseCents((string)$item['seller_payout_amount'],false);
            if($net+$fee!==$gross){
                throw new \DomainException('Refund item earnings do not reconcile.');
            }
            $earnings=DB::rows(
                'select id from seller_earnings where order_id=? and order_item_id=? for update',
                [$orderId,$item['id']]
            );
            if(count($earnings)!==1){
                throw new \DomainException('Refund earnings item link is missing or ambiguous.');
            }
            DB::exec(
                'update seller_earnings set gross_sale=?,marketplace_commission=?,seller_earning=? where id=?',
                [CreditService::formatCents($gross),CreditService::formatCents($fee),
                 CreditService::formatCents($net),$earnings[0]['id']]
            );
            $key=json_encode([$item['designer_id'],$item['product_id'],$item['custom_service_id']]);
            if(!isset($groups[$key])){
                $groups[$key]=[
                    'designer'=>$item['designer_id'],'product'=>$item['product_id'],
                    'custom'=>$item['custom_service_id'],'gross'=>0,'fee'=>0
                ];
            }
            $groups[$key]['gross']+=$gross;
            $groups[$key]['fee']+=$fee;
        }
        foreach($groups as $group){
            $rows=DB::rows(
                'select id from platform_commissions where order_id=? and designer_id=?
                 and product_id<=>? and custom_service_id<=>? and collab_id is null for update',
                [$orderId,$group['designer'],$group['product'],$group['custom']]
            );
            if(count($rows)!==1){
                throw new \DomainException('Refund commission snapshot is missing or ambiguous.');
            }
            DB::exec(
                'update platform_commissions set gross_sale=?,commission_amount=? where id=?',
                [CreditService::formatCents($group['gross']),
                 CreditService::formatCents($group['fee']),$rows[0]['id']]
            );
        }
        DB::exec('update orders set platform_commission_total=? where id=?',
            [CreditService::formatCents($orderFee),$orderId]);
    }
}
