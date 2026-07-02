<h1>Payment not completed</h1>
<div class="notice warning">
  <p>Your Stripe payment was not completed, so no downloads or manual delivery details have been unlocked.</p>
  <p>Current payment status: <strong><?=H::e($order['payment_status'] ?? $order['status'])?></strong></p>
</div>
<form method="post" action="/checkout/retry/<?=$order['id']?>">
  <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
  <button class="btn">Retry payment</button>
  <a class="btn alt" href="/cart">Return to cart</a>
</form>
