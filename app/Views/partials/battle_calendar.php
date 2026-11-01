<?php if(!empty($event['first_round_opens_at'])): ?>

<div class="notice">

<p>
    <strong>
        Battle schedule — Eastern Time, Detroit
    </strong>
</p>

<p>
    Voting begins:
    <strong><?=H::e(
        \App\Services\BattleService::eastern(
            $event['first_round_opens_at']
        )
    )?></strong>.
</p>

<p>
    One matchup is featured at a time.
    Each matchup stays open for 24 hours.
    The next scheduled matchup begins at noon EST.
</p>

</div>

<?php endif; ?>

<p>
    <strong>Tie rules:</strong>
    A tied matchup gets a fresh 24-hour re-battle.
    Everyone may vote once again.
    If it ties again, overtime begins and the first new valid vote wins.
    The next matchup waits until the active matchup is finished.
    Earlier votes stay in the admin history.
</p>
