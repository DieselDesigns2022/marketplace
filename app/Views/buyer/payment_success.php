<h1>Payment Status</h1>

<?php
$paymentStatus=
    $order['payment_status']
    ??$order['status'];
?>

<?php if($paymentStatus==='paid'): ?>

    <script>
    window.sessionStorage.removeItem(
        'creative-moth-payment-confirmation:'+
        <?=json_encode((string)$order['id'])?>
    );
    </script>

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
        const key=
            'creative-moth-payment-confirmation:'+
            <?=json_encode((string)$order['id'])?>;

        const maxWait=30000;
        const now=Date.now();

        let started=parseInt(
            window.sessionStorage.getItem(key) || '0',
            10
        );

        if(!started){
            started=now;
            window.sessionStorage.setItem(
                key,
                String(started)
            );
        }

        if(now-started<maxWait){
            window.setTimeout(()=>{
                window.location.reload();
            },2500);
        }
    })();
    </script>

<?php endif; ?>
