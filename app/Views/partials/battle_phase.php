<?php $phase=(int)($matchup['voting_phase']??0); ?>
<?php if($phase>0): ?>
<p><strong><?=$phase===1?'Re-battle':'Overtime'?></strong> —
<?php if($matchup['status']==='completed'): ?>Completed.
<?php elseif($phase===1): ?>Fresh votes. Ends <?=H::e(\App\Services\BattleService::eastern($matchup['voting_closes_at']))?>. Everyone may vote once again.
<?php else: ?>The first new valid vote wins. Everyone may vote again.
<?php endif; ?></p>
<?php endif; ?>
