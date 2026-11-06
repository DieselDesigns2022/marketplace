<h1>Cart</h1>
<?php if(!empty($pricing['notice'])): ?><p class="notice warning"><?=H::e($pricing['notice'])?></p><?php endif; ?>
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
           <?php $isCustom=(($p['listing_type']??'product')==='custom_design'); ?>
           <tr>
               <td>
               <?php if($p['thumbnail']):?>
                   <img class="thumb" src="<?=H::e($p['thumbnail'])?>" alt="<?=H::e($p['title'])?>">
               <?php endif;?>
               <a href="<?=$isCustom?'/custom-design/':'/product/'?><?=H::e($p['slug'])?>">
               <?=H::e($p['title'])?>
               </a>
               <?php if($isCustom):?>
                   <br><span class="badge">Custom Design</span>
                    <?php
                    $missingCustomAnswers = null;
                    if (
                        !empty($p['custom_service_id']) &&
                        !empty($p['custom_checkout_token']) &&
                        H::user()
                    ) {
                        try {
                            $missingCustomAnswers =
                                \App\Services\CustomCartReadinessService::missingAnswers(
                                    (int)H::user()['id'],
                                    (int)$p['custom_service_id'],
                                    (string)$p['custom_checkout_token']
                                );
                        } catch (\Throwable $readinessError) {
                            error_log(
                                'Custom cart readiness: '.
                                $readinessError->getMessage()
                            );
                        }
                    }
                    ?>
                    <?php if (
                        $missingCustomAnswers === null ||
                        $missingCustomAnswers > 0
                    ): ?>
                        <div class="notice warning">
                            <strong>Action needed before checkout</strong>
                            <p>
                                <?php if ($missingCustomAnswers !== null): ?>
                                    This custom design is missing
                                    <?=(int)$missingCustomAnswers?>
                                    required answer(s).
                                <?php else: ?>
                                    The saved details for this custom design
                                    could not be verified.
                                <?php endif; ?>
                            </p>
                            <p>
                                Remove this item, reopen its listing,
                                complete all required questions,
                                and add it to your cart again.
                            </p>
                            <a href="/custom-design/<?=H::e($p['slug'])?>">
                                Open custom-design listing
                            </a>
                        </div>
                    <?php endif; ?>
               <?php endif;?>
               </td>
               <td>
               <a href="/store/<?=H::e($p['store_slug'])?>">
               <?=H::e($p['display_name'])?>
               </a>
               </td>
               <td>
               <?php if(!empty($p['license_invalid'])):?><span class="muted">Unavailable</span><?php else:?><?php if((float)($p['go_regular_price']??$p['price'])>(float)$p['price']): ?><del><?=H::money($p['go_regular_price'])?></del> <?php endif; ?><?=H::money($p['price'])?><?php endif;?>
               </td>
               <td>
               <?php if($isCustom):?>
                   <strong><?=H::e($p['license_name'] ?? 'Personal Use')?></strong>

                   <?php if((float)($p['license_price']??0)>0):?>
                       <br>
                       <span class="muted">
                           License add-ons:
                           <?=H::money($p['license_price'])?>
                       </span>
                   <?php endif;?>

                   <?php if(!empty($p['license_invalid'])):?>
                       <p class="notice error">
                           <?=H::e(
                               $p['custom_checkout_error']
                               ??'This Custom Design request is no longer available. Remove it and add it again.'
                           )?>
                       </p>
                   <?php endif;?>
               <?php else:?>
               <form method="post" action="/cart/update">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <p class="help-text license-help-note">Hover over ? for a quick preview or click ? to open the full license details.</p>
                    <?php foreach($p['licenses'] as $license):?>
                       <label><input type="checkbox" name="license_type[<?=$p['cart_item_id']?>][]" value="<?=H::e($license['license_key'])?>" <?=in_array($license['license_key'], $p['selected_license_keys'] ?? [], true)?'checked':''?> <?=$license['license_key']==='personal'?'checked disabled':''?>> <?=H::e($license['name'])?> <?php if($license['license_key']==='personal'):?><span class="muted">included/free</span><?php else:?><span class="muted">+<?=H::money(\App\Services\GrandOpeningPricingService::component(\App\Services\CreditService::parseCents((string)$license['price'],false),(int)($p['go_applied_points']??0))/100)?></span><?php endif;?><?php if($license['description']):?><span class="license-help" role="button" tabindex="0" aria-label="<?=H::e($license['name'])?> license details"><span class="license-help-icon">?</span><span class="license-help-text"><?=H::e($license['description'])?></span></span><?php endif;?></label>
                       <?php if($license['license_key']==='personal'):?><input type="hidden" name="license_type[<?=$p['cart_item_id']?>][]" value="personal"><?php endif;?>
                   <?php endforeach;?>
                   <?php if(!empty($p['license_invalid'])):?><p class="notice error">This license is no longer available. Please choose another license.</p><?php endif;?>
                   <button>Update</button>
               </form>
               <?php endif;?>
               </td>
               <td><?=H::e($p['fulfillment_label'] ?? 'Downloadable Product')?></td>
               <td>
               <?php if($isCustom):?>
                   <span class="muted">
                       Made to order
                   </span>
               <?php else:?>
               <?=$p['pod_allowed']?'POD allowed':'POD not allowed'?>
               <br>
               <span class="badge ai">
               <?=H::e($p['ai_disclosure'])?>
               </span>
               <?php if(!empty($p['is_hand_drawn'])):?>
               <br>
               <span class="badge hand-drawn">✏️ Hand Drawn</span>
               <?php endif;?>
               <?php endif;?>
               </td>
               <td>
               <?=H::money($p['line_total'])?>
               <?php if(!empty($p['go_applied_points'])): ?><br><span class="badge ok">Grand Opening Sale</span><?php endif; ?>
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
        <strong><?=H::money(($pricing['regular_cents']??0)/100)?></strong>
      </p>

      <?php if(!empty($pricing['sale_savings_cents'])): ?><p class="cart-summary-row"><span>Grand Opening Sale</span><strong>-<?=H::money($pricing['sale_savings_cents']/100)?></strong></p><?php endif; ?>
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

      <?php endif;?>

      <?php if(!empty($_SESSION['coupon_code'])): ?>
        <form method="post" action="/cart/coupon/remove"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="coupon-action">Remove coupon</button></form>
      <?php endif; ?>
      <p>Sale prices apply first. You can use one eligible coupon on top of the sale price.</p>
      <p><a class="btn" href="/checkout">Checkout</a></p>
    </div>
    </div>
<?php endif;?>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
