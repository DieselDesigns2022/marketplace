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
    <label>Product Title<input name="title" required maxlength="190" value="<?=H::e($_POST['title']??$p['title']??'') ?>" data-slug-source data-character-counter>
    </label>
    <p class="help-text">The public product URL is generated automatically from the product title.</p>
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
           <span><?=H::e($img['alt_text']??'')?> · <?=H::e($img['watermark_status'] ?? 'legacy preview')?></span>
           <?php if(!empty($img['original_image_path'])):?><button name="regenerate_image" value="<?=$img['id']?>">Regenerate watermark</button><?php endif;?>
           <button name="delete_image" value="<?=$img['id']?>">Delete image</button>
           <?php if(!empty($img['watermark_error'])):?><small class="help-text">Watermark note: <?=H::e($img['watermark_error'])?></small><?php endif;?>
        </div>
    <?php endforeach;?>
    <p class="help-text">Public preview images are watermarked automatically. JPG, PNG, or WEBP up to 25MB each. Purchased/downloadable files below are never watermarked or altered.</p>
    <label>Upload preview images<input type="file" name="preview_images[]" multiple accept=".jpg,.jpeg,.png,.webp" data-preview-images>
    </label>
    <div data-preview-alt-fields>
        <p class="muted">Select preview images to add separate alt text for each image.</p>
    </div>
    <h2>Fulfillment</h2>
    <label>Fulfillment type<select name="fulfillment_type">
      <?php $ft=$_POST['fulfillment_type']??$p['fulfillment_type']??'downloadable'; ?>
      <option value="downloadable" <?=$ft==='downloadable'?'selected':''?>>Downloadable Product</option>
      <option value="google_drive" <?=$ft==='google_drive'?'selected':''?>>Google Drive / Manual Delivery Product</option>
    </select></label>
    <label>Manual delivery instructions<textarea name="manual_delivery_instructions"><?=H::e($_POST['manual_delivery_instructions']??$p['manual_delivery_instructions']??'')?></textarea></label>
    <p class="help-text">Google Drive/manual delivery products do not require a protected downloadable file. Sellers manually grant access using the buyer email collected during checkout.</p>
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
    <p class="help-text">Personal use is included with the base product price. Enable any additional paid license permissions and set the add-on price for each one.</p>
    <?php
        $configured = [];
        foreach (($productLicenses ?? []) as $license) $configured[$license['license_key']] = $license;
        $postedEnabled = $_POST['license_enabled'] ?? null;
    ?>
    <p class="help-text license-help-note">Hover over ? for a quick preview or click ? to open the full license details.</p>
    <table>
        <tr>
            <th>Enabled</th>
            <th>License type</th>
            <th>Add-on price</th>
        </tr>
        <?php foreach($licenseTypes as $type):
            $key = $type['license_key'];
            $existing = $configured[$key] ?? null;
            $enabled = $postedEnabled !== null ? isset($postedEnabled[$key]) : ($existing ? true : ($key === 'personal' || ($key === 'pod' && !empty($p['pod_allowed']))));
            $price = $key === 'personal' ? '0.00' : ($_POST['license_price'][$key] ?? $existing['price'] ?? '0.00');
            $postedDescription = $_POST['license_description'][$key] ?? null;
            $existingDescription = $existing['description'] ?? '';
            $description = $postedDescription ?? ($existingDescription !== '' ? $existingDescription : ($type['description'] ?? ''));
        ?>
            <tr>
                <td><label><input type="checkbox" name="license_enabled[<?=H::e($key)?>]" value="1" <?=$enabled?'checked':''?> <?=$key==='personal'?'checked disabled':''?>> <?=$key==='personal'?'Always included':'Enable'?></label><?php if($key==='personal'):?><input type="hidden" name="license_enabled[personal]" value="1"><?php endif;?></td>
                <td><strong><?=H::e($type['name'])?></strong><?php if($description):?><span class="license-help" role="button" tabindex="0" aria-label="<?=H::e($type['name'])?> license details"><span class="license-help-icon">?</span><span class="license-help-text"><?=H::e($description)?></span></span><?php endif;?><br><span class="muted"><?=H::e($key)?><?=$key==='personal'?' · included/free':' · optional add-on'?></span></td>
                <td><?php if($key==='personal'):?><span class="muted">$0.00 included</span><input type="hidden" name="license_price[personal]" value="0.00"><?php else:?><input type="number" step="0.01" min="0" name="license_price[<?=H::e($key)?>]" value="<?=H::e($price)?>"><?php endif;?><input type="hidden" name="license_description[<?=H::e($key)?>]" value=""></td>
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
<details class="advanced-panel"><summary>Advanced SEO (optional)</summary><p class="help-text">Leave these blank to use the product title and short description automatically.</p>
<label>SEO Title<input name="seo_title" maxlength="70" value="<?=H::e($_POST['seo_title']??$p['seo_title']??'')?>" data-character-counter></label>
<label>SEO Description<textarea name="seo_description" maxlength="170" data-character-counter><?=H::e($_POST['seo_description']??$p['seo_description']??'')?></textarea></label></details>

<?php if(!empty($ipRisk['matches'])): ?>
<section class="notice warning">
    <p><strong>This product may contain trademarked, copyrighted, or protected terms. Please confirm you own the rights, have permission, or that your use is legally allowed before publishing.</strong></p>
    <ul>
    <?php $labels=['title'=>'Title','description'=>'Description','tags'=>'Tags','seo_title'=>'SEO title','seo_description'=>'SEO description','file_name'=>'File name']; foreach($ipRisk['matches'] as $match): ?>
        <li><?=H::e($match['matched_term'])?><?php if(!empty($match['matched_alias'])): ?> (matched alias: <?=H::e($match['matched_alias'])?>)<?php endif; ?> — <?=H::e($labels[$match['source_field']] ?? $match['source_field'])?></li>
    <?php endforeach; ?>
    </ul>
    <p>Automated matching may be incorrect and cannot identify every legal issue. Asset Moth does not provide legal advice. You are responsible for confirming that you have the right to sell your product.</p>
    <?php if(!empty($ipRisk['requires_confirmation'])): ?>
        <p class="help-text">Confirmation is required before publishing or submitting this flagged product for review. Draft saves are allowed without confirmation.</p>
        <label><input type="checkbox" name="ip_rights_confirmation" value="1"> I confirm I have the legal right to sell this design and any included wording, artwork, or references.</label>
    <?php else: ?>
        <p class="help-text">The latest scan already has a seller confirmation or does not currently require another confirmation.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<button name="action" value="draft">Save Draft</button>
<button class="btn" name="action" value="review">Submit For Review</button>
</form>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
