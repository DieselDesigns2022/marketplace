<section class="card">
<h1>You won <?=H::e($prize['prize_title'])?>!</h1>
<p style="white-space:pre-wrap"><?=H::e($prize['delivery_instructions'])?></p>
<?php if($prize['delivery_file_path']): ?><a class="btn" href="/dashboard/giveaway-prizes/<?=(int)$prize['id']?>?download=1">Download your prize</a><?php endif; ?>
<p><a href="/buyer/messages/<?=(int)$prize['conversation_id']?>">Open your prize message</a></p>
</section>
