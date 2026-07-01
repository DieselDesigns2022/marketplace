<h1>Mock Checkout</h1>
<p>No payment processor is contacted in Phase 4. Submitting this page creates a completed mock order.</p>
<?php if(!$items):?>
    <p>Your cart is empty.</p>
<?php else:?>
    <ul>
        <?php foreach($items as $p):?>
           <li>
           <?=H::e($p['title'])?> — <?=H::e($p['license_name'] ?? $p['license_type'])?> — <?=H::money($p['line_total'])?>
           </li>
        <?php endforeach;?>
    </ul>
    <h2>Total <?=H::money($subtotal)?>
    </h2>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <button class="btn">Complete mock checkout</button>
    </form>
<?php endif;?>
