<style>
.money-report-page {
    max-width: 100%;
    overflow-x: hidden;
}
.money-report-page .card {
    max-width: 100%;
    overflow: hidden;
}
.money-scroll {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
}
.money-scroll table {
    width: 100%;
    min-width: 980px;
}
.money-scroll th,
.money-scroll td {
    vertical-align: top;
}
.money-wrap {
    max-width: 220px;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.money-small {
    font-size: 0.9em;
}
</style>
<div class="money-report-page">
<h1>Payment, Commission & Stripe Logs</h1>
<p><a href="/admin/orders">Back to orders</a></p>

<section class="card">
    <h2>Marketplace money summary</h2>
    <p class="muted">Commission shown here counts live Stripe paid orders only and shows the Asset Moth marketplace commission snapshot before Stripe processing fees. Stripe fees may reduce the final amount paid out to the platform bank account.</p>
    <div style="overflow-x:auto; max-width:100%;"><div class="money-scroll"><table>
        <tr>
            <th>Paid Orders</th>
            <th>Gross Sales<br><span class="muted money-small">excludes tax</span></th>
            <th>Order Tax Collected</th>
            <th>Asset Moth Commission</th>
            <th>Seller Payouts Owed</th>
            <th>Stripe Fees Recorded</th>
            <th>Seller Transfers Sent</th>
            <th>Seller Transfers Failed</th>
        </tr>
        <tr>
            <td><?= (int)($summary['paid_orders'] ?? 0) ?></td>
            <td><?= H::money($summary['gross_sales'] ?? 0) ?></td>
            <td><?= H::money($summary['tax_collected'] ?? 0) ?></td>
            <td><strong><?= H::money($summary['marketplace_commission'] ?? 0) ?></strong></td>
            <td><?= H::money($summary['seller_payouts'] ?? 0) ?></td>
            <td><?= H::money($summary['stripe_fees_recorded'] ?? 0) ?></td>
            <td><?= H::money($summary['seller_transfers_sent'] ?? 0) ?></td>
            <td><?= H::money($summary['seller_transfers_failed'] ?? 0) ?></td>
        </tr>
    </table></div>
</section>

<section class="card">
    <h2>Open attention items</h2>
    <p class="muted">Mark an item resolved after you have investigated it. The original Stripe status and error stay in the history below.</p>
    <p><a class="btn" href="/admin/payment-logs?issue=failed_transfers">Open failed seller transfers (<?=count($transferIssues)?>)</a> <a class="btn" href="/admin/payment-logs?issue=platform_credit_holds">Platform-credit holds (<?=count($platformCreditHolds)?>)</a> <a class="btn alt" href="/admin/payment-logs?issue=webhook_issues">Open webhook / Stripe issues (<?=count($webhookIssues)?>)</a></p>

    <?php if($issue === '' || $issue === 'platform_credit_holds'):?>
        <h3>Platform-funded credit payout holds</h3>
        <p class="muted">These paid internal-credit orders have no buyer source charge. Settlement transfers the stored obligation from the Asset Moth platform balance.</p>
        <?php if(!$platformCreditHolds):?><p class="muted">No platform-credit payout holds.</p><?php else:?><div class="money-scroll"><table><thead><tr><th>Order</th><th>Seller</th><th>Amount</th><th>Stripe readiness</th><th>Last safe error</th><th>Settlement</th></tr></thead><tbody>
        <?php foreach($platformCreditHolds as $hold):?><tr><td><a href="/admin/order/<?=(int)$hold['order_id']?>">#<?=(int)$hold['order_id']?></a></td><td><?=H::e($hold['seller_name'])?></td><td><?=H::money($hold['seller_payout_amount'])?> <?=H::e(strtoupper($hold['currency']))?></td><td><?=!empty($hold['stripe_connect_account_id'])&&!empty($hold['stripe_payouts_enabled'])&&!empty($hold['stripe_details_submitted'])?'Payout enabled':'Seller setup required'?></td><td class="money-wrap"><?=H::e($hold['stripe_transfer_error']??'')?></td><td><form method="post" action="/admin/platform-credit-payouts/<?=(int)$hold['id']?>/settle"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button <?=empty($hold['stripe_connect_account_id'])||empty($hold['stripe_payouts_enabled'])||empty($hold['stripe_details_submitted'])?'disabled':''?>>Transfer from platform balance</button></form></td></tr><?php endforeach;?>
        </tbody></table></div><?php endif;?>
    <?php endif;?>

    <?php if($issue === '' || $issue === 'failed_transfers'):?>
        <h3>Failed seller transfers</h3>
        <?php if(!$transferIssues):?><p class="muted">No open failed seller transfers.</p><?php else:?><div class="money-scroll"><table><tr><th>Order</th><th>Seller</th><th>Amount</th><th>Status</th><th>Stripe error</th><th>Resolve</th></tr>
        <?php foreach($transferIssues as $transfer):?><tr><td><a href="/admin/order/<?=(int)$transfer['order_id']?>">#<?=(int)$transfer['order_id']?></a></td><td class="money-wrap"><?=H::e($transfer['seller_name'])?><br><span class="muted money-small"><?=H::e($transfer['seller_email'])?></span></td><td><?=H::money($transfer['seller_payout_amount'])?></td><td><?=H::e($transfer['payout_status'])?></td><td class="money-wrap"><?=H::e($transfer['stripe_transfer_error'] ?? '')?></td><td><form method="post" action="/admin/payment-logs?issue=failed_transfers"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="resolve_transfer_issue"><input type="hidden" name="id" value="<?=(int)$transfer['id']?>"><label>Resolution note <textarea name="resolution_note" maxlength="500" rows="2"></textarea></label><button>Mark transfer issue resolved</button></form></td></tr><?php endforeach;?>
        </table></div><?php endif;?>
    <?php endif;?>

    <?php if($issue === '' || $issue === 'webhook_issues'):?>
        <h3>Webhook / Stripe issues</h3>
        <?php if(!$webhookIssues):?><p class="muted">No open webhook or Stripe issues.</p><?php else:?><div class="money-scroll"><table><tr><th>Event</th><th>Type</th><th>Status</th><th>Error</th><th>Resolve</th></tr>
        <?php foreach($webhookIssues as $event):?><tr><td class="money-wrap"><?=H::e($event['stripe_event_id'])?></td><td><?=H::e($event['event_type'])?></td><td><?=H::e($event['processing_status'])?></td><td class="money-wrap"><?=H::e($event['processing_error'] ?? '')?></td><td><form method="post" action="/admin/payment-logs?issue=webhook_issues"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="resolve_webhook_issue"><input type="hidden" name="id" value="<?=(int)$event['id']?>"><label>Resolution note <textarea name="resolution_note" maxlength="500" rows="2"></textarea></label><button>Mark webhook issue resolved</button></form></td></tr><?php endforeach;?>
        </table></div><?php endif;?>
    <?php endif;?>
</section>

<section class="card">
    <h2>Commission detail</h2>
    <p class="muted">Use this table to verify each paid order's gross sale, order-level tax collected, Asset Moth commission, seller payout amount, and Stripe transfer status. Summary Tax Collected is authoritative; order tax is shown once per order below and is excluded from commission and seller payout calculations.</p>
    <table>
        <tr>
            <th>Order</th>
            <th>Buyer</th>
            <th>Seller</th>
            <th>Product</th>
            <th>Item Total<br><span class="muted money-small">excludes tax</span></th>
            <th>Order Tax Collected</th>
            <th>Rate</th>
            <th>Asset Moth Commission</th>
            <th>Seller Payout</th>
            <th>Payout Status</th>
            <th>Stripe Transfer / Error</th>
            <th>Paid</th>
        </tr>
        <?php $shownTaxOrders = []; foreach($commissionRows as $r): ?>
            <?php $taxAlreadyShown = isset($shownTaxOrders[(int)$r['order_id']]); $shownTaxOrders[(int)$r['order_id']] = true; ?>
            <tr>
                <td><a href="/admin/order/<?=$r['order_id']?>">#<?=H::e($r['order_id'])?></a><br><span class="muted"><?=H::e($r['payment_status'])?></span></td>
                <td class="money-wrap"><?=H::e($r['buyer_email'])?></td>
                <td class="money-wrap"><?=H::e($r['seller_name'])?><br><span class="muted money-small"><?=H::e($r['seller_email'])?></span></td>
                <td class="money-wrap"><?=H::e($r['product_title'])?></td>
                <td><?=H::money($r['item_total'])?></td>
                <td><?php if(!$taxAlreadyShown): ?><?=H::money($r['order_tax_amount'] ?? 0)?><?php else: ?><span class="muted money-small">shown above</span><?php endif; ?></td>
                <td><?=H::e(number_format(((float)$r['commission_rate']) * 100, 2))?>%</td>
                <td><strong><?=H::money($r['platform_commission_amount'])?></strong></td>
                <td><?=H::money($r['seller_payout_amount'])?></td>
                <td><?=H::e($r['ledger_payout_status'] ?? $r['seller_payout_status'] ?? '')?></td>
                <td class="money-wrap">
                    <?=H::e($r['ledger_transfer_id'] ?? $r['item_transfer_id'] ?? '')?>
                    <?php if(!empty($r['ledger_transfer_error']) || !empty($r['item_transfer_error'])): ?>
                        <br><span class="muted money-small"><?=H::e($r['ledger_transfer_error'] ?? $r['item_transfer_error'])?></span>
                    <?php endif; ?>
                </td>
                <td><?=H::e($r['paid_at'] ?? '')?></td>
            </tr>
        <?php endforeach; ?>
        <?php if(empty($commissionRows)): ?>
            <tr><td colspan="12" class="muted">No paid commission records found yet.</td></tr>
        <?php endif; ?>
    </table></div>
</section>

<h2>Payment transactions</h2>
<div class="money-scroll"><table><tr><th>ID</th><th>Order</th><th>Buyer</th><th>Type</th><th>Status</th><th>Amount</th><th>Stripe refs</th><th>Message</th><th>Date</th></tr>
<?php foreach($transactions as $t):?><tr><td><?=$t['id']?></td><td>#<?=H::e($t['order_id'])?></td><td><?=H::e($t['buyer_email'] ?? '')?></td><td><?=H::e($t['transaction_type'])?></td><td><?=H::e($t['payment_status'])?></td><td><?=H::money($t['amount'])?> <?=H::e(strtoupper($t['currency']))?></td><td><?=H::e(trim(($t['stripe_checkout_session_id'] ?? '').' '.($t['stripe_payment_intent_id'] ?? '').' '.($t['stripe_charge_id'] ?? '')))?></td><td><?=H::e($t['message'] ?? '')?></td><td><?=$t['created_at']?></td></tr><?php endforeach;?>
</table></div>
<h2>Webhook events</h2>
<div class="money-scroll"><table><tr><th>Event</th><th>Type</th><th>Status</th><th>Error</th><th>Processed</th><th>Created</th></tr>
<?php foreach($events as $e):?><tr><td><?=H::e($e['stripe_event_id'])?></td><td><?=H::e($e['event_type'])?></td><td><?=H::e($e['processing_status'])?></td><td><?=H::e($e['processing_error'] ?? '')?></td><td><?=H::e($e['processed_at'] ?? '')?></td><td><?=$e['created_at']?></td></tr><?php endforeach;?>
</table></div>
</div>
