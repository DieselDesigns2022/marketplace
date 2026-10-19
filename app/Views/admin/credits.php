<?php use App\Core\Helpers as H; ?>
<h1><?=$mode==='credits'?'Credits':'Referrals & Seller Referral Payouts'?></h1>

<?php if($mode==='credits'):?>
<p>Marketplace Credits and Advertising Credits are managed here. Their balances and ledgers remain financially separate.</p>

<form method="get" action="/admin/credits" class="card form" role="search">
    <label>
        Search users
        <input name="q" value="<?=H::e($query)?>" maxlength="190">
    </label>
    <button class="btn">Search</button>
</form>

<div class="responsive-table">
<table>
<thead>
<tr>
    <th>User</th>
    <th>Marketplace Credit</th>
    <th>Advertising Credit</th>
    <th>Seller</th>
    <th>Review</th>
</tr>
</thead>
<tbody>
<?php foreach($users as $user):?>
<tr>
    <td><?=H::e($user['name'])?><br><small><?=H::e($user['email'])?></small></td>
    <td><?=H::money($user['available_balance'])?> available</td>
    <td><?=H::money($user['advertising_available_balance'])?> available</td>
    <td><?=$user['approved_seller']?'Approved':'No'?></td>
    <td><a href="/admin/credits?user_id=<?=(int)$user['id']?>">Review account</a></td>
</tr>
<?php endforeach;?>
<?php if(!$users):?>
<tr><td colspan="5">No matching users.</td></tr>
<?php endif;?>
</tbody>
</table>
</div>

<nav aria-label="User results pages">
    <a href="?q=<?=rawurlencode($query)?>&amp;page=<?=max(1,$page-1)?>">Previous</a>
    ·
    <a href="?q=<?=rawurlencode($query)?>&amp;page=<?=$page+1?>">Next</a>
</nav>

<?php if($selected):?>
<section class="card">
    <h2><?=H::e($selected['name'])?></h2>
    <p><?=H::e($selected['email'])?></p>

    <h3>Marketplace Credit</h3>
    <p>
        Total <?=H::money($selected['total_balance'])?>
        · Reserved <?=H::money($selected['reserved_balance'])?>
        · Available <?=H::money($selected['available_balance'])?>
    </p>

    <h3>Advertising Credit</h3>
    <p>
        Total <?=H::money($selected['advertising_total_balance'])?>
        · Reserved <?=H::money($selected['advertising_reserved_balance'])?>
        · Available <?=H::money($selected['advertising_available_balance'])?>
    </p>

    <?php if(H::canAdmin('credits.adjust')):?>
    <hr>
    <h3>Issue or adjust credit</h3>

    <form method="post" action="/admin/credits/adjust" class="form">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <input type="hidden" name="user_id" value="<?=(int)$selected['id']?>">

        <label>
            Credit type
            <select name="credit_type" required>
                <option value="marketplace">Marketplace Credit</option>
                <option value="advertising" <?=$selected['approved_seller']?'':'disabled'?>>Advertising Credit</option>
            </select>
        </label>

        <label>
            Adjustment amount
            <input name="amount" required inputmode="decimal" placeholder="10.00 or -5.00">
        </label>

        <label>
            Reason
            <textarea name="reason" required minlength="3" maxlength="500"></textarea>
        </label>

        <?php if(!$selected['approved_seller']):?>
        <p class="muted">Advertising Credit is available only for approved sellers.</p>
        <?php endif;?>

        <button class="btn">Record adjustment</button>
    </form>

    <?php if($selected['approved_seller']):?>
    <hr>
    <h3>Create recurring Advertising Credit</h3>

    <form method="post" action="/admin/credits/advertising-recurring" class="form">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <input type="hidden" name="user_id" value="<?=(int)$selected['id']?>">

        <label>
            Monthly amount
            <input name="amount" required inputmode="decimal" placeholder="250.00">
        </label>

        <label>
            Day of month
            <input type="number" name="day_of_month" min="1" max="28" value="1" required>
        </label>

        <label>
            Reason / label
            <textarea name="reason" required minlength="3" maxlength="500" placeholder="Social media manager compensation"></textarea>
        </label>

        <label>
            <input type="checkbox" name="grant_now" value="1" checked>
            Issue the first credit immediately
        </label>

        <button class="btn">Create recurring Advertising Credit</button>
    </form>
    <?php endif;?>
    <?php endif;?>
