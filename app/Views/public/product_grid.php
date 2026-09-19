<div class="grid products">
<?php if(!empty($gridSponsoredPromo))include app_path('app/Views/public/sponsored_card.php');?>
<?php foreach ($products as $p): ?>
<?php
$listingType = $p['listing_type'] ?? 'product';
$listingUrl = $listingType === 'custom'
    ? '/custom-design/'.($p['slug'] ?? '')
    : '/product/'.($p['slug'] ?? '');
?>
<article class="card product">
    <a href="<?=H::e($listingUrl)?>">
        <?php if (!empty($p['preview_image'])): ?>
            <img class="thumb" src="<?=H::e($p['preview_image'])?>" alt="<?=H::e($p['title'])?> preview image">
        <?php else: ?>
            <div class="thumb"><?= $listingType === 'custom' ? 'Custom design preview unavailable' : 'Digital design preview unavailable' ?></div>
        <?php endif; ?>
    </a>

    <h3>
        <a href="<?=H::e($listingUrl)?>">
            <?=H::e($p['title'])?>
        </a>
    </h3>

    <?php
    $sellerName = trim((string)($p['display_name'] ?? ''));
    $sellerSlug = trim((string)($p['store_slug'] ?? ''));
    ?>

    <p>
        by
        <?php if($sellerName !== '' && $sellerSlug !== ''):?>
            <a href="/store/<?=H::e($sellerSlug)?>"><?=H::e($sellerName)?></a>
        <?php else:?>
            <?=H::e($sellerName !== '' ? $sellerName : 'Independent designer')?>
        <?php endif;?>
    </p>
    <p><a href="/store/<?=H::e($sellerSlug)?>#seller-reviews"><?=empty($p['review_count'])?'No reviews yet':'★ '.number_format((float)$p['average_rating'],1).' ('.(int)$p['review_count'].' reviews)'?></a></p>

    <?php if(!empty($p['category_slug'])):?>
        <p>
            <a href="/category/<?=H::e($p['category_slug'])?>">
                <?=H::e($p['category_name'])?>
            </a>
        </p>
    <?php endif;?>

    <div class="product-card-badges">

        <?php if($listingType === 'custom'): ?>

            <span class="badge">Custom Design</span>

            <?php if(!empty($p['created_at']) && strtotime($p['created_at']) >= strtotime('-30 days')):?>
                <span class="badge ok">New</span>
            <?php endif;?>

            <?php if(!empty($p['turnaround_days'])):?>
                <span class="badge">
                    <?=H::e((string)$p['turnaround_days'])?> day turnaround
                </span>
            <?php endif;?>

            <?php if(isset($p['included_revisions'])):?>
                <span class="badge">
                    <?=H::e((string)$p['included_revisions'])?> revision<?=((int)$p['included_revisions'] === 1 ? '' : 's')?> included
                </span>
            <?php endif;?>

        <?php else: ?>

            <?php if(!empty($p['is_featured'])):?>
                <span class="badge rank">Featured</span>
            <?php endif;?>

            <?php if(!empty($p['created_at']) && strtotime($p['created_at']) >= strtotime('-30 days')):?>
                <span class="badge ok">New</span>
            <?php endif;?>

            <?php if(!empty($p['ai_disclosure'])):?>
                <span class="badge ai"><?=H::e($p['ai_disclosure'])?></span>
            <?php endif;?>

            <?php if(!empty($p['is_hand_drawn'])):?>
                <span class="badge hand-drawn">✏️ Hand Drawn</span>
            <?php endif;?>

            <span class="badge <?= !empty($p['pod_allowed']) ? 'ok' : 'no' ?>">
                <?= !empty($p['pod_allowed']) ? 'POD allowed' : 'No POD' ?>
            </span>

            <?php if(!empty($p['commercial_license_enabled'])):?>
                <span class="badge">Commercial available</span>
            <?php endif;?>

            <?php
            $fileTypes = array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(',', (string)($p['file_types'] ?? ''))
                    ),
                    fn($type) => $type !== ''
                )
            );
            $visibleFileTypes = array_slice($fileTypes, 0, 3);
            ?>

            <?php foreach($visibleFileTypes as $fileType):?>
                <span class="badge"><?=H::e($fileType)?></span>
            <?php endforeach;?>

            <?php if(count($fileTypes) > 3):?>
                <span class="badge">+<?=count($fileTypes) - 3?> more</span>
            <?php endif;?>

        <?php endif;?>

    </div>

    <a href="<?=H::e($listingUrl)?>" class="product-meta">
        <p><strong><?=H::money($p['price'])?></strong></p>
    </a>
</article>
<?php endforeach; ?>
</div>
