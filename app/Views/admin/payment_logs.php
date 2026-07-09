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