</section>

<h2>Recurring Advertising Credits</h2>
<div class="responsive-table">
<table>
<thead>
<tr>
    <th>Amount</th>
    <th>Day</th>
    <th>Next grant</th>
    <th>Last grant</th>
    <th>Status</th>
    <th>Reason</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach($recurringGrants as $grant):?>
<tr>
    <td><?=H::money($grant['amount'])?></td>
    <td><?=(int)$grant['day_of_month']?></td>
    <td><?=H::e($grant['next_run_at'])?></td>
    <td><?=H::e($grant['last_granted_at']??'—')?></td>
    <td><?=$grant['is_active']?'Active':'Stopped'?></td>
    <td><?=H::e($grant['description'])?></td>
    <td>
        <?php if($grant['is_active']&&H::canAdmin('credits.adjust')):?>
        <form method="post" action="/admin/credits/advertising-recurring/<?=(int)$grant['id']?>/deactivate">
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
            <button>Stop recurring credit</button>
        </form>
        <?php else:?>—<?php endif;?>
    </td>
</tr>
<?php endforeach;?>
<?php if(!$recurringGrants):?>
<tr><td colspan="7">No recurring Advertising Credits.</td></tr>
<?php endif;?>
</tbody>
</table>
</div>

<h2>Marketplace Credit Ledger</h2>
<div class="responsive-table">
<table>
<thead>
<tr>
    <th>Type</th><th>Amount</th><th>Status</th><th>Order</th>
    <th>Referral</th><th>Related</th><th>Admin</th><th>Reason</th><th>Created</th>
</tr>
</thead>
<tbody>
<?php foreach($ledger as $entry):?>
<tr>
    <td><?=H::e(ucwords(str_replace('_',' ',$entry['type'])))?></td>
    <td><?=H::money($entry['amount'])?></td>
    <td><?=H::e($entry['status'])?></td>
    <td><?=$entry['order_id']?'#'.(int)$entry['order_id']:'—'?></td>
    <td><?=$entry['referral_id']?'#'.(int)$entry['referral_id']:'—'?></td>
    <td><?=$entry['related_transaction_id']?'#'.(int)$entry['related_transaction_id']:'—'?></td>
    <td><?=H::e($entry['admin_name']??'—')?></td>
    <td><?=H::e($entry['description']??'—')?></td>
    <td><?=H::e($entry['created_at'])?></td>
</tr>
<?php endforeach;?>
<?php if(!$ledger):?>
<tr><td colspan="9">No Marketplace Credit activity.</td></tr>
<?php endif;?>
</tbody>
</table>
</div>

<h2>Advertising Credit Ledger</h2>
<div class="responsive-table">
<table>
<thead>
<tr>
    <th>Type</th><th>Amount</th><th>Status</th><th>Promotion</th>
    <th>Recurring</th><th>Related</th><th>Admin</th><th>Reason</th><th>Created</th>
</tr>
</thead>
<tbody>
<?php foreach($advertisingLedger as $entry):?>
<tr>
    <td><?=H::e(ucwords(str_replace('_',' ',$entry['type'])))?></td>
    <td><?=H::money($entry['amount'])?></td>
    <td><?=H::e($entry['status'])?></td>
    <td><?=$entry['ad_id']?'#'.(int)$entry['ad_id']:'—'?></td>
    <td><?=$entry['recurring_grant_id']?'#'.(int)$entry['recurring_grant_id']:'—'?></td>
    <td><?=$entry['related_transaction_id']?'#'.(int)$entry['related_transaction_id']:'—'?></td>
    <td><?=H::e($entry['admin_name']??'—')?></td>
    <td><?=H::e($entry['description']??'—')?></td>
    <td><?=H::e($entry['created_at'])?></td>
