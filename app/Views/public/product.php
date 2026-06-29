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
        <h2>
        <?=H::money($p['price'])?>
        </h2>
        <p>
        <span class="badge ai">
        <?=H::e($p['ai_disclosure'])?>
        </span>
        <span class="badge <?=$p['pod_allowed']?'ok':'no'?>">
        <?=$p['pod_allowed']?'POD allowed':'POD not allowed'?>
        </span>
        </p>
        <p>Commercial License: <?=$p['commercial_license_enabled']?'Available for '.H::money($p['commercial_license_price']):'Not available'?>
        </p>
        <p>Digital resale, file sharing, and redistribution are prohibited. Review the license details before purchase.</p>
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
           <label>
           <input type="radio" name="license_type" value="personal" checked> Personal use included</label>
           <?php if($p['commercial_license_enabled']):?>
               <label>
               <input type="radio" name="license_type" value="commercial"> Add commercial license (+<?=H::money($p['commercial_license_price'])?>)</label>
           <?php endif;?>
           <button class="btn">Add to cart</button>
           <p class="help-text">Review the product details, license options, POD permission, and AI disclosure before adding this digital item.</p>
        </form>
    <?php endif;?>
    <form method="post" action="/product/<?=$p['id']?>/wishlist">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <button>Wishlist</button>
    </form>
</aside>
</div>
<section class="card"><h2>License and trust notes</h2><p>This is a digital download. Personal use is included unless the product page states otherwise. Commercial license availability, POD permission, and AI disclosure are shown before purchase.</p><p><a href="/licensing-help">Read licensing help</a> or <a href="/buyer-faq">visit the buyer FAQ</a>.</p></section>
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
