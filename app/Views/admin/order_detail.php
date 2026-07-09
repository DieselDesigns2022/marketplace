<h1>Order #<?=$order['id']?>
</h1>
<p>Buyer: <?=H::e($order['buyer_name'])?> (<?=H::e($order['buyer_email'])?>)</p>
<p>Status: <?=H::e($order['status'])?> · Payment: <strong><?=H::e($order['payment_status'] ?? $order['status'])?></strong> · Total: <?=H::money($order['total'])?>
</p>
<?php if(!empty($order['coupon_code'])):?><p>Coupon <?=H::e($order['coupon_code'])?> saved <?=H::money($order['coupon_discount'] ?? 0)?>. Commission is based on discounted item totals.</p><?php endif;?>
<section class="card">
  <h2>Tax details</h2>
  <p>Tax collected: <strong><?=H::money($order['tax_amount'] ?? 0)?></strong></p>
  <p>Provider: <?=H::e($order['tax_provider'] ?? 'stripe_tax')?></p>
  <p>Status: <?=H::e($order['tax_status'] ?? 'pending')?></p>
  <p>Liability owner: <?=H::e($order['tax_liability_owner'] ?? 'platform')?></p>
  <?php if(!empty($order['tax_collected_at'])):?><p>Collected at: <?=H::e($order['tax_collected_at'])?></p><?php endif;?>
  <?php if(!empty($order['tax_snapshot'])):?><details><summary>Stripe tax snapshot/reference</summary><pre style="white-space:pre-wrap; overflow-wrap:anywhere;"><?=H::e($order['tax_snapshot'])?></pre></details><?php endif;?>
</section>
<section class="card"><h2>Stripe payment details</h2><p>Provider: <?=H::e($order['payment_provider'] ?? $order['payment_processor'] ?? "")?> · Session: <?=H::e($order['stripe_checkout_session_id'] ?? "")?> · Intent: <?=H::e($order['stripe_payment_intent_id'] ?? "")?> · Charge: <?=H::e($order['stripe_charge_id'] ?? "")?></p><p>Stripe amount: <?=H::e($order['stripe_amount_total'] ?? "")?> <?=H::e(strtoupper($order['stripe_currency'] ?? ""))?> · Paid: <?=H::e($order['paid_at'] ?? "")?> · Failed: <?=H::e($order['failed_at'] ?? "")?> · Refunded: <?=H::e($order['refunded_at'] ?? "")?></p><p>Manual review: <?=!empty($order['manual_review_required'])?'Required':'No'?> <?=H::e($order['manual_review_reason'] ?? "")?></p><p><a href="/admin/payment-logs">View all payment logs</a></p></section>
<table>
    <tr>
        <th>Product</th>
        <th>Designer</th>
        <th>Purchased permissions</th>
        <th>Item Total</th>
        <th>Commission</th>
        <th>Seller Payout</th><th>Payout Status</th><th>Fulfillment</th><th>Admin override</th>
    </tr>
    <?php foreach($items as $i):?>
        <tr>
           <td>
           <?=H::e($i['title'])?>
           </td>
           <td>
           <?=H::e($i['designer_name'])?> (<?=H::e($i['designer_email'])?>)</td>
           <td>
           <?=H::e($i['license_name'] ?: $i['license_type'])?><?php if(!empty($i['license_description'])):?><br><span class="muted"><?=H::e($i['license_description'])?></span><?php endif;?>
           </td>
           <td>
           <?=H::money($i['total_price'])?>
           </td>
           <td>
           <?=H::money($i['commission_amount']??($i['total_price']*$i['commission_rate']))?>
           </td>
           <td>
           <?=H::money($i['seller_payout_amount'] ?? $i['seller_earning'] ?? ($i['total_price']-($i['total_price']*$i['commission_rate'])))?>
           </td>
           <td><?=H::e($i['ledger_payout_status'] ?? $i['seller_payout_status'] ?? 'pending_payment')?><?php if(!empty($i['ledger_transfer_error']) || !empty($i['stripe_transfer_error'])):?><br><span class="badge no">transfer failed</span><br><span class="muted"><?=H::e($i['ledger_transfer_error'] ?? $i['stripe_transfer_error'])?></span><?php endif;?><br><span class="muted">Stripe: <?=H::e($i['stripe_account_status'] ?? 'not_connected')?> / <?=(!empty($i['stripe_details_submitted']) && !empty($i['stripe_payouts_enabled'])) ? 'payout-ready' : 'not payout-ready / onboarding incomplete'?></span></td>
           <td><?=H::e($i['fulfillment_type'] ?? 'downloadable')?><?php if(($i['fulfillment_type'] ?? '')==='google_drive'):?><br>Email: <?=H::e($i['buyer_google_drive_email'] ?: 'Needed')?><br>Status: <?=H::e(str_replace('_',' ', $i['manual_delivery_status']))?><?php endif;?></td>
           <td><?php if(($i['fulfillment_type'] ?? '')==='google_drive'):?><form method="post"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="override_fulfillment"><input type="hidden" name="order_item_id" value="<?=$i['id']?>"><select name="manual_delivery_status"><?php foreach(['pending_delivery','buyer_email_needed','ready_for_seller_delivery','delivered','cancelled_refunded'] as $st):?><option value="<?=$st?>" <?=$i['manual_delivery_status']===$st?'selected':''?>><?=H::e(str_replace('_',' ',$st))?></option><?php endforeach;?></select><input name="delivery_notes" value="<?=H::e($i['delivery_notes'] ?? '')?>"><button>Update</button></form><?php endif;?></td>
        </tr>
    <?php endforeach;?>
</table>
<p>
<a href="/admin/orders">Back to orders</a>
</p>

<h2>Order payment transactions</h2><table><tr><th>Type</th><th>Status</th><th>Amount</th><th>Message</th><th>Date</th></tr><?php foreach(($transactions ?? []) as $t):?><tr><td><?=H::e($t['transaction_type'])?></td><td><?=H::e($t['payment_status'])?></td><td><?=H::money($t['amount'])?> <?=H::e(strtoupper($t['currency']))?></td><td><?=H::e($t['message'] ?? '')?></td><td><?=$t['created_at']?></td></tr><?php endforeach;?></table>
