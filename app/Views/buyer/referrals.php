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
<div class="responsive-table"><table><thead><tr><th>Buyer reward</th><th>Seller reward</th><th>Qualifying events</th><th>Amounts</th></tr></thead><tbody>
<?php if (!$referrals['made']): ?><tr><td colspan="4">You have not referred anyone yet.</td></tr><?php endif; ?>
<?php foreach ($referrals['made'] as $referral): $sellerState=$referral['commission_ended_at']?'permanently stopped':($referral['seller_rewarded_at']?'rewarded':($referral['status']==='qualified'?'qualified':$referral['seller_status'])); ?><tr><td><?= H::e(ucfirst($referral['buyer_status'])) ?></td><td><?= H::e(ucwords(str_replace('_',' ',$sellerState))) ?><br><?= H::e($referral['referred_store'] ?? $referral['referred_name']) ?></td><td><?= $referral['buyer_qualifying_order_id'] ? 'Buyer order #' . (int)$referral['buyer_qualifying_order_id'] : 'Waiting for an eligible order' ?><br><?= $referral['seller_qualifying_order_id'] ? 'Seller order #' . (int)$referral['seller_qualifying_order_id'] : 'Waiting for an eligible sale' ?></td><td>Buyer <?= H::money($referral['buyer_referrer_reward_amount']) ?> · Seller <?= H::money($referral['seller_referrer_reward_amount']) ?><?= $referral['seller_rewarded_at']?'<br>Awarded '.H::e($referral['seller_rewarded_at']):'' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h2>Referral attached to you</h2>
<?php if (!$referrals['connected']): ?><p>No referral is attached to your account.</p><?php else: ?><p>Your account has one immutable referrer. Buyer status: <?= H::e(ucfirst($referrals['connected']['buyer_status'])) ?>; seller status: <?= H::e(ucfirst($referrals['connected']['seller_status'])) ?>.</p><?php endif; ?>
<h2>Credit ledger</h2><div class="responsive-table"><table><thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php if (!$tx): ?><tr><td colspan="4">No credit activity yet.</td></tr><?php endif; ?><?php foreach ($tx as $entry): ?><tr><td><?= H::e(ucwords(str_replace('_', ' ', $entry['type']))) ?></td><td><?= H::money($entry['amount']) ?></td><td><?= H::e(ucfirst($entry['status'])) ?></td><td><?= H::e($entry['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
