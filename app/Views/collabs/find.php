<?php
$utc = new \DateTimeZone('UTC');

?>

<style>
.find-collab-card{padding:20px;margin-bottom:18px}
.find-collab-title{margin:0 0 14px}
.find-collab-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin:14px 0}
.find-collab-detail{background:#faf8fd;border:1px solid #ece6f6;border-radius:12px;padding:12px 14px}
.find-collab-label{display:block;font-weight:900;color:#6d28d9;margin-bottom:4px}
.find-collab-value{font-weight:700}
.find-collab-local{display:block;font-size:13px;margin-top:4px;color:#5f5870}
</style>

<h1>Find Collabs</h1>

<?php foreach($collabs as $c):?>
<?php
    $hostTimezoneName = (string)($c['host_timezone'] ?? 'UTC');
    $hostTimezone = new \DateTimeZone($hostTimezoneName);

    $deadlineHost =
        (new \DateTimeImmutable($c['upload_deadline'], $utc))
            ->setTimezone($hostTimezone);

    $saleThrough =
        new \DateTimeImmutable(
            $c['sale_close_date'].' 12:00:00',
            $hostTimezone
        );
?>

<article class="card find-collab-card">

    <h2 class="find-collab-title"><?=H::e($c['title'])?></h2>

    <div class="find-collab-details">

        <div class="find-collab-detail">
            <span class="find-collab-label">Host</span>
            <span class="find-collab-value"><?=H::e($c['host_name'])?></span>
        </div>

        <div class="find-collab-detail">
            <span class="find-collab-label">Minimum</span>
            <span class="find-collab-value">
                <?=intval($c['minimum_file_count'])?>
                contribution file<?=intval($c['minimum_file_count'])===1?'':'s'?>
            </span>
        </div>

        <div class="find-collab-detail">
            <span class="find-collab-label">File Upload Deadline</span>

            <span class="find-collab-value">
                <strong>Host time:</strong> <?=$deadlineHost->format('F j, Y \a\t g:i A T')?>
            </span>

            <span
                class="find-collab-local js-local-collab-time"
                data-utc="<?=$deadlineHost->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')?>"
                data-host-timezone="<?=H::e($hostTimezoneName)?>"
            ></span>
        </div>

        <div class="find-collab-detail">
            <span class="find-collab-label">Sale Through</span>
            <span class="find-collab-value">
                <?=$saleThrough->format('F j, Y')?>
            </span>
        </div>

    </div>

    <?php if(!$c['membership_status']):?>
        <form method="post" action="/seller/collabs/<?=$c['id']?>/request">
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
            <button>Request to join</button>
        </form>
    <?php else:?>
        <span class="badge"><?=H::e($c['membership_status'])?></span>
    <?php endif;?>

</article>
<?php endforeach;?>

<?php if(!$collabs):?>
    <p>No open collabs are joinable.</p>
<?php endif;?>

<script>
document.querySelectorAll('.js-local-collab-time').forEach((element) => {
    const date = new Date(element.dataset.utc);

    const formatter = new Intl.DateTimeFormat(undefined,{
        year:'numeric',
        month:'long',
        day:'numeric',
        hour:'numeric',
        minute:'2-digit',
        timeZoneName:'short'
    });

    const viewerZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const hostZone = element.dataset.hostTimezone;

    if (viewerZone === hostZone) {
        element.textContent = '';
        return;
    }

    element.textContent = 'Your time: ' + formatter.format(date);
});
</script>
