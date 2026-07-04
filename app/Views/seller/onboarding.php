<h1>Seller onboarding</h1>
<p class="muted">Complete these Phase 10 launch-readiness steps after your seller application is approved.</p>
<?php $ok = fn($v) => $v ? '<span class="badge ok">Complete</span>' : '<span class="badge pending">Needs setup</span>'; ?>
<section class="card page-section">
    <h2>Your setup checklist</h2>
    <ol class="grid">
        <li><strong>Complete seller profile</strong><br><?=$ok($readiness['profile'])?> <a href="/seller/store">Edit profile</a></li>
        <li><strong>Set up Stripe payouts</strong><br><?=$ok($readiness['stripe'])?> <?php if(!empty($readiness['stripe_started']) && empty($readiness['stripe'])):?><span class="muted">Stripe connection started; onboarding is not payout-ready yet.</span><?php endif;?> <a href="/seller/stripe">Open Stripe setup</a></li>
        <li><strong>Set up store details</strong><br><?=$ok($readiness['store'])?> <a href="/seller/store">Edit store</a></li>
        <li><strong>Add products/listings</strong><br><?=$ok($readiness['products'])?> <?=H::e((string)$readiness['product_count'])?> product(s) started. <a href="/seller/products">Manage products</a></li>
        <li><strong>Review seller FAQ / marketplace rules</strong><br><span class="badge ok">Shown below</span></li>
    </ol>
</section>
<section class="card page-section">
    <h2>Payout readiness</h2>
    <p>Stripe account: <strong><?=H::e($d['stripe_account_status'] ?? 'not_connected')?></strong></p>
    <p>Details submitted: <?=!empty($d['stripe_details_submitted']) ? 'Yes' : 'No'?> · Payouts enabled: <?=!empty($d['stripe_payouts_enabled']) ? 'Yes' : 'No'?> · Payout-ready: <strong><?=$readiness['payout'] ? 'Yes' : 'No'?></strong></p>
</section>
<section class="card page-section">
    <h2>Seller FAQ: fees, payouts, refunds, and cancellations</h2>
    <h3>What does it cost to sell on Asset Moth?</h3>
    <p>There is no startup fee, no monthly fee, and no listing fee. Asset Moth only earns when you make a sale.</p>
    <p>Asset Moth keeps an <?=H::e((string)$commissionPercent)?>% marketplace commission when a sale happens. Stripe/payment processing fees also apply, and Asset Moth’s <?=H::e((string)$commissionPercent)?>% commission is separate from Stripe/payment processing fees.</p>
    <h3>How do seller payouts work?</h3>
    <p>Buyer checkout can work before seller onboarding is complete, but seller payouts remain pending until Stripe Connect onboarding is complete and Stripe marks the account payout-ready. Seller payouts are handled through Stripe Connect after onboarding.</p>
    <h3>Refunds and digital purchase cancellations</h3>
    <p>Refunds are Stripe-processed and admin-exception only because Asset Moth is a digital product marketplace. Buyers cannot self-cancel completed digital purchases, and sellers cannot issue instant refunds themselves.</p>
</section>
