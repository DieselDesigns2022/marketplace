<h1>Store Settings</h1>
<?php if (!empty($errors)): ?>
<div class="notice error">
    <strong>Please fix these issues:</strong>
    <ul>
        <?php foreach($errors as $error):?>
           <li>
           <?=H::e($error)?>
           </li>
        <?php endforeach;?>
    </ul>
</div>
<?php endif; ?>
<section class="store-preview card">
    <div class="storefront-banner small" <?php if(!empty($d['banner_path'])):?>style="background-image:url('<?=H::e($d['banner_path'])?>')"<?php endif; ?>>
        <?php if(empty($d['banner_path'])):?>Banner preview<?php endif;?>
    </div>
    <div class="store-preview-row">
        <div class="avatar">
           <?php if(!empty($d['avatar_path'])):?>
               <img src="<?=H::e($d['avatar_path'])?>" alt="<?=H::e($d['display_name'])?> avatar">
           <?php else:?>
               <span>
               <?=H::e(substr($d['display_name']??'S',0,1))?>
               </span>
           <?php endif;?>
        </div>
        <div>
           <h2>
           <?=H::e($d['display_name'])?>
           </h2>
           <p>
           <a href="/store/<?=H::e($d['store_slug'])?>">View public store</a>
           </p>
        </div>
    </div>
</section>
<form method="post" enctype="multipart/form-data" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label>Store display name<input name="display_name" required value="<?=H::e($d['display_name'])?>">
    </label>
    <label>Store slug<input name="store_slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?=H::e($d['store_slug'])?>">
    </label>
    <label>Bio/about section<textarea name="bio" maxlength="1200">
    <?=H::e($d['bio'])?>
    </textarea>
    </label>
    <label>Website URL<input name="website_url" value="<?=H::e($d['website_url']??'')?>" placeholder="https://example.com"></label>
    <h2>Storefront social links</h2>
    <p class="help-text">Optional public links must be valid http/https URLs. Empty links are hidden from your storefront.</p>
    <?php foreach(['facebook_url'=>'Facebook','instagram_url'=>'Instagram','tiktok_url'=>'TikTok','pinterest_url'=>'Pinterest','etsy_url'=>'Etsy','shopify_url'=>'Shopify'] as $field=>$label):?>
        <label><?=$label?> URL<input name="<?=$field?>" value="<?=H::e($d[$field]??'')?>" placeholder="https://"></label>
    <?php endforeach;?>
    <label>Legacy social notes<textarea name="social_links">
    <?=H::e($d['social_links'])?>
    </textarea>
    </label>
    <label>Store announcement<textarea name="announcement">
    <?=H::e($d['announcement'])?>
    </textarea>
    </label>
    <label>SEO title<input name="seo_title" maxlength="70" value="<?=H::e($d['seo_title']??'')?>">
    </label>
    <label>SEO description<textarea name="seo_description" maxlength="170">
    <?=H::e($d['seo_description']??'')?>
    </textarea>
    </label>
    <label>Avatar/logo upload <small>JPG, PNG, or WEBP up to 15MB.</small>
    <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <label>Banner image upload <small>JPG, PNG, or WEBP up to 15MB.</small>
    <input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <section class="card">
        <h2>Store-level sales tax</h2>
        <p class="help-text">Phase 10.3 sales tax is seller-reported and opt-in. Asset Moth marketplace facilitator tax automation and Stripe Tax automation are not enabled yet.</p>
        <label><input type="checkbox" name="sales_tax_enabled" value="1" <?=!empty($d['sales_tax_enabled'])?'checked':''?>> Collect sales tax for my whole store</label>
        <label>Tax collection state<input name="sales_tax_state" maxlength="2" pattern="[A-Za-z]{2}" value="<?=H::e($d['sales_tax_state']??'')?>" placeholder="CA"></label>
        <label>Sales tax rate (%)<input name="sales_tax_rate" type="number" min="0" max="20" step="0.01" value="<?=H::e((string)($d['sales_tax_rate']??'0.00'))?>" placeholder="7.25"></label>
        <p class="help-text">You are responsible for entering the correct sales tax rate for your store and tax obligations. Asset Moth does not calculate marketplace facilitator or Stripe Tax rates in Phase 10.3.</p>
        <label>Optional tax registration / permit number<input name="sales_tax_registration_id" maxlength="120" value="<?=H::e($d['sales_tax_registration_id']??'')?>"></label>
        <label><input type="checkbox" name="sales_tax_responsibility_confirmed" value="1" <?=!empty($d['sales_tax_responsibility_confirmed'])?'checked':''?>> <?=H::e($taxResponsibilityCopy ?? '')?></label>
        <p class="muted">Last updated: <?=H::e($d['sales_tax_updated_at'] ?? 'Never')?></p>
    </section>
    <button class="btn">Save Store Settings</button>
</form>
