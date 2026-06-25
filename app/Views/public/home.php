<section class="hero">
    <h1>The marketplace built by designers, for designers.</h1>
    <form action="/browse">
        <input name="q" placeholder="Search SVGs, fonts, templates...">
        <button>Search</button>
    </form>
    <p>
    <a class="btn" href="/browse">Browse Designs</a>
    <a class="btn alt" href="/apply">Apply to Sell</a>
    </p>
</section>
<h2>Featured Categories</h2>
<div class="grid">
    <?php foreach($cats as $c):?>
        <a class="card" href="/category/<?=H::e($c['slug'])?>">
        <h3>
        <?=H::e($c['name'])?>
        </h3>
        <p>
        <?=H::e($c['description'])?>
        </p>
        </a>
    <?php endforeach;?>
</div>
<h2>Featured Products</h2>
<?php include app_path('app/Views/public/product_grid.php');?>
<h2>Featured Designers</h2>
<div class="grid">
    <?php foreach($designers as $d):?>
        <a class="card" href="/store/<?=H::e($d['store_slug'])?>">
        <span class="badge rank">
        <?=H::e($d['creator_rank'])?>
        </span>
        <h3>
        <?=H::e($d['display_name'])?>
        </h3>
        <p>
        <?=H::e($d['bio'])?>
        </p>
        </a>
    <?php endforeach;?>
</div>
