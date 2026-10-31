<h1>Stripe payouts</h1>
<p class="muted">Connect Stripe so Creative Moth can transfer your seller portion after paid marketplace orders.</p>
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
        <form method="post" action="/seller/stripe/connect">
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

            <?php if(empty($d['stripe_connect_account_id'])): ?>
                <label>Seller country
                    <select name="country_code" required autocomplete="country">
                        <option value="">Select country</option>
                        <?php
                        $sellerCountry=\App\Services\StripeService::normalizeCountryCode(
                            (string)($d['country_code']??'')
                        );
                        ?>
                        <?php foreach(\App\Services\StripeService::countryOptions() as $countryCode=>$countryName): ?>
                            <option value="<?=H::e($countryCode)?>" <?=$sellerCountry===$countryCode?'selected':''?>><?=H::e($countryName)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <p class="muted">
                    Stripe payout availability varies by country.
                    Stripe will verify whether payouts are supported for your location.
                </p>
            <?php endif; ?>

            <button class="btn"><?=empty($d['stripe_connect_account_id']) ? 'Connect with Stripe' : 'Continue Stripe setup'?></button>
        </form>
    <?php endif; ?>
</section>
<section class="card page-section">
    <h2>Fees and payout notes</h2>
    <p>Sellers must complete Stripe onboarding before payouts/transfers can be sent. Stripe payment processing is handled by Creative Moth and is not deducted as a separate seller fee.</p>
    <p>There is no startup fee, monthly fee, or listing fee. Creative Moth charges 9% + $0.30 when you make a sale. The $0.30 applies once to each seller’s portion of a completed order, not to every product.</p>
    <p>Sales tax, when required, is handled by Creative Moth through Stripe Tax and is excluded from seller payouts.</p>
</section>
