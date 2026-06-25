<h1>Browse Designs</h1>
<form class="filters">
    <input name="q" value="<?=H::e($_GET['q']??'')?>" placeholder="Keyword">
    <select name="category">
    <option value="">All categories</option>
    <?php foreach($cats as $c):?>
        <option value="<?=$c['slug']?>">
        <?=$c['name']?>
        </option>
    <?php endforeach;?>
    </select>
    <select name="file_type">
    <option value="">Any file</option>
    <option>PNG</option>
    <option>SVG</option>
    <option>JPG</option>
    <option>PDF</option>
    <option>ZIP</option>
    <option>Canva Template</option>
    <option>Procreate File</option>
    <option>Font</option>
    </select>
    <select name="ai">
    <option value="">Any AI disclosure</option>
    <option>Hand Drawn</option>
    <option>Digitally Created</option>
    <option>AI Assisted</option>
    <option>AI Generated</option>
    </select>
    <select name="pod">
    <option value="">POD?</option>
    <option value="1">Allowed</option>
    <option value="0">Not allowed</option>
    </select>
    <select name="sort">
    <option value="newest">Newest</option>
    <option value="popular">Popular</option>
    <option value="price_asc">Price low-high</option>
    <option value="price_desc">Price high-low</option>
    </select>
    <button>Filter</button>
</form>
<?php include app_path('app/Views/public/product_grid.php');?>
