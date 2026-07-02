<h1>Payment and Stripe webhook logs</h1>
<p><a href="/admin/orders">Back to orders</a></p>
<h2>Payment transactions</h2>
<table><tr><th>ID</th><th>Order</th><th>Buyer</th><th>Type</th><th>Status</th><th>Amount</th><th>Stripe refs</th><th>Message</th><th>Date</th></tr>
<?php foreach($transactions as $t):?><tr><td><?=$t['id']?></td><td>#<?=H::e($t['order_id'])?></td><td><?=H::e($t['buyer_email'] ?? '')?></td><td><?=H::e($t['transaction_type'])?></td><td><?=H::e($t['payment_status'])?></td><td><?=H::money($t['amount'])?> <?=H::e(strtoupper($t['currency']))?></td><td><?=H::e(trim(($t['stripe_checkout_session_id'] ?? '').' '.($t['stripe_payment_intent_id'] ?? '').' '.($t['stripe_charge_id'] ?? '')))?></td><td><?=H::e($t['message'] ?? '')?></td><td><?=$t['created_at']?></td></tr><?php endforeach;?>
</table>
<h2>Webhook events</h2>
<table><tr><th>Event</th><th>Type</th><th>Status</th><th>Error</th><th>Processed</th><th>Created</th></tr>
<?php foreach($events as $e):?><tr><td><?=H::e($e['stripe_event_id'])?></td><td><?=H::e($e['event_type'])?></td><td><?=H::e($e['processing_status'])?></td><td><?=H::e($e['processing_error'] ?? '')?></td><td><?=H::e($e['processed_at'] ?? '')?></td><td><?=$e['created_at']?></td></tr><?php endforeach;?>
</table>