</tr>
<?php endforeach;?>
<?php if(!$advertisingLedger):?>
<tr><td colspan="9">No Advertising Credit activity.</td></tr>
<?php endif;?>
</tbody>
</table>
</div>
<?php endif;?>

<?php else:?>
<p>Referral reward and payout history is immutable.</p>
<h2>Seller-referral commission totals</h2><div class="responsive-table"><table><thead><tr><th>Referrer</th><th>Referred store</th><th>Eligibility</th><th>Unpaid</th><th>Paid</th><th>Recovery</th><th>Lifetime net</th></tr></thead><tbody><?php foreach($commissionTotals as $total):?><tr><td><?=H::e($total['referrer_name'])?></td><td><?=H::e($total['referred_store']??'Store unavailable')?></td><td><?=$total['commission_ended_at']?'Stopped '.H::e($total['commission_ended_at']):'Active'?></td><td><?=H::money(((int)$total['unpaid_cents'])/100)?></td><td><?=H::money(((int)$total['paid_cents'])/100)?></td><td><?=H::money(((int)$total['recovery_cents'])/100)?></td><td><?=H::money(((int)$total['lifetime_cents'])/100)?></td></tr><?php endforeach;?></tbody></table></div>
<h2>Monthly seller-referral payouts</h2><form method="get" action="/admin/referrals"><select name="payout_status"><option value="">All</option><?php foreach(['processing','paid','failed','not_ready'] as $status):?><option value="<?=$status?>" <?=$payoutStatus===$status?'selected':''?>><?=H::e($status)?></option><?php endforeach;?></select><button>Filter</button></form><div class="responsive-table"><table><thead><tr><th>Referrer</th><th>Period</th><th>Amount</th><th>Status</th><th>Transfer</th><th>Action</th></tr></thead><tbody><?php foreach($payouts as $batch):?><tr><td><?=H::e($batch['referrer_name'])?></td><td><?=H::e($batch['period_start'])?>–<?=H::e($batch['period_end'])?></td><td><?=H::money(((int)$batch['amount_cents'])/100)?></td><td><?=H::e($batch['status'])?></td><td><?=H::e($batch['stripe_transfer_id']??'—')?></td><td><?php if(H::canAdmin('payouts.manage')&&in_array($batch['status'],['failed','not_ready','processing'],true)):?><form method="post" action="/admin/seller-referral-payouts/<?=(int)$batch['id']?>/retry"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Retry</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<h3>Transfer-attempt history</h3><div class="responsive-table"><table><thead><tr><th>Batch</th><th>Status</th><th>Transfer</th><th>Attempted</th><th>Result</th></tr></thead><tbody><?php foreach($attempts as $attempt):?><tr><td>#<?=(int)$attempt['batch_id']?></td><td><?=H::e($attempt['status'])?></td><td><?=H::e($attempt['stripe_transfer_id']??'—')?></td><td><?=H::e($attempt['attempted_at'])?></td><td><?=H::e($attempt['failure_reason']??'—')?></td></tr><?php endforeach;?></tbody></table></div>
<h2>Matching referrals</h2><div class="responsive-table"><table><thead><tr><th>Referrer</th><th>Referred user</th><th>Buyer status</th><th>Seller status</th><th>Rewards</th></tr></thead><tbody><?php foreach($referrals as $referral):?><tr><td><?=H::e($referral['referrer_name'])?></td><td><?=H::e($referral['referred_name'])?></td><td><?=H::e($referral['buyer_status'])?></td><td><?=H::e($referral['seller_status'])?></td><td>Buyer <?=H::money($referral['buyer_referrer_reward_amount'])?> / Seller <?=H::money($referral['seller_referrer_reward_amount'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?>
