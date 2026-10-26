<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Battle of the Designers</h1>
        <p class="muted">
            Create and manage Creative Moth Battle tournaments.
        </p>
    </div>
</header>

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

        <label>
            Voting duration per round, in minutes
            <input
                type="number"
                name="voting_minutes"
                min="1"
            >
        </label>

        <p class="muted">
            Voting duration may be left blank now, but it must be deliberately set before the first round can open.
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
                    <td><?=H::e($event['title'])?></td>
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
