<?php use App\Core\Helpers as H; ?>

<h1>Prize Wheel</h1>

<section class="card page-section">
    <h2>
        Current status:
        <?php if(!empty($settings['is_enabled'])): ?>
            <span class="badge yes">ON</span>
        <?php else: ?>
            <span class="badge no">OFF</span>
        <?php endif; ?>
    </h2>

    <?php if(!empty($settings['is_enabled'])): ?>
        <p class="notice success">
            The Prize Wheel is currently live on every approved seller
            storefront that opted in.
        </p>
    <?php else: ?>
        <p class="notice warning">
            The Prize Wheel is currently hidden from the entire public
            marketplace. Seller opt-in choices remain saved.
        </p>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <strong>Opted-in stores</strong>
            <p><?=number_format((int)$optedInStores)?></p>
        </div>

        <div class="card">
            <strong>Available Prize Wheel inventory</strong>
            <p><?=number_format((int)$availablePrizes)?></p>
        </div>
    </div>
</section>

<?php if(H::canAdmin('prizes.manage')): ?>
<section class="card page-section">
    <h2>Grand Opening Prize Wheel Settings</h2>

    <form method="post" class="form">
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <label>
            Prize Wheel visibility
            <select name="is_enabled" required>
                <option
                    value="0"
                    <?=empty($settings['is_enabled'])?'selected':''?>
                >
                    OFF — hide from all storefronts
                </option>

                <option
                    value="1"
                    <?=!empty($settings['is_enabled'])?'selected':''?>
                >
                    ON — show on all opted-in storefronts
                </option>
            </select>
        </label>

        <p class="help-text">
            This is the master switch. Turning it OFF immediately prevents
            the Prize Wheel from rendering anywhere on seller storefronts.
            Sellers remain opted in, so turning it back ON restores it
            automatically to those stores.
        </p>

        <label>
            Wheel heading
            <input
                name="heading"
                maxlength="190"
                required
                value="<?=H::e(
                    $settings['heading']
                    ??'Grand Opening Prize Wheel'
                )?>"
            >
        </label>

        <label>
            Storefront message
            <textarea
                name="description"
                maxlength="1000"
                rows="4"
            ><?=H::e($settings['description']??'')?></textarea>
        </label>

        <button class="btn">
            Save Prize Wheel Settings
        </button>
    </form>
</section>
<?php endif; ?>

<section class="card page-section">
    <h2>How visibility works</h2>

    <p>
        The Prize Wheel appears only when both conditions are true:
    </p>

    <ol>
        <li>
            The master Prize Wheel switch on this page is ON.
        </li>
        <li>
            The seller has opted their storefront into the Prize Wheel.
        </li>
    </ol>

    <p>
        A seller who does not opt in will never display the wheel.
        When the master switch is OFF, no storefront displays it regardless
        of seller preference.
    </p>
</section>
