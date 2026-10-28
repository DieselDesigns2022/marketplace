<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <a href="/admin/battles">
            ← Battle Events
        </a>

        <h1><?=H::e($event['title'])?></h1>
<?php include app_path('app/Views/partials/battle_calendar.php'); ?>

        <p>
            Status:
            <strong><?=H::e($event['status'])?></strong>
        </p>
    </div>


</header>



<section class="card">
    <h2>Battle Configuration</h2>

    <p>
        <strong>Champion Prize:</strong>
        $100 Creative Moth Advertising Credit
    </p>

    <p>
        <strong>Round duration:</strong>
        24 hours exactly
    </p>

    <p class="muted">
        Designers receive a reminder 12 hours before the first round and conditional reminders for later rounds,
        when voting goes live, and when 1 hour remains.
    </p>
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

    <?php if($event['status']==='signup'&&empty($event['signup_closes_at'])): ?>
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
                <?php
                $opensEastern=(new DateTimeImmutable(
                    $round['voting_opens_at'],
                    new DateTimeZone('UTC')
                ))->setTimezone(
                    new DateTimeZone('America/Detroit')
                );

                $closesEastern=$round['voting_closes_at'] ? (new DateTimeImmutable(
                    $round['voting_closes_at'],
                    new DateTimeZone('UTC')
                ))->setTimezone(
                    new DateTimeZone('America/Detroit')
                ) : null;
                ?>

                <p>
                    <strong>
                        <?=H::e(
                            $round['status']==='pending'
                                ? 'Scheduled:'
                                : 'Voting window:'
                        )?>
                    </strong>

                    <?=H::e($opensEastern->format('M j, Y g:i A T'))?>
                    →
                    <?=H::e(($closesEastern ? $closesEastern->format('M j, Y g:i A T') : 'Overtime — first new valid vote wins'))?>
                </p>
            <?php endif; ?>
        </div>

        <?php if(
            $round['status']==='pending' && empty($event['first_round_opens_at']) &&
            empty($round['voting_opens_at']) &&
            (
                (int)$round['round_number']===1 ||
                $event['status']==='active'
            )
        ): ?>
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
                    value="schedule_round"
                >

                <input
                    type="hidden"
                    name="round_number"
                    value="<?=(int)$round['round_number']?>"
                >

                <label>
                    Round start — Eastern Time
                    <input
                        type="datetime-local"
                        name="starts_at"
                        required
                    >
                </label>

                <p class="muted">
                    Round 1 must be scheduled at least 12 hours ahead.
                    Voting will open automatically and remain open exactly 24 hours.
                    Later rounds are scheduled automatically when the winners advance.
                </p>

                <button class="btn">
                    Schedule Round
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
        <?php include app_path('app/Views/partials/battle_phase.php'); ?>

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

<section class="card">
    <h2>Registered Voter Audit</h2>

    <p class="muted">
        Admin-only voter details for manual fraud and bot review.
        These details are never shown publicly.
    </p>

    <?php if(!empty($voterLedger)): ?>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Round</th>
                        <th>Matchup</th><th>Voting phase</th>
                        <th>Registered voter</th>
                        <th>Email</th>
                        <th>Account created</th>
                        <th>Voted for</th>
                        <th>Vote time</th>
                        <th>IP address</th>
                        <th>Browser / device evidence</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach($voterLedger as $vote): ?>
                    <tr>
                        <td>
                            <?=number_format((int)$vote['round_number'])?>
                        </td>

                        <td>
                            #<?=number_format((int)$vote['bracket_position'])?>
                        </td><td><?=H::e(['Original','Re-battle','Overtime'][(int)$vote['voting_phase']]??'Unknown')?></td>

                        <td>
                            <strong>
                                <?=H::e($vote['voter_name'])?>
                            </strong>
                            <br>
                            User #<?=(int)$vote['user_id']?>
                        </td>

                        <td>
                            <?=H::e($vote['voter_email'])?>
                        </td>

                        <td>
                            <?=H::e(\App\Services\BattleService::eastern($vote['account_created_at']))?>
                        </td>

                        <td>
                            <strong>
                                <?=H::e($vote['voted_for_name'])?>
                            </strong>
                        </td>

                        <td>
                            <?=H::e(\App\Services\BattleService::eastern($vote['voted_at']))?>
                        </td>

                        <td>
                            <code><?=H::e($vote['ip_address'])?></code>
                        </td>

                        <td style="max-width:360px;word-break:break-word">
                            <?=H::e($vote['user_agent'])?>

                            <?php if(!empty($vote['browser_fingerprint_hash'])): ?>
                                <br>
                                <small>
                                    Fingerprint:
                                    <code>
                                        <?=H::e(
                                            substr(
                                                $vote['browser_fingerprint_hash'],
                                                0,
                                                16
                                            )
                                        )?>…
                                    </code>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No registered users have voted in this Battle yet.</p>
    <?php endif; ?>
</section>
