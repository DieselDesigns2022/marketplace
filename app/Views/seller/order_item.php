<h1>Seller Order Item #<?=$item['id']?></h1>
<p><a href="/seller/sales">Back to sales</a></p>
<div class="card">
  <p>Order #<?=$item['order_id']?> · <?=H::e($item['order_status'])?> · <?=$item['order_created']?></p>
  <p>Buyer: <?=H::e($item['buyer_name'])?> (<?=H::e($item['buyer_email'])?>)</p>
  <p>Product: <?=H::e($item['product_title'] ?: ('Product #'.$item['product_id']))?></p>
  <p>License: <?=H::e($item['license_name'] ?: $item['license_type'])?></p>
  <p>Fulfillment: <?=($item['fulfillment_type']==='google_drive')?'Google Drive / Manual Delivery':'Downloadable Product'?></p>
  <?php if($item['fulfillment_type']==='google_drive'):?>
    <p>Buyer Google Drive email: <strong><?=H::e($item['buyer_google_drive_email'] ?: 'Needed')?></strong></p>
    <p>Status: <?=H::e(str_replace('_',' ', $item['manual_delivery_status']))?></p>
    <p>Instructions snapshot: <?=H::e($item['delivery_instructions_snapshot'] ?? '')?></p>
    <form method="post" class="form">
      <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
      <input type="hidden" name="action" value="mark_delivered">
      <label>Delivery notes<textarea name="delivery_notes"><?=H::e($item['delivery_notes'] ?? '')?></textarea></label>
      <button class="btn">Mark delivered</button>
    </form>
  <?php endif;?>
</div>
