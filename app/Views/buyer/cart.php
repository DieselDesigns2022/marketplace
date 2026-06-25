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
           <th>Base</th>
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
               <?=H::money($p['price'])?>
               </td>
               <td>
               <form method="post" action="/cart/update">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <select name="license_type[<?=$p['cart_item_id']?>]">
                   <option value="personal">Personal use included</option>
                   <?php if($p['commercial_license_enabled']):?>
                       <option value="commercial" <?=$p['commercial_selected']?'selected':''?>>Commercial +<?=H::money($p['commercial_license_price'])?>
                       </option>
                   <?php endif;?>
                   </select>
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
