<h1>Stripe payouts</h1>
<p class="muted">Connect Stripe so Asset Moth can transfer your seller portion after paid marketplace orders.</p>
<section class="card page-section">
    <h2>Current Stripe Connect status</h2>
    <p>Connection: <strong><?=$readiness['payout'] ? 'Connected' : (empty($d['stripe_connect_account_id']) ? 'Not connected' : 'Onboarding started')?></strong></p>
    <p>Details submitted: <strong><?=!empty($d['stripe_details_submitted']) ? 'Yes' : 'No'?></strong></p>
    <p>Payouts enabled: <strong><?=!empty($d['stripe_payouts_enabled']) ? 'Yes' : 'No'?></strong></p>
    <p>Payout-ready: <strong><?=$readiness['payout'] ? 'Yes — Stripe connected and payout setup ready.' : 'Not payout-ready yet.'?></strong></p>
    <p>Raw account status: <span class="badge"><?=H::e($d['stripe_account_status'] ?? 'not_connected')?></span></p>
    <?php if($readiness['payout']): ?>
        <p><span class="badge ok">Complete</span> Stripe connected and payout setup ready.</p>
    <?php else: ?>
        <form method="post" action="/seller/stripe/connect"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="btn"><?=empty($d['stripe_connect_account_id']) ? 'Connect with Stripe' : 'Continue Stripe setup'?></button></form>
    <?php endif; ?>
</section>
<section class="card page-section">
    <h2>Fees and payout notes</h2>
    <p>Sellers must complete Stripe onboarding before payouts/transfers can be sent. Stripe/payment processing fees apply.</p>
    <p>Asset Moth keeps an <?=H::e((string)$commissionPercent)?>% marketplace commission on each sale. Asset Moth has no startup fee, no monthly fee, and no listing fee.</p>
</section>
