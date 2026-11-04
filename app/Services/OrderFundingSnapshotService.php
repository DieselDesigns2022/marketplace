<?php
namespace App\Services;
use App\Core\Database as DB;

final class OrderFundingSnapshotService
{
    public static function capture(int $orderId): array
    {
        if(!DB::pdo()->inTransaction()){
            throw new \DomainException('Funding snapshot requires checkout transaction.');
        }
        $order=DB::row('select * from orders where id=? for update',[$orderId]);
        if(!$order || $order['status']!=='pending'
            || $order['payment_status']!=='pending'
            || !empty($order['finalization_key'])
            || (int)$order['financial_snapshot_version']!==0){
            throw new \DomainException('Funding snapshot requires a new pending order.');
        }
        if($order['marketplace_fee_model']!=='percentage_plus_fixed'){
            throw new \DomainException('Unexpected new-order fee model.');
        }
        $credit=CreditService::parseCents((string)$order['credits_applied'],false);
        $reserved=DB::row(
            'select coalesce(sum(abs(r.amount)),0) amount
             from credit_transactions r
             where r.order_id=? and r.user_id=? and r.type="reservation"
               and not exists (
                 select 1 from credit_transactions x
                 where x.related_transaction_id=r.id
                   and x.type in ("redemption","release")
               )',
            [$orderId,$order['user_id']]
        );
        if(CreditService::parseCents((string)$reserved['amount'],false)!==$credit){
            throw new \DomainException('Marketplace Credit reservation does not match order.');
        }
        $rows=DB::rows('select * from order_items where order_id=? order by id for update',[$orderId]);
        if(!$rows) throw new \DomainException('Order has no funding items.');
        $input=[];$prize=0;
        foreach($rows as $row){
            $itemPrize=CreditService::parseCents((string)$row['prize_credit_applied'],false);
            $prize+=$itemPrize;
            $input[]=[
                'id'=>(int)$row['id'],'seller_id'=>(int)$row['designer_id'],
                'merchandise_cents'=>CreditService::parseCents((string)$row['total_price'],false),
                'prize_credit_cents'=>$itemPrize
            ];
        }
        if($prize!==CreditService::parseCents((string)$order['prize_credit_applied'],false)){
            throw new \DomainException('Prize Credit item snapshots do not match order.');
        }
        $funding=CheckoutFundingService::calculate(
            $input,CreditService::parseCents((string)$order['tax_amount'],false),$credit,
            (int)$order['marketplace_fee_basis_points'],(int)$order['marketplace_fixed_fee_cents']
        );
        if($funding['cash_total_cents']!==CreditService::parseCents((string)$order['total'],false)
            || $funding['cash_total_cents']!==(int)$order['stripe_amount_total']){
            throw new \DomainException('Order payment does not reconcile with funding snapshot.');
        }
        $earnings=DB::rows('select * from seller_earnings where order_id=? order by id for update',[$orderId]);
        $groups=[];
        foreach($earnings as $earning){
            $key=(int)$earning['designer_id'].':'.($earning['product_id']??'custom');
            $groups[$key][]=$earning;
        }
        $matches=[];
        foreach($rows as $row){
            if($row['collab_id']!==null) continue;
            $key=(int)$row['designer_id'].':'.($row['product_id']??'custom');
            if(empty($groups[$key])){
                throw new \DomainException('Checkout earnings snapshot is missing.');
            }
            $earning=array_shift($groups[$key]);
            if($earning['status']!=='pending_payment'){
                throw new \DomainException('Cannot rewrite finalized earnings.');
            }
            $matches[(int)$row['id']]=$earning;
        }
        foreach($groups as $unused){
            if($unused) throw new \DomainException('Unmatched checkout earnings snapshot.');
        }
        foreach($rows as $row){
            $item=$funding['items'][(int)$row['id']];
            DB::exec(
                'update order_items set marketplace_credit_applied=?,
                 cash_merchandise_amount=?,platform_commission_amount=?,
                 marketplace_percentage_fee_amount=?,marketplace_fixed_fee_amount=?,
                 seller_payout_amount=? where id=? and order_id=?',
                [
                    CreditService::formatCents($item['marketplace_credit_cents']),
                    CreditService::formatCents($item['gross_cents']),
                    CreditService::formatCents($item['fee_cents']),
                    CreditService::formatCents($item['percentage_fee_cents']),
                    CreditService::formatCents($item['fixed_fee_cents']),
                    CreditService::formatCents($item['seller_earnings_cents']),
                    $row['id'],$orderId
                ]
            );
            if(isset($matches[(int)$row['id']])){
                DB::exec(
                    'update seller_earnings set order_item_id=?,gross_sale=?,
                     marketplace_commission=?,seller_earning=? where id=?',
                    [
                        $row['id'],CreditService::formatCents($item['gross_cents']),
                        CreditService::formatCents($item['fee_cents']),
                        CreditService::formatCents($item['seller_earnings_cents']),
                        $matches[(int)$row['id']]['id']
                    ]
                );
            }
        }
        DB::exec(
            'update orders set financial_snapshot_version=1,
             marketplace_credit_merchandise=?,marketplace_credit_tax=?,
             platform_commission_total=?,credit_reserved=?,credit_payment_status=?
             where id=?',
            [
                CreditService::formatCents($funding['merchandise_credit_cents']),
                CreditService::formatCents($funding['tax_credit_cents']),
                CreditService::formatCents($funding['fee_cents']),
                CreditService::formatCents($credit),$credit>0?'reserved':'none',$orderId
            ]
        );
        return $funding;
    }
}
