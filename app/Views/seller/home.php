<h1>Seller dashboard</h1>
<p class="muted">Manage your reviewed storefront, product drafts, submitted listings, and public catalog from one place.</p>
<section class="card seller-summary">
    <div class="storefront-banner small" <?php if(!empty($d['banner_path'])):?>style="background-image:url('<?=H::e($d['banner_path'])?>')"<?php endif; ?>>
    </div>
    <div class="store-preview-row">
        <div class="avatar">
           <?php if(!empty($d['avatar_path'])):?>
               <img src="<?=H::e($d['avatar_path'])?>" alt="<?=H::e($d['display_name'])?> avatar">
           <?php else:?>
               <span>
               <?=H::e(substr($d['display_name']??'S',0,1))?>
               </span>
           <?php endif;?>
        </div>
        <div>
           <h2>
           <?=H::e($d['display_name'])?>
           </h2>
           <p>Store status: <span class="badge ok">
           <?=H::e($d['status']??'not approved')?>
           </span>
           <span class="badge rank">
           <?=H::e($d['creator_rank']??'Bronze')?>
           </span>
           </p>
           <p>Public store: <a href="/store/<?=H::e($d['store_slug'])?>">/store/<?=H::e($d['store_slug'])?>
           </a>
           </p>
        </div>
    </div>
</section>
<section class="stats-row">
    <div class="card">
        <strong>
        <?=H::e((string)($d['follower_count']??0))?>
        </strong>
        <span>Followers</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($stats['product_count']??0))?>
        </strong>
        <span>Products</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($stats['sales_count']??$d['sales_count']??0))?>
        </strong>
        <span>Sales</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($d['average_rating']??'0.00'))?>
        </strong>
        <span>Average rating</span>
    </div>
</section>
<div class="dash">
    <a class="card" href="/seller/onboarding">Seller Onboarding</a>
    <a class="card" href="/seller/stripe">Stripe Payouts</a>
    <a class="card" href="/seller/store">Store Settings</a>
    <a class="card" href="/store/<?=H::e($d['store_slug'])?>">View Public Store</a>
    <a class="card" href="/seller/products">Products</a>
    <a class="card" href="/seller/sales">Sales</a>
    <a class="card" href="/seller/coupons">Coupons</a>
    <a class="card" href="/seller/referrals">Referrals</a>
    <a class="card" href="/seller/rank">Rank</a>
</div>

<section class="card page-section">
    <h2>Product status guide</h2>
    <div class="grid">
        <p><span class="badge">Draft</span> Saved privately until submitted.</p>
        <p><span class="badge pending">Pending review</span> Submitted for admin review.</p>
        <p><span class="badge ok">Approved</span> Visible on public marketplace pages.</p>
        <p><span class="badge no">Rejected</span> Needs updates before resubmission.</p>
        <p><span class="badge">Disabled</span> Hidden from public browsing.</p>
    </div>
    <p class="cta-row"><a class="btn" href="/seller/product/new">Create a product</a><a class="btn alt" href="/seller/store">Edit storefront</a></p>
</section>
