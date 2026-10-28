<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Battle of the Designers</h1>
        <p class="muted">
            Creative Moth designers go head-to-head in community-voted matchups until one champion remains.
        </p>
    </div>

    <?php if(H::user() && H::hasApprovedDesigner()): ?>
        <a class="btn" href="/seller/battle-of-the-designers">
            Seller Signup
        </a>
    <?php endif; ?>
</header>

<div class="dashboard-grid">
<?php foreach($events as $event): ?>
    <article class="card">
        <h2><?=H::e($event['title'])?></h2>
<?php include app_path('app/Views/partials/battle_calendar.php'); ?>

        <p>
            <span class="status-badge">
                <?=H::e(ucwords($event['status']))?>
            </span>
        </p>

        <p>
            <?=number_format((int)$event['participant_count'])?>
            designer<?=((int)$event['participant_count']===1?'':'s')?>
            participating
        </p>

        <p>
            <strong>Champion Prize:</strong>
            $100 Creative Moth Advertising Credit
        </p>

        <a
            class="btn"
            href="/battle-of-the-designers/<?=H::e($event['slug'])?>"
        >
            View Battle
        </a>
    </article>
<?php endforeach; ?>

<?php if(!$events): ?>
    <section class="card">
        <p>No Battle of the Designers event is available yet.</p>
    </section>
<?php endif; ?>
</div>
