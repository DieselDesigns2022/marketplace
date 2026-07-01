<?php
$isCategory = !empty($category);
$filters = $filters ?? [];
$selectedCategory = $filters['category'] ?? ($isCategory ? ($category['slug'] ?? '') : '');
$selectedSort = $sort ?? 'newest';
$pagination = $pagination ?? ['total'=>count($products ?? []),'page'=>1,'pages'=>1,'pageSize'=>12];
$basePath = $isCategory ? '/category/'.($category['slug'] ?? '') : '/browse';
$queryLink = function(array $overrides = []) use ($filters, $selectedSort, $isCategory) {
    $query = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    $query = array_merge($query, $overrides);
    $query = array_filter($query, fn($v) => $v !== '' && $v !== null);
    if ($isCategory) unset($query['category']);
    if (($query['sort'] ?? $selectedSort) !== 'newest') $query['sort'] = $query['sort'] ?? $selectedSort;
    if (($query['sort'] ?? '') === 'newest') unset($query['sort']);
    if (empty($query['page']) || (int)$query['page'] === 1) unset($query['page']);
    $queryString = http_build_query($query);
    return $queryString === '' ? '' : '?'.$queryString;
};
$categoryNames = [];
foreach (($cats ?? []) as $c) $categoryNames[$c['slug']] = $c['name'];
$active = [];
foreach (['q'=>'Search','category'=>'Category','ai'=>'AI','pod'=>'POD','creator'=>'Creator','min_price'=>'Min price','max_price'=>'Max price','featured'=>'Featured','new'=>'Recently added','file_type'=>'File type','commercial'=>'Commercial license'] as $key=>$label) {
    $value = $filters[$key] ?? '';
    if ($value === '') continue;
    if ($key === 'category') $value = $categoryNames[$value] ?? $value;
    if ($key === 'pod') $value = $value === '1' ? 'Allowed' : 'Not allowed';
    if ($key === 'featured' && $value === '1') { $active[] = 'Featured only'; continue; }
    if ($key === 'new' && $value === '1') { $active[] = 'Recently added: Last 30 days'; continue; }
    if ($key === 'commercial' && $value === '1') { $active[] = 'Commercial license: Available'; continue; }
    $active[] = $label.': '.$value;
}
?>
<nav class="breadcrumbs"><a href="/">Home</a> / <a href="/browse">Browse</a><?php if($isCategory): ?> / <?=H::e($category['name'])?><?php endif; ?></nav>
<section class="page-hero"><p class="eyebrow"><?= $isCategory ? 'Category' : 'Marketplace browse' ?></p><h1><?= $isCategory ? H::e($category['name']) : 'Browse digital designs' ?></h1>
<p><?= $isCategory ? H::e($category['description'] ?: 'Browse approved downloadable products in this category on Asset Moth.') : 'Discover SVGs, PNGs, sublimation designs, seamless patterns, templates, fonts, brushes, mockups, printables, and other creative files from independent designers.' ?></p></section>
<section class="card">
    <h2>Categories</h2>
    <div class="grid"><?php foreach($cats as $c):?><a href="/category/<?=H::e($c['slug'])?>"><?=H::e($c['name'])?></a><?php endforeach;?></div>
