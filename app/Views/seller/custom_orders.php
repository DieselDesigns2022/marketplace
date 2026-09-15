<section class="custom-orders-page">

    <div class="custom-orders-header">
        <div>
            <p class="custom-orders-eyebrow">Seller dashboard</p>
            <h1>Custom Orders</h1>
            <p class="custom-orders-intro">
                Manage buyer briefs, proofs, revisions, and final delivery.
            </p>
        </div>

        <div class="custom-orders-count">
            <strong><?=count($orders)?></strong>
            <span><?=count($orders) === 1 ? 'Order' : 'Orders'?></span>
        </div>
    </div>

    <?php if(!$orders): ?>

        <div class="card empty-state custom-orders-empty">
            <h2>No custom orders yet</h2>
            <p>
                Paid Custom Design orders will appear here when buyers place them.
            </p>
            <a class="btn secondary" href="/seller/custom-designs">
                View Custom Designs
            </a>
        </div>

    <?php else: ?>

        <div class="custom-orders-grid">

            <?php foreach($orders as $o): ?>

                <?php
                $status = (string)$o['status'];
                $statusLabel = ucwords(str_replace('_', ' ', $status));
                $statusClass = preg_replace(
                    '/[^a-z0-9_-]/',
                    '',
                    strtolower(str_replace('_', '-', $status))
                );

                $paymentStatus = (string)$o['payment_status'];
                $paymentLabel = ucwords(
                    str_replace('_', ' ', $paymentStatus)
                );

                $updated = !empty($o['updated_at'])
                    ? strtotime((string)$o['updated_at'])
                    : null;
                ?>

                <article class="card custom-order-card">

                    <div class="custom-order-card-top">

                        <span class="custom-order-number">
                            Order #<?=(int)$o['id']?>
                        </span>

                        <div class="custom-order-badges">
                            <span class="custom-order-status status-<?=H::e($statusClass)?>">
                                <?=H::e($statusLabel)?>
                            </span>

                            <span class="custom-order-payment payment-<?=H::e($paymentStatus)?>">
                                <?=H::e($paymentLabel)?>
                            </span>
                        </div>

                    </div>

                    <h2 class="custom-order-title">
                        <?=H::e($o['title'])?>
                    </h2>

                    <div class="custom-order-details">

                        <div>
                            <span class="custom-order-detail-label">Buyer</span>
                            <strong><?=H::e($o['buyer_name'])?></strong>
                        </div>

                        <div>
                            <span class="custom-order-detail-label">Turnaround</span>
                            <strong>
                                <?=(int)$o['turnaround_days']?>
                                <?=((int)$o['turnaround_days'] === 1) ? 'day' : 'days'?>
                            </strong>
                        </div>

                        <div>
                            <span class="custom-order-detail-label">Revisions</span>
                            <strong>
                                <?=(int)$o['revisions_used']?>
                                of
                                <?=(int)$o['included_revisions']?>
                                used
                            </strong>
                        </div>

                        <?php if($updated): ?>
                            <div>
                                <span class="custom-order-detail-label">Updated</span>
                                <strong><?=date('M j, Y', $updated)?></strong>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div class="custom-order-card-footer">
                        <a
                            class="btn"
                            href="/seller/custom-orders/<?=(int)$o['id']?>"
                        >
                            Open Order
                        </a>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>
