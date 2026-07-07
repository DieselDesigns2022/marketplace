<h1><?=!empty($coupon['id'])?'Edit':'Create'?> seller coupon</h1>
<form method="post" class="form card"><input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Code<input name="code" required value="<?=H::e($coupon['code'] ?? '')?>"></label>
<label>Discount type<select name="discount_type"><option value="percent" <?=($coupon['discount_type'] ?? '')==='percent'?'selected':''?>>Percent</option><option value="fixed" <?=($coupon['discount_type'] ?? '')==='fixed'?'selected':''?>>Fixed amount</option></select></label>
<label>Value<input type="number" step="0.01" min="0.01" name="discount_value" required value="<?=H::e($coupon['discount_value'] ?? '')?>"></label>
<label>Starts<input type="date" name="starts_at" value="<?=H::e(substr($coupon['starts_at'] ?? '',0,10))?>"></label><label>Ends<input type="date" name="ends_at" value="<?=H::e(substr($coupon['ends_at'] ?? '',0,10))?>"></label>
<label><input type="checkbox" name="is_active" value="1" <?=!isset($coupon['is_active']) || !empty($coupon['is_active'])?'checked':''?>> Active</label>
<label>Minimum eligible cart amount<input type="number" step="0.01" min="0" name="min_cart_amount" value="<?=H::e($coupon['min_cart_amount'] ?? '0.00')?>"></label>
<label>Total usage limit<input type="number" min="0" name="usage_limit" value="<?=H::e($coupon['usage_limit'] ?? '')?>"></label><label>Per-user usage limit<input type="number" min="0" name="per_user_limit" value="<?=H::e($coupon['per_user_limit'] ?? '')?>"></label>
<p class="help-text">Restriction IDs are optional. Product IDs and category IDs must belong to your own approved store catalog; other sellers' IDs are ignored server-side.</p>
<label>Your product restriction IDs (comma-separated)<input name="product_ids" value="<?=H::e($restrictions['product'] ?? '')?>"></label>
<label>Your category restriction IDs (comma-separated)<input name="category_ids" value="<?=H::e($restrictions['category'] ?? '')?>"></label>
<button class="btn">Save coupon</button></form>