</section>
<form class="filters browse-filters" action="<?=H::e($basePath)?>" method="get">
    <label class="wide">Search keywords<input name="q" value="<?=H::e($filters['q']??'')?>" placeholder="Try sublimation mockup, font, POD..."></label>
    <?php if(!$isCategory): ?><label>Category<select name="category"><option value="">All categories</option><?php foreach($cats as $c):?><option value="<?=H::e($c['slug'])?>" <?=$selectedCategory===$c['slug']?'selected':''?>><?=H::e($c['name'])?></option><?php endforeach;?></select></label><?php endif; ?>
    <label>Creator<select name="creator"><option value="">All creators</option><?php foreach(($creators??[]) as $d):?><option value="<?=H::e($d['store_slug'])?>" <?=($filters['creator']??'')===$d['store_slug']?'selected':''?>><?=H::e($d['display_name'])?></option><?php endforeach;?></select></label>
    <label>Min price<input name="min_price" inputmode="decimal" value="<?=H::e($filters['min_price']??'')?>" placeholder="0"></label>
    <label>Max price<input name="max_price" inputmode="decimal" value="<?=H::e($filters['max_price']??'')?>" placeholder="50"></label>
    <label>AI disclosure<select name="ai"><option value="">Any AI disclosure</option><?php foreach(['No AI Used','AI Assisted','AI Generated'] as $o):?><option value="<?=H::e($o)?>" <?=($filters['ai']??'')===$o?'selected':''?>><?=H::e($o)?></option><?php endforeach;?></select></label>
    <label>POD permission<select name="pod"><option value="">Any POD permission</option><option value="1" <?=($filters['pod']??'')==='1'?'selected':''?>>Allowed</option><option value="0" <?=($filters['pod']??'')==='0'?'selected':''?>>Not allowed</option></select></label>
    <label>File type<select name="file_type"><option value="">Any file type</option><?php foreach(($fileTypes??[]) as $ft): $value=$ft['file_types']; ?><option value="<?=H::e($value)?>" <?=($filters['file_type']??'')===$value?'selected':''?>><?=H::e($value)?></option><?php endforeach;?></select></label>
    <label>Featured<select name="featured"><option value="">Any</option><option value="1" <?=($filters['featured']??'')==='1'?'selected':''?>>Featured only</option></select></label>
    <label>Recently added<select name="new"><option value="">Any age</option><option value="1" <?=($filters['new']??'')==='1'?'selected':''?>>Last 30 days</option></select></label>
    <label>Commercial license<select name="commercial"><option value="">Any</option><option value="1" <?=($filters['commercial']??'')==='1'?'selected':''?>>Available</option></select></label>
    <label>Sort results<select name="sort"><option value="relevance" <?=$selectedSort==='relevance'?'selected':''?>>Relevance</option><option value="newest" <?=$selectedSort==='newest'?'selected':''?>>Newest</option><option value="oldest" <?=$selectedSort==='oldest'?'selected':''?>>Oldest</option><option value="price_asc" <?=$selectedSort==='price_asc'?'selected':''?>>Price low to high</option><option value="price_desc" <?=$selectedSort==='price_desc'?'selected':''?>>Price high to low</option><option value="title_asc" <?=$selectedSort==='title_asc'?'selected':''?>>A to Z</option><option value="title_desc" <?=$selectedSort==='title_desc'?'selected':''?>>Z to A</option><option value="featured" <?=$selectedSort==='featured'?'selected':''?>>Featured first</option></select></label>
    <div class="filter-actions"><button>Apply filters</button><a class="btn alt" href="<?=H::e($basePath)?>">Clear filters</a></div>
</form>
<section class="browse-summary">
    <p><strong><?=H::e((string)$pagination['total'])?></strong> approved product<?=($pagination['total']==1?'':'s')?> found. Page <?=H::e((string)$pagination['page'])?> of <?=H::e((string)$pagination['pages'])?>.</p>
    <?php if($active): ?><p>Active filters: <?php foreach($active as $item): ?><span class="badge"><?=H::e($item)?></span><?php endforeach; ?></p><?php endif; ?>
</section>
<?php include app_path('app/Views/public/product_grid.php');?>
<?php if(empty($products)):?><section class="card empty-state"><h2>No products found for the current search/filter</h2><p>Try removing filters, checking spelling, using fewer keywords, or browsing all approved products.</p><p><a class="btn" href="<?=H::e($basePath)?>">Clear filters</a> <a class="btn alt" href="/browse">Browse all designs</a></p></section><?php endif;?>
<?php if(($pagination['pages'] ?? 1) > 1): ?>
<nav class="pagination" aria-label="Browse pagination">
    <?php if($pagination['page'] > 1): ?><a href="<?=H::e($basePath.$queryLink(['page'=>$pagination['page']-1]))?>">Previous</a><?php endif; ?>
    <?php for($i=max(1,$pagination['page']-2); $i<=min($pagination['pages'],$pagination['page']+2); $i++): ?>
        <a class="<?=$i===$pagination['page']?'active':''?>" href="<?=H::e($basePath.$queryLink(['page'=>$i]))?>"><?=$i?></a>
    <?php endfor; ?>
    <?php if($pagination['page'] < $pagination['pages']): ?><a href="<?=H::e($basePath.$queryLink(['page'=>$pagination['page']+1]))?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
