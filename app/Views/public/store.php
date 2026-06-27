<article class="storefront" itemscope itemtype="https://schema.org/ProfilePage">
    <nav class="store-links">
        <a href="/browse">← Browse designs</a>
        <a href="#newest-products">Newest products</a>
        <a href="#store-categories">Store categories</a>
    </nav>
    <section class="storefront-hero">
        <div class="storefront-banner" <?php if(!empty($d['banner_path'])):?>style="background-image:url('<?=H::e($d['banner_path'])?>')"<?php endif; ?>>
           <?php if(empty($d['banner_path'])):?>
               <span>Store banner artwork has not been added yet.</span>
           <?php endif;?>
        </div>
        <div class="storefront-profile">
           <div class="avatar large" itemprop="image">
               <?php if(!empty($d['avatar_path'])):?>
                   <img src="<?=H::e($d['avatar_path'])?>" alt="<?=H::e($d['display_name'])?> logo">
               <?php else:?>
                   <span>
                   <?=H::e(substr($d['display_name']??'S',0,1))?>
                   </span>
               <?php endif;?>
           </div>
           <div class="storefront-title">
               <h1 itemprop="name">
               <?=H::e($d['display_name'])?>
               </h1>
               <p class="muted">/store/<?=H::e($d['store_slug'])?>
               </p>
               <span class="badge rank">
               <?=H::e($d['creator_rank'] ?: 'Bronze')?>
               </span>
               <span class="badge ok">Approved seller</span>
           </div>
           <div class="follow-box">
               <?php if($isOwner): ?>
               <span class="badge">This is your store</span>
           <?php else: ?>
               <form method="post" action="/store/<?=$d['id']?>/follow">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <button class="btn">
                   <?= $isFollowing ? 'Unfollow Designer' : 'Follow Designer' ?>
                   </button>
               </form>
               <small>Follow this designer to find them again later.</small>
           <?php endif; ?>
        </div>
    </div>
</section>
<section class="stats-row">
    <div class="card">
        <strong>
        <?=$followers?>
        </strong>
        <span>Followers</span>
    </div>
    <div class="card">
        <strong>
        <?=$productCount?>
        </strong>
        <span>Products</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($salesCount ?? 0))?>
        </strong>
        <span>Sales</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($d['average_rating'] ?? '0.00'))?>
        </strong>
        <span>Average rating</span>
    </div>
</section>
<?php if(!empty($d['announcement'])): ?>
<section class="card announcement">
    <h2>Store announcement</h2>
    <p>
    <?=nl2br(H::e($d['announcement']))?>
    </p>
</section>
<?php endif; ?>
<section class="card" itemprop="about">
    <h2>About this designer</h2>
    <p>
    <?=nl2br(H::e($d['bio'] ?: 'This approved designer has not added a public bio yet. Browse their available digital products below.'))?>
    </p>
    <?php if(!empty($d['website_url'])):?>
        <p>
        <strong>Website:</strong>
        <a rel="nofollow ugc" href="<?=H::e($d['website_url'])?>">
        <?=H::e($d['website_url'])?>
        </a>
        </p>
    <?php endif; ?>
    <?php if(!empty($d['social_links'])):?>
        <h3>Social links</h3>
        <p>
        <?=nl2br(H::e($d['social_links']))?>
        </p>
    <?php endif; ?>
</section>
<section id="featured-products">
    <h2>Featured products</h2>
    <?php if(empty($products)): ?>
    <div class="card empty-state">
        <p>This approved storefront does not have public products right now. Browse other designers while new products are reviewed.</p>
    </div>
<?php else: ?>
    <?php include app_path('app/Views/public/product_grid.php');?>
<?php endif; ?>
</section>
<section id="newest-products">
    <h2>Newest products</h2>
    <?php if(empty($products)): ?>
    <div class="card empty-state">
        <p>This store has no approved products available at the moment.</p>
    </div>
<?php else: ?>
    <?php include app_path('app/Views/public/product_grid.php');?>
<?php endif; ?>
</section>
<section id="store-categories">
    <h2>Store categories</h2>
    <?php $cats=[]; foreach($products as $p){ if(!empty($p['category_slug'])) $cats[$p['category_slug']]=$p['category_name']; } if(!$cats): ?>
    <div class="card empty-state">
        <p>Store category links appear after products are approved for this designer.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach($cats as $slug=>$name):?>
           <a class="card" href="/category/<?=H::e($slug)?>">
           <?=H::e($name)?>
           </a>
        <?php endforeach;?>
    </div>
<?php endif; ?>
</section>
</article>
