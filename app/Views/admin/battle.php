<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <a href="/admin/battles">
            ← Battle Events
        </a>

        <h1><?=H::e($event['title'])?></h1>

        <p>
            Status:
            <strong><?=H::e($event['status'])?></strong>
        </p>
    </div>

    <a
        class="btn"
        href="/battle-of-the-designers/<?=H::e($event['slug'])?>"
        target="_blank"
    >
        View Public Battle
    </a>
</header>

<section class="card">
    <h2>Battle Configuration</h2>

    <p>
        <strong>Champion Prize:</strong>
        $100 Creative Moth Advertising Credit
    </p>

    <p>
        <strong>Voting duration:</strong>
        <?=$event['voting_minutes']
            ? number_format((int)$event['voting_minutes']).' minutes'
            : 'Not set'?>
    </p>

    <?php if($event['status']!=='completed'): ?>
        <form
            method="post"
            action="/admin/battles/<?=(int)$event['id']?>/action"
            class="form"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::e(H::csrf())?>"
            >
            <input
                type="hidden"
                name="action"
                value="voting_minutes"
            >

            <label>
                Voting duration per round, in minutes
                <input
                    type="number"
                    name="voting_minutes"
                    min="1"
                    value="<?=H::e((string)($event['voting_minutes']??''))?>"
                    required
                >
            </label>

            <button class="btn">
                Update Voting Duration
            </button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Registered Designers</h2>

    <p>
        <?=number_format(count($participants))?>
        active participant<?=count($participants)===1?'':'s'?>
    </p>

    <div class="dashboard-grid">
        <?php foreach($participants as $participant): ?>
            <article class="card">
                <img
                    src="<?=H::e($participant['logo_path'])?>"
                    alt="<?=H::e($participant['display_name'])?> logo"
                    style="width:180px;height:180px;object-fit:contain"
                >

                <strong>
                    <?=H::e($participant['display_name'])?>
                </strong>

                <?php if($participant['seed_position']): ?>
                    <span>
                        Seed #<?=(int)$participant['seed_position']?>
                    </span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if($event['status']==='signup'): ?>
        <form
            method="post"
            action="/admin/battles/<?=(int)$event['id']?>/action"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::e(H::csrf())?>"
            >
            <input
                type="hidden"
                name="action"
                value="generate"
            >

            <button
                class="btn"
                <?=count($participants)<2?'disabled':''?>
            >
                Close Signup &amp; Generate Randomized Bracket
            </button>
        </form>
    <?php endif; ?>

    <?php if($event['seed_token']): ?>
        <p class="muted">
            Reproducible bracket seed:
            <code><?=H::e($event['seed_token'])?></code>
        </p>
    <?php endif; ?>
</section>

