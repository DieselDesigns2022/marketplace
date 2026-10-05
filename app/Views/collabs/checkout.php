<h1>Checkout: <?=H::e($collab['title'])?></h1>
<section class="card">
    <p><strong><?=H::money($collab['price_cents'] / 100)?></strong> for one shared Collab Bundle.</p>
    <p>Available through <?=H::e($collab['sale_close_date'])?>.</p>
</section>
<form method="post" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <?php if($storefront):?><input type="hidden" name="storefront_designer_id" value="<?=(int)$storefront?>"><?php endif;?>
    <label>Platform coupon code (optional)<input name="coupon_code" value="<?=H::e($_POST['coupon_code'] ?? '')?>"></label>
    <fieldset>
        <legend>US billing address for sales tax</legend>
        <label>Address line 1<input required name="billing_line1" autocomplete="billing address-line1"></label>
        <label>Address line 2<input name="billing_line2" autocomplete="billing address-line2"></label>
        <label>City<input required name="billing_city" autocomplete="billing address-level2"></label>
        <label>State<input required maxlength="2" name="billing_state" autocomplete="billing address-level1"></label>
        <label>ZIP code<input required name="billing_postal_code" autocomplete="billing postal-code"></label>
    </fieldset>
    <label><input type="checkbox" name="use_credits" value="1" <?=((float)$balances['available'] > 0)?'':'disabled'?>> Use available store credit (<?=H::money($balances['available'])?>)</label>
    <button class="btn">Calculate tax and continue securely</button>
</form>
