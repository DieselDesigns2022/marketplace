<h1>My Reviews</h1>
<?php if(!$reviews):?><div class="card empty-state"><p>You have not reviewed a downloaded purchase yet.</p></div><?php endif;?>
<?php foreach($reviews as $r):?><article class="card">
  <h2><?=H::e($r['product_title_snapshot'])?></h2><p>Sold by <?=H::e($r['seller_name_snapshot'])?></p>
  <p aria-label="<?=(int)$r['rating']?> out of 5 stars"><?=str_repeat('★',(int)$r['rating']).str_repeat('☆',5-(int)$r['rating'])?></p>
  <?php if($r['review_text']):?><p><?=nl2br(H::e($r['review_text']))?></p><?php endif;?><p><small>Submitted <?=H::e($r['reviewed_at'])?><?php if($r['edited_at']):?> · Edited<?php endif;?></small></p>
  <?php if($r['reply_text']&&$r['reply_moderation_status']==='published'):?><aside><strong>Seller Response</strong><p><?=nl2br(H::e($r['reply_text']))?></p><small><?=H::e($r['reply_created_at'])?><?php if(strtotime($r['reply_updated_at'])>strtotime($r['reply_created_at'])):?> · Edited<?php endif;?></small></aside><?php endif;?>
  <a class="btn secondary" href="/dashboard/reviews/<?=(int)$r['id']?>">Edit Review</a>
</article><?php endforeach;?>
