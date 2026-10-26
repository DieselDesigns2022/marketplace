<?php
$collabTiming = static function(array $c): array {
    $now = time();

    $upload = strtotime((string)($c['upload_deadline'] ?? '')) ?: 0;
    $saleStart = strtotime((string)($c['sale_starts_at'] ?? '')) ?: 0;
    $saleEnd = strtotime((string)($c['sale_close_date'] ?? '')) ?: 0;

    $soldOut = !empty($c['quantity_limit'])
        && (int)($c['sold_count'] ?? 0) >= (int)$c['quantity_limit'];

    $formatDate = static fn(int $timestamp): string =>
        $timestamp > 0 ? date('M j, Y', $timestamp) : 'Not set';

    $countdown = static function(int $target, int $now): string {
        if ($target <= $now) return '0 DAYS';

        $seconds = $target - $now;
        $days = (int)ceil($seconds / 86400);

        if ($days <= 1) {
            $hours = max(1, (int)ceil($seconds / 3600));
            return $hours.' HOUR'.($hours === 1 ? '' : 'S');
        }

        return $days.' DAYS';
    };

    if ($soldOut) {
        $phase = 'SOLD OUT';
        $tone = 'ended';
    } elseif (($c['status'] ?? '') === 'ended' || ($saleEnd > 0 && $saleEnd <= $now)) {
        $phase = 'COLLAB ENDED';
        $tone = 'ended';
    } elseif ($upload > $now) {
        $phase = $countdown($upload, $now).' LEFT TO UPLOAD';
        $tone = ($upload - $now) <= 3 * 86400 ? 'urgent' : 'upload';
    } elseif ($saleStart > $now) {
        $phase = $countdown($saleStart, $now).' UNTIL SALES START';
        $tone = 'waiting';
    } elseif ($saleEnd > $now) {
        $phase = $countdown($saleEnd, $now).' LEFT TO SELL';
        $tone = ($saleEnd - $now) <= 3 * 86400 ? 'urgent' : 'selling';
    } else {
        $phase = strtoupper((string)($c['status'] ?? 'COLLAB'));
        $tone = 'ended';
    }

    return [
        'phase' => $phase,
        'tone' => $tone,
        'upload' => $formatDate($upload),
        'sale_start' => $formatDate($saleStart),
        'sale_end' => $formatDate($saleEnd),
    ];
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
    <?php $timing=$collabTiming($c);?>
    <article class="card collab-list-card">
        <div class="collab-list-heading">
            <div>
                <a class="collab-list-title" href="/seller/collabs/<?=$c['id']?>"><?=H::e($c['title'])?></a>
                <div class="collab-list-subline">
                    Hosted by you · <?=H::e(ucwords(str_replace('_',' ',(string)$c['status'])))?>
                    <?php if(!empty($c['quantity_limit'])):?>
                        · <?=intval($c['sold_count'] ?? 0)?> / <?=intval($c['quantity_limit'])?> sold
                    <?php endif;?>
                </div>
            </div>

            <div class="collab-countdown collab-countdown-<?=H::e($timing['tone'])?>">
                <?=H::e($timing['phase'])?>
            </div>
        </div>

        <div class="collab-date-grid">
            <div class="collab-date-item">
                <span>FILES DUE</span>
                <strong><?=H::e($timing['upload'])?></strong>
            </div>
            <div class="collab-date-item">
                <span>SALE STARTS</span>
                <strong><?=H::e($timing['sale_start'])?></strong>
            </div>
            <div class="collab-date-item">
                <span>SALES END</span>
                <strong><?=H::e($timing['sale_end'])?></strong>
            </div>
        </div>
    </article>
<?php endforeach;?>

<?php if(!$hosted):?><p>None yet.</p><?php endif;?>

<h2>Participating Collabs</h2>

<?php foreach($participating as $c):?>
    <?php $timing=$collabTiming($c);?>
    <article class="card collab-list-card">
        <div class="collab-list-heading">
            <div>
                <a class="collab-list-title" href="/seller/collabs/<?=$c['id']?>"><?=H::e($c['title'])?></a>
                <div class="collab-list-subline">
                    <?=H::e(ucwords(str_replace('_',' ',(string)$c['membership_status'])))?>
                    · <?=H::e(ucwords(str_replace('_',' ',(string)$c['eligibility'])))?>
                    <?php if(!empty($c['quantity_limit'])):?>
                        · <?=intval($c['sold_count'] ?? 0)?> / <?=intval($c['quantity_limit'])?> sold
                    <?php endif;?>
                </div>
            </div>

            <div class="collab-countdown collab-countdown-<?=H::e($timing['tone'])?>">
                <?=H::e($timing['phase'])?>
            </div>
        </div>

        <div class="collab-date-grid">
            <div class="collab-date-item">
                <span>FILES DUE</span>
                <strong><?=H::e($timing['upload'])?></strong>
            </div>
            <div class="collab-date-item">
                <span>SALE STARTS</span>
                <strong><?=H::e($timing['sale_start'])?></strong>
            </div>
            <div class="collab-date-item">
                <span>SALES END</span>
                <strong><?=H::e($timing['sale_end'])?></strong>
            </div>
        </div>
    </article>
<?php endforeach;?>

<?php if(!$participating):?><p>None yet.</p><?php endif;?>


<?php
$allUpcomingCollabs = array_merge($hosted, $participating);

$calendarStart = new DateTimeImmutable('first day of this month 00:00:00');
$calendarEnd = $calendarStart->modify('+12 months');

$collabsByMonth = [];

foreach ($allUpcomingCollabs as $calendarCollab) {
    $deadlineRaw = trim((string)($calendarCollab['upload_deadline'] ?? ''));

    if ($deadlineRaw === '') {
        continue;
    }

    try {
        $deadline = new DateTimeImmutable($deadlineRaw);
    } catch (Throwable $e) {
        continue;
    }

    if ($deadline < $calendarStart || $deadline >= $calendarEnd) {
        continue;
    }

    $monthKey = $deadline->format('Y-m');

    if (!isset($collabsByMonth[$monthKey])) {
        $collabsByMonth[$monthKey] = [];
    }

    $calendarCollab['_calendar_deadline'] = $deadline;
    $collabsByMonth[$monthKey][] = $calendarCollab;
}

foreach ($collabsByMonth as &$monthCollabs) {
    usort(
        $monthCollabs,
        static fn(array $a, array $b): int =>
            $a['_calendar_deadline'] <=> $b['_calendar_deadline']
    );
}
unset($monthCollabs);
?>

<section class="collab-calendar-section">
    <div class="collab-calendar-heading">
        <div>
            <h2>12-Month Collab Calendar</h2>
            <p class="muted">
                Hosted and participating collabs grouped by file upload deadline.
            </p>
        </div>
    </div>

    <div class="collab-calendar-months">
        <?php for($monthOffset = 0; $monthOffset < 12; $monthOffset++):?>
            <?php
                $month = $calendarStart->modify("+{$monthOffset} months");
                $monthKey = $month->format('Y-m');
                $monthItems = $collabsByMonth[$monthKey] ?? [];
            ?>

            <section class="card collab-calendar-month">
                <h3><?=H::e(strtoupper($month->format('F Y')))?></h3>

                <?php if($monthItems):?>
                    <div class="collab-calendar-items">
                        <?php foreach($monthItems as $calendarCollab):?>
                            <div class="collab-calendar-item">
                                <div>
                                    <a href="/seller/collabs/<?=(int)$calendarCollab['id']?>">
                                        <?=H::e($calendarCollab['title'])?>
                                    </a>

                                    <?php if((int)($calendarCollab['host_designer_id'] ?? 0) === (int)($calendarCollab['host_designer_id'] ?? -1) && isset($calendarCollab['membership_status'])):?>
                                        <span class="collab-calendar-role">Participating</span>
                                    <?php elseif(!isset($calendarCollab['membership_status'])):?>
                                        <span class="collab-calendar-role">Hosted by you</span>
                                    <?php endif;?>
                                </div>

                                <strong>
                                    FILES DUE:
                                    <?=H::e($calendarCollab['_calendar_deadline']->format('M j, Y'))?>
                                </strong>
                            </div>
                        <?php endforeach;?>
                    </div>
                <?php else:?>
                    <p class="collab-calendar-empty">No collabs scheduled yet.</p>
                <?php endif;?>
            </section>
        <?php endfor;?>
    </div>
</section>

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
