<section class="hero">
    <h1>Shop digital designs from independent creators.</h1>
    <p>Asset Moth helps buyers discover downloadable graphics, templates, fonts, SVG files, printables, and creative assets from reviewed designer storefronts.</p>
    <form action="/browse">
        <input name="q" placeholder="Search SVGs, fonts, templates, graphics...">
        <button>Search</button>
    </form>
    <p><a class="btn" href="/browse">Browse Designs</a> <a class="btn alt" href="/sell">Sell on Asset Moth</a></p>
</section>
<section>
    <h2>Explore popular categories</h2>
    <p>Start with curated category pages, then use filters on browse results to narrow by license needs, AI disclosure, or POD permission.</p>
    <div class="grid">
        <?php foreach($cats as $c):?><a class="card" href="/category/<?=H::e($c['slug'])?>"><h3><?=H::e($c['name'])?></h3><p><?=H::e($c['description'] ?: 'Browse approved digital products in this category.')?></p></a><?php endforeach;?>
    </div>
</section>
<section>
    <h2>Featured products</h2>
    <p>Every public product is reviewed before it appears in the marketplace.</p>
    <?php include app_path('app/Views/public/product_grid.php');?>
</section>
<section>
    <h2>Featured designers</h2>
    <p>Visit designer stores to learn about each creator and find more of their products.</p>
    <div class="grid">
        <?php foreach($designers as $d):?><a class="card" href="/store/<?=H::e($d['store_slug'])?>"><span class="badge rank"><?=H::e($d['creator_rank'])?></span><h3><?=H::e($d['display_name'])?></h3><p><?=H::e($d['bio'] ?: 'Browse this designer’s approved digital products.')?></p></a><?php endforeach;?>
    </div>
</section>
<section class="card">
    <h2>For buyers and designers</h2>
    <div class="grid"><div><h3>Buy with clear licensing</h3><p>Product pages display personal use, optional commercial license availability, POD permission, and AI disclosure when provided.</p><a href="/licensing-help">Read licensing help</a></div><div><h3>Apply to sell</h3><p>Designers can apply for a reviewed storefront, upload products, set SEO fields, and submit products for marketplace review.</p><a href="/seller-faq">Read seller FAQ</a></div></div>
</section>
