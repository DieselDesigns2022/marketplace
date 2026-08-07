<section class="hero">
    <p class="eyebrow">Digital designs for creative projects</p>
    <h1>Shop downloadable designs from independent creators.</h1>
    <p>Asset Moth is a digital design marketplace for SVGs, print-ready PNG files, seamless patterns, templates, fonts, brushes, mockups, printables, and other creative files from reviewed designer storefronts.</p>
    <form action="/browse">
        <input name="q" placeholder="Search SVGs, PNGs, templates, fonts..." aria-label="Search digital designs">
        <button>Search</button>
    </form>
    <p><a class="btn" href="/browse">Browse Digital Designs</a> <a class="btn alt" href="/sell">Sell on Asset Moth</a></p>
</section>
<section class="page-section">
    <h2>Explore marketplace categories</h2>
    <p class="muted">Browse by creative use case, then narrow results by category, AI disclosure, POD permission, or sort order on the browse page.</p>
    <div class="grid">
        <?php foreach($cats as $c):?><a class="card" href="/category/<?=H::e($c['slug'])?>"><h3><?=H::e($c['name'])?></h3><p><?=H::e($c['description'] ?: 'Browse approved downloadable products in this category.')?></p></a><?php endforeach;?>
        <?php if(empty($cats)):?><div class="card empty-state"><h3>Categories are being prepared</h3><p>Marketplace categories will appear here as the catalog is organized.</p></div><?php endif;?>
    </div>
</section>
<?php $featuredProducts = $products ?? []; ?>
<section class="page-section featured-products-section">
    <h2>Featured products</h2>
    <p class="muted">Reviewed public listings appear here when products are featured for launch.</p>
    <?php if(empty($products)):?><div class="card empty-state"><h3>No featured products yet</h3><p>Featured products will appear after designers submit approved listings.</p><a class="btn" href="/browse">Browse Digital Designs</a></div><?php else: include app_path('app/Views/public/product_grid.php'); endif;?>
</section>
<section class="page-section featured-designers-section">
    <div class="section-heading-row">
        <div>
            <h2>Featured designers</h2>
            <p class="muted">Discover independent designers and browse their latest digital creations.</p>
        </div>
    </div>

    <?php if(empty($designers)): ?>
        <div class="card empty-state">
            <h3>No featured designers yet</h3>
            <p>Designer storefronts will appear after applications and stores are approved.</p>
            <a class="btn alt" href="/sell">Apply to Sell</a>
        </div>
    <?php else: ?>
        <div class="featured-designer-grid">
            <?php foreach($designers as $d): ?>
                <?php
                    $displayName = $d['display_name'] ?: 'Independent Designer';
                    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                    $bio = trim((string)($d['bio'] ?? ''));
                ?>

                <article class="featured-designer-card">
                    <a
                        class="featured-designer-banner"
                        href="/store/<?=H::e($d['store_slug'])?>"
                        aria-label="Visit <?=H::e($displayName)?>"
                        <?php if(!empty($d['banner_path'])): ?>
                            style="background-image:url('<?=H::e($d['banner_path'])?>')"
                        <?php endif; ?>
                    >
                        <?php if(empty($d['banner_path'])): ?>
                            <span class="featured-banner-fallback">
                                <?=H::e($displayName)?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <div class="featured-designer-content">
                        <div class="featured-designer-top">
                            <a
                                class="featured-designer-avatar"
                                href="/store/<?=H::e($d['store_slug'])?>"
                                aria-label="Visit <?=H::e($displayName)?>"
                            >
                                <?php if(!empty($d['avatar_path'])): ?>
                                    <img
                                        src="<?=H::e($d['avatar_path'])?>"
                                        alt="<?=H::e($displayName)?> logo"
                                    >
                                <?php else: ?>
                                    <span><?=H::e($initial)?></span>
                                <?php endif; ?>
                            </a>

                        </div>

                        <h3>
                            <a href="/store/<?=H::e($d['store_slug'])?>">
                                <?=H::e($displayName)?>
                            </a>
                        </h3>

                        <p class="featured-designer-bio">
                            <?=H::e(
                                $bio !== ''
                                    ? $bio
                                    : 'Browse this designer’s approved digital products and creative resources.'
                            )?>
                        </p>

                        <div class="featured-designer-meta">
                            <span>
                                <?=number_format((int)($d['sales_count'] ?? 0))?> sales
                            </span>
                            <span>
                                <?=number_format((int)($d['follower_count'] ?? 0))?> followers
                            </span>
                            <?php if((float)($d['average_rating'] ?? 0) > 0): ?>
                                <span>
                                    ★ <?=number_format((float)$d['average_rating'], 1)?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <a
                            class="btn featured-store-button"
                            href="/store/<?=H::e($d['store_slug'])?>"
                        >
                            Visit Store
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="page-section">
    <h2>Recently added</h2>
    <p class="muted">Newest approved products from reviewed creators, shown with real marketplace data only.</p>
    <?php $products = $recentProducts ?? []; if(empty($products)):?><div class="card empty-state"><h3>No recent products yet</h3><p>Recently approved products will appear here after marketplace review.</p></div><?php else: include app_path('app/Views/public/product_grid.php'); endif; $products = $featuredProducts; ?>
</section>
<section class="card page-section">
    <h2>Built for buyers and designers</h2>
    <div class="grid"><div><h3>Browse with clear product details</h3><p>Listings show previews, descriptions, categories, tags, current license options, POD permission, and AI disclosure when provided.</p><a href="/licensing-help">Read licensing help</a></div><div><h3>Apply for a reviewed storefront</h3><p>Designers can apply to sell, customize a storefront, upload protected product files, add SEO fields, and submit listings for review.</p><a href="/seller-faq">Read seller FAQ</a></div></div>
</section>
