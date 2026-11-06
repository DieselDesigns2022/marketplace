
<style>
/* Larger, wrapping product fields for phone and tablet editing. */
.seller-mobile-editor {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.5;
    resize: vertical;
}
@media (max-width: 767px) {
    .seller-mobile-title {
        min-height: 7rem;
    }
    .seller-mobile-short {
        min-height: 9rem;
    }
    .seller-mobile-full {
        min-height: 15rem;
    }
    .seller-mobile-tags {
        min-height: 10rem;
    }
    .seller-mobile-editor {
        font-size: 16px;
        padding: 0.85rem;
        overflow-x: hidden;
    }
}
</style>

<h1>
<?= $p?'Edit':'Create' ?> Product</h1>
<?php foreach($errors??[] as $error):?>
    <div class="notice error">
        <?=H::e($error)?>
    </div>
<?php endforeach;?>
<?php if(!empty($importReviewErrors)):?><div class="notice warning"><strong>This imported draft still requires explicit review:</strong><ul><?php foreach($importReviewErrors as $error):?><li><?=H::e($error)?></li><?php endforeach;?></ul><p>Review the form below and save it to confirm these Creative Moth settings.</p></div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <h2>Basic Information</h2>
    <label>Product Title<textarea name="title" required maxlength="190" rows="3" data-slug-source data-character-counter class="seller-mobile-editor seller-mobile-title"><?=H::e($_POST['title']??$p['title']??'') ?></textarea>
    </label>
    <p class="help-text">The public product URL is generated automatically from the product title.</p>
    <label>Short Description<textarea name="short_description" rows="5" class="seller-mobile-editor seller-mobile-short">
    <?=H::e($_POST['short_description']??$p['short_description']??'')?>
    </textarea>
    </label>
    <label>Full Description<textarea name="description" required rows="10" class="seller-mobile-editor seller-mobile-full">
    <?=H::e($_POST['description']??$p['description']??'')?>
    </textarea>
    </label>
    <h2>Product Preview Images</h2>

    <?php if($images):?>
        <p class="help-text">
            Drag thumbnails to arrange their order.
            The first image becomes the main product image.
        </p>

        <div class="seller-preview-grid" data-image-sort-grid>
            <?php foreach($images as $index=>$img):?>
                <div class="seller-preview-card"
                     data-image-card
                     data-image-id="<?=(int)$img['id']?>"
                     draggable="true">

                    <div class="seller-preview-photo">
                        <img
                            src="<?=H::e($img['image_path'])?>"
                            alt="<?=H::e($img['alt_text']??'Product preview')?>"
                            draggable="false"
                        >
                    </div>

                    <div class="seller-preview-position">
                        <span data-image-position>
                            <?=($index+1)?>
                        </span>
                        <span class="seller-preview-drag-handle">
                            ☰ Drag
                        </span>
                    </div>

                    <div class="seller-preview-actions">
                        <?php if(!empty($img['original_image_path'])):?>
                            <button type="submit"
                                    name="regenerate_image"
                                    value="<?=(int)$img['id']?>"
                                    formnovalidate>
                                Regenerate
                            </button>
                        <?php endif;?>

                        <button type="submit"
                                name="delete_image"
                                value="<?=(int)$img['id']?>"
                                formnovalidate
                                onclick="return confirm('Delete this preview image?');">
                            Delete
                        </button>
                    </div>

                    <?php if(!empty($img['watermark_error'])):?>
                        <small class="help-text">
                            <?=H::e($img['watermark_error'])?>
                        </small>
                    <?php endif;?>
                </div>
            <?php endforeach;?>
        </div>

        <input type="hidden"
               name="image_order"
               data-image-order
               value="<?=H::e(implode(',',array_column($images,'id')))?>">

        <?php if(count($images)>1):?>
            <button type="submit"
                    class="btn"
                    name="save_image_order"
                    value="1"
                    formnovalidate>
                Save Image Order
            </button>
        <?php endif;?>
    <?php endif;?>

    <script>
    (() => {
        const grid=document.querySelector('[data-image-sort-grid]');
        const field=document.querySelector('[data-image-order]');
        if(!grid || !field)return;

        let dragging=null;

        function sync() {
            const cards=[...grid.querySelectorAll('[data-image-card]')];
            field.value=cards.map(card=>card.dataset.imageId).join(',');
            cards.forEach((card,index)=>{
                card.querySelector('[data-image-position]').textContent=
                    String(index+1);
            });
        }

        grid.querySelectorAll('[data-image-card]').forEach(card=>{
            card.addEventListener('dragstart',event=>{
                if(event.target.closest('button')){
                    event.preventDefault();
                    return;
                }

                dragging=card;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed='move';
                event.dataTransfer.setData(
                    'text/plain',
                    card.dataset.imageId
                );
            });

            card.addEventListener('dragover',event=>{
                if(!dragging || dragging===card)return;
                event.preventDefault();

                const rect=card.getBoundingClientRect();
                const after=event.clientX>rect.left+rect.width/2;

                if(after)card.after(dragging);
                else card.before(dragging);

                sync();
            });

            card.addEventListener('drop',event=>{
                event.preventDefault();
                sync();
            });

            card.addEventListener('dragend',()=>{
                card.classList.remove('is-dragging');
                dragging=null;
                sync();
            });
        });

        sync();
    })();
    </script>

    <p class="help-text">Public preview images are watermarked automatically. JPG, PNG, or WEBP up to 25MB each. Purchased/downloadable files below are never watermarked or altered.</p>
    <?php
    $extraProtectionChecked =
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? isset($_POST['extra_protection_watermark'])
            : !empty($p['extra_protection_watermark']);
    ?>

    <label class="checkbox-option">
        <input
            type="checkbox"
            name="extra_protection_watermark"
            value="1"
            <?=$extraProtectionChecked ? 'checked' : ''?>
        >
        <strong>Extra preview protection</strong>
    </label>

    <p class="help-text">
        Adds an additional transparent protection layer across the entire
        preview image. The standard centered Creative Moth watermark remains
        on top. Downloadable product files are never altered.
    </p>
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
    <?php if(in_array('fulfillment',$importReviewKeys??[],true)):?><label><input type="checkbox" name="confirm_import_fulfillment" value="1"> I reviewed and confirm this Creative Moth fulfillment choice.</label><?php endif;?>
    <?php if(in_array('source_type',$importReviewKeys??[],true)):?><div class="notice warning">Imported source type: <strong><?=H::e($importReviewContext['source_type']??'Unknown')?></strong>. Creative Moth did not retrieve any source download file.</div><label><input type="checkbox" name="confirm_import_source_type" value="1"> I reviewed this source type and configured an eligible Creative Moth product.</label><?php endif;?>
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
    <?php if(!empty($p['id'])): ?>
    <p>
        <a class="btn" href="/seller/product/<?=(int)$p['id']?>/sale">
            Manage Individual Product Sale
        </a>
    </p>
    <?php endif; ?>
    <?php $priceValue=array_key_exists('price',$_POST)?$_POST['price']:($p?(($p['price']??null)===null?'':$p['price']):'5.00'); ?>
    <label>Base Price<input type="number" step="0.01" min="0" name="price" value="<?=H::e($priceValue)?>">
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
    <?php if(in_array('licenses',$importReviewKeys??[],true)):?><label><input type="checkbox" name="confirm_import_licenses" value="1"> I reviewed and confirm these Creative Moth license settings.</label><?php endif;?>
    <p>Digital Resale: always prohibited.</p>
    <h2>Product Details</h2>
    <label>Category<select name="category_id" data-category-guidance-source>
    <option value="">None</option>
    <?php foreach($cats as $c):?>
        <option value="<?=$c['id']?>" data-slug="<?=H::e($c['slug'])?>" <?=(string)($p['category_id']??'')===(string)$c['id']?'selected':''?>>
        <?=H::e($c['name'])?>
        </option>
    <?php endforeach;?>
    </select>
    </label>
    <p class="notice warning" data-category-guidance="freebies" hidden>Freebies is an active category, but $0.00 checkout is not supported yet. Existing paid checkout rules still apply.</p>
    <p class="notice warning" data-category-guidance="digital-services" hidden>Digital Services must use the existing downloadable or Google Drive / manual-delivery choice.</p>
    <p class="notice warning" data-category-guidance="customs-personalized" hidden>Customs / Personalized products must use the existing downloadable or Google Drive / manual-delivery choice.</p>

    <?php $selectedStoreSection=$_POST['store_section_id']??$p['store_section_id']??''; ?>

    <label>
        Shop Section
        <select name="store_section_id">
            <option value="">All Listings / No Section</option>

            <?php foreach(($shopSections??[]) as $section): ?>
                <option
                    value="<?=(int)$section['id']?>"
                    <?=(string)$selectedStoreSection===(string)$section['id']?'selected':''?>
                >
                    <?=H::e($section['name'])?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <p class="help-text">
        Optional. Customers can click shop sections on your public storefront
        to view only the products inside that section.
    </p>
    <label>Tags<textarea name="tags" rows="4" class="seller-mobile-editor seller-mobile-tags"><?=H::e($_POST['tags']??$tagText??'')?></textarea>
    </label>
