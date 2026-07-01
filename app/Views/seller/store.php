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
    <button class="btn">Save Store Settings</button>
</form>
