<?php
use App\Core\Helpers as H;
$when=static function(?string $value): string {
    if (!$value) return 'Not scheduled';
    return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Detroit'))->format('M j, Y g:i A T');
};
?>
<h1>Social Content</h1>
<p><a class="btn" href="/admin/promo-library">Open Promo Graphics Library</a></p>
<p>Prepare posts, copy shop details, and track what has been posted. This queue does not publish to social accounts.</p>
<p class="muted">Seller spotlights appear when an active seller has an approved store and an approved product. Newest stores appear first, based on their store-joined date. Existing eligible stores are included once. Dates below use Eastern time.</p>
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
<details class="card" <?=($form['action']??'')==='create'?'open':''?>><summary>Social Media Planner</summary>
<h2>Plan a social media post</h2>
<p>Use this planner to keep post ideas and ready-to-share captions in one place. The website owner or social media manager can add a post here for the team to use later.</p>

<h3>How to add a planned post</h3>
<ol>
<li><strong>Choose a type:</strong> Giveaway, Referral, or Event / Prize Highlight. This helps you find the post using the content-type filter above.</li>
<li><strong>Give it a clear title:</strong> For example, “Friday giveaway reminder.” This title is for your team to recognize the post.</li>
<li><strong>Add the page to promote:</strong> Enter the part of the Creative Moth link after creativemoth.com. For example, enter <code>/browse</code> to send people to the marketplace.</li>
<li><strong>Choose when you want to post:</strong> The date and time use Eastern time. You can leave this blank if you have not decided yet. This is a planning note—not an automatic publishing time or reminder.</li>
<li><strong>Write the caption:</strong> Add the words you want to share on social media. Include the important details and tell readers what to do next, such as shop, enter, or visit a page.</li>
<li><strong>Click Add to planner:</strong> Your post will appear in the queue above with a “To post” status. Use the content-type filter or search to find it.</li>
</ol>

<h3>When you are ready to share it</h3>
<ol>
<li>Find the planned post in the queue and check that its details and link are still correct.</li>
<li>Click <strong>Copy caption</strong>. The copied text includes the link you entered.</li>
<li>Open the <strong>Promo Graphics Library</strong> if you need an image, then create the post on your social media platform using the caption and image you choose.</li>
<li>After publishing it yourself, return here and click <strong>Mark posted / done</strong>. This tells the rest of the team it has been handled.</li>
</ol>

<p>To find completed posts, change the Status filter to <strong>Posted / Done</strong>. If a post was marked done by mistake, click <strong>Return to queue</strong>.</p>

<p><strong>Example:</strong> Choose Giveaway, use “Friday giveaway reminder” as the title, enter the page explaining that giveaway, choose Friday’s posting time, and write your reminder caption. Your manager can then find everything needed to prepare that post.</p>

<p><strong>This planner saves your posting task only.</strong> It does not publish or schedule posts on Facebook, Instagram, or other platforms, send reminders, or create the giveaway or referral offer itself. Add those posts here once the offer and its details are ready.</p>
<form method="post" action="/admin/social-content">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="create">
<label>Type <select name="content_type"><?php foreach(['giveaway','referral','highlight'] as $t): ?><option value="<?=$t?>" <?=($form['content_type']??'')===$t?'selected':''?>><?=H::e($types[$t])?></option><?php endforeach; ?></select></label>
<label>Post title (for example, Friday giveaway reminder) <input name="title" maxlength="190" required value="<?=H::e($form['title']??'')?>"></label>
<label>Link to promote — the part after creativemoth.com <input name="target_path" placeholder="/browse" maxlength="500" required value="<?=H::e($form['target_path']??'')?>"></label>
<label>When to post — Eastern time (optional) <input type="datetime-local" name="relevant_at" value="<?=H::e($form['relevant_at']??'')?>"></label>
<label>Caption <textarea name="caption" rows="5" maxlength="4000" required><?=H::e($form['caption']??'')?></textarea></label>
<button>Add to planner</button>
</form></details>
<?php endif; ?>
<p id="social-copy-status" role="status" aria-live="polite"></p>
<style>.social-content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.social-content-card{min-width:0;overflow-wrap:anywhere}.social-content-card input,.social-content-card textarea{width:100%;box-sizing:border-box}.social-content-card label{display:block;margin-top:12px}.social-content-card form{margin-top:18px}@media(max-width:720px){.social-content-grid{grid-template-columns:1fr}}</style>
<script>
document.querySelectorAll('[data-copy]').forEach(function(button){button.addEventListener('click',async function(){const field=document.getElementById(button.dataset.copy);const status=document.getElementById('social-copy-status');try{await navigator.clipboard.writeText(field.value);status.textContent='Copied.';}catch(error){field.focus();field.select();status.textContent='Text selected. Use Copy on your device.';}});});
</script>
