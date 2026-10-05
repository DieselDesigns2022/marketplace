<?php
$deadlineText = static function(array $c): string {
    $soldOut = !empty($c['quantity_limit']) && (int)($c['sold_count'] ?? 0) >= (int)$c['quantity_limit'];

    if ($soldOut) return 'Sold Out';
    if (($c['status'] ?? '') === 'ended') return 'Ended';

    if (strtotime((string)$c['upload_deadline']) > time()) {
        return 'Files due: '.date('m/d/Y', strtotime((string)$c['upload_deadline']));
    }

    return 'Sales end: '.date('m/d/Y', strtotime((string)$c['sale_close_date']));
};
?>

<h1>My Collabs</h1>

<p>
    <a class="btn" href="/seller/collabs/new">Create collab</a>
    <a href="/seller/collabs/find">Find Collabs</a>
</p>

<section class="card">
    <h2>Payout Calculator</h2>

    <input type="hidden" id="collab-calc-csrf" value="<?=H::csrf()?>">

    <label>
        Sale price
        <input id="calc-price" type="text" inputmode="decimal" value="10.00">
    </label>

    <label>
        Number of designers
        <input id="calc-designers" type="number" min="1" value="5">
    </label>

    <p id="calc-output" aria-live="polite">Calculating…</p>
</section>

<h2>Hosted Collabs</h2>

<?php foreach($hosted as $c):?>
    <article class="card">
        <a href="/seller/collabs/<?=$c['id']?>"><?=H::e($c['title'])?></a>
        — <?=H::e($c['status'])?>
        · <?=H::e($deadlineText($c))?>

        <?php if(!empty($c['quantity_limit'])):?>
            · <?=intval($c['sold_count'] ?? 0)?> / <?=intval($c['quantity_limit'])?> sold
        <?php endif;?>
    </article>
<?php endforeach;?>

<?php if(!$hosted):?><p>None yet.</p><?php endif;?>

<h2>Participating Collabs</h2>

<?php foreach($participating as $c):?>
    <article class="card">
        <a href="/seller/collabs/<?=$c['id']?>"><?=H::e($c['title'])?></a>
        — <?=H::e($c['membership_status'])?> / <?=H::e($c['eligibility'])?>
        · <?=H::e($deadlineText($c))?>

        <?php if(!empty($c['quantity_limit'])):?>
            · <?=intval($c['sold_count'] ?? 0)?> / <?=intval($c['quantity_limit'])?> sold
        <?php endif;?>
    </article>
<?php endforeach;?>

<?php if(!$participating):?><p>None yet.</p><?php endif;?>

<script>
const price = document.querySelector('#calc-price');
const designers = document.querySelector('#calc-designers');
const output = document.querySelector('#calc-output');
let estimateRequest;

async function calculateEstimate() {
    clearTimeout(estimateRequest);

    estimateRequest = setTimeout(async () => {
        output.textContent = 'Calculating…';

        const body = new FormData();
        body.set('_csrf', document.querySelector('#collab-calc-csrf').value);
        body.set('price', price.value);
        body.set('designers', designers.value);

        try {
            const response = await fetch('/seller/collabs/payout-estimate', {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {'Accept':'application/json'}
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'Estimate unavailable.');
            }

            const remainder = result.remainder_cents > 0
                ? ` · ${result.remainder_cents} remainder cent${result.remainder_cents === 1 ? '' : 's'} distributed`
                : '';

            output.textContent =
                `Creative Moth fee $${result.fee} · contributor pool $${result.pool} · base per designer $${result.per_designer}${remainder}`;

        } catch (error) {
            output.textContent = error.message || 'Estimate unavailable.';
        }
    }, 200);
}

price.addEventListener('input', calculateEstimate);
designers.addEventListener('input', calculateEstimate);
calculateEstimate();
</script>
