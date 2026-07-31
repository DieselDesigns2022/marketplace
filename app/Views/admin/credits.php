<h1>Credits &amp; Referrals</h1>
<p>Credit and referral history is immutable. Adjustments create new audited ledger entries.</p>

<form method="get" action="/admin/referrals" class="card form" role="search">
    <label for="credit-user-search">Search users</label>
    <input id="credit-user-search" name="q" value="<?= H::e($query) ?>" maxlength="190">
    <button class="btn">Search</button>
</form>

<div class="responsive-table">
<table>
    <thead><tr><th>User</th><th>Total</th><th>Reserved</th><th>Available</th><th>Review</th></tr></thead>
    <tbody>
    <?php if (!$users): ?><tr><td colspan="5">No matching users.</td></tr><?php endif; ?>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= H::e($user['name']) ?><br><small><?= H::e($user['email']) ?></small></td>
            <td><?= H::money($user['total_balance']) ?></td>
            <td><?= H::money($user['reserved_balance']) ?></td>
            <td><?= H::money($user['available_balance']) ?></td>
            <td><a href="/admin/referrals?user_id=<?= (int)$user['id'] ?>">Review account</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<nav aria-label="User results pages"><a href="?q=<?= rawurlencode($query) ?>&amp;page=<?= max(1, $page - 1) ?>">Previous</a> · <a href="?q=<?= rawurlencode($query) ?>&amp;page=<?= $page + 1 ?>">Next</a></nav>

<?php if ($selected): ?>
<section class="card" aria-labelledby="selected-credit-user">
    <h2 id="selected-credit-user"><?= H::e($selected['name']) ?></h2>
    <p>Total <?= H::money($selected['total_balance']) ?> · Reserved <?= H::money($selected['reserved_balance']) ?> · Available <?= H::money($selected['available_balance']) ?></p>
    <form method="post" action="/admin/credits/adjust" class="form">
        <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">
        <input type="hidden" name="user_id" value="<?= (int)$selected['id'] ?>">
        <label for="credit-adjustment">Adjustment amount</label>
        <input id="credit-adjustment" name="amount" required inputmode="decimal" placeholder="10.00 or -5.00">
        <label for="credit-reason">Reason</label>
        <textarea id="credit-reason" name="reason" required minlength="3" maxlength="500"></textarea>
        <button class="btn">Record adjustment</button>
    </form>
</section>

<h2>Selected user ledger</h2>
<div class="responsive-table"><table>
<thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Order</th><th>Referral</th><th>Related entry</th><th>Admin</th><th>Reason</th><th>Created / finalized / released</th></tr></thead>
<tbody>
<?php if (!$ledger): ?><tr><td colspan="9">No credit activity for this user.</td></tr><?php endif; ?>
<?php foreach ($ledger as $entry): ?><tr>
<td><?= H::e(ucwords(str_replace('_', ' ', $entry['type']))) ?></td><td><?= H::money($entry['amount']) ?></td><td><?= H::e(ucfirst($entry['status'])) ?></td>
<td><?= $entry['order_id'] ? '#' . (int)$entry['order_id'] : '—' ?></td><td><?= $entry['referral_id'] ? '#' . (int)$entry['referral_id'] : '—' ?></td><td><?= $entry['related_transaction_id'] ? '#' . (int)$entry['related_transaction_id'] : '—' ?></td>
<td><?= H::e($entry['admin_name'] ?? '—') ?></td><td><?= H::e($entry['description'] ?? '—') ?></td><td><?= H::e($entry['created_at']) ?><br><?= H::e($entry['finalized_at'] ?? '') ?><br><?= H::e($entry['released_at'] ?? '') ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<h2>Matching referrals</h2>
<div class="responsive-table"><table>
<thead><tr><th>Referrer</th><th>Referred user</th><th>Buyer status</th><th>Seller status</th><th>Qualifying order/item</th><th>Rewards</th></tr></thead>
<tbody>
<?php if (!$referrals): ?><tr><td colspan="6">No matching referrals.</td></tr><?php endif; ?>
<?php foreach ($referrals as $referral): ?><tr>
<td><?= H::e($referral['referrer_name']) ?></td><td><?= H::e($referral['referred_name']) ?></td>
<td><?= H::e(ucfirst($referral['buyer_status'])) ?></td><td><?= H::e(ucfirst($referral['seller_status'])) ?></td>
<td><?= $referral['buyer_qualifying_order_id'] ? 'Buyer order #' . (int)$referral['buyer_qualifying_order_id'] : '—' ?><br><?= $referral['seller_qualifying_order_id'] ? 'Seller order #' . (int)$referral['seller_qualifying_order_id'] . ', item #' . (int)$referral['seller_qualifying_order_item_id'] : '' ?></td>
<td>Buyer: referrer <?= H::money($referral['buyer_referrer_reward_amount']) ?> / referred <?= H::money($referral['buyer_referred_reward_amount']) ?><br>Seller: referrer <?= H::money($referral['seller_referrer_reward_amount']) ?> / referred <?= H::money($referral['seller_referred_reward_amount']) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
