<?php if(!empty($event['signup_closes_at'])&& !empty($event['first_round_opens_at'])): ?>
<div class="notice">
<p><strong>Battle schedule — Eastern Time, Detroit</strong></p>
<p>Signups close: <strong><?=H::e(\App\Services\BattleService::eastern($event['signup_closes_at']))?></strong> (end of October 31).</p>
<p>Voting begins: <strong><?=H::e(\App\Services\BattleService::eastern($event['first_round_opens_at']))?></strong>.</p>
<p>Each round runs for 24 hours. The next round begins at noon EST the following day. Ties can delay the next round. After overtime, voting resumes at the next noon EST.</p>
<?php if($event['status']==='signup'&&!\App\Services\BattleService::signupOpen($event)): ?><p><strong>Signups are closed.</strong> The bracket is being prepared. At least two registered sellers are required.</p><?php endif; ?>
</div>
<?php endif; ?>
<p><strong>Tie rules:</strong> A tied matchup gets a fresh 24-hour re-battle. Everyone may vote once again. If it ties again, overtime begins and the first new valid vote wins. The next round waits until every matchup has a winner. Earlier votes stay in the admin history.</p>
