<h1>Grand Prize Wheel</h1>
<p>Select the giveaway and donated prize, then award the real prize. Each win uses one entry. Remaining entries can win again.</p>
<p>Final drawings open after the saved Grand Opening dates end. This does not change the store purchase wheels.</p>
<?php if($poolError): ?><p role="alert"><?=H::e($poolError)?></p><?php endif; ?>
<?php if($canManage): ?>
<section class="card">
<h2>Live drawing visibility</h2>
<form method="post">
<input type="hidden" name="_csrf" value="<?=H::e(H::csrf())?>">
<?php if($live): ?><button name="action" value="done">Giveaway done — close live drawing</button>
<?php else: ?><button name="action" value="live">Show live on the homepage</button><?php endif; ?>
</form>
<p>The drawing stays live until you click <strong>Giveaway done</strong>. That closes the public wheel and removes the homepage live link.</p>
<a href="/live-giveaway" target="_blank" rel="noopener">View public live drawing — no admin controls</a>
</section>
<section class="card">
<h2>Choose the next prize</h2>
<form method="post" id="final-draw-form">
<input type="hidden" name="_csrf" value="<?=H::e(H::csrf())?>">
<input type="hidden" name="request_key" value="<?=H::e(bin2hex(random_bytes(24)))?>">
<label>Giveaway <select name="pool" required>
<?php foreach(\App\Services\FinalGiveawayService::POOLS as $key=>$label): ?><option value="<?=H::e($key)?>" ><?=H::e($label)?> — <?=(int)($poolCounts[$key]??0)?> entries</option><?php endforeach; ?>
</select></label>
<label>Prize <select name="prize_id" id="final-prize" required><option value="">Choose a donated prize</option>
<?php foreach($prizes as $p): ?><option value="<?=(int)$p['id']?>"  data-delivery="<?=H::e($p['description']."\n\n".$p['fulfillment_instructions'].($p['restrictions']?"\n\nRestrictions: ".$p['restrictions']:'')."\n\n".($p['prize_file_storage_path']?'Uploaded prize file: included as a secure download.':'No uploaded file; the seller’s instructions will be sent.'))?>"><?=H::e($p['title'].' — '.$p['shop_name'])?> (<?=(int)$p['quantity_remaining']?> left)</option><?php endforeach; ?>
</select></label>
<div id="final-delivery-preview" style="white-space:pre-wrap"></div>
<p>The seller’s uploaded file, description, delivery instructions, and restrictions are automatically sent to the winner through Messages and email.</p>
<button type="submit" name="action" value="draw" <?=!$prizes?'disabled':''?>>Spin and award REAL prize</button>
<p><strong>Real spin</strong> awards the prize immediately and cannot be undone.</p>
</form>
<?php if(!$prizes): ?><p>No available prizes are authorized for giveaways. <a href="/admin/prize-donations">Review Prize Donations</a>.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php if($live): ?><section class="card"><h2>Admin wheel — confirm winning entry removal here</h2><iframe src="/admin/final-giveaway/view" title="Grand Prize live drawing" style="width:100%;height:600px;border:0"></iframe></section><?php endif; ?>
<section class="card"><h2>Drawing history</h2>
<?php if(!$draws): ?><p>No prizes have been drawn yet.</p><?php endif; ?>
<?php foreach($draws as $d): ?>
<article style="border-bottom:1px solid #ccc;padding:1rem 0">
<h3><?=H::e($d['prize_title'])?></h3>
<p>Winner: <?=H::e($d['winner_label'])?> · Ticket <?=H::e($d['winner_entry_key'])?> · <?=(int)$d['pool_count']?> entries in this draw</p>
<p>Message saved · Email: <?=H::e($d['email_status']??'not queued')?></p>
<?php if($d['email_status']==='failed'): ?><p role="alert">Email delivery failed. The prize remains in the winner’s Messages. Check the email worker before the next drawing.</p><?php endif; ?>
<a href="?audit=<?=(int)$d['id']?>">Download drawing record</a> · <a href="/admin/prize-donations">Prize fulfillment</a>
</article>
<?php endforeach; ?></section>
<script src="/assets/final-giveaway-admin.js?v=3" defer></script>
