<div class="grid products">
<?php foreach ($products as $p): ?>
<article class="card product">
    <a href="/product/<?=H::e($p['slug'])?>"><?php if (!empty($p['preview_image'])): ?><img class="thumb" src="<?=H::e($p['preview_image'])?>" alt="<?=H::e($p['title'])?> preview image"><?php else: ?><div class="thumb">Digital design preview unavailable</div><?php endif; ?></a>
    <h3><a href="/product/<?=H::e($p['slug'])?>"><?=H::e($p['title'])?></a></h3>
    <p>by <a href="/store/<?=H::e($p['store_slug'] ?? '')?>"><?=H::e($p['display_name'] ?? 'Independent designer')?></a></p>
    <?php if(!empty($p['category_slug'])):?><p><a href="/category/<?=H::e($p['category_slug'])?>"><?=H::e($p['category_name'])?></a></p><?php endif;?>
    <div class="product-card-badges">
        <?php if(!empty($p['is_featured'])):?><span class="badge rank">Featured</span><?php endif;?>
        <?php if(!empty($p['created_at']) && strtotime($p['created_at']) >= strtotime('-30 days')):?><span class="badge ok">New</span><?php endif;?>
        <?php if(!empty($p['ai_disclosure'])):?><span class="badge ai"><?=H::e($p['ai_disclosure'])?></span><?php endif;?>
        <span class="badge <?= !empty($p['pod_allowed']) ? 'ok' : 'no' ?>"><?= !empty($p['pod_allowed']) ? 'POD allowed' : 'No POD' ?></span>
        <?php if(!empty($p['commercial_license_enabled'])):?><span class="badge">Commercial available</span><?php endif;?>
        <?php
        $fileTypes = array_values(array_filter(array_map('trim', explode(',', (string)($p['file_types'] ?? ''))), fn($type) => $type !== ''));
        $visibleFileTypes = array_slice($fileTypes, 0, 3);
        ?>
        <?php foreach($visibleFileTypes as $fileType):?><span class="badge"><?=H::e($fileType)?></span><?php endforeach;?>
        <?php if(count($fileTypes) > 3):?><span class="badge">+<?=count($fileTypes) - 3?> more</span><?php endif;?>
    </div>
    <a href="/product/<?=H::e($p['slug'])?>" class="product-meta"><p><strong><?=H::money($p['price'])?></strong></p></a>
</article>
<?php endforeach; ?>
</div>
