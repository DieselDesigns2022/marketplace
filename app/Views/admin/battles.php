<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Battle of the Designers</h1>
        <p class="muted">
            Create and manage Creative Moth Battle tournaments.
        </p>
    </div>
</header>

<section class="card attention-panel">
    <h2>Preview / Test Battle</h2>

    <p>
        Create a private Battle with two fake designers so you can review
        the real Battle presentation before opening registration to sellers.
    </p>

    <p class="muted">
        Preview Battles are admin-only. They do not appear to sellers or buyers,
        do not send Battle emails, and do not award Advertising Credit.
    </p>

    <form
        method="post"
        action="/admin/battles/preview/create"
    >
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::e(H::csrf())?>"
        >

        <button class="btn">
            Create 2-Designer Battle Preview
        </button>
    </form>
</section>

<section class="card">
    <h2>Create Battle</h2>

    <form
        method="post"
        action="/admin/battles/create"
        class="form"
    >
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::e(H::csrf())?>"
        >

        <label>
            Battle title
            <input
                name="title"
                maxlength="190"
                required
            >
        </label>

        <p>
            <strong>Round duration:</strong>
            24 hours exactly
        </p>

        <p class="muted">
            Every designer receives the same 24-hour voting window.
        </p>

        <button class="btn">
            Create Battle
        </button>
    </form>
</section>

<section class="card">
    <h2>Battle Events</h2>

    <div class="responsive-table">
        <table>
            <thead>
            <tr>
                <th>Battle</th>
                <th>Status</th>
                <th>Designers</th>
                <th>Open Vote Flags</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            <?php foreach($events as $event): ?>
                <tr>
                    <td>
                        <?=H::e($event['title'])?>
                        <?php if((int)($event['is_preview']??0)===1): ?>
                            <strong> — ADMIN PREVIEW</strong>
                        <?php endif; ?>
                    </td>
                    <td><?=H::e($event['status'])?></td>
                    <td><?=number_format((int)$event['participant_count'])?></td>
                    <td><?=number_format((int)$event['open_flag_count'])?></td>
                    <td>
                        <a
                            class="btn"
                            href="/admin/battles/<?=(int)$event['id']?>"
                        >
                            Manage
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(!$events): ?>
                <tr>
                    <td colspan="5">
                        No Battle events created yet.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