<?php foreach($rounds as $round): ?>
<section class="card">
    <header class="dashboard-heading">
        <div>
            <h2>
                Round <?=number_format((int)$round['round_number'])?>
            </h2>

            <p>
                Status:
                <strong><?=H::e($round['status'])?></strong>
            </p>

            <?php if($round['voting_opens_at']): ?>
                <p>
                    <?=H::e($round['voting_opens_at'])?>
                    →
                    <?=H::e($round['voting_closes_at'])?>
                </p>
            <?php endif; ?>
        </div>

        <?php if($round['status']==='pending'): ?>
            <form
                method="post"
                action="/admin/battles/<?=(int)$event['id']?>/action"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::e(H::csrf())?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="open_round"
                >
                <input
                    type="hidden"
                    name="round_number"
                    value="<?=(int)$round['round_number']?>"
                >

                <button class="btn">
                    Open Round Voting
                </button>
            </form>
        <?php elseif($round['status']==='open'): ?>
            <form
                method="post"
                action="/admin/battles/<?=(int)$event['id']?>/action"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::e(H::csrf())?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="close_round"
                >
                <input
                    type="hidden"
                    name="round_number"
                    value="<?=(int)$round['round_number']?>"
                >

                <button class="btn">
                    Close Round &amp; Count Votes
                </button>
            </form>
        <?php endif; ?>
    </header>

    <div class="dashboard-grid">
    <?php foreach($round['matchups'] as $matchup): ?>
        <article class="card">
            <h3>
                Matchup #<?=(int)$matchup['bracket_position']?>
            </h3>

            <div>
                <?php if($matchup['participant_one_id']): ?>
                    <img
                        src="<?=H::e($matchup['participant_one_logo'])?>"
                        alt=""
                        style="width:120px;height:120px;object-fit:contain"
                    >
                    <strong>
                        <?=H::e($matchup['participant_one_name'])?>
                    </strong>
                    —
                    <?=number_format((int)$matchup['participant_one_votes'])?>
                    votes
                <?php else: ?>
                    <em>Bye</em>
                <?php endif; ?>
            </div>

            <p>vs.</p>

            <div>
                <?php if($matchup['participant_two_id']): ?>
                    <img
                        src="<?=H::e($matchup['participant_two_logo'])?>"
                        alt=""
                        style="width:120px;height:120px;object-fit:contain"
                    >
                    <strong>
                        <?=H::e($matchup['participant_two_name'])?>
                    </strong>
                    —
                    <?=number_format((int)$matchup['participant_two_votes'])?>
                    votes
                <?php else: ?>
                    <em>Bye</em>
                <?php endif; ?>
            </div>

            <?php if($matchup['winner_participant_id']): ?>
                <p>
                    <strong>
                        Winner:
                        <?=H::e($matchup['winner_name'])?>
                    </strong>
                </p>
            <?php endif; ?>

            <?php if($matchup['status']==='tied'): ?>
                <div class="attention-panel">
                    <strong>Tied Matchup</strong>

                    <p>
                        The system did not automatically choose a winner.
                        Select the winner according to the Battle tie decision.
                    </p>

                    <form
                        method="post"
                        action="/admin/battles/<?=(int)$event['id']?>/action"
                        class="form"
                    >
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?=H::e(H::csrf())?>"
                        >
                        <input
                            type="hidden"
                            name="action"
                            value="resolve_tie"
                        >
                        <input
                            type="hidden"
                            name="matchup_id"
                            value="<?=(int)$matchup['id']?>"
                        >

                        <label>
                            Winner
                            <select
                                name="winner_participant_id"
                                required
                            >
                                <option value="">
                                    — Choose —
                                </option>

                                <option value="<?=(int)$matchup['participant_one_id']?>">
                                    <?=H::e($matchup['participant_one_name'])?>
                                </option>

                                <option value="<?=(int)$matchup['participant_two_id']?>">
                                    <?=H::e($matchup['participant_two_name'])?>
                                </option>
                            </select>
                        </label>

                        <button class="btn">
                            Resolve Tie
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<section class="card">
    <h2>Voting Review Flags</h2>

    <p class="muted">
        These are anti-abuse signals for admin review only.
        A flag does not automatically delete or invalidate votes.
    </p>

    <?php foreach($flags as $flag): ?>
        <article class="card">
            <p>
                <strong><?=H::e($flag['signal_type'])?></strong>
                ·
                <?=number_format((int)$flag['event_count'])?>
                events
            </p>

            <p>
                Round <?=number_format((int)($flag['round_number']??0))?>,
                Matchup <?=number_format((int)($flag['bracket_position']??0))?>
            </p>

            <p>
                Status:
                <?=H::e($flag['status'])?>
            </p>

            <?php if($flag['status']==='open'): ?>
                <form
                    method="post"
                    action="/admin/battles/<?=(int)$event['id']?>/action"
                    class="form"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::e(H::csrf())?>"
                    >
                    <input
                        type="hidden"
                        name="action"
                        value="review_flag"
                    >
                    <input
                        type="hidden"
                        name="flag_id"
                        value="<?=(int)$flag['id']?>"
                    >

                    <label>
                        Admin notes
                        <textarea
                            name="notes"
                            maxlength="1000"
                        ></textarea>
                    </label>

                    <button
                        class="btn"
                        name="decision"
                        value="reviewed"
                    >
                        Mark Reviewed
                    </button>

                    <button
                        class="btn alt"
                        name="decision"
                        value="dismissed"
                    >
                        Dismiss Flag
                    </button>
                </form>
            <?php elseif($flag['admin_notes']): ?>
                <p>
                    Admin notes:
                    <?=H::e($flag['admin_notes'])?>
                </p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if(!$flags): ?>
        <p>No Battle voting flags.</p>
    <?php endif; ?>
</section>
