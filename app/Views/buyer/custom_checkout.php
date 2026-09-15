<h1>Secure checkout</h1>

<p class="notice warning">
    Sales tax is calculated using your US billing address.
    Store credit is applied after tax.
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
        Before tax and store credit:
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

        <legend>US billing address for sales tax</legend>

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
            State
            <input
                name="billing_state"
                required
                maxlength="2"
                autocomplete="billing address-level1"
                value="<?=H::e($_POST['billing_state'] ?? '')?>"
            >
        </label>

        <label>
            ZIP code
            <input
                name="billing_postal_code"
                required
                inputmode="numeric"
                autocomplete="billing postal-code"
                value="<?=H::e($_POST['billing_postal_code'] ?? '')?>"
            >
        </label>

        <input
            type="hidden"
            name="billing_country"
            value="US"
        >

    </fieldset>

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
        minus any store credit you choose to use.
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
