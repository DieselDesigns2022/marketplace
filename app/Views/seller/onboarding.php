<?php $dashboardGate = $dashboardGate ?? false; ?>
<h1><?= $dashboardGate ? 'Seller onboarding required' : 'Seller onboarding' ?></h1>
<?php if ($dashboardGate): ?>
<section class="notice warning">Your seller application is approved, but onboarding is not complete yet. Complete the steps below before accessing products, orders, payouts, coupons, or other seller dashboard tools.</section>
<?php endif; ?>
<p class="muted">Complete these seller onboarding steps after your application is approved.</p>
<?php $ok = fn($v) => $v ? '<span class="badge ok">Complete</span>' : '<span class="badge pending">Needs setup</span>'; ?>
<section class="card page-section">
    <h2>Your setup checklist</h2>
    <ol class="grid">
        <li><strong>Complete seller profile</strong><br><?=$ok($readiness['profile'])?> <a href="/seller/store">Edit profile</a></li>
        <li><strong>Set up Stripe payouts</strong><br><?=$ok($readiness['stripe'])?> <?php if(!empty($readiness['stripe_started']) && empty($readiness['stripe'])):?><span class="muted">Stripe connection started; onboarding is not payout-ready yet.</span><?php endif;?> <a href="/seller/stripe">Open Stripe setup</a></li>
        <li><strong>Set up store details</strong><br><?=$ok($readiness['store'])?> <a href="/seller/store">Edit store</a></li>
        <li><strong>Unlock product tools</strong><br><?=!empty($readiness['complete']) ? '<span class="badge ok">Unlocked</span>' : '<span class="badge pending">Locked until onboarding is complete</span>'?> Product creation opens after profile, store, and Stripe payout setup are complete.</li>
        <li><strong>Review seller FAQ / marketplace rules</strong><br><span class="badge ok">Shown below</span></li>
    </ol>
</section>
<section class="card page-section">
    <h2>Payout readiness</h2>
    <p>Stripe account: <strong><?=H::e($d['stripe_account_status'] ?? 'not_connected')?></strong></p>
    <p>Details submitted: <?=!empty($d['stripe_details_submitted']) ? 'Yes' : 'No'?> · Payouts enabled: <?=!empty($d['stripe_payouts_enabled']) ? 'Yes' : 'No'?> · Payout-ready: <strong><?=$readiness['payout'] ? 'Yes' : 'No'?></strong></p>
</section>
<details class="card page-section">
    <summary style="color:#6d28d9; cursor:pointer; font-size:1.05rem;"><strong>Seller FAQ: fees, payouts, refunds, and cancellations</strong></summary>

    <div style="margin-top:1rem; padding:1rem; border-radius:16px; background:#f5f3ff;">
        <p style="margin-top:0; color:#2f1b63;"><strong>Quick questions:</strong></p>
        <p style="margin-bottom:0; line-height:1.8;">
            <a href="#faq-selling-costs" style="color:#6d28d9; font-weight:700;">What does it cost to sell?</a><br>
            <a href="#faq-seller-payouts" style="color:#6d28d9; font-weight:700;">How do seller payouts work?</a><br>
            <a href="#faq-refunds-cancellations" style="color:#6d28d9; font-weight:700;">How do refunds and cancellations work?</a>
        </p>
    </div>

    <h3 id="faq-selling-costs" style="color:#4c1d95;">What does it cost to sell on Asset Moth?</h3>
    <p style="color:#334155;">There is no startup fee, no monthly fee, and no listing fee. Asset Moth only earns when you make a sale.</p>
    <p style="color:#334155;">Asset Moth keeps an <?=H::e((string)$commissionPercent)?>% marketplace commission when a sale happens. Stripe/payment processing fees also apply, and Asset Moth’s <?=H::e((string)$commissionPercent)?>% commission is separate from Stripe/payment processing fees.</p>

    <h3 id="faq-seller-payouts" style="color:#4c1d95;">How do seller payouts work?</h3>
    <p style="color:#334155;">Buyer checkout can work before seller onboarding is complete, but seller payouts remain pending until Stripe Connect onboarding is complete and Stripe marks the account payout-ready. Seller payouts are handled through Stripe Connect after onboarding.</p>

    <h3 id="faq-refunds-cancellations" style="color:#4c1d95;">Refunds and digital purchase cancellations</h3>
    <p style="color:#334155;">Refunds are Stripe-processed and admin-exception only because Asset Moth is a digital product marketplace. Buyers cannot self-cancel completed digital purchases, and sellers cannot issue instant refunds themselves.</p>
</details>
