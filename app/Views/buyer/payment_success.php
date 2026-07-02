<h1>Payment processing</h1>
<div class="notice warning">
  <p>Stripe has returned you to Asset Moth. Your payment is being confirmed by Stripe webhook before downloads or manual delivery details unlock.</p>
  <p>Current payment status: <strong><?=H::e($order['payment_status'] ?? $order['status'])?></strong></p>
</div>
<p><a class="btn" href="/dashboard/order/<?=$order['id']?>">View order status</a></p>
