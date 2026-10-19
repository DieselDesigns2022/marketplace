<h1>Secure checkout</h1>
<p class="notice warning">
Creative Moth calculates US sales tax before applying credits.
Prize Credit applies to eligible merchandise only and never pays sales tax.
Marketplace Credit is applied after Prize Credit. Stripe securely collects
anything still due.
</p>
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
      <?php if(!empty($couponResult) && !empty($couponResult['ok'])):?><p>Coupon <?=H::e($couponResult['coupon']['code'])?>: <strong>-<?=H::money($discount)?></strong></p><?php endif;?>
      <p class="muted">Sales tax calculated at checkout when required. Creative Moth is currently available for US purchases only. International checkout will be added in a future expansion.</p>
      <p>
        Tax:
        <strong>calculated as applicable</strong>
      </p>

      <p>
        Available Prize Credit:
        <strong><?=H::money($prizeBalances['available']??0)?></strong>
      </p>

      <p>
        Eligible Prize Credit on this cart before tax:
        <strong>
          <?=H::money(
              ((int)($prizePreview['total_cents']??0))/100
          )?>
        </strong>
      </p>

      <p>
        Available Marketplace Credit:
        <strong><?=H::money($balances['available']??0)?></strong>
      </p>

      <h2>
        Subtotal − coupon − Prize Credit + tax − Marketplace Credit
        = final total
      </h2>

      <p>
        Before tax and selected credits:
        <strong><?=H::money($finalTotal ?? $subtotal)?></strong>
      </p>
    </div>
    <form method="post" class="form card">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <fieldset>
          <legend>US billing address for sales tax</legend>
          <label>Address line 1<input name="billing_line1" required autocomplete="billing address-line1" value="<?=H::e($_POST['billing_line1'] ?? '')?>"></label>
          <label>Address line 2<input name="billing_line2" autocomplete="billing address-line2" value="<?=H::e($_POST['billing_line2'] ?? '')?>"></label>
          <label>City<input name="billing_city" required autocomplete="billing address-level2" value="<?=H::e($_POST['billing_city'] ?? '')?>"></label>
          <label>State<input name="billing_state" required maxlength="2" autocomplete="billing address-level1" value="<?=H::e($_POST['billing_state'] ?? '')?>"></label>
          <label>ZIP code<input name="billing_postal_code" required autocomplete="billing postal-code" inputmode="numeric" value="<?=H::e($_POST['billing_postal_code'] ?? '')?>"></label>
          <input type="hidden" name="billing_country" value="US">
        </fieldset>
        <label>
          <input
            type="checkbox"
            name="use_prize_credit"
            value="1"
            <?=(
                (float)($prizeBalances['available']??0)>0
                && (int)($prizePreview['total_cents']??0)>0
            )?'':'disabled'?>
          >
          Use available Prize Credit first
        </label>

        <p class="help-text">
          Prize Credit applies only to merchandise from participating
          sellers. It cannot pay sales tax. Each seller's selected
          per-order limit is enforced automatically.
        </p>

        <label>
          <input
            type="checkbox"
            name="use_credits"
            value="1"
            <?=((float)($balances['available']??0)>0)?'':'disabled'?>
          >
          Use available Marketplace Credit after Prize Credit
        </label>

        <p class="help-text">
          Marketplace Credit is applied after Prize Credit.
          If the final amount becomes $0.00, checkout completes securely
          without Stripe.
        </p>
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
          <p class="help-text">Sellers use this email to manually grant Google Drive access outside Creative Moth.</p>
        <?php endif;?>
        <button class="btn">Calculate tax and complete checkout</button>
    </form>
<?php endif;?>