<label>AI Disclosure<select name="ai_disclosure"<?=in_array('ai',$importReviewKeys??[],true)?'':' required'?>>
<option value="">Choose AI disclosure</option>
<?php foreach(['No AI Used','AI Assisted','AI Generated'] as $ai):?>
    <option <?=($_POST['ai_disclosure']??$p['ai_disclosure']??'')===$ai?'selected':''?>>
    <?=$ai?>
    </option>
<?php endforeach;?>
</select>
</label>

<?php
$handDrawnChecked = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    ? isset($_POST['is_hand_drawn'])
    : !empty($p['is_hand_drawn']);
?>
<label class="checkbox-option">
    <input
        type="checkbox"
        name="is_hand_drawn"
        value="1"
        <?=$handDrawnChecked ? 'checked' : ''?>
    >
    <strong>✏️ Hand Drawn</strong>
</label>
<p class="help-text">
    Select this only when the main artwork was personally drawn or illustrated
    by you. Do not select it for photographs, mockups, fonts, templates,
    edited stock images, or designs created only by arranging premade elements.
</p>

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
    <p>Automated matching may be incorrect and cannot identify every legal issue. Creative Moth does not provide legal advice. You are responsible for confirming that you have the right to sell your product.</p>
    <?php if(!empty($ipRisk['requires_confirmation'])): ?>
        <p class="help-text">Confirmation is required before publishing or submitting this flagged product for review. Draft saves are allowed without confirmation.</p>
        <label class="ip-rights-confirmation"><input type="checkbox" name="ip_rights_confirmation" value="1"> <span>I confirm I have the legal right to sell this design and any included wording, artwork, or references.</span></label>
    <?php else: ?>
        <p class="help-text">The latest scan already has a seller confirmation or does not currently require another confirmation.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php
