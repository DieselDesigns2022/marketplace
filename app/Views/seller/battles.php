<?php use App\Core\Helpers as H; ?>

<style>
.battle-how-it-works {
    max-width: 950px;
}

.battle-steps {
    margin: 24px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 18px;
}

.battle-step {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.battle-step-number {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    font-weight: 800;
    background: var(--accent, #2d1657);
    color: #fff;
}

.battle-step h3 {
    margin: 2px 0 6px;
}

.battle-step p {
    margin: 0;
}

.battle-info-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.battle-info-row .card {
    margin: 0;
}

@media (max-width: 720px) {
    .battle-info-row {
        grid-template-columns: 1fr;
    }

    .battle-step {
        grid-template-columns: 38px minmax(0, 1fr);
        gap: 12px;
    }

    .battle-step-number {
        width: 38px;
        height: 38px;
        font-size: 1rem;
    }
}
</style>

<section class="card battle-how-it-works">
    <h2>How Battle of the Designers Works</h2>

    <p>
        Sign up, promote your matchup, collect votes, and keep advancing
        through the bracket until one Creative Moth designer remains.
    </p>

    <ol class="battle-steps">
        <li class="battle-step">
            <span class="battle-step-number">1</span>

            <div>
                <h3>Sign Up</h3>

                <p>
                    Join the Battle below and upload the logo you want
                    displayed during your matchups.
                </p>
            </div>
        </li>

        <li class="battle-step">
            <span class="battle-step-number">2</span>

            <div>
                <h3>Designers Are Randomly Paired</h3>

                <p>
                    After signup closes, Creative Moth automatically creates
                    the tournament bracket and randomly pairs participating
                    designers for the first round.
                </p>
            </div>
        </li>

        <li class="battle-step">
            <span class="battle-step-number">3</span>

            <div>
                <h3>Get Your Votes</h3>

                <p>
                    Each Battle round lasts exactly <strong>24 hours</strong>.
                    Share your matchup with your followers, customers, friends,
                    and supporters and get as many votes as possible.
                </p>

                <p style="margin-top:8px">
                    Votes must come from
                    <strong>registered Creative Moth accounts</strong>.
                    Each account may vote once in the original matchup, once again in a re-battle, and once in overtime.
                </p>
            </div>
        </li>

        <li class="battle-step">
            <span class="battle-step-number">4</span>

            <div>
                <h3>Winners Advance</h3>

                <p>
                    The designer with the most votes advances automatically.
                    Round winners are placed into the next stage of the bracket
                    and battle again.
                </p>
            </div>
        </li>

        <li class="battle-step">
            <span class="battle-step-number">5</span>

            <div>
                <h3>Keep Battling Until One Winner Remains</h3>

                <p>
                    The tournament continues round by round until the final
                    matchup determines the Battle of the Designers champion.
                </p>
            </div>
        </li>
    </ol>
</section>

<div class="battle-info-row">
    <section class="card">
        <h2>Battle Emails</h2>

        <p>
            If you are competing in a round, Creative Moth will email you:
        </p>

        <ul>
            <li>
                <strong>12 hours before</strong> the first round begins; later-round reminders explain when you will compete if you advance
            </li>

            <li>
                When your matchup goes <strong>LIVE</strong>
            </li>

            <li>
                When there is <strong>1 hour remaining</strong>
            </li>
        </ul>

        <p class="muted">
            If you advance, you will receive the same reminders for your
            next round.
        </p>
    </section>

    <section class="card attention-panel">
        <h2>$100 Grand Prize</h2>

        <p>
            The final winner receives
            <strong>$100 Creative Moth Advertising Credit</strong>.
        </p>

        <p>
            Advertising Credit can be used to
            <strong>promote your shop and products on Creative Moth</strong>,
            helping put your designs in front of more shoppers.
        </p>

        <p>
            The $100 credit is added to the winning seller's Advertising
            Credit balance after the championship is completed.
        </p>
    </section>
</div>



<header class="dashboard-heading">
    <div>
        <h1>Battle of the Designers</h1>
        <p class="muted">
            Sign your store up for an open Battle and upload the logo that will represent you in every matchup.
        </p>
    </div>
</header>

<?php foreach($events as $event): ?>
<section class="card">
    <h2><?=H::e($event['title'])?></h2>
<?php include app_path('app/Views/partials/battle_calendar.php'); ?>

    <p>
        Status:
        <strong><?=H::e(ucwords($event['status']))?></strong>
    </p>

    <p>
        <strong>Champion Prize:</strong>
        $100 Creative Moth Advertising Credit
    </p>

    <?php if($event['participant_id']): ?>
        <p>
            <span class="status-badge ok">
                Registered
            </span>
        </p>

        <img
            src="<?=H::e($event['logo_path'])?>"
            alt="Your Battle logo"
            style="width:240px;height:240px;object-fit:contain"
        >

        <?php if($event['seed_position']): ?>
            <p>
                Bracket seed position:
                #<?=(int)$event['seed_position']?>
            </p>
        <?php endif; ?>

        <?php if(
            \App\Services\BattleService::signupOpen($event) &&
            $event['participant_status']==='active'
        ): ?>
            <form
                method="post"
                action="/seller/battle-of-the-designers/<?=(int)$event['id']?>/withdraw"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::e(H::csrf())?>"
                >

                <button class="btn alt">
                    Withdraw From Battle
                </button>
            </form>
        <?php endif; ?>

    <?php elseif(\App\Services\BattleService::signupOpen($event)): ?>

        <form
            method="post"
            enctype="multipart/form-data"
            action="/seller/battle-of-the-designers/<?=(int)$event['id']?>/signup"
            class="form"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::e(H::csrf())?>"
            >

            <label>
                Your store logo
                <input
                    type="file"
                    name="logo"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >
            </label>

            <p class="muted">
                Required. JPG, PNG, or WEBP, maximum 10 MB.
                Creative Moth automatically places your logo on a consistent square canvas for Battle matchups.
            </p>

            <button class="btn">
                Sign Up for Battle
            </button>
        </form>

    <?php endif; ?>

    <p>
        <a href="/battle-of-the-designers/<?=H::e($event['slug'])?>">
            View Battle
        </a>
    </p>
</section>
<?php endforeach; ?>

<?php if(!$events): ?>
<section class="card">
    <p>No Battle of the Designers event is available yet.</p>
</section>
<?php endif; ?>
