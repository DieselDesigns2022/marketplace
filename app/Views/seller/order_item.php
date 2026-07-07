<h1>Seller Order Item #<?=$item['id']?></h1>
<p><a href="/seller/sales">Back to sales</a></p>
<div class="card">
  <p>Order #<?=$item['order_id']?> · <?=H::e($item['order_status'])?> · payment <?=H::e($item['payment_status'] ?? $item['order_status'])?> · <?=$item['order_created']?></p>
  <?php $paid = (($item['payment_status'] ?? $item['order_status']) === 'paid'); ?><p>Buyer: <?=H::e($paid ? $item['buyer_name'] : 'Hidden until payment clears')?> <?php if($paid):?>(<?=H::e($item['buyer_email'])?>)<?php endif;?></p>
  <p>Product: <?=H::e($item['product_title'] ?: ('Product #'.$item['product_id']))?></p>
  <p>License: <?=H::e($item['license_name'] ?: $item['license_type'])?></p>
  <p>Discounted item total: <?=H::money($item['total_price'] ?? 0)?> · Seller tax collected: <?=H::money($item['tax_amount'] ?? 0)?></p>
  <p class="muted">Platform commission is based on the discounted item total before tax. Seller payable includes collected seller tax for seller remittance.</p>
  <?php if(!empty($item['tax_snapshot'])):?><p class="muted">Tax snapshot: <?=H::e($item['tax_snapshot'])?></p><?php endif;?>
  <?php if(!empty($item['coupon_code'])):?><p>Coupon <?=H::e($item['coupon_code'])?> item discount: <?=H::money($item['coupon_discount'] ?? 0)?>. Earnings use the discounted item total.</p><?php endif;?>
  <p>Fulfillment: <?=($item['fulfillment_type']==='google_drive')?'Google Drive / Manual Delivery':'Downloadable Product'?></p>
  <?php if($item['fulfillment_type']==='google_drive'):?>
    <?php if($paid):?>
      <p>Buyer Google Drive email: <strong><?=H::e($item['buyer_google_drive_email'] ?: 'Needed')?></strong></p>
      <p>Status: <?=H::e(str_replace('_',' ', $item['manual_delivery_status']))?></p>
      <p>Instructions snapshot: <?=H::e($item['delivery_instructions_snapshot'] ?? '')?></p>
      <?php if(($item['manual_delivery_status'] ?? '') === 'delivered'):?>
        <p class="notice success">This manual delivery item has been marked delivered.</p>
        <?php if(!empty($item['delivered_at'])):?><p>Delivered at: <?=H::e($item['delivered_at'])?></p><?php endif;?>
        <?php if(!empty($item['delivery_notes'])):?><p>Delivery notes:<br><?=nl2br(H::e($item['delivery_notes']))?></p><?php endif;?>
      <?php else:?>
        <form method="post" class="form">
          <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
          <input type="hidden" name="action" value="mark_delivered">
          <label>Delivery notes<textarea name="delivery_notes"><?=H::e($item['delivery_notes'] ?? '')?></textarea></label>
          <button class="btn">Mark delivered</button>
        </form>
      <?php endif;?>
    <?php elseif(($item['payment_status'] ?? '') === 'partially_refunded'):?>
      <p class="notice warning">This order has a partial refund/payment adjustment. Delivery actions are locked until admin review.</p>
    <?php else:?>
      <p class="notice warning">Buyer delivery details unlock only after Stripe confirms payment.</p>
    <?php endif;?>
  <?php endif;?>
</div>
