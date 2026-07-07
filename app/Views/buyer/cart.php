<h1>Cart</h1>
<style>
.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:1.5rem;align-items:start}
.cart-items{min-width:0;overflow-x:auto}
.cart-summary{position:sticky;top:1rem}
.cart-summary-row{display:flex;justify-content:space-between;gap:1rem}
.coupon-action{background:none;border:0;color:#6d28d9;padding:0;margin-top:.35rem;font:inherit;font-weight:700;cursor:pointer;text-decoration:underline}
.coupon-action:hover{text-decoration:none}
@media (max-width: 950px){
  .cart-layout{grid-template-columns:1fr}
  .cart-summary{position:static}
}
</style>
<?php if(!$items):?>
    <p class="muted">Your cart is empty.</p>
    <p>
    <a class="btn" href="/browse">Browse products</a>
    </p>
<?php else:?>
    <div class="cart-layout">
    <div class="cart-items">
    <table>
        <tr>
           <th>Product</th>
           <th>Designer</th>
           <th>Base Price</th>
           <th>Selected Permissions</th>
           <th>Fulfillment</th>
           <th>POD / AI</th>
           <th>Total</th>
           <th>Actions</th>
        </tr>
        <?php foreach($items as $p):?>
           <tr>
               <td>
               <?php if($p['thumbnail']):?>
                   <img class="thumb" src="<?=H::e($p['thumbnail'])?>" alt="<?=H::e($p['title'])?>">
               <?php endif;?>
               <a href="/product/<?=H::e($p['slug'])?>">
               <?=H::e($p['title'])?>
               </a>
               </td>
               <td>
               <a href="/store/<?=H::e($p['store_slug'])?>">
               <?=H::e($p['display_name'])?>
               </a>
               </td>
               <td>
               <?php if(!empty($p['license_invalid'])):?><span class="muted">Unavailable</span><?php else:?><?=H::money($p['price'])?><?php endif;?>
               </td>
               <td>
               <form method="post" action="/cart/update">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <p class="help-text license-help-note">Hover over ? for a quick preview or click ? to open the full license details.</p>
                    <?php foreach($p['licenses'] as $license):?>
                       <label><input type="checkbox" name="license_type[<?=$p['cart_item_id']?>][]" value="<?=H::e($license['license_key'])?>" <?=in_array($license['license_key'], $p['selected_license_keys'] ?? [], true)?'checked':''?> <?=$license['license_key']==='personal'?'checked disabled':''?>> <?=H::e($license['name'])?> <?php if($license['license_key']==='personal'):?><span class="muted">included/free</span><?php else:?><span class="muted">+<?=H::money($license['price'])?></span><?php endif;?><?php if($license['description']):?><span class="license-help" role="button" tabindex="0" aria-label="<?=H::e($license['name'])?> license details"><span class="license-help-icon">?</span><span class="license-help-text"><?=H::e($license['description'])?></span></span><?php endif;?></label>
                       <?php if($license['license_key']==='personal'):?><input type="hidden" name="license_type[<?=$p['cart_item_id']?>][]" value="personal"><?php endif;?>
                   <?php endforeach;?>
                   <?php if(!empty($p['license_invalid'])):?><p class="notice error">This license is no longer available. Please choose another license.</p><?php endif;?>
                   <button>Update</button>
               </form>
               </td>
               <td><?=H::e($p['fulfillment_label'] ?? 'Downloadable Product')?></td>
               <td>
               <?=$p['pod_allowed']?'POD allowed':'POD not allowed'?>
               <br>
               <span class="badge ai">
               <?=H::e($p['ai_disclosure'])?>
               </span>
               </td>
               <td>
               <?=H::money($p['line_total'])?>
               </td>
               <td>
               <form method="post" action="/cart/remove/<?=$p['cart_item_id']?>">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <button>Remove</button>
               </form>
               </td>
           </tr>
        <?php endforeach;?>
    </table>
    </div>
    <div class="card cart-summary">
      <?php $discount = (!empty($couponResult) && !empty($couponResult['ok'])) ? (float)$couponResult['discount'] : 0.0; ?>
      <?php $cartTotal = max(0, round((float)$subtotal - $discount, 2)); ?>

      <h2>Order summary</h2>

      <p class="cart-summary-row">
        <span>Subtotal</span>
        <strong><?=H::money($subtotal)?></strong>
      </p>

      <?php if(!empty($couponResult) && !empty($couponResult['ok'])):?>
        <p class="cart-summary-row">
          <span>Coupon <?=H::e($couponResult['coupon']['code'])?></span>
          <strong>-<?=H::money($discount)?></strong>
        </p>
      <?php endif;?>

      <hr>

      <h2 class="cart-summary-row">
        <span>Total</span>
        <span><?=H::money($cartTotal)?></span>
      </h2>

      <form method="post" action="/cart/coupon">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <label>Coupon code <input name="coupon_code" value="<?=H::e($_SESSION['coupon_code'] ?? '')?>"></label>
        <button class="coupon-action">Apply coupon</button>
      </form>

      <?php if(!empty($couponResult) && !empty($couponResult['ok'])):?>
        <p class="notice success">Coupon <?=H::e($couponResult['coupon']['code'])?> saves <?=H::money($discount)?>.</p>
        <form method="post" action="/cart/coupon/remove"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="coupon-action">Remove coupon</button></form>
      <?php endif;?>

      <p><a class="btn" href="/checkout">Checkout</a></p>
    </div>
    </div>
<?php endif;?>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
