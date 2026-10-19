<h1>Payment Status</h1>

<?php
$paymentStatus=
    $order['payment_status']
    ??$order['status'];
?>

<?php if($paymentStatus==='paid'): ?>

    <div class="notice success">
        <p>
            <strong>Payment confirmed!</strong>
        </p>

        <p>
            Your Creative Moth order is complete.
        </p>
    </div>

    <?php if(!empty($prizeWheelUnlocks)): ?>
        <section class="card page-section">
            <h2>🎡 You unlocked a Prize Wheel spin!</h2>

            <?php foreach($prizeWheelUnlocks as $unlock): ?>
                <div
                    style="
                        padding:16px 0;
                        border-bottom:1px solid rgba(128,128,128,.25);
                    "
                >
                    <p>
                        Your qualifying purchase from
                        <strong>
                            <?=H::e($unlock['display_name'])?>
                        </strong>
                        unlocked one spin on their Prize Wheel.
                    </p>

                    <p>
                        <a
                            class="btn"
                            href="/store/<?=H::e(
                                $unlock['store_slug']
                            )?>#creative-moth-prize-wheel"
                        >
                            🎡 Spin Now
                        </a>
                    </p>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <p>
        <a
            class="btn alt"
            href="/dashboard/order/<?=(int)$order['id']?>"
        >
            View Order
        </a>
    </p>

<?php else: ?>

    <div class="notice warning">
        <p>
            Stripe has returned you to Creative Moth.
            Your payment is being confirmed by Stripe before
            downloads, delivery details, or Prize Wheel spins unlock.
        </p>

        <p>
            Current payment status:
            <strong>
                <?=H::e($paymentStatus)?>
            </strong>
        </p>
    </div>

    <p>
        <a
            class="btn"
            href="/dashboard/order/<?=(int)$order['id']?>"
        >
            View Order Status
        </a>
    </p>

    <script>
    (() => {
        const started=Date.now();
        const maxWait=30000;

        const refresh=()=>{
            if(Date.now()-started>=maxWait){
                return;
            }

            window.location.reload();
        };

        window.setTimeout(refresh,2500);
    })();
    </script>

<?php endif; ?>
