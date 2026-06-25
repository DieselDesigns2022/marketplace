<div class="grid products">
<?php foreach ($products as $p): ?>
<article class="card product">

    <a href="/product/<?=H::e($p['slug'])?>">
        <?php if (!empty($p['preview_image'])): ?>
            <img class="thumb" src="<?=H::e($p['preview_image'])?>" alt="<?=H::e($p['title'])?>">
        <?php else: ?>
            <div class="thumb">Preview</div>
        <?php endif; ?>
    </a>

    <h3>
        <a href="/product/<?=H::e($p['slug'])?>">
            <?=H::e($p['title'])?>
        </a>
    </h3>

    <p>
        by
        <a href="/store/<?=H::e($p['store_slug'] ?? '')?>">
            <?=H::e($p['display_name'] ?? 'Designer')?>
        </a>
    </p>

    <a href="/product/<?=H::e($p['slug'])?>" class="product-meta">
        <p><strong><?=H::money($p['price'])?></strong></p>

        <span class="badge ai">
            <?=H::e($p['ai_disclosure'])?>
        </span>

        <span class="badge <?= $p['pod_allowed'] ? 'ok' : 'no' ?>">
            <?= $p['pod_allowed'] ? 'POD allowed' : 'No POD' ?>
        </span>
    </a>

</article>
<?php endforeach; ?>
</div>