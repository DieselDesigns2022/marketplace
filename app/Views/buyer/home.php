<h1>Buyer dashboard</h1>
<p>Welcome, <?=H::e(H::user()['name'])?>. Use your dashboard to revisit purchases, wishlist products, and followed designers as marketplace features continue to grow.</p>
<div class="dash">
    <a class="card" href="/dashboard/purchases"><strong>Purchases</strong><p class="muted">Purchases and downloads will appear here as marketplace checkout and order features are completed.</p></a>
    <a class="card" href="/dashboard/wishlist"><strong>Wishlist</strong><p class="muted">Save products to compare or revisit later.</p></a>
    <a class="card" href="/dashboard/following"><strong>Followed designers</strong><p class="muted">Find storefronts you follow.</p></a>
    <a class="card" href="/dashboard/referrals"><strong>Credits / referrals</strong><p class="muted">Referral and credit polish is planned for a later phase.</p></a>
</div>
<h2>Recent purchases</h2>
<?php if(!$orders):?>
    <div class="card empty-state"><p>No purchases are shown yet. Future completed orders and available downloads will appear here.</p><a class="btn" href="/browse">Browse Digital Designs</a></div>
<?php endif;?>
<?php foreach($orders as $o):?>
    <p>Order #<?=$o['id']?> — <?=H::money($o['total'])?></p>
<?php endforeach;?>
