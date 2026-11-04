<?php use App\Core\Helpers as H; ?>
<h2>Your order credit refund is complete</h2>
<p>Hello <?=H::e($data['name'] ?? '')?>.</p>
<p>Order #<?=(int)($data['order_id'] ?? 0)?> is fully refunded.</p>
<p>Total credit restored for this order:</p>
<ul>
<li>Marketplace Credit: $<?=H::e($data['marketplace_credit'] ?? '0.00')?></li>
<li>Prize Credit: $<?=H::e($data['prize_credit'] ?? '0.00')?></li>
</ul>
<p>These are credits restored to your account, not an additional card refund.
Prize Credit retains its original expiry and spending rules.</p>
<p>Download access and further delivery for this order are closed.</p>
<p><a href="<?=H::e(H::baseUrl().'/dashboard/order/'.(int)($data['order_id'] ?? 0))?>">View your order</a></p>
