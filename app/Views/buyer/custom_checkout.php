<h1>Secure checkout</h1>

<p class="notice warning">
    Sales tax is calculated using your billing address when required. All prices are in USD.
    Prize Credit applies to merchandise first. Store credit may then apply to the remaining merchandise and tax.
</p>

<?php foreach($errors as $error): ?>
    <p class="alert error">
        <?=H::e($error)?>
    </p>
<?php endforeach; ?>

<section class="card">

    <h2><?=H::e($service['title'])?></h2>

    <p>
        Custom Design by
        <strong><?=H::e($service['display_name'])?></strong>
    </p>

    <p>
        Base price:
        <strong><?=H::money($service['price'])?></strong>
    </p>

    <?php if($selectedLicenses): ?>

        <h3>Selected licenses</h3>

        <?php foreach($selectedLicenses as $license): ?>

            <p>
                <?=H::e($license['name'])?>

                <?php if((float)$license['price'] > 0): ?>
                    · +<?=H::money($license['price'])?>
                <?php else: ?>
                    · included
                <?php endif; ?>
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        Before tax and credits:
        <strong><?=H::money($subtotal)?></strong>
    </p>

    <p>
        Available store credit:
        <strong><?=H::money($balances['available'] ?? 0)?></strong>
    </p>

</section>

<form
    method="post"
    class="form card"
    action="/custom-design/<?=H::e($service['slug'])?>/checkout?token=<?=H::e(rawurlencode($token))?>"
>

    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <input
        type="hidden"
        name="checkout_token"
        value="<?=H::e($token)?>"
    >

    <fieldset>

        <legend>Billing address for tax calculation</legend>

        <label>
            Address line 1
            <input
                name="billing_line1"
                required
                autocomplete="billing address-line1"
                value="<?=H::e($_POST['billing_line1'] ?? '')?>"
            >
        </label>

        <label>
            Address line 2
            <input
                name="billing_line2"
                autocomplete="billing address-line2"
                value="<?=H::e($_POST['billing_line2'] ?? '')?>"
            >
        </label>

        <label>
            City
            <input
                name="billing_city"
                required
                autocomplete="billing address-level2"
                value="<?=H::e($_POST['billing_city'] ?? '')?>"
            >
        </label>

        <label>
            State / Province / Region
            <input
                name="billing_state"
                required
                autocomplete="billing address-level1"
                value="<?=H::e($_POST['billing_state'] ?? '')?>"
            >
        </label>

        <label>
            Postal code
            <input
                name="billing_postal_code"
                required
                autocomplete="billing postal-code"
                value="<?=H::e($_POST['billing_postal_code'] ?? '')?>"
            >
        </label>

        <label>
            Country
            <select
                name="billing_country"
                required
                autocomplete="billing country"
            >
                <option value="">Select country</option>
                <?php
                $billingCountry=\App\Services\StripeService::normalizeCountryCode(
                    (string)($_POST['billing_country']??'US')
                );
                ?>
                <?php foreach(\App\Services\StripeService::countryOptions() as $countryCode=>$countryName): ?>
                    <option
                        value="<?=H::e($countryCode)?>"
                        <?=$billingCountry===$countryCode?'selected':''?>
                    ><?=H::e($countryName)?></option>
                <?php endforeach; ?>
            </select>
        </label>

    </fieldset>

    <?php if((string)($service['prize_credit_mode'] ?? 'none') !== 'none'): ?>
        <p>
            Available Prize Credit:
            <strong><?=H::money($prizeAvailable ?? 0)?></strong>
        </p>

        <label>
            <input
                type="checkbox"
                name="use_prize_credit"
                value="1"
                <?=((float)($prizeAvailable ?? 0) > 0) ? '' : 'disabled'?>
            >

            Use available Prize Credit
        </label>

        <p class="help-text">
            Prize Credit applies only to this Custom Design merchandise.
            It is applied before store credit and cannot pay sales tax.
        </p>
    <?php endif; ?>

    <label>
        <input
            type="checkbox"
            name="use_credits"
            value="1"
            <?=((float)($balances['available'] ?? 0) > 0) ? '' : 'disabled'?>
        >

        Use available store credit
    </label>

    <p class="help-text">
        Your final total will be the Custom Design price
        plus selected license add-ons and applicable tax,
        minus Prize Credit and store credit you choose to use.
    </p>

    <button class="btn" type="submit">
        Calculate Tax &amp; Complete Checkout
    </button>

</form>

<p>
    <a href="/custom-design/<?=H::e($service['slug'])?>">
        ← Back to Custom Design
    </a>
</p>


<?php require __DIR__.'/../partials/billing_address_helper.php'; ?>
