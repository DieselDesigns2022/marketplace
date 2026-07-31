<h1>Seller referrals</h1>
<p>Your seller referral link: <code><?= H::e(H::baseUrl() . '/apply?seller_ref=' . rawurlencode($referralCode)) ?></code></p>
<p>You and the referred seller each earn $5.00 only after the approved seller completes their first non-refunded sale. Application or approval alone does not qualify.</p>
<p>The account keeps one immutable referrer. Store credit never expires, is marketplace-only, non-transferable, and has no cash value.</p>
<div class="responsive-table"><table><thead><tr><th>Status</th><th>Qualifying sale</th><th>Reward</th></tr></thead><tbody>
<?php if (!$refs): ?><tr><td colspan="3">No seller referrals yet.</td></tr><?php endif; ?>
<?php foreach ($refs as $referral): ?><tr><td><?= H::e(ucfirst($referral['seller_status'])) ?></td><td><?= $referral['seller_qualifying_order_id'] ? 'Order #' . (int)$referral['seller_qualifying_order_id'] . ', item #' . (int)$referral['seller_qualifying_order_item_id'] : 'Waiting for the first eligible sale' ?></td><td><?= $referral['seller_rewarded_at'] ? H::money($referral['seller_referrer_reward_amount']) . ' earned by each participant' : '$5.00 each when qualified' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
