<?php if($openingState==='live'): ?>
<section class="page-section" id="grand-opening-sale">
<div class="section-heading-row"><div><h2>Grand Opening Sale</h2>
<p class="muted">Opening deals from participating shops. Sale prices also cover paid license add-ons. We automatically apply whichever saves more: the sale or your coupon.</p></div>
<a class="btn" href="/browse?grand_opening=1">Shop All Sale Products</a></div>
<?php if($openingProducts): ?>
<?php $goSavedProducts=$products; $products=$openingProducts; include app_path('app/Views/public/product_grid.php'); $products=$goSavedProducts; unset($goSavedProducts); ?>
<?php else: ?><div class="card"><p>Participating sellers are getting their sale products ready. Check back for opening deals.</p></div><?php endif; ?>
<?php if($openingSellers): ?>
<h3>Explore participating shops</h3>
<div class="opening-shop-grid">
<?php foreach($openingSellers as $goSeller): ?>
<article class="card">
<?php if(!empty($goSeller['avatar_path'])): ?><img src="<?=H::e(H::assetUrl($goSeller['avatar_path']))?>" alt="<?=H::e($goSeller['display_name'])?> logo" width="72" height="72" style="object-fit:contain;border-radius:12px" loading="lazy"><?php endif; ?>
<h3><a href="/store/<?=H::e($goSeller['store_slug'])?>"><?=H::e($goSeller['display_name'])?></a></h3>
<p><?=number_format((int)$goSeller['discount_basis_points']/100,2)?>% off <?=$goSeller['product_scope']==='shop'?'their shop':'selected products'?></p>
<a class="btn alt" href="/browse?grand_opening=1&amp;creator=<?=H::e(rawurlencode($goSeller['store_slug']))?>">Shop Their Sale</a>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?php endif; ?>
