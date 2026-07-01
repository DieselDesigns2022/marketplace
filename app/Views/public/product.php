<nav class="breadcrumbs"><a href="/">Home</a> / <a href="/browse">Browse</a> / <?php if($p['category_slug']):?><a href="/category/<?=H::e($p['category_slug'])?>"><?=H::e($p['category_name'])?></a> / <?php endif;?><?=H::e($p['title'])?></nav>
<h1>
<?=H::e($p['title'])?>
</h1>
<div class="detail">
    <div class="gallery">
        <?php foreach($images as $i):?>
           <img src="<?=H::e($i['image_path'])?>" alt="<?=H::e($i['alt_text']??$p['title'])?>">
        <?php endforeach;?>
        <?php if(!$images):?>
           <div class="thumb big">Preview images are not available for this digital product yet.</div>
        <?php endif;?>
    </div>
    <aside class="card">
        <p>by <a href="/store/<?=H::e($p['store_slug'])?>">
        <?=H::e($p['display_name'])?>
        </a>
        </p>
        <p>Category: <?php if($p['category_slug']):?>
        <a href="/category/<?=H::e($p['category_slug'])?>">
        <?=H::e($p['category_name'])?>
        </a>
    <?php else:?>Uncategorized<?php endif;?>
        </p>
        <h2><?=H::money($p['price'])?></h2>
        <p>
        <span class="badge ai">
        <?=H::e($p['ai_disclosure'])?>
        </span>
        <span class="badge <?=$p['pod_allowed']?'ok':'no'?>">
        <?=$p['pod_allowed']?'POD allowed':'POD not allowed'?>
        </span>
        </p>
        <?php $globalLicenseTerms = str_replace('\\n', "\n", 'All licenses are non-exclusive and non-transferable. Purchasing a file gives you permission to use the file under the license purchased. It does not give you ownership of the design.\n\nAll designs remain the intellectual property of the original designer or seller.\n\nYou may not share, gift, trade, copy, upload, transfer, resell, modify for resale, or distribute the digital files unless the purchased license specifically allows it.\n\nYou may not claim the design as your own, copyright it, trademark it, register it, or use it as a logo or main brand identity.\n\nFiles must remain private and protected at all times.\n\nVisible watermarks are required on mockups, product previews, listing images, customer previews, and promotional images when displaying the design online.\n\nAny violation may result in revoked access, removal from the platform, denied future purchases, DMCA takedowns, account reports, and/or legal action.'); ?>
        <p>All license types follow Asset Moth global terms <span class="license-help" role="button" tabindex="0" aria-label="Global license terms"><span class="license-help-icon">?</span><span class="license-help-text"><?=H::e($globalLicenseTerms)?></span></span></p>
        <p>Personal use is included with the product base price.</p>
        <p>Select any additional permissions you need before adding to cart. Digital resale, file sharing, and redistribution are prohibited.</p>
        <p>Tags: <?php if($tags): ?>
        <?php foreach($tags as $tag): ?>
        <a class="badge" href="/browse?q=<?=H::e(urlencode($tag['name']))?>">
        <?=H::e($tag['name'])?>
        </a>
    <?php endforeach; ?>
<?php else: ?>None<?php endif; ?>
    </p>
    <?php if($owned):?>
        <div class="notice success">You already own this product.</div>
        <?php foreach($files as $file):?>
           <p>
           <a class="btn" href="/download/<?=$file['id']?>">Download <?=H::e($file['original_name'])?>
           </a>
           </p>
        <?php endforeach;?>
    <?php else:?>
        <form method="post" action="/cart/add/<?=$p['id']?>">
           <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
           <fieldset class="license-options" data-license-options>
               <legend>Select additional permissions</legend>
               <p class="help-text license-help-note">Hover over ? for a quick preview or click ? to open the full license details.</p>
               <?php foreach($licenses as $license):?>
                   <label>
                       <input type="checkbox" name="license_type[]" value="<?=H::e($license['license_key'])?>" <?=$license['license_key']==='personal'?'checked disabled':''?>>
                       <?php if($license['license_key']==='personal'):?><input type="hidden" name="license_type[]" value="personal"><?php endif;?>
                       <strong><?=H::e($license['name'])?></strong> <?php if($license['license_key']==='personal'):?><span class="muted">included/free</span><?php else:?><span class="muted">+<?=H::money($license['price'])?></span><?php endif;?>
                       <?php if($license['description']):?><span class="license-help" role="button" tabindex="0" aria-label="<?=H::e($license['name'])?> license details"><span class="license-help-icon">?</span><span class="license-help-text"><?=H::e($license['description'])?></span></span><?php endif;?>
                   </label>
               <?php endforeach;?>
           </fieldset>
           <button class="btn">Add to cart</button>
           <p class="help-text">Review the product details, selected permissions, POD permission, and AI disclosure before adding this digital item.</p>
        </form>
    <?php endif;?>
    <form method="post" action="/product/<?=$p['id']?>/wishlist">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <button>Add to Wishlist</button>
    </form>
</aside>
</div>

<section class="card share-card">
    <h2>Share this product</h2>
    <div class="share-actions">
        <a class="btn small" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=<?=H::e(rawurlencode($shareUrl))?>">Facebook</a>
        <a class="btn small" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?url=<?=H::e(rawurlencode($shareUrl))?>&text=<?=H::e(rawurlencode($shareText))?>">X/Twitter</a>
        <button type="button" class="btn small" data-copy-link="<?=H::e($shareUrl)?>">Copy link</button>
        <button type="button" class="btn small" data-copy-link="<?=H::e($shareText . ' ' . $shareUrl)?>">Copy for Instagram</button>
    </div>
    <p class="help-text">Sharing uses the public product URL and watermarked preview image when available.</p>
</section>
<section class="card"><h2>License and trust notes</h2><p>This is a digital download. Personal use is always included. Seller-enabled basic, commercial, POD, wholesale, fabric, VA, reseller, and extended commercial permissions may be selected as add-ons when available.</p><p><a href="/licensing-help">Read licensing help</a> or <a href="/buyer-faq">visit the buyer FAQ</a>.</p></section>
<h2>Description</h2>
<?php if($p['short_description']):?>
    <p>
    <strong>
    <?=H::e($p['short_description'])?>
    </strong>
    </p>
<?php endif;?>
<p>
<?=nl2br(H::e($p['description']))?>
</p>
<h2>More from this designer</h2>
<?php if(!$more):?><div class="card empty-state"><p>This designer does not have other approved products available yet.</p><a href="/store/<?=H::e($p['store_slug'])?>">Visit designer storefront</a></div><?php else: $products=$more; include app_path('app/Views/public/product_grid.php'); endif;?>
<h2>Related products</h2>
<?php if(!$related):?><div class="card empty-state"><p>No related approved products are available yet. Try browsing all digital designs.</p><a class="btn" href="/browse">Browse Digital Designs</a></div><?php else: $products=$related; include app_path('app/Views/public/product_grid.php'); endif;?>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
