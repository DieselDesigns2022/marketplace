<?php
use App\Core\Helpers as H;
$giveawaySettings=$giveaway['settings'];
$giveawayDate=static fn(string $v):string=>(new DateTimeImmutable($v,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Detroit'))->format('M j, Y g:i A T');
?>
<section class="card">
<h2>Grand Opening Referral Giveaway</h2>
<?php if(!$giveawaySettings['starts_at']): ?>
<p>The referral giveaway has not opened yet. A prize from the donation pool will be selected by the Creative Moth team.</p>
<?php else: ?>
<p><strong><?=H::e($giveawayDate($giveawaySettings['starts_at']))?> through <?=H::e($giveawayDate($giveawaySettings['ends_at']))?></strong></p>
<p>Buyer referrals that qualify under the existing referral program during these dates earn one giveaway entry each. Your usual referral link, qualification rules, and rewards stay the same. Refunded payments do not count toward giveaway eligibility.</p>
<p>The winner receives a prize from the donation pool. The exact prize will be selected by the Creative Moth team. These giveaway entries are separate from normal referral rewards.</p>
<?php if(!$giveawaySettings['is_enabled']): ?><p><strong>The giveaway is currently paused.</strong> The normal referral program continues.</p><?php elseif(strtotime($giveawaySettings['starts_at'].' UTC')>time()): ?><p>Giveaway counting starts on the date above. Your usual referral program continues before then.</p><?php elseif(strtotime($giveawaySettings['ends_at'].' UTC')<=time()): ?><p>The entry period has ended. Your usual referral program continues.</p><?php endif; ?>
<?php if($giveaway['canRefer']): ?><p><a href="/seller/referrals#your-referral-link">Use your existing referral link and view the normal referral rules</a></p><?php else: ?><p>Buyer referral rewards remain an approved-seller benefit. This giveaway does not create a new buyer-to-buyer referral program.</p><?php endif; ?>
<p><strong>Your eligible entries: <?=(int)($giveaway['counts']['eligible']??0)?></strong> · Waiting to qualify: <?=(int)($giveaway['counts']['pending']??0)?></p>
<p class="muted">Entries are checked again before the drawing and prize award. Refunded purchases or account eligibility changes can remove an entry.</p>
<?php endif; ?>
</section>
