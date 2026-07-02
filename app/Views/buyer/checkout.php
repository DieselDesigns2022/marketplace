<h1>Phase 9 Checkout Foundation</h1>
<p class="notice warning">No Stripe or real payment is collected in Phase 9. Submitting creates a pending-payment foundation order for cart/order/download/manual-delivery testing.</p>
<?php if(!$items):?>
    <p>Your cart is empty.</p>
<?php else:?>
    <?php $needsDrive=false; foreach($items as $p) if(($p['fulfillment_type'] ?? 'downloadable')==='google_drive') $needsDrive=true; ?>
    <table>
        <tr><th>Product</th><th>License</th><th>Fulfillment</th><th>Total</th></tr>
        <?php foreach($items as $p):?>
           <tr>
             <td><?=H::e($p['title'])?></td>
             <td><?=H::e($p['license_name'] ?? $p['license_type'])?><?php if(($p['license_price'] ?? 0)>0):?><br><span class="muted">License add-ons: <?=H::money($p['license_price'])?></span><?php endif;?></td>
             <td><?=H::e($p['fulfillment_label'] ?? 'Downloadable Product')?></td>
             <td><?=H::money($p['line_total'])?></td>
           </tr>
        <?php endforeach;?>
    </table>
    <div class="card">
      <p>Subtotal: <strong><?=H::money($subtotal)?></strong></p>
      <p>Taxes: <span class="muted">$0.00 placeholder for Phase 10+</span></p>
      <p>Credits: <span class="muted">$0.00 placeholder</span></p>
      <p>Coupons: <span class="muted">placeholder; no coupon is applied in Phase 9</span></p>
      <h2>Total <?=H::money($subtotal)?></h2>
    </div>
    <form method="post" class="form card">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <?php if($needsDrive):?>
          <div class="notice warning">
            <strong>Google Drive delivery instructions</strong>
            <?php foreach($items as $p):?>
              <?php if(($p['fulfillment_type'] ?? '')==='google_drive' && !empty($p['manual_delivery_instructions'])):?>
                <p><strong><?=H::e($p['title'])?>:</strong><br><?=nl2br(H::e($p['manual_delivery_instructions']))?></p>
              <?php endif;?>
            <?php endforeach;?>
          </div>
          <label>Google Drive email required for manual delivery<input type="email" name="google_drive_email" required value="<?=H::e($_POST['google_drive_email'] ?? H::user()['email'] ?? '')?>"></label>
          <p class="help-text">Sellers use this email to manually grant Google Drive access outside Asset Moth.</p>
        <?php endif;?>
        <button class="btn">Create pending-payment Phase 9 foundation order</button>
    </form>
<?php endif;?>
