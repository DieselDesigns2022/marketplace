<h1>
<?= $p?'Edit':'Create' ?> Product</h1>
<?php foreach($errors??[] as $error):?>
    <div class="notice error">
        <?=H::e($error)?>
    </div>
<?php endforeach;?>
<form method="post" enctype="multipart/form-data" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <h2>Basic Information</h2>
    <label>Product Name<input name="title" required value="<?=H::e($_POST['title']??$p['title']??'') ?>" data-slug-source>
    </label>
    <label>Product Slug<input name="slug" required value="<?=H::e($_POST['slug']??$p['slug']??'') ?>" data-slug-target>
    </label>
    <label>Short Description<textarea name="short_description">
    <?=H::e($_POST['short_description']??$p['short_description']??'')?>
    </textarea>
    </label>
    <label>Full Description<textarea name="description" required>
    <?=H::e($_POST['description']??$p['description']??'')?>
    </textarea>
    </label>
    <h2>Product Preview Images</h2>
    <?php foreach($images as $img):?>
        <div class="inline">
           <img class="thumb" src="<?=H::e($img['image_path'])?>" alt="<?=H::e($img['alt_text']??'Product preview')?>">
           <span>
           <?=H::e($img['alt_text']??'')?>
           </span>
           <button name="delete_image" value="<?=$img['id']?>">Delete image</button>
        </div>
    <?php endforeach;?>
    <label>Upload preview images<input type="file" name="preview_images[]" multiple accept="image/*" data-preview-images>
    </label>
    <div data-preview-alt-fields>
        <p class="muted">Select preview images to add separate alt text for each image.</p>
    </div>
    <h2>Product Files</h2>
    <?php foreach($files as $file):?>
        <div class="inline">
           <span>
           <?=H::e($file['original_name'])?> (<?=number_format(($file['file_size']??0)/1024,1)?> KB)</span>
           <button name="delete_file" value="<?=$file['id']?>">Delete file</button>
        </div>
    <?php endforeach;?>
    <label>Protected downloadable files<input type="file" name="product_files[]" multiple>
    </label>
    <h2>Pricing and Licenses</h2>
    <label>Base Price<input type="number" step="0.01" name="price" value="<?=H::e($_POST['price']??$p['price']??'5.00')?>">
    </label>
    <p class="help-text">Enable at least one license. License prices are full buyer-facing prices, not add-ons.</p>
    <?php
        $configured = [];
        foreach (($productLicenses ?? []) as $license) $configured[$license['license_key']] = $license;
        $postedEnabled = $_POST['license_enabled'] ?? null;
        $postedDefault = $_POST['default_license_key'] ?? null;
    ?>
    <table>
        <tr>
            <th>Enabled</th>
            <th>Default</th>
            <th>License type</th>
            <th>Price</th>
            <th>Sort</th>
            <th>Description</th>
        </tr>
        <?php foreach($licenseTypes as $type):
            $key = $type['license_key'];
            $existing = $configured[$key] ?? null;
            $enabled = $postedEnabled !== null ? isset($postedEnabled[$key]) : ($existing ? true : ($key === 'personal' || ($key === 'pod' && !empty($p['pod_allowed']))));
            $default = $postedDefault !== null ? $postedDefault === $key : ($existing['is_default'] ?? $key === 'personal');
            $price = $_POST['license_price'][$key] ?? $existing['price'] ?? ($key === 'personal' ? ($_POST['price']??$p['price']??'5.00') : ($_POST['price']??$p['price']??'5.00'));
            $description = $_POST['license_description'][$key] ?? $existing['description'] ?? $type['description'] ?? '';
            $sort = $_POST['license_sort_order'][$key] ?? $existing['sort_order'] ?? $type['sort_order'] ?? 0;
        ?>
            <tr>
                <td><label><input type="checkbox" name="license_enabled[<?=H::e($key)?>]" value="1" <?=$enabled?'checked':''?>> Enable</label></td>
                <td><label><input type="radio" name="default_license_key" value="<?=H::e($key)?>" <?=$default?'checked':''?>> Default</label></td>
                <td><strong><?=H::e($type['name'])?></strong><br><span class="muted"><?=H::e($key)?></span></td>
                <td><input type="number" step="0.01" min="0" name="license_price[<?=H::e($key)?>]" value="<?=H::e($price)?>"></td>
                <td><input type="number" step="1" name="license_sort_order[<?=H::e($key)?>]" value="<?=H::e($sort)?>"></td>
                <td><textarea name="license_description[<?=H::e($key)?>]"><?=H::e($description)?></textarea></td>
            </tr>
        <?php endforeach;?>
    </table>
    <p>Digital Resale: always prohibited.</p>
    <h2>Product Details</h2>
    <label>Category<select name="category_id">
    <option value="">None</option>
    <?php foreach($cats as $c):?>
        <option value="<?=$c['id']?>" <?=(string)($p['category_id']??'')===(string)$c['id']?'selected':''?>>
        <?=H::e($c['name'])?>
        </option>
    <?php endforeach;?>
    </select>
    </label>
    <label>Tags<input name="tags" value="<?=H::e($_POST['tags']??$tagText??'')?>">
    </label>
<label>AI Disclosure<select name="ai_disclosure" required>
<?php foreach(['No AI Used','AI Assisted','AI Generated'] as $ai):?>
    <option <?=($_POST['ai_disclosure']??$p['ai_disclosure']??'')===$ai?'selected':''?>>
    <?=$ai?>
    </option>
<?php endforeach;?>
</select>
</label>
<h2>SEO Section</h2>
<label>SEO Title<input name="seo_title" value="<?=H::e($_POST['seo_title']??$p['seo_title']??'')?>">
</label>
<label>SEO Description<textarea name="seo_description">
<?=H::e($_POST['seo_description']??$p['seo_description']??'')?>
</textarea>
</label>
<button name="action" value="draft">Save Draft</button>
<button class="btn" name="action" value="review">Submit For Review</button>
</form>
