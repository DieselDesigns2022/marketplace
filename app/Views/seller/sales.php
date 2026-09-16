<header class="dashboard-heading"><div><h1>Sales</h1><p class="muted">Seller-owned orders, gross sales, earnings, and payout status are shown separately.</p></div></header>
<?php if(!$sales):?><section class="card empty-state"><h2>No paid sales yet</h2><p>Paid and partially refunded seller-owned order items will appear here.</p><a class="btn" href="/seller/products">Review products</a></section><?php else:?><div class="responsive-table"><table>
    <tr>
        <th>Product</th>
        <th>Buyer</th>
        <th>Sale amount</th><th>Marketplace fee</th><th>You earned</th>
        <th>Fulfillment</th>
        <th>Payment/Payout</th><th>Actual cash / recovery</th><th>Delivery</th>
        <th>Date</th>
    </tr>
    <?php foreach($sales as $s):?>
        <tr>
           <td>
           <?=H::e($s['product_title'] ?: ('Product #'.$s['product_id']))?>
           </td>
           <td>
           <?=H::e($s['email'])?>
           </td>
           <td>
           <?=H::money($s['total_price'])?>
           </td><td><?=H::money($s['platform_commission_amount']??0)?></td><td><?=H::money($s['seller_payout_amount']??0)?></td>
           <td><?=H::e(($s['fulfillment_type']??'')==='custom_design'?'Custom Design':(($s['fulfillment_type']??'')==='google_drive'?'Google Drive / Manual Delivery':'Downloadable Product'))?></td><td><?=H::e($s['payment_status'] ?? 'paid')?> / <?=H::e(($s['seller_payout_status'] ?? $s['payout_status'] ?? '')==='platform_credit_hold'?'Platform-funded credit — admin transfer required':str_replace('_',' ',$s['seller_payout_status'] ?? $s['payout_status'] ?? 'pending'))?></td><td><?php if($s['transfer_amount_after_recovery']!==null):?>Cash <?=H::money($s['transfer_amount_after_recovery'])?><br>Recovery applied <?=H::money($s['recovery_applied_amount']??0)?><?php if((float)($s['recovery_reserved_amount']??0)>0):?><br>Reserved <?=H::money($s['recovery_reserved_amount'])?><?php endif;?><?php else:?><span class="muted">Not transferred or legacy amount unavailable</span><?php endif;?></td>
           <td><?php if(($s['fulfillment_type'] ?? '')==='google_drive'):?><?=H::e(str_replace('_',' ',$s['manual_delivery_status'] ?? ''))?><?php else:?><span class="muted">Not manual delivery</span><?php endif;?></td>
           <td><?=$s['created_at']?> · <a href="<?=($s['fulfillment_type']??'')==='custom_design'&&$s['custom_order_id']?'/seller/custom-orders/'.(int)$s['custom_order_id']:'/seller/order-item/'.(int)$s['id']?>">View</a></td>
        </tr>
    <?php endforeach;?>
</table></div><?php endif;?>
