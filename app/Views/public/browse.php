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
<p><?= $isCategory ? H::e($category['description'] ?: 'Browse approved downloadable products in this category on Creative Moth.') : 'Discover SVGs, print-ready PNG files, seamless patterns, templates, fonts, brushes, mockups, printables, and other creative files from independent designers.' ?></p></section>
<section class="card">
    <h2>Categories</h2>
    <div class="grid"><?php foreach($cats as $c):?><a href="/category/<?=H::e($c['slug'])?>"><?=H::e($c['name'])?></a><?php endforeach;?></div>
</section>
<style>
.browse-filter-shell{
    margin:16px 0 10px;
}

.browse-filter-primary{
    display:grid;
    grid-template-columns:minmax(240px,2fr) minmax(150px,1fr) minmax(150px,1fr) minmax(140px,1fr) auto auto;
    gap:10px;
    align-items:end;
}

.browse-filter-primary label,
.browse-filter-more-grid label{
    margin:0;
    font-size:13px;
    font-weight:700;
}

.browse-filter-primary input,
.browse-filter-primary select,
.browse-filter-more-grid input,
.browse-filter-more-grid select{
    width:100%;
    margin-top:4px;
    min-height:40px;
    padding:8px 10px;
}

.browse-more-filters{
    margin:0;
    position:relative;
}

.browse-more-filters summary{
    list-style:none;
    cursor:pointer;
    white-space:nowrap;
    min-height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:8px 14px;
    border:1px solid #d9d0e7;
    border-radius:12px;
    background:#fff;
    font-weight:700;
}

.browse-more-filters summary::-webkit-details-marker{
    display:none;
}

.browse-more-filters[open] summary{
    background:#f6f1fb;
}

.browse-filter-more-panel{
    position:absolute;
    top:calc(100% + 8px);
    right:0;
    width:min(680px,90vw);
    padding:14px;
    border:1px solid #e6deef;
    border-radius:14px;
    background:#fff;
    box-shadow:0 12px 35px rgba(31,20,58,.18);
    z-index:100;
}

.browse-filter-more-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}

.browse-filter-actions{
    display:flex;
    gap:8px;
    align-items:center;
    margin-top:10px;
}

.browse-filter-primary > button{
    min-height:40px;
    white-space:nowrap;
    padding:8px 16px;
}

@media(max-width:1050px){
    .browse-filter-primary{
        grid-template-columns:2fr 1fr 1fr;
    }

    .browse-filter-primary > button,
    .browse-more-filters{
        width:100%;
    }

    .browse-filter-more-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:650px){
    .browse-filter-primary{
        grid-template-columns:1fr;
    }

    .browse-filter-more-grid{
        grid-template-columns:1fr 1fr;
    }

    .browse-filter-more-panel{
        left:0;
        right:auto;
        width:min(92vw,680px);
    }
}
</style>

