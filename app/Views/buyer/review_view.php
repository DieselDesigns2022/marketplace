<h1>Review</h1>
<article class="card">
  <h2><?=H::e($review['product_title_snapshot'])?></h2>
  <p>Sold by <?=H::e($review['seller_name_snapshot'])?></p>
  <p aria-label="<?=(int)$review['rating']?> out of 5 stars"><?=str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating'])?></p>
  <?php if($review['review_text']):?><p><?=nl2br(H::e($review['review_text']))?></p><?php endif;?>
  <p><small>Submitted <?=H::e($review['reviewed_at'])?><?php if($review['edited_at']):?> · Edited<?php endif;?></small></p>

  <?php if($reply && $reply['moderation_status']==='published'):?>
    <aside>
      <strong>Seller Response</strong>
      <p><?=nl2br(H::e($reply['reply_text']))?></p>
      <small><?=H::e($reply['created_at'])?><?php if(strtotime($reply['updated_at'])>strtotime($reply['created_at'])):?> · Edited<?php endif;?></small>
    </aside>
  <?php endif;?>

  <div class="quick-actions">
    <a class="btn secondary" href="/dashboard/reviews/<?=(int)$review['id']?>?edit=1">Edit Review</a>
    <a class="btn alt" href="/dashboard/reviews">Back to My Reviews</a>
  </div>
</article>
