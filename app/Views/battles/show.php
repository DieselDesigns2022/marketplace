<?php use App\Core\Helpers as H; ?>

<style>
.battle-round-section {
    margin-bottom: 28px;
}

.battle-matchup-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

.battle-matchup-card {
    padding: 28px;
    min-width: 0;
}

.battle-head-to-head {
    display: grid;
    grid-template-columns: minmax(190px, 1fr) 64px minmax(190px, 1fr);
    gap: 28px;
    align-items: center;
}

.battle-designer-option {
    text-align: center;
    min-width: 0;
}

.battle-designer-logo {
    width: min(100%, 220px);
    aspect-ratio: 1 / 1;
    object-fit: contain;
    display: block;
    margin: 0 auto 18px;
    border-radius: 18px;
}

.battle-designer-option h3 {
    margin: 0 0 16px;
    font-size: 1.15rem;
    line-height: 1.3;
    word-break: normal;
    overflow-wrap: normal;
    hyphens: none;
}

.battle-designer-option .btn {
    width: 100%;
    max-width: 260px;
    white-space: normal;
}

.battle-voting-banner {
    padding: 24px;
    margin-bottom: 28px;
    text-align: center;
    border: 2px solid currentColor;
}

.battle-voting-banner h2 {
    margin-top: 0;
}

.battle-preopen-vote {
    width: 100%;
    max-width: 260px;
    opacity: .7;
    cursor: not-allowed;
}

.battle-vs {
    font-size: 2.25rem;
    font-weight: 800;
    text-align: center;
}

.battle-previous-winners {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 20px;
}

.battle-previous-winner {
    text-align: center;
}

.battle-previous-winner img {
    width: 160px;
    height: 160px;
    object-fit: contain;
    display: block;
    margin: 0 auto 12px;
    border-radius: 14px;
}

