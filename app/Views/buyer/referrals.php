<h1>Referrals &amp; store credit</h1>
<?php include app_path('app/Views/buyer/referral_giveaway.php'); ?>
<div class="attention-panel">
<p><strong>Buyer referral rewards are now an approved-seller advertising benefit.</strong></p>
<p>
Approved Creative Moth sellers can earn $5 in Advertising Credit when a referred buyer spends at least $8 in qualifying purchases within 30 days. Approved sellers can find their buyer referral link and the complete rules on the Seller Referrals page.
</p>
</div>
<p>Seller referrals follow the Creative Moth seller referral program. If you are an approved seller when a seller referral qualifies, you earn the applicable seller referral commission. If you are not an approved seller, the existing $5.00 store-credit reward applies. The referred seller does not receive a matching reward.</p>
<p>Store credit never expires, is marketplace-only, non-transferable, and has no cash value.</p>
<div class="cards"><div class="card"><strong>Total</strong><br><?= H::money($balances['total']) ?></div><div class="card"><strong>Reserved</strong><br><?= H::money($balances['reserved']) ?></div><div class="card"><strong>Available</strong><br><?= H::money($balances['available']) ?></div></div>
<h2>Referrals you made</h2>
<div class="responsive-table">
<table>
<thead>
<tr>
    <th>Referred person</th>
    <th>Buyer reward</th>
    <th>Seller reward</th>
    <th>Progress / next step</th>
    <th>Earned</th>
</tr>
</thead>
<tbody>

<?php if (!$referrals['made']): ?>
<tr><td colspan="5">You have not referred anyone yet.</td></tr>
<?php endif; ?>

<?php foreach ($referrals['made'] as $referral): ?>
<?php
$isSellerReferral =
    !empty($referral['seller_intent']) ||
    ($referral['referral_type'] ?? '') === 'seller';

$buyerSpend = (float)($referral['buyer_qualifying_spend'] ?? 0);

$buyerExpired =
    !empty($referral['buyer_expires_at']) &&
    strtotime((string)$referral['buyer_expires_at']) <= time();

if (!empty($referral['buyer_rewarded_at'])) {
    $buyerLabel = 'Earned';
    $buyerReason = '$5 Advertising Credit awarded.';
} elseif (($referral['buyer_status'] ?? '') === 'rejected' && $isSellerReferral) {
    $buyerLabel = 'Not applicable';
    $buyerReason = 'This person became a seller before the buyer reward qualified. The referral continues as a seller referral.';
} elseif (($referral['buyer_status'] ?? '') === 'rejected' && $buyerExpired) {
    $buyerLabel = 'Expired';
    $buyerReason = 'The buyer did not reach $8 in qualifying purchases within 30 days.';
} elseif (($referral['buyer_status'] ?? '') === 'rejected') {
    $buyerLabel = 'Unavailable';
    $buyerReason = 'This referral no longer qualifies for the buyer reward.';
} else {
    $buyerLabel = 'Pending';
    $buyerReason =
        H::money($buyerSpend) .
        ' of $8.00 qualifying purchases completed' .
        (!empty($referral['buyer_expires_at'])
            ? ' · deadline ' . date('M j, Y', strtotime($referral['buyer_expires_at']))
            : '') .
        '.';
}

if (!empty($referral['commission_ended_at'])) {
    $sellerLabel = 'Permanently stopped';
    $sellerReason = 'Seller referral commission has ended.';
} elseif (!empty($referral['seller_rewarded_at'])) {
    $sellerLabel = 'Qualified';
    $sellerReason =
        ($referral['seller_reward_type'] ?? '') === 'lifetime_commission'
            ? '1% lifetime seller referral commission is active.'
            : 'Seller referral reward was earned.';
} elseif (($referral['seller_status'] ?? '') === 'rejected') {
    $sellerLabel = 'Unavailable';
    $sellerReason = 'This referral does not qualify for a seller reward.';
} elseif ($isSellerReferral) {
    $sellerLabel = 'Pending';
    $sellerReason = 'Waiting for the referred seller\'s first qualifying sale.';
} else {
    $sellerLabel = 'Not applicable';
    $sellerReason = 'This person is currently a buyer referral.';
}
?>

<tr>
    <td>
        <strong><?= H::e($referral['referred_store'] ?? $referral['referred_name']) ?></strong>
    </td>

    <td>
        <strong><?= H::e($buyerLabel) ?></strong><br>
        <small><?= H::e($buyerReason) ?></small>
    </td>

    <td>
        <strong><?= H::e($sellerLabel) ?></strong><br>
        <small><?= H::e($sellerReason) ?></small>
    </td>

    <td>
        <?php if (!empty($referral['buyer_qualifying_order_id'])): ?>
            Buyer order #<?= (int)$referral['buyer_qualifying_order_id'] ?><br>
        <?php elseif (!$isSellerReferral && ($referral['buyer_status'] ?? '') === 'pending'): ?>
            Waiting for qualifying buyer purchases.<br>
        <?php endif; ?>

        <?php if (!empty($referral['seller_qualifying_order_id'])): ?>
            Seller order #<?= (int)$referral['seller_qualifying_order_id'] ?>
        <?php elseif ($isSellerReferral && ($referral['seller_status'] ?? '') === 'pending'): ?>
            Waiting for first qualifying seller sale.
        <?php elseif (($referral['buyer_status'] ?? '') === 'rejected' && !$isSellerReferral): ?>
            Buyer referral closed.
        <?php endif; ?>
    </td>

    <td>
        Buyer <?= H::money($referral['buyer_referrer_reward_amount']) ?><br>
        Seller <?= H::money($referral['seller_referrer_reward_amount']) ?>

        <?php if (!empty($referral['seller_rewarded_at'])): ?>
            <br><small>Awarded <?= H::e($referral['seller_rewarded_at']) ?></small>
        <?php endif; ?>
    </td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>
<h2>Referral attached to you</h2>
<?php if (!$referrals['connected']): ?><p>No referral is attached to your account.</p><?php else: ?><p>Your account has one immutable referrer. Buyer status: <?= H::e(ucfirst($referrals['connected']['buyer_status'])) ?>; seller status: <?= H::e(ucfirst($referrals['connected']['seller_status'])) ?>.</p><?php endif; ?>
<h2>Credit ledger</h2><div class="responsive-table"><table><thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php if (!$tx): ?><tr><td colspan="4">No credit activity yet.</td></tr><?php endif; ?><?php foreach ($tx as $entry): ?><tr><td><?= H::e(ucwords(str_replace('_', ' ', $entry['type']))) ?></td><td><?= H::money($entry['amount']) ?></td><td><?= H::e(ucfirst($entry['status'])) ?></td><td><?= H::e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
