<h1>Designer Management</h1>

<nav class="tabs">
    <?php foreach(['approved'=>'Approved','disabled'=>'Disabled','all'=>'All'] as $key=>$label): ?>
        <a class="<?=($status??'approved')===$key?'active':''?>" href="/admin/designers?status=<?=$key?>"><?=H::e($label)?></a>
    <?php endforeach; ?>
</nav>

<p class="muted">Approved sellers show by default. Disabled/test sellers stay preserved for payment history but are hidden from the default view.</p>

<style>
.designer-admin-table {
    width: 100%;
    table-layout: auto;
}

.designer-admin-table th,
.designer-admin-table td {
    vertical-align: top;
}

.designer-admin-table .designer-email,
.designer-admin-table .small-detail {
    display: block;
    margin-top: .2rem;
    font-size: .85em;
    opacity: .75;
}

.designer-admin-table .recognition-cell {
    min-width: 150px;
}

.designer-admin-table .stripe-cell {
    min-width: 150px;
}

.designer-admin-table .actions-cell {
    min-width: 190px;
}

.designer-admin-table .status-good {
    color: #18794e;
    font-weight: 600;
}

.designer-admin-table .status-warn {
    color: #b26a00;
    font-weight: 600;
}

.designer-admin-table .status-bad {
    color: #b42318;
    font-weight: 600;
}

.designer-action-form {
    display: flex;
    flex-direction: column;
    gap: .45rem;
}

.designer-action-form select,
.designer-action-form input,
.designer-action-form button {
    width: 100%;
    box-sizing: border-box;
}

.designer-action-extra[hidden] {
    display: none;
}
</style>

<table class="designer-admin-table">
    <tr>
        <th>Designer</th>
        <th>Store</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Recognition</th>
        <th>Stripe</th>
        <th>Actions</th>
    </tr>

    <?php foreach($designers as $d): ?>
        <tr>
            <td>
                <strong><?=H::e($d['display_name'])?></strong>
                <span class="designer-email"><?=H::e($d['email'])?></span>
            </td>

            <td>
                <a href="/store/<?=H::e($d['store_slug'])?>">View Store</a>
            </td>

            <td><?=H::e($d['status'])?></td>

            <td><?=$d['follower_count']??0?></td>

            <td class="recognition-cell">
                <strong><?=H::e($d['creator_rank'])?></strong>

                <span class="small-detail">
                    <?=number_format((int)$d['qualifying_sales_count'])?> qualifying sale<?=((int)$d['qualifying_sales_count']===1?'':'s')?>
                </span>

                <?php if(($d['calculated_rank']??$d['creator_rank']) !== $d['creator_rank']): ?>
                    <span class="small-detail">
                        Calculated: <?=H::e($d['calculated_rank'])?>
                    </span>
                <?php endif; ?>

                <?php if($d['founder_position']): ?>
                    <span class="small-detail">
                        Founder #<?=(int)$d['founder_position']?> —
                        <?=$d['founder_active']?'active':'inactive'?>
                    </span>
                <?php endif; ?>
            </td>

            <td class="stripe-cell">
                <?php
                    $stripeStatus = $d['stripe_account_status'] ?? 'not_connected';
                    $payoutReady = !empty($d['stripe_details_submitted']) && !empty($d['stripe_payouts_enabled']);
                    $stripeClass = $payoutReady ? 'status-good' : ($stripeStatus === 'not_connected' ? 'status-bad' : 'status-warn');
                ?>
                <span class="<?=$stripeClass?>"><?=H::e($stripeStatus)?></span>

                <span class="small-detail <?=$payoutReady ? 'status-good' : 'status-warn'?>">
                    <?=$payoutReady ? 'Payout-ready' : 'Onboarding incomplete'?>
                </span>
            </td>

            <td class="actions-cell">
                <form method="post" class="designer-action-form">
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <input type="hidden" name="id" value="<?=$d['id']?>">

                    <select name="action" class="designer-action-select" required>
                        <option value="">Choose an action…</option>

                        <optgroup label="Creator Rank">
                            <option value="set_rank_override">Set rank override</option>
                            <option value="remove_rank_override">Remove rank override</option>
                        </optgroup>

                        <optgroup label="Founder">
                            <option value="grant">Grant Founder</option>
                            <option value="force_active">Lock Founder active</option>
                            <option value="force_inactive">Force Founder inactive</option>
                            <option value="restore">Restore Founder</option>
                            <option value="automatic">Return Founder to automatic</option>
                        </optgroup>

                        <optgroup label="Seller Account">
                            <?php if(($d['status'] ?? '') === 'disabled'): ?>
                                <option value="enable">Enable seller</option>
                            <?php else: ?>
                                <option value="disable">Disable seller</option>
                                <option value="inactive">Mark inactive</option>
                                <option value="delete">Mark deleted</option>
                            <?php endif; ?>
                        </optgroup>
                    </select>

                    <div class="designer-action-extra rank-choice" hidden>
                        <select name="creator_rank">
                            <?php foreach(['Bronze','Silver','Gold','Platinum','Diamond'] as $rank): ?>
                                <option value="<?=$rank?>" <?=$d['creator_rank']===$rank?'selected':''?>><?=$rank?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="designer-action-extra reason-choice" hidden>
                        <input
                            name="reason"
                            minlength="3"
                            maxlength="500"
                            placeholder="Required audit reason"
                        >
                    </div>

                    <button type="submit">Apply</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>
document.querySelectorAll('.designer-action-form').forEach(function(form) {
    const action = form.querySelector('.designer-action-select');
    const rankWrap = form.querySelector('.rank-choice');
    const reasonWrap = form.querySelector('.reason-choice');
    const reasonInput = reasonWrap.querySelector('input');

    const reasonActions = [
        'set_rank_override',
        'remove_rank_override',
        'grant',
        'force_active',
        'force_inactive',
        'restore',
        'automatic'
    ];

    function updateFields() {
        const value = action.value;

        rankWrap.hidden = value !== 'set_rank_override';

        const needsReason = reasonActions.includes(value);
        reasonWrap.hidden = !needsReason;
        reasonInput.required = needsReason;
    }

    action.addEventListener('change', updateFields);

    form.addEventListener('submit', function(event) {
        const value = action.value;

        const confirmations = {
            disable: 'Disable this seller? Their seller account will no longer be approved.',
            inactive: 'Mark this store permanently inactive? Referral commission can never restart.',
            delete: 'Mark this store deleted? Financial history will remain.',
            enable: 'Enable this seller?'
        };

        if (confirmations[value] && !confirm(confirmations[value])) {
            event.preventDefault();
        }
    });

    updateFields();
});
</script>
