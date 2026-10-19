<?php use App\Core\Helpers as H; ?>

<h1>Prize Wheel</h1>

<?php
$minimumCents=(int)(
    $settings['minimum_purchase_cents']
    ??500
);

$minimumAmount=
    number_format(
        $minimumCents/100,
        2,
        '.',
        ''
    );
?>

<section class="card page-section">
    <h2>
        Promotion status:
        <?php if(!empty($settings['is_enabled'])): ?>
            <span class="badge yes">ON</span>
        <?php else: ?>
            <span class="badge no">OFF</span>
        <?php endif; ?>
    </h2>

    <p>
        <strong>Participating sellers:</strong>
        <?=number_format((int)$optedInStores)?>

        ·

        <strong>Total available prize units:</strong>
        <?=number_format((int)$availablePrizes)?>

        ·

        <strong>Minimum store purchase:</strong>
        $<?=H::e($minimumAmount)?>
    </p>

    <?php if(!empty($settings['is_enabled'])): ?>
        <p class="notice success">
            The Prize Wheel promotion is live. Each participating seller
            has their own wheel and only awards prizes donated by that
            seller.
        </p>
    <?php else: ?>
        <p class="notice warning">
            The Prize Wheel promotion is currently hidden from every
            storefront.
        </p>
    <?php endif; ?>
</section>

<section class="card page-section">
    <h2>Participating Sellers</h2>

    <p class="help-text">
        These sellers have opted into the promotion. Each seller's wheel
        uses only that seller's own approved Prize Wheel donations.
    </p>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Seller</th>
                <th>Store</th>
                <th>Wheel prizes</th>
                <th>Prize units</th>
                <th>Ready?</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach(($participatingStores??[]) as $seller): ?>
                <tr>
                    <td>
                        <?=H::e($seller['display_name'])?>
                    </td>

                    <td>
                        <a
                            href="/store/<?=H::e($seller['store_slug'])?>"
                            target="_blank"
                        >
                            View Store
                        </a>
                    </td>

                    <td>
                        <?=number_format(
                            (int)$seller['available_prizes']
                        )?>
                    </td>

                    <td>
                        <?=number_format(
                            (int)$seller['available_prize_units']
                        )?>
                    </td>

                    <td>
                        <?php if(
                            (int)$seller['available_prize_units']>0
                        ): ?>
                            <span class="badge yes">
                                Ready
                            </span>
                        <?php else: ?>
                            <span class="badge no">
                                Needs Wheel Prizes
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($participatingStores)): ?>
                <tr>
                    <td colspan="5">
                        No sellers have opted into the Prize Wheel yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if(H::canAdmin('prizes.manage')): ?>
<section class="card page-section">
    <h2>Promotion Settings</h2>

    <form method="post" class="form">
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <input
            type="hidden"
            name="action"
            value="settings"
        >

        <label>
            Prize Wheel promotion
            <select name="is_enabled" required>
                <option
                    value="0"
                    <?=empty($settings['is_enabled'])?'selected':''?>
                >
                    OFF — hide promotion from every store
                </option>

                <option
                    value="1"
                    <?=!empty($settings['is_enabled'])?'selected':''?>
                >
                    ON — activate participating seller wheels
                </option>
            </select>
        </label>

        <label>
            Minimum purchase required at the participating store
            <input
                type="number"
                name="minimum_purchase"
                min="1.00"
                max="1000.00"
                step="0.01"
                required
                value="<?=H::e($minimumAmount)?>"
            >
        </label>

        <p class="help-text">
            One paid qualifying order unlocks one spin for that seller's
            wheel. Sales tax does not count toward the minimum.
        </p>

        <label>
            Promotion heading
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
            Storefront popup / wheel message
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

<section class="card page-section">
    <h2>Start Another Promotion Round</h2>

    <p>
        A new round makes only purchases made during the new round
        eligible. Previous winners and spin history remain saved.
    </p>

    <?php if(!empty($settings['is_enabled'])): ?>
        <p class="notice warning">
            Turn the promotion OFF before starting a new round.
        </p>
    <?php else: ?>
        <form method="post">
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::csrf()?>"
            >

            <input
                type="hidden"
                name="action"
                value="new_round"
            >

            <button class="btn alt">
                Start New Promotion Round
            </button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card page-section">
    <h2>Recent Spins / Winners</h2>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Winner</th>
                <th>Seller Wheel</th>
                <th>Qualifying Order</th>
                <th>Prize</th>
                <th>Status</th>
                <th>Won</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach(($recentSpins??[]) as $spin): ?>
                <tr>
                    <td>
                        <?=H::e($spin['winner_name'])?>
                        <br>
                        <small>
                            <?=H::e($spin['winner_email'])?>
                        </small>
                    </td>

                    <td>
                        <a
                            href="/store/<?=H::e($spin['store_slug'])?>"
                            target="_blank"
                        >
                            <?=H::e($spin['storefront_name'])?>
                        </a>
                    </td>

                    <td>
                        #<?=(int)$spin['qualifying_order_id']?>
                    </td>

                    <td>
                        <?=H::e($spin['prize_title'])?>
                    </td>

                    <td>
                        <?=H::e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $spin['assignment_status']
                                )
                            )
                        )?>
                    </td>

                    <td>
                        <?=H::e($spin['created_at'])?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($recentSpins)): ?>
                <tr>
                    <td colspan="6">
                        No Prize Wheel spins yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
