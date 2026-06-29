<?php $isCategory = !empty($category); $selectedCategory = $_GET['category'] ?? ($isCategory ? ($category['slug'] ?? '') : ''); $selectedSort = $_GET['sort'] ?? 'newest'; ?>
<nav class="breadcrumbs"><a href="/">Home</a> / <a href="/browse">Browse</a><?php if($isCategory): ?> / <?=H::e($category['name'])?><?php endif; ?></nav>
<h1><?= $isCategory ? H::e($category['name']) : 'Browse digital designs' ?></h1>
<p><?= $isCategory ? H::e($category['description'] ?: 'Browse approved digital products in this category on Asset Moth.') : 'Discover digital designs, templates, graphics, fonts, SVG files, printables, and creative assets from independent designers.' ?></p>
<section class="card">
    <h2>Categories</h2>
    <div class="grid"><?php foreach($cats as $c):?><a href="/category/<?=H::e($c['slug'])?>"><?=H::e($c['name'])?></a><?php endforeach;?></div>
</section>
<form class="filters" action="/browse" method="get">
    <input name="q" value="<?=H::e($_GET['q']??'')?>" placeholder="Keyword">
    <select name="category"><option value="">All categories</option><?php foreach($cats as $c):?><option value="<?=H::e($c['slug'])?>" <?=$selectedCategory===$c['slug']?'selected':''?>><?=H::e($c['name'])?></option><?php endforeach;?></select>
    <select name="ai"><option value="">Any AI disclosure</option><?php foreach(['No AI Used','AI Assisted','AI Generated'] as $o):?><option <?=($_GET['ai']??'')===$o?'selected':''?>><?=H::e($o)?></option><?php endforeach;?></select>
    <select name="pod"><option value="">POD permission</option><option value="1" <?=($_GET['pod']??'')==='1'?'selected':''?>>Allowed</option><option value="0" <?=($_GET['pod']??'')==='0'?'selected':''?>>Not allowed</option></select>
    <select name="sort"><option value="newest" <?=$selectedSort==='newest'?'selected':''?>>Newest</option><option value="popular" <?=$selectedSort==='popular'?'selected':''?>>Popular</option><option value="price_asc" <?=$selectedSort==='price_asc'?'selected':''?>>Price low-high</option><option value="price_desc" <?=$selectedSort==='price_desc'?'selected':''?>>Price high-low</option></select>
    <button>Filter</button>
</form>
<?php include app_path('app/Views/public/product_grid.php');?>
<?php if(empty($products)):?><section class="card empty-state"><h2>No approved products found</h2><p>Try browsing all designs or another category. New products appear after designer submission and marketplace review.</p><a class="btn" href="/browse">Browse all designs</a></section><?php endif;?>
