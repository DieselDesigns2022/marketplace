<h1>Admin Dashboard</h1>

<section class="card">
    <h2>Money & Payments</h2>
    <p class="muted">Quick access to marketplace commission, seller payout status, payment transactions, and Stripe webhook logs.</p>
    <p><a class="btn" href="/admin/payment-logs">View Commission & Payment Report</a></p>
</section>

<p class="muted">Review marketplace activity, applications, products, and catalog operations from this admin area.</p>
<?php
$statLabels = [
    'active_users' => 'Active Users',
    'approved_designers' => 'Approved Designers',
    'pending_apps' => 'Pending Applications',
    'pending_products' => 'Pending Products',
    'live_paid_orders' => 'Live Paid Orders',
    'live_gross_sales' => 'Live Gross Sales',
    'asset_moth_commission' => 'Asset Moth Commission',
];
$moneyStats = ['live_gross_sales', 'asset_moth_commission'];
?>
<div class="dash">
    <?php foreach($s as $k=>$v):?>
        <div class="card">
           <b><?=H::e($statLabels[$k] ?? ucwords(str_replace('_',' ',$k)))?></b>
           <p>
           <?php if(in_array($k, $moneyStats, true)): ?>
               <?=H::money($v)?>
           <?php else: ?>
               <?=H::e($v)?>
           <?php endif; ?>
           </p>
        </div>
    <?php endforeach;?>
</div>
<nav class="adminnav">
    <a href="/admin/users">Users</a>
    <a href="/admin/applications">Applications</a>
    <a href="/admin/designers">Designers</a>
    <a href="/admin/products">Products</a>
    <a href="/admin/categories">Categories</a>
    <a href="/admin/orders">Orders</a>
    <a href="/admin/referrals">Referrals</a>
    <a href="/admin/homepage">Homepage</a>
    <a href="/admin/ads">Ads</a>
</nav>
