<?php
use App\Core\Helpers as H;

$storefrontId =
    isset($collabStorefrontId)
        ? (int)$collabStorefrontId
        : (int)($c['host_designer_id'] ?? 0);

$collabHref =
    '/collab/'.
    H::e($c['slug']).
    (
        $storefrontId > 0
            ? '?store='.$storefrontId
            : ''
    );
?>

<article class="card product collab-card">
    <a
        href="<?=$collabHref?>"
        class="collab-card-image-link"
    >
        <img
            class="thumb collab-card-cover"
            src="<?=H::e(
                $c['cover_url']
                ?? \App\Services\CollabService::coverUrl(
                    (int)$c['id']
                )
            )?>"
            alt="<?=H::e($c['title'])?> collab bundle preview"
            loading="lazy"
            decoding="async"
        >
    </a>

    <div class="product-card-badges">
        <span class="badge">Collab Bundle</span>
    </div>

    <h3>
        <a href="<?=$collabHref?>">
            <?=H::e($c['title'])?>
        </a>
    </h3>

    <?php if(
        !empty($c['host_name']) &&
        !empty($c['host_store_slug'])
    ):?>
        <p class="collab-card-host">
            Hosted by
            <a href="/store/<?=H::e($c['host_store_slug'])?>">
                <?=H::e($c['host_name'])?>
            </a>
        </p>
    <?php endif;?>

    <p class="collab-card-meta">
        <strong>
            <?=H::money(
                ((int)$c['price_cents']) / 100
            )?>
        </strong>

        <span>
            through <?=H::e($c['sale_close_date'])?>
        </span>
    </p>

    <p class="muted collab-card-designers">
        <?=intval($c['designer_count'] ?? 0)?>
        designer<?=intval($c['designer_count'] ?? 0) === 1 ? '' : 's'?>
        included
    </p>
</article>
