<?php use App\Core\Helpers as H; ?>

<h1>Prize Wheel</h1>

<?php
$minimumCents=(int)(
    $settings['minimum_purchase_cents']
    ??500
);

$minimumAmount=number_format(
    $minimumCents/100,
    2,
    '.',
    ''
);
?>

<section class="card page-section">
    <h2>
        Promotion status:
        <strong><?=H::e(ucfirst(\App\Services\PrizeWheelService::promotionState($settings)))?></strong>
    </h2>

    <?php if(!empty($settings['starts_at']) && !empty($settings['ends_at'])): ?>
    <p><strong>Automatic schedule — Detroit time:</strong><br>
    Starts <?=H::e(\App\Services\BattleService::eastern($settings['starts_at']))?><br>
    Runs through <?=H::e(\App\Services\BattleService::eastern(gmdate('Y-m-d H:i:s',strtotime($settings['ends_at'].' UTC')-1)))?>.</p>
    <p>ON schedules the wheel for these dates. It stays hidden before the start and closes automatically after the final minute. OFF pauses it immediately.</p>
    <?php endif; ?>

    <p>
        <strong>Participating sellers:</strong>
        <?=number_format((int)$optedInStores)?>

        ·

        <strong>Active wheel prizes:</strong>
        <?=number_format((int)$availablePrizes)?>

        ·

        <strong>Minimum purchase:</strong>
        $<?=H::e($minimumAmount)?>
    </p>

    <p class="help-text">
        Sellers are default OPT-OUT. Only sellers who manually agree
        to participate in Creative Moth giveaways receive the Prize
        Wheel on their storefront.
    </p>
</section>

<section class="card page-section">
    <h2>Participating Sellers</h2>

    <p class="help-text">
        These sellers voluntarily opted into Creative Moth giveaways.
        Giveaway store credits and coupons are seller-funded and
        Creative Moth does not reimburse the seller for their value.
    </p>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Seller</th>
                <th>Store</th>
                <th>Status</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach(($participatingStores??[]) as $seller): ?>
                <tr>
                    <td><?=H::e($seller['display_name'])?></td>

                    <td>
                        <a
                            href="/store/<?=H::e($seller['store_slug'])?>"
                            target="_blank"
                        >
                            View Store
                        </a>
                    </td>

                    <td>
                        <span class="badge yes">
                            Participating
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($participatingStores)): ?>
                <tr>
                    <td colspan="3">
                        No sellers have opted into Creative Moth
                        giveaways yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if(H::canAdmin('prizes.manage')): ?>

<section class="card page-section">
    <h2>Wheel Prizes</h2>

    <p class="help-text">
        This single prize list applies to every participating seller.
        Every prize is fulfilled in the context of the storefront
        where the buyer spun the wheel.
    </p>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Prize</th>
                <th>Type</th>
                <th>Value</th>
                <th>Weight</th>
                <th>Order</th>
                <th>Active</th>
                <th>Save</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach(($wheelPrizes??[]) as $prize): ?>
                <tr>
                    <form method="post">
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?=H::csrf()?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="save_prize"
                        >

                        <input
                            type="hidden"
                            name="prize_id"
                            value="<?=(int)$prize['id']?>"
                        >

                        <td>
                            <input
                                name="title"
                                maxlength="190"
                                required
                                value="<?=H::e($prize['title'])?>"
                            >
                        </td>

                        <td>
                            <select
                                name="prize_type"
                                required
                            >
                                <?php
                                $types=[
                                    'seller_fixed_coupon'=>
                                        'Seller Store Credit',
                                    'seller_percent_coupon'=>
                                        'Seller % Off Coupon',
                                    'spin_again'=>
                                        'Spin Again',
                                    'grand_opening_entry'=>
                                        'Grand Opening Giveaway Entry',
                                ];
                                ?>

                                <?php foreach($types as $key=>$label): ?>
                                    <option
                                        value="<?=H::e($key)?>"
                                        <?=$prize['prize_type']===$key?'selected':''?>
                                    >
                                        <?=H::e($label)?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <input
                                type="number"
                                name="prize_value"
                                min="0"
                                step="0.01"
                                value="<?=H::e(
                                    $prize['prize_value']??''
                                )?>"
                            >
                        </td>

                        <td>
                            <input
                                type="number"
                                name="weight"
                                min="1"
                                max="100000"
                                required
                                value="<?=(int)$prize['weight']?>"
                            >
                        </td>

                        <td>
                            <input
                                type="number"
                                name="sort_order"
                                value="<?=(int)$prize['sort_order']?>"
                            >
                        </td>

                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    <?=!empty($prize['is_active'])?'checked':''?>
                                >
                                Active
                            </label>
                        </td>

                        <td>
                            <button class="btn">
                                Save
                            </button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3>Add Prize</h3>

    <form method="post" class="form">
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <input
            type="hidden"
            name="action"
            value="save_prize"
        >

        <label>
            Prize name
            <input
                name="title"
                maxlength="190"
                required
            >
        </label>

        <label>
            Prize type
            <select name="prize_type" required>
                <option value="seller_fixed_coupon">
                    Seller Store Credit
                </option>

                <option value="seller_percent_coupon">
                    Seller % Off Coupon
                </option>

                <option value="spin_again">
                    Spin Again
                </option>

                <option value="grand_opening_entry">
                    Grand Opening Giveaway Entry
                </option>
            </select>
        </label>

        <label>
            Value
            <input
                type="number"
                name="prize_value"
                min="0"
                step="0.01"
            >
        </label>

        <label>
            Weight / odds
            <input
                type="number"
                name="weight"
                min="1"
                max="100000"
                value="1"
                required
            >
        </label>

        <label>
            Display order
            <input
                type="number"
                name="sort_order"
                value="100"
            >
        </label>

        <label>
            <input
                type="checkbox"
                name="is_active"
                value="1"
                checked
            >
            Active
        </label>

        <button class="btn">
            Add Wheel Prize
        </button>
    </form>
</section>

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
            Minimum purchase required at participating store
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
            One paid qualifying order unlocks one spin for that
            seller's storefront. The minimum must remain after all
            coupons, Prize Credit, Marketplace Credit, and other
            discounts, and must be paid through checkout.
            Sales tax does not count.
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
        A new round makes only purchases made during the new
        round eligible. Previous spin and giveaway history remains.
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
                <th>Seller</th>
                <th>Order</th>
                <th>Prize</th>
                <th>Prize Code</th>
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
                        <?=!empty($spin['qualifying_order_id'])
                            ? '#'.(int)$spin['qualifying_order_id']
                            : 'Bonus spin'?>
                    </td>

                    <td>
                        <?=H::e(
                            $spin['prize_title']
                            ??$spin['result_title']
                        )?>
                    </td>

                    <td>
                        <?=H::e($spin['coupon_code']??'—')?>
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
