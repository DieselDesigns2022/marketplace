<h1>Referrals &amp; store credit</h1>
<p>Your buyer referral link: <code><?= H::e(H::baseUrl() . '/register?ref=' . rawurlencode($referrals['code'])) ?></code></p>
<p>You and a new buyer each receive $1.50 after their first completed order with a positive payment after credits. Credit-only, failed, cancelled, refunded, unpaid, or unresolved review orders do not qualify.</p>
<p>Store credit never expires, is marketplace-only, non-transferable, and has no cash value.</p>
<div class="cards"><div class="card"><strong>Total</strong><br><?= H::money($balances['total']) ?></div><div class="card"><strong>Reserved</strong><br><?= H::money($balances['reserved']) ?></div><div class="card"><strong>Available</strong><br><?= H::money($balances['available']) ?></div></div>
<h2>Referrals you made</h2>
<div class="responsive-table"><table><thead><tr><th>Buyer reward</th><th>Seller reward</th><th>Qualifying events</th><th>Amounts</th></tr></thead><tbody>
<?php if (!$referrals['made']): ?><tr><td colspan="4">You have not referred anyone yet.</td></tr><?php endif; ?>
<?php foreach ($referrals['made'] as $referral): ?><tr><td><?= H::e(ucfirst($referral['buyer_status'])) ?></td><td><?= H::e(ucfirst($referral['seller_status'])) ?></td><td><?= $referral['buyer_qualifying_order_id'] ? 'Buyer order #' . (int)$referral['buyer_qualifying_order_id'] : 'Waiting for an eligible order' ?><br><?= $referral['seller_qualifying_order_id'] ? 'Seller order #' . (int)$referral['seller_qualifying_order_id'] : 'Waiting for an eligible sale' ?></td><td>Buyer <?= H::money($referral['buyer_referrer_reward_amount']) ?> · Seller <?= H::money($referral['seller_referrer_reward_amount']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h2>Referral attached to you</h2>
<?php if (!$referrals['connected']): ?><p>No referral is attached to your account.</p><?php else: ?><p>Your account has one immutable referrer. Buyer status: <?= H::e(ucfirst($referrals['connected']['buyer_status'])) ?>; seller status: <?= H::e(ucfirst($referrals['connected']['seller_status'])) ?>.</p><?php endif; ?>
<h2>Credit ledger</h2><div class="responsive-table"><table><thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php if (!$tx): ?><tr><td colspan="4">No credit activity yet.</td></tr><?php endif; ?><?php foreach ($tx as $entry): ?><tr><td><?= H::e(ucwords(str_replace('_', ' ', $entry['type']))) ?></td><td><?= H::money($entry['amount']) ?></td><td><?= H::e(ucfirst($entry['status'])) ?></td><td><?= H::e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
