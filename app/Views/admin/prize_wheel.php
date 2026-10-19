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

    <p>
        <strong>Opted-in stores:</strong>
        <?=number_format((int)$optedInStores)?>
        ·
        <strong>Available prize units:</strong>
        <?=number_format((int)$availablePrizes)?>
        ·
        <strong>Spins per person:</strong>
        <?=number_format((int)($settings['spins_per_user']??1))?>
    </p>

    <?php if(!empty($settings['is_enabled'])): ?>
        <p class="notice success">
            The Prize Wheel is live on every approved seller storefront
            that opted in.
        </p>
    <?php else: ?>
        <p class="notice warning">
            The Prize Wheel is hidden from the entire public marketplace.
            Seller opt-in choices and previous winner history remain saved.
        </p>
    <?php endif; ?>
</section>

<?php if(H::canAdmin('prizes.manage')): ?>
<section class="card page-section">
    <h2>Giveaway Settings</h2>

    <form method="post" class="form">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <input type="hidden" name="action" value="settings">

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

        <label>
            Spins allowed per person for this giveaway
            <input
                type="number"
                name="spins_per_user"
                min="1"
                max="25"
                required
                value="<?=(int)($settings['spins_per_user']??1)?>"
            >
        </label>

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

<section class="card page-section">
    <h2>Start Another Giveaway Round</h2>

    <p>
        Starting a new round resets spin eligibility without deleting
        previous winners or prize history.
    </p>

    <?php if(!empty($settings['is_enabled'])): ?>
        <p class="notice warning">
            Turn the Prize Wheel OFF before starting a new giveaway round.
        </p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
            <input type="hidden" name="action" value="new_round">

            <button class="btn alt">
                Start New Giveaway Round
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
                <th>Prize</th>
                <th>Storefront</th>
                <th>Spin</th>
                <th>Prize Status</th>
                <th>Won</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach(($recentSpins??[]) as $spin): ?>
                <tr>
                    <td>
                        <?=H::e($spin['winner_name'])?>
                        <br>
                        <small><?=H::e($spin['winner_email'])?></small>
                    </td>

                    <td>
                        <?=H::e($spin['prize_title'])?>
                        <?php if(
                            (float)($spin['approximate_retail_value']??0)>0
                        ): ?>
                            <br>
                            <small>
                                Approx.
                                <?=H::money(
                                    $spin['approximate_retail_value']
                                )?>
                            </small>
                        <?php endif; ?>
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
                        #<?=(int)$spin['spin_number']?>
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