$scheduleZone=$_POST['publication_timezone']??$p['publication_timezone']??$d['timezone']??'';
$scheduleLocal=$_POST['scheduled_local']??'';
if($scheduleLocal===''&&!empty($p['scheduled_publish_at'])&&$scheduleZone){
    $scheduleLocal=(new \DateTimeImmutable($p['scheduled_publish_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($scheduleZone))->format('Y-m-d\TH:i');
}
$publishMode=$_POST['publish_mode']??(!empty($p['scheduled_publish_at'])?'scheduled':'immediate');
?>
<h2>Publication</h2>
<?php if(!empty($p['schedule_error'])):?><div class="notice warning">Publication needs attention: <?=H::e($p['schedule_error'])?></div><?php endif;?>
<label>Publication option<select name="publish_mode">
<option value="immediate" <?=$publishMode==='immediate'?'selected':''?>>Publish immediately</option>
<option value="scheduled" <?=$publishMode==='scheduled'?'selected':''?>>Schedule publication</option>
<?php if(!empty($p['scheduled_publish_at'])):?><option value="cancel" <?=$publishMode==='cancel'?'selected':''?>>Cancel schedule and keep as draft</option><?php endif;?>
</select></label>
<label>Your publication timezone<select name="publication_timezone" id="publication-timezone">
<option value="">Choose your timezone</option>
<?php foreach(\DateTimeZone::listIdentifiers() as $zone):?><option value="<?=H::e($zone)?>" <?=$scheduleZone===$zone?'selected':''?>><?=H::e($zone)?></option><?php endforeach;?>
</select></label>
<label>Publication date/time in the selected timezone<input type="datetime-local" name="scheduled_local" value="<?=H::e($scheduleLocal)?>"></label>
<p class="help-text">The timezone above is saved as your preference. Choose a future date/time to schedule. Saving a draft does not submit a new listing. Publication requires all product and IP reviews to be complete.</p>
<script>
(() => { const select=document.getElementById('publication-timezone'); if(!select.value){const zone=Intl.DateTimeFormat().resolvedOptions().timeZone;if([...select.options].some(o=>o.value===zone))select.value=zone;} })();
</script>
<button name="action" value="draft">Save Product / Draft</button>
<button class="btn" name="action" value="review">Publish / Schedule Product</button>
</form>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
