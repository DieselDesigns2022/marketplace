<h1><?=!empty($coupon['id'])?'Edit':'Create'?> seller coupon</h1>
<form method="post" class="form card"><input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Code<input name="code" required value="<?=H::e($coupon['code'] ?? '')?>"></label>
<label>Discount type<select name="discount_type"><option value="percent" <?=($coupon['discount_type'] ?? '')==='percent'?'selected':''?>>Percent</option><option value="fixed" <?=($coupon['discount_type'] ?? '')==='fixed'?'selected':''?>>Fixed amount</option></select></label>
<label>Value<input type="number" step="0.01" min="0.01" name="discount_value" required value="<?=H::e($coupon['discount_value'] ?? '')?>"></label>
<label>Starts<input type="date" name="starts_at" value="<?=H::e(substr($coupon['starts_at'] ?? '',0,10))?>"></label><label>Ends<input type="date" name="ends_at" value="<?=H::e(substr($coupon['ends_at'] ?? '',0,10))?>"></label>
<label><input type="checkbox" name="is_active" value="1" <?=!isset($coupon['is_active']) || !empty($coupon['is_active'])?'checked':''?>> Active</label>
<label>Minimum eligible cart amount<input type="number" step="0.01" min="0" name="min_cart_amount" value="<?=H::e($coupon['min_cart_amount'] ?? '0.00')?>"></label>
<label>Total usage limit<input type="number" min="0" name="usage_limit" value="<?=H::e($coupon['usage_limit'] ?? '')?>"></label><label>Per-user usage limit<input type="number" min="0" name="per_user_limit" value="<?=H::e($coupon['per_user_limit'] ?? '')?>"></label>
<?php
    $savedProducts = array_filter(
        array_map('intval', explode(',', (string)($restrictions['product']??'')))
    );
    $selectedProducts = isset($_POST['target_mode'])
        ? array_map('intval', (array)($_POST['product_ids']??[]))
        : $savedProducts;
    $savedCollection = (string)($restrictions['collection']??'');
    $selectedCollection = (string)($_POST['collection_id']??$savedCollection);
    $legacyCategory = (string)($restrictions['category']??'');
    $targetMode = (string)($_POST['target_mode']??(
        $savedCollection!=='' ? 'collection' :
        ($savedProducts ? 'products' :
        ($legacyCategory!=='' ? 'category' : 'shop'))
    ));
?>
<section class="card" style="margin-top:1rem">
    <h2>Where can shoppers use this coupon?</h2>
    <p class="muted">Choose one option. Only eligible products receive the discount.</p>

    <label><input type="radio" name="target_mode" value="shop"
        <?=$targetMode==='shop'?'checked':''?>>
        Entire Shop — all my products
    </label>

    <label><input type="radio" name="target_mode" value="products"
        <?=$targetMode==='products'?'checked':''?>>
        Specific Products
    </label>

    <label><input type="radio" name="target_mode" value="collection"
        <?=$targetMode==='collection'?'checked':''?>>
        Shop Collection
    </label>

    <?php if($legacyCategory!==''):?>
    <label><input type="radio" name="target_mode" value="category"
        <?=$targetMode==='category'?'checked':''?>>
        Existing Category Restriction
    </label>
    <?php endif;?>

    <div data-coupon-target="products" <?=$targetMode==='products'?'':'hidden'?>>
        <p class="muted">Check the products this coupon should discount.</p>
        <div style="max-height:280px;overflow:auto;padding:.75rem;border:1px solid #ddd;border-radius:8px">
            <?php foreach(($couponProducts??[]) as $product):?>
            <label style="display:block;margin:.25rem 0">
                <input type="checkbox" name="product_ids[]"
                    value="<?=(int)$product['id']?>"
                    <?=in_array((int)$product['id'],$selectedProducts,true)?'checked':''?>>
                <?=H::e($product['title'])?>
                <small class="muted">(<?=H::e($product['status'])?>)</small>
            </label>
            <?php endforeach;?>
            <?php if(empty($couponProducts)):?>
                <p class="muted">No products available yet.</p>
            <?php endif;?>
        </div>
    </div>

    <div data-coupon-target="collection" <?=$targetMode==='collection'?'':'hidden'?>>
        <label>Choose Shop Collection
            <select name="collection_id">
                <option value="">Select a collection...</option>
                <?php foreach(($shopSections??[]) as $section):?>
                <option value="<?=(int)$section['id']?>"
                    <?=(string)$section['id']===$selectedCollection?'selected':''?>>
                    <?=H::e($section['name'])?>
                </option>
                <?php endforeach;?>
            </select>
        </label>
        <p class="muted">
            The coupon follows the collection as you add or move products.
        </p>
    </div>

    <?php if($legacyCategory!==''):?>
    <div data-coupon-target="category" <?=$targetMode==='category'?'':'hidden'?>>
        <label>Existing category IDs
            <input name="category_ids"
                value="<?=H::e($_POST['category_ids']??$legacyCategory)?>">
        </label>
    </div>
    <?php endif;?>
</section>

<script>
(function () {
    const modes = document.querySelectorAll('input[name="target_mode"]');
    function updateCouponTargets() {
        const chosen = document.querySelector(
            'input[name="target_mode"]:checked'
        )?.value || 'shop';
        document.querySelectorAll('[data-coupon-target]').forEach(panel => {
            panel.hidden = panel.dataset.couponTarget !== chosen;
        });
    }
    modes.forEach(input => input.addEventListener('change', updateCouponTargets));
    updateCouponTargets();
})();
</script>

<button class="btn">Save coupon</button></form>
