<h1>Cart</h1>
<?php if(!$items):?>
    <p class="muted">Your cart is empty.</p>
    <p>
    <a class="btn" href="/browse">Browse products</a>
    </p>
<?php else:?>
    <table>
        <tr>
           <th>Product</th>
           <th>Designer</th>
           <th>Price</th>
           <th>License</th>
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
               <?php if(!empty($p['license_invalid'])):?><span class="muted">Unavailable</span><?php else:?><?=H::money($p['license_price'] ?? $p['line_total'])?><?php endif;?>
               </td>
               <td>
               <form method="post" action="/cart/update">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <select name="license_type[<?=$p['cart_item_id']?>]">
                   <?php foreach($p['licenses'] as $license):?>
                       <option value="<?=H::e($license['license_key'])?>" <?=$p['license_key']===$license['license_key']?'selected':''?>><?=H::e($license['name'])?> — <?=H::money($license['price'])?></option>
                   <?php endforeach;?>
                   </select>
                   <?php if(!empty($p['license_invalid'])):?><p class="notice error">This license is no longer available. Please choose another license.</p><?php endif;?>
                   <?php if($p['license_description']):?><p class="help-text"><?=H::e($p['license_description'])?></p><?php endif;?>
                   <button>Update</button>
               </form>
               </td>
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
    <h2>Subtotal <?=H::money($subtotal)?>
    </h2>
    <a class="btn" href="/checkout">Checkout</a>
<?php endif;?>
