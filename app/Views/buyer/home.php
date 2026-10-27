<?php use App\Core\Helpers as H; ?>

<section class="card">
    <div class="dashboard-heading">
        <div>
            <h2>Grand Opening Passport</h2>

            <p>
                Complete all 8 activities to earn an entry into the
                Grand Prize Giveaway.
            </p>
        </div>

        <a
            class="btn"
            href="/dashboard/passport"
        >
            View Passport
        </a>
    </div>

    <p>
        <strong>
            <?=(int)($passport['completed']??0)?>
            of
            <?=(int)($passport['total']??8)?>
            stamps complete
        </strong>
    </p>

    <?php if(!empty($passport['giveaway_entry'])): ?>
        <p>
            <span class="status-badge ok">
                Passport Complete — Giveaway Entry Earned
            </span>
        </p>
    <?php endif; ?>
</section>


<header class="dashboard-heading"><div><h1>Welcome back, <?=H::e(H::user()['name'])?></h1><p class="muted">Your purchases, saved designs, and marketplace updates at a glance.</p></div><a class="btn" href="/browse">Browse designs</a></header>
<div class="dashboard-grid"><?php foreach([['Purchases',$summary['purchase_count']??0,'/dashboard/purchases'],['Available downloads',$summary['available_downloads']??0,'/dashboard/downloads'],['Wishlist',$summary['wishlist_count']??0,'/dashboard/wishlist'],['Unread notifications',$summary['unread_count']??0,'/notifications']] as [$label,$count,$url]):?><a class="card" href="<?=$url?>"><strong><?=number_format((int)$count)?></strong><span><?=$label?></span></a><?php endforeach;?></div>
<section><div class="dashboard-heading"><h2>Recent orders</h2><a href="/dashboard/purchases">View all</a></div><?php if(!$orders):?><div class="card empty-state"><p>No purchases yet. Find a design you love to get started.</p><a class="btn" href="/browse">Browse marketplace</a></div><?php else:?><div class="responsive-table"><table><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><a href="/dashboard/order/<?=(int)$o['id']?>">#<?=(int)$o['id']?></a></td><td><?=H::e($o['created_at'])?></td><td><span class="status-badge <?=($o['payment_status']??'')==='paid'?'ok':'neutral'?>"><?=H::e(str_replace('_',' ',$o['payment_status']??$o['status']))?></span></td><td><?=H::money($o['total'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<div class="dashboard-columns"><section><div class="dashboard-heading"><h2>Recently wished</h2><a href="/dashboard/wishlist">Wishlist</a></div><?php if(!$wishlist):?><div class="card empty-state">Use the heart on products to save them here.</div><?php else:?><?php foreach($wishlist as $p):?><p class="card"><a href="/product/<?=H::e($p['slug'])?>"><?=H::e($p['title'])?></a></p><?php endforeach;?><?php endif;?></section><section><div class="dashboard-heading"><h2>Recent notifications</h2><a href="/notifications">View all</a></div><?php if(!$notifications):?><div class="card empty-state">You're all caught up.</div><?php else:?><?php foreach($notifications as $n):?><p class="card notification <?=empty($n['read_at'])?'unread':''?>"><strong><?=H::e($n['title'])?></strong><br><span class="muted"><?=H::e($n['message']??'')?></span></p><?php endforeach;?><?php endif;?></section></div>
