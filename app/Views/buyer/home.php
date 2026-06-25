<h1>Buyer dashboard</h1>
<p>Welcome, <?=H::e(H::user()['name'])?>.</p>
<div class="dash">
    <a class="card" href="/dashboard/purchases">Purchases</a>
    <a class="card" href="/dashboard/wishlist">Wishlist</a>
    <a class="card" href="/dashboard/following">Followed Designers</a>
    <a class="card" href="/dashboard/referrals">Credits/Referrals</a>
</div>
<h2>Recent purchases</h2>
<?php foreach($orders as $o):?>
    <p>Order #<?=$o['id']?> — <?=H::money($o['total'])?>
    </p>
<?php endforeach;?>
