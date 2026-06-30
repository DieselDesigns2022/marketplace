<section class="hero">
    <p class="eyebrow">Digital designs for creative projects</p>
    <h1>Shop downloadable designs from independent creators.</h1>
    <p>Asset Moth is a digital design marketplace for SVGs, PNGs, sublimation designs, seamless patterns, templates, fonts, brushes, mockups, printables, and other creative files from reviewed designer storefronts.</p>
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
<section class="page-section">
    <h2>Featured products</h2>
    <p class="muted">Reviewed public listings appear here when products are featured for launch.</p>
    <?php if(empty($products)):?><div class="card empty-state"><h3>No featured products yet</h3><p>Featured products will appear after designers submit approved listings.</p><a class="btn" href="/browse">Browse Digital Designs</a></div><?php else: include app_path('app/Views/public/product_grid.php'); endif;?>
</section>
<section class="page-section">
    <h2>Featured designers</h2>
    <p class="muted">Approved designers can build storefronts with profiles, product previews, and reviewed listings.</p>
    <div class="grid">
        <?php foreach($designers as $d):?><a class="card" href="/store/<?=H::e($d['store_slug'])?>"><span class="badge rank"><?=H::e($d['creator_rank'])?></span><h3><?=H::e($d['display_name'])?></h3><p><?=H::e($d['bio'] ?: 'Visit this designer’s storefront to browse approved digital products.')?></p></a><?php endforeach;?>
        <?php if(empty($designers)):?><div class="card empty-state"><h3>No featured designers yet</h3><p>Designer storefronts will appear after applications and stores are approved.</p><a class="btn alt" href="/sell">Apply to Sell</a></div><?php endif;?>
    </div>
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