<form class="browse-filter-shell" action="<?=H::e($basePath)?>" method="get">

    <div class="browse-filter-primary">

        <label>
            Search
            <input
                name="q"
                value="<?=H::e($filters['q']??'')?>"
                placeholder="Search designs..."
            >
        </label>

        <?php if(!$isCategory): ?>
            <label>
                Category
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach($cats as $c):?>
                        <option
                            value="<?=H::e($c['slug'])?>"
                            <?=$selectedCategory===$c['slug']?'selected':''?>
                        >
                            <?=H::e($c['name'])?>
                        </option>
                    <?php endforeach;?>
                </select>
            </label>
        <?php endif; ?>

        <label>
            Creator
            <select name="creator">
                <option value="">All creators</option>
                <?php foreach(($creators??[]) as $d):?>
                    <option
                        value="<?=H::e($d['store_slug'])?>"
                        <?=($filters['creator']??'')===$d['store_slug']?'selected':''?>
                    >
                        <?=H::e($d['display_name'])?>
                    </option>
                <?php endforeach;?>
            </select>
        </label>

        <label>
            Sort
            <select name="sort">
                <option value="relevance" <?=$selectedSort==='relevance'?'selected':''?>>Relevance</option>
                <option value="newest" <?=$selectedSort==='newest'?'selected':''?>>Newest</option>
                <option value="oldest" <?=$selectedSort==='oldest'?'selected':''?>>Oldest</option>
                <option value="price_asc" <?=$selectedSort==='price_asc'?'selected':''?>>Price low to high</option>
                <option value="price_desc" <?=$selectedSort==='price_desc'?'selected':''?>>Price high to low</option>
                <option value="title_asc" <?=$selectedSort==='title_asc'?'selected':''?>>A to Z</option>
                <option value="title_desc" <?=$selectedSort==='title_desc'?'selected':''?>>Z to A</option>
                <option value="featured" <?=$selectedSort==='featured'?'selected':''?>>Featured first</option>
            </select>
        </label>

        <details class="browse-more-filters">
            <summary>More filters ▾</summary>

            <div class="browse-filter-more-panel">
                <div class="browse-filter-more-grid">

                    <label>
                        Min price
                        <input
                            name="min_price"
                            inputmode="decimal"
                            value="<?=H::e($filters['min_price']??'')?>"
                            placeholder="0"
                        >
                    </label>

                    <label>
                        Max price
                        <input
                            name="max_price"
                            inputmode="decimal"
                            value="<?=H::e($filters['max_price']??'')?>"
                            placeholder="50"
                        >
                    </label>

                    <label>
                        AI disclosure
                        <select name="ai">
                            <option value="">Any</option>
                            <?php foreach(['No AI Used','AI Assisted','AI Generated'] as $o):?>
                                <option value="<?=H::e($o)?>" <?=($filters['ai']??'')===$o?'selected':''?>>
                                    <?=H::e($o)?>
                                </option>
                            <?php endforeach;?>
                        </select>
                    </label>

                    <label>
                        POD permission
                        <select name="pod">
                            <option value="">Any</option>
                            <option value="1" <?=($filters['pod']??'')==='1'?'selected':''?>>Allowed</option>
                            <option value="0" <?=($filters['pod']??'')==='0'?'selected':''?>>Not allowed</option>
                        </select>
                    </label>

                    <label>
                        File type
                        <select name="file_type">
                            <option value="">Any file type</option>
                            <?php foreach(($fileTypes??[]) as $ft): $value=$ft['file_types']; ?>
                                <option value="<?=H::e($value)?>" <?=($filters['file_type']??'')===$value?'selected':''?>>
                                    <?=H::e($value)?>
                                </option>
                            <?php endforeach;?>
                        </select>
                    </label>

                    <label>
                        Featured
                        <select name="featured">
                            <option value="">Any</option>
                            <option value="1" <?=($filters['featured']??'')==='1'?'selected':''?>>Featured only</option>
                        </select>
                    </label>

                    <label>
                        Recently added
                        <select name="new">
                            <option value="">Any age</option>
                            <option value="1" <?=($filters['new']??'')==='1'?'selected':''?>>Last 30 days</option>
                        </select>
                    </label>

                    <label>
                        Commercial license
                        <select name="commercial">
                            <option value="">Any</option>
                            <option value="1" <?=($filters['commercial']??'')==='1'?'selected':''?>>Available</option>
                        </select>
                    </label>

                </div>

                <div class="browse-filter-actions">
                    <button type="submit">Apply filters</button>
                    <a class="btn alt" href="<?=H::e($basePath)?>">Clear filters</a>
                </div>
            </div>
        </details>

        <button type="submit">Apply</button>

    </div>
</form>

<script>
document.addEventListener('click', (event) => {
    document.querySelectorAll('.browse-more-filters[open]').forEach((details) => {
        if (!details.contains(event.target)) {
            details.removeAttribute('open');
        }
    });
});
</script>

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
