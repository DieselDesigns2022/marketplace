<?php
use App\Core\Helpers as H;
$when=static function(?string $value): string {
    if (!$value) return 'Not scheduled';
    return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Detroit'))->format('M j, Y g:i A T');
};
?>
<h1>Social Content</h1>
<p>Prepare posts, copy shop details, and track what has been posted. This queue does not publish to social accounts.</p>
<p class="muted">Seller spotlights appear when an active seller has an approved store and an approved product. Existing eligible stores are included once. Dates below use Eastern time.</p>
<?php if($error): ?><div class="notice error" role="alert"><?=H::e($error)?></div><?php endif; ?>
<form method="get" action="/admin/social-content" class="card">
<label>Content type <select name="type"><option value="">All content</option><?php foreach($types as $key=>$label): ?><option value="<?=H::e($key)?>" <?=$type===$key?'selected':''?>><?=H::e($label)?></option><?php endforeach; ?></select></label>
<label>Status <select name="status"><?php foreach(['pending'=>'To post','done'=>'Posted / Done','all'=>'All statuses'] as $key=>$label): ?><option value="<?=$key?>" <?=$status===$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select></label>
<label>Find a shop or event <input name="q" value="<?=H::e($q)?>" maxlength="150"></label>
<button>Filter queue</button> <a href="/admin/social-content">Reset</a>
</form>
<p><strong><?=$total?></strong> matching items</p>
<div class="social-content-grid">
<?php foreach($items as $i=>$item): $url=H::baseUrl().$item['url'];$copy=$item['caption']."\n".$url; if($item['date'] && $item['type']==='sale')$copy.="\n".$item['date_label'].': '.$when($item['date']); ?>
<article class="card social-content-card">
<p class="muted"><?=H::e($types[$item['type']])?> · <?=$item['status']==='done'?'Posted / Done':'To post'?></p>
<?php if($item['image']): ?><a href="<?=H::e(H::assetUrl($item['image']))?>" target="_blank" rel="noopener"><img src="<?=H::e(H::assetUrl($item['image']))?>" alt="<?=H::e($item['title'])?> logo" width="100" height="100" style="object-fit:contain" loading="lazy"></a><p><a href="<?=H::e(H::assetUrl($item['image']))?>" target="_blank" rel="noopener">Open logo</a></p><?php elseif($item['type']==='spotlight'): ?><p class="muted">No shop logo uploaded.</p><?php endif; ?>
<h2><?=H::e($item['title'])?></h2>
<p><?=H::e($item['date_label'])?>: <?=H::e($when($item['date']))?></p>
<?php if($item['detail']): ?><p><?=H::e($item['detail'])?></p><?php endif; ?>
<p><a href="<?=H::e($url)?>" target="_blank" rel="noopener">Open <?= $item['type']==='spotlight'?'store':'page' ?></a></p>
<label>Name <input id="sc-name-<?=$i?>" value="<?=H::e($item['title'])?>" readonly></label>
<button type="button" data-copy="sc-name-<?=$i?>">Copy name</button>
<label>Direct link <input id="sc-url-<?=$i?>" value="<?=H::e($url)?>" readonly></label>
<button type="button" data-copy="sc-url-<?=$i?>">Copy link</button>
<label>Suggested caption <textarea id="sc-copy-<?=$i?>" rows="5" readonly><?=H::e($copy)?></textarea></label>
<button type="button" data-copy="sc-copy-<?=$i?>">Copy caption</button>
<?php if($item['status']==='done'): ?><p>Marked done <?=H::e($when($item['done_at']))?> by <?=H::e($item['done_by_name']??'previous team member')?>.</p><?php endif; ?>
<?php if($canManage): ?><form method="post" action="/admin/social-content">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="mark">
<input type="hidden" name="key" value="<?=H::e($item['key'])?>"><input type="hidden" name="version" value="<?=(int)$item['version']?>">
<input type="hidden" name="status" value="<?=$item['status']==='done'?'pending':'done'?>">
<button><?=$item['status']==='done'?'Return to queue':'Mark posted / done'?></button></form><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if(!$items): ?><div class="card">No content matches these filters. Disabled sale events are not advertised as live.</div><?php endif; ?>
<nav aria-label="Queue pages"><?php if($page>1): ?><a href="?<?=H::e(http_build_query(['type'=>$type,'status'=>$status,'q'=>$q,'page'=>$page-1]))?>">Previous</a><?php endif; ?> Page <?=$page?> of <?=$pages?> <?php if($page<$pages): ?><a href="?<?=H::e(http_build_query(['type'=>$type,'status'=>$status,'q'=>$q,'page'=>$page+1]))?>">Next</a><?php endif; ?></nav>
<?php if($canManage): ?>
<details class="card" <?=($form['action']??'')==='create'?'open':''?>><summary>Add giveaway, referral, or event highlight</summary>
<p>Add only confirmed event details. This creates a content task; it does not create or activate a giveaway or referral program.</p>
<form method="post" action="/admin/social-content">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="create">
<label>Type <select name="content_type"><?php foreach(['giveaway','referral','highlight'] as $t): ?><option value="<?=$t?>" <?=($form['content_type']??'')===$t?'selected':''?>><?=H::e($types[$t])?></option><?php endforeach; ?></select></label>
<label>Title <input name="title" maxlength="190" required value="<?=H::e($form['title']??'')?>"></label>
<label>Public site path <input name="target_path" placeholder="/browse" maxlength="500" required value="<?=H::e($form['target_path']??'')?>"></label>
<label>Planned date — Eastern <input type="datetime-local" name="relevant_at" value="<?=H::e($form['relevant_at']??'')?>"></label>
<label>Caption <textarea name="caption" rows="5" maxlength="4000" required><?=H::e($form['caption']??'')?></textarea></label>
<button>Add content task</button>
</form></details>
<?php endif; ?>
<p id="social-copy-status" role="status" aria-live="polite"></p>
<style>.social-content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.social-content-card{min-width:0;overflow-wrap:anywhere}.social-content-card input,.social-content-card textarea{width:100%;box-sizing:border-box}.social-content-card label{display:block;margin-top:12px}.social-content-card form{margin-top:18px}@media(max-width:720px){.social-content-grid{grid-template-columns:1fr}}</style>
<script>
document.querySelectorAll('[data-copy]').forEach(function(button){button.addEventListener('click',async function(){const field=document.getElementById(button.dataset.copy);const status=document.getElementById('social-copy-status');try{await navigator.clipboard.writeText(field.value);status.textContent='Copied.';}catch(error){field.focus();field.select();status.textContent='Text selected. Use Copy on your device.';}});});
</script>
