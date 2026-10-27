<?php use App\Core\Helpers as H; ?>

<style>
.battle-matchup-card {
    padding: 28px;
}

.battle-head-to-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 90px minmax(0, 1fr);
    gap: 28px;
    align-items: center;
}

.battle-designer-option {
    text-align: center;
    min-width: 0;
}

.battle-designer-logo {
    width: min(100%, 320px);
    aspect-ratio: 1 / 1;
    object-fit: contain;
    display: block;
    margin: 0 auto 18px;
    border-radius: 18px;
}

.battle-designer-option h3 {
    margin: 0 0 16px;
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

@media (max-width: 720px) {
    .battle-head-to-head {
        grid-template-columns: 1fr;
    }

    .battle-vs {
        padding: 6px 0;
    }
}
</style>



<header class="dashboard-heading">
    <div>
        <a href="/battle-of-the-designers">
            ← Battle of the Designers
        </a>

        <h1><?=H::e($event['title'])?></h1>

        <p>
            <strong>Champion Prize:</strong>
            $100 Creative Moth Advertising Credit
        </p>
    </div>

    <?php if(H::user() && H::hasApprovedDesigner()): ?>
        <a class="btn" href="/seller/battle-of-the-designers">
            Seller Battle Signup
        </a>
    <?php endif; ?>
</header>

<?php if($event['status']==='signup'): ?>
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
<section class="card">
    <h2>
        Round <?=number_format((int)$round['round_number'])?>
    </h2>

    <?php if($round['status']==='open'): ?>
        <p>
            Voting closes:
            <strong>
                <?=H::e($round['voting_closes_at'])?>
            </strong>
        </p>
    <?php elseif($round['status']==='tied'): ?>
        <p>
            This round contains a tied matchup awaiting review.
        </p>
    <?php endif; ?>

    <div class="dashboard-grid">
    <?php foreach($round['matchups'] as $matchup): ?>
        <article class="card battle-matchup-card">
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
                                        name="participant_id"
                                        value="<?=(int)$matchup['participant_one_id']?>"
                                    >

                                    <button class="btn">
                                        Vote
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
                                        name="participant_id"
                                        value="<?=(int)$matchup['participant_two_id']?>"
                                    >

                                    <button class="btn">
                                        Vote
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