@media (max-width: 980px) {
    .battle-matchup-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .battle-matchup-card {
        padding: 20px;
    }

    .battle-head-to-head {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .battle-vs {
        padding: 6px 0;
    }

    .battle-designer-logo {
        width: min(100%, 200px);
    }
}
</style>



<header class="dashboard-heading">
    <div>
        <a href="/battle-of-the-designers">
            ← Battle of the Designers
        </a>

        <h1><?=H::e($event['title'])?></h1>
<?php include app_path('app/Views/partials/battle_calendar.php'); ?>

        <p>
            <strong>Champion Prize:</strong>
            $100 Creative Moth Advertising Credit
        </p>
    </div>

    <?php if(
        \App\Services\BattleService::signupOpen($event) &&
        H::user() &&
        H::hasApprovedDesigner()
    ): ?>
        <a class="btn" href="/seller/battle-of-the-designers">
            Seller Battle Signup
        </a>
    <?php endif; ?>
</header>

<?php
$battleNow = time();

$battleFirstOpen = !empty($event['first_round_opens_at'])
    ? strtotime($event['first_round_opens_at'].' UTC')
    : null;

$battlePreVoting =
    $battleFirstOpen &&
    $battleFirstOpen > $battleNow &&
    in_array($event['status'], ['seeded','active'], true);

$battleOpenRound = null;

foreach($rounds as $battleStatusRound){
    if(($battleStatusRound['status'] ?? '') === 'open'){
        $battleOpenRound = $battleStatusRound;
        break;
    }
}
?>

<?php if($battleOpenRound): ?>

<section class="card battle-voting-banner">
    <h2>🗳️ VOTING IS LIVE!</h2>

    <p>
        Choose your favorite designer in each matchup below.
    </p>

    <p>
        Click the <strong>Vote for [Designer]</strong>
        button under the designer you want to support.
    </p>

    <?php if(!empty($battleOpenRound['voting_closes_at'])): ?>
        <p>
            Voting closes:
            <strong><?=H::e(
                \App\Services\BattleService::eastern(
                    $battleOpenRound['voting_closes_at']
                )
            )?></strong>
        </p>
    <?php endif; ?>
</section>

<?php elseif($battlePreVoting): ?>

<section class="card battle-voting-banner">
    <h2>🕛 Round 1 Voting Opens Today</h2>

    <p>
        Voting opens at
        <strong><?=H::e(
            \App\Services\BattleService::eastern(
                $event['first_round_opens_at']
            )
        )?></strong>.
    </p>

    <p>
        Vote buttons are disabled until voting officially opens.
    </p>
</section>

<?php endif; ?>

<?php if(\App\Services\BattleService::signupOpen($event)): ?>
<section class="card">
    <h2>Designer Signup Is Open</h2>
    <p>
        Participating sellers must explicitly register and upload their logo.
        The uploaded logo is used throughout the Battle bracket.
    </p>
</section>
<?php endif; ?>

<?php if($event['status']==='completed'): ?>
<section class="card">
    <h2>🏆 Battle Champion</h2>

    <?php
    $champion=null;
    foreach($participants as $participant){
        if(
            (int)$participant['id']===
            (int)$event['champion_participant_id']
        ){
            $champion=$participant;
            break;
        }
    }
    ?>

    <?php if($champion): ?>
        <img
            src="<?=H::e($champion['logo_path'])?>"
            alt="<?=H::e($champion['display_name'])?> logo"
            style="width:220px;height:220px;object-fit:contain"
        >

        <h3><?=H::e($champion['display_name'])?></h3>

        <p>
            Winner of $100 Creative Moth Advertising Credit
        </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if(!$rounds): ?>
<section class="card">
    <h2>Registered Designers</h2>

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
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php foreach($rounds as $round): ?>
<?php
if(
    $event['status'] !== 'completed' &&
    (int)$round['round_number'] > 1 &&
    ($round['status'] ?? '') === 'pending'
){
    continue;
}
?>
<section class="card battle-round-section">
    <h2>
        Round <?=number_format((int)$round['round_number'])?>
    </h2>

    <?php if($round['status']==='open'): ?>
        <p>
            Voting closes:
            <strong>
                <?=H::e(($round['voting_closes_at']?\App\Services\BattleService::eastern($round['voting_closes_at']):'Overtime — waiting for the first new valid vote'))?>
            </strong>
        </p>
    <?php elseif($round['status']==='tied'): ?>
        <p>
            Tied matchups require a re-battle before advancement.
        </p>
    <?php endif; ?>

    <div class="battle-matchup-grid">
    <?php foreach($round['matchups'] as $matchup): ?>
        <article class="card battle-matchup-card">
        <?php include app_path('app/Views/partials/battle_phase.php'); ?>

            <p>
                <strong>
                    Matchup #<?=number_format((int)$matchup['bracket_position'])?>
                </strong>
            </p>

            <div class="battle-head-to-head">
                <div class="battle-designer-option">
                    <?php if($matchup['participant_one_id']): ?>
                        <img
                            src="<?=H::e($matchup['participant_one_logo'])?>"
                            alt="<?=H::e($matchup['participant_one_name'])?> logo"
                            class="battle-designer-logo"
                        >

                        <h3>
                            <?=H::e($matchup['participant_one_name'])?>
                        </h3>

                        <?php if(
                            $battlePreVoting &&
                            (int)$round['round_number'] === 1 &&
                            $matchup['status'] === 'pending'
                        ): ?>

                            <button
                                class="btn battle-preopen-vote"
                                type="button"
                                disabled
                            >
                                Vote for <?=H::e($matchup['participant_one_name'])?>
                                — Opens <?=H::e(
                                    \App\Services\BattleService::eastern(
                                        $matchup['voting_opens_at']
                                    )
                                )?>
                            </button>

                        <?php endif; ?>

                        <?php if(
                            $matchup['status']==='open'
                        ): ?>

                            <?php if(!H::user()): ?>
                                <a class="btn" href="/login">
                                    Log In to Vote
                                </a>

                            <?php elseif(isset($votes[(int)$matchup['id']])): ?>

                                <?php if(
                                    $votes[(int)$matchup['id']]===
                                    (int)$matchup['participant_one_id']
                                ): ?>
                                    <span class="status-badge ok">
                                        Your Vote
                                    </span>
                                <?php endif; ?>

                            <?php else: ?>
                                <form
                                    method="post"
                                    action="/battle-of-the-designers/<?=H::e($event['slug'])?>/matchups/<?=(int)$matchup['id']?>/vote"
                                >
                                    <input
                                        type="hidden"
                                        name="_csrf"
                                        value="<?=H::e(H::csrf())?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="voting_phase" value="<?=(int)$matchup['voting_phase']?>"><input type="hidden" name="participant_id"
                                        value="<?=(int)$matchup['participant_one_id']?>"
                                    >

                                    <button class="btn">
                                        Vote for <?=H::e($matchup['participant_one_name'])?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php else: ?>
                        <p><em>Bye</em></p>
                    <?php endif; ?>
                </div>

                <div class="battle-vs" aria-hidden="true">
                    VS
                </div>

                <div class="battle-designer-option">
                    <?php if($matchup['participant_two_id']): ?>
                        <img
                            src="<?=H::e($matchup['participant_two_logo'])?>"
                            alt="<?=H::e($matchup['participant_two_name'])?> logo"
                            class="battle-designer-logo"
                        >

                        <h3>
                            <?=H::e($matchup['participant_two_name'])?>
                        </h3>

                        <?php if(
                            $battlePreVoting &&
                            (int)$round['round_number'] === 1 &&
                            $matchup['status'] === 'pending'
                        ): ?>

                            <button
                                class="btn battle-preopen-vote"
                                type="button"
                                disabled
                            >
                                Vote for <?=H::e($matchup['participant_two_name'])?>
                                — Opens <?=H::e(
                                    \App\Services\BattleService::eastern(
                                        $matchup['voting_opens_at']
                                    )
                                )?>
                            </button>

                        <?php endif; ?>

                        <?php if(
                            $matchup['status']==='open'
                        ): ?>

                            <?php if(!H::user()): ?>
                                <a class="btn" href="/login">
                                    Log In to Vote
                                </a>

                            <?php elseif(isset($votes[(int)$matchup['id']])): ?>

                                <?php if(
                                    $votes[(int)$matchup['id']]===
                                    (int)$matchup['participant_two_id']
                                ): ?>
                                    <span class="status-badge ok">
                                        Your Vote
                                    </span>
                                <?php endif; ?>

                            <?php else: ?>
                                <form
                                    method="post"
                                    action="/battle-of-the-designers/<?=H::e($event['slug'])?>/matchups/<?=(int)$matchup['id']?>/vote"
                                >
                                    <input
                                        type="hidden"
                                        name="_csrf"
                                        value="<?=H::e(H::csrf())?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="voting_phase" value="<?=(int)$matchup['voting_phase']?>"><input type="hidden" name="participant_id"
                                        value="<?=(int)$matchup['participant_two_id']?>"
                                    >

                                    <button class="btn">
                                        Vote for <?=H::e($matchup['participant_two_name'])?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php else: ?>
                        <p><em>Bye</em></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(
                in_array(
                    $matchup['status'],
                    ['completed','tied'],
                    true
                )
            ): ?>
                <p>
                    Final vote:
                    <?=number_format((int)$matchup['participant_one_votes'])?>
                    –
                    <?=number_format((int)$matchup['participant_two_votes'])?>
                </p>
            <?php endif; ?>

            <?php if($matchup['winner_participant_id']): ?>
                <p>
                    <strong>
                        Winner:
                        <?=H::e($matchup['winner_name'])?>
                    </strong>
                </p>

            <?php elseif($matchup['status']==='tied'): ?>
                <p>
                    <strong>
                        Tied — awaiting resolution
                    </strong>
                </p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php if(
    !empty($previousRoundWinners)
): ?>
<section class="card">
    <h2>🏆 Previous Round Winners</h2>

    <p class="muted">
        These designers advanced from the previous round.
        Visit their Creative Moth stores while they continue through the Battle.
    </p>

    <?php if(!empty($previousRoundWinners)): ?>
        <div class="battle-previous-winners">
            <?php foreach($previousRoundWinners as $winner): ?>
                <article class="battle-previous-winner">
                    <a href="/store/<?=H::e($winner['store_slug'])?>">
                        <img
                            src="<?=H::e($winner['logo_path'])?>"
                            alt="<?=H::e($winner['display_name'])?> logo"
                        >

                        <h3>
                            <?=H::e($winner['display_name'])?>
                        </h3>
                    </a>

                    <a
                        class="btn alt"
                        href="/store/<?=H::e($winner['store_slug'])?>"
                    >
                        Visit Their Store
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</section>
<?php endif; ?>
