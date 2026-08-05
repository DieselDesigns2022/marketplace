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

<h2>Seller-referral commission totals</h2>
<div class="responsive-table"><table><thead><tr><th>Referrer</th><th>Referred store</th><th>Eligibility</th><th>Unpaid</th><th>Paid</th><th>Recovery</th><th>Lifetime net</th></tr></thead><tbody>
<?php if (!$commissionTotals): ?><tr><td colspan="7">No lifetime-commission referrals.</td></tr><?php endif; ?>
<?php foreach ($commissionTotals as $total): ?><tr><td><?= H::e($total['referrer_name']) ?></td><td><?= H::e($total['referred_store'] ?? 'Store unavailable') ?></td><td><?= $total['commission_ended_at'] ? 'Permanently stopped ' . H::e($total['commission_ended_at']) . '<br>' . H::e(ucwords(str_replace('_', ' ', $total['commission_end_reason']))) : 'Active' ?></td><td><?= H::money(((int)$total['unpaid_cents']) / 100) ?></td><td><?= H::money(((int)$total['paid_cents']) / 100) ?></td><td><?= H::money(((int)$total['recovery_cents']) / 100) ?></td><td><?= H::money(((int)$total['lifetime_cents']) / 100) ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<h2>Monthly seller-referral payouts</h2>
<form method="get" action="/admin/referrals" class="inline"><label>Status <select name="payout_status"><option value="">All</option><?php foreach (['processing','paid','failed','not_ready'] as $status): ?><option value="<?= H::e($status) ?>" <?= $payoutStatus === $status ? 'selected' : '' ?>><?= H::e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label><button>Filter payouts</button></form>
<div class="responsive-table"><table><thead><tr><th>Referrer / store</th><th>Period</th><th>Amount</th><th>Status / readiness</th><th>Stripe transfer</th><th>Attempted / completed</th><th>Problem</th><th>Action</th></tr></thead><tbody>
<?php if (!$payouts): ?><tr><td colspan="8">No matching payout batches.</td></tr><?php endif; ?>
<?php foreach ($payouts as $batch): ?><tr><td><?= H::e($batch['referrer_name']) ?><br><?= H::e($batch['display_name'] ?? 'Store unavailable') ?></td><td><?= H::e($batch['period_start']) ?>–<?= H::e($batch['period_end']) ?><br>Sequence <?= (int)$batch['sequence_no'] ?></td><td><?= H::money(((int)$batch['amount_cents']) / 100) ?></td><td><?= H::e(ucwords(str_replace('_', ' ', $batch['status']))) ?><br><?= !empty($batch['stripe_details_submitted']) && !empty($batch['stripe_payouts_enabled']) ? 'Ready' : 'Not ready' ?></td><td><?= H::e($batch['stripe_transfer_id'] ?? '—') ?></td><td><?= H::e($batch['attempted_at'] ?? '—') ?><br><?= H::e($batch['succeeded_at'] ?? '—') ?></td><td><?= H::e($batch['failure_reason'] ?? '—') ?><br><?= (int)$batch['attempt_count'] ?> audit events</td><td><?php if (in_array($batch['status'], ['failed','not_ready','processing'], true)): ?><form method="post" action="/admin/seller-referral-payouts/<?= (int)$batch['id'] ?>/retry"><input type="hidden" name="_csrf" value="<?= H::csrf() ?>"><button><?= $batch['status']==='processing'?'Recover stale claim':'Retry' ?></button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>

<h3>Transfer-attempt history</h3>
<div class="responsive-table"><table><thead><tr><th>Batch</th><th>Status</th><th>Transfer</th><th>Attempted</th><th>Succeeded</th><th>Sanitized result</th></tr></thead><tbody><?php foreach ($attempts as $attempt): ?><tr><td>#<?= (int)$attempt['batch_id'] ?></td><td><?= H::e(ucwords(str_replace('_', ' ', $attempt['status']))) ?></td><td><?= H::e($attempt['stripe_transfer_id'] ?? '—') ?></td><td><?= H::e($attempt['attempted_at']) ?></td><td><?= H::e($attempt['succeeded_at'] ?? '—') ?></td><td><?= H::e($attempt['failure_reason'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<h2>Matching referrals</h2>
<div class="responsive-table"><table>
<thead><tr><th>Referrer</th><th>Referred user</th><th>Buyer status</th><th>Seller status / immutable reward</th><th>Qualifying order/item</th><th>Rewards</th></tr></thead>
<tbody>
<?php if (!$referrals): ?><tr><td colspan="6">No matching referrals.</td></tr><?php endif; ?>
<?php foreach ($referrals as $referral): ?><tr>
<td><?= H::e($referral['referrer_name']) ?></td><td><?= H::e($referral['referred_name']) ?></td>
<td><?= H::e(ucfirst($referral['buyer_status'])) ?></td><td><?= H::e(ucfirst($referral['seller_status'])) ?> · <?= H::e($referral['seller_reward_type']?ucwords(str_replace('_',' ',$referral['seller_reward_type'])):'Not selected') ?><?= $referral['commission_ended_at']?'<br>Permanently stopped '.H::e($referral['commission_ended_at'].' ('.str_replace('_',' ',$referral['commission_end_reason']).')'):'' ?></td>
<td><?= $referral['buyer_qualifying_order_id'] ? 'Buyer order #' . (int)$referral['buyer_qualifying_order_id'] : '—' ?><br><?= $referral['seller_qualifying_order_id'] ? 'Seller order #' . (int)$referral['seller_qualifying_order_id'] . ', item #' . (int)$referral['seller_qualifying_order_item_id'] : '' ?></td>
<td>Buyer: referrer <?= H::money($referral['buyer_referrer_reward_amount']) ?> / referred <?= H::money($referral['buyer_referred_reward_amount']) ?><br>Seller: referrer <?= H::money($referral['seller_referrer_reward_amount']) ?> / referred <?= H::money($referral['seller_referred_reward_amount']) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
