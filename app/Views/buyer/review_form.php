<nav><a href="/dashboard/reviews">← My Reviews</a></nav>
<section class="card review-form">
  <h1><?=$review?'Edit Review':'Leave a Review'?></h1>
  <div class="media-row">
    <?php if(!empty($item['preview_image'])):?><img class="product-thumb" src="<?=H::e(H::assetUrl($item['preview_image']))?>" alt=""><?php endif;?>
    <div><h2><?=H::e($item['product_title'])?></h2><p>Sold by <?=H::e($item['seller_name'])?></p><p>Order #<?=(int)$item['order_id']?> · Purchased <?=H::e($item['purchase_date'])?></p></div>
  </div>
  <?php if($error):?><div class="notice error" role="alert"><?=H::e($error)?></div><?php endif;?>
  <form method="post"><input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <fieldset><legend>Rating <span aria-hidden="true">*</span></legend><div class="star-input">
      <?php for($star=1;$star<=5;$star++):?><label><input type="radio" name="rating" value="<?=$star?>" <?=((int)($review['rating']??0)===$star)?'checked':''?> required aria-label="Rate <?=$star?> out of 5 stars"> <span aria-hidden="true"><?=$star?> ★</span></label><?php endfor;?>
    </div></fieldset>
    <label>Written feedback (optional)<textarea name="review_text" maxlength="2000" rows="7"><?=H::e($review['review_text']??'')?></textarea></label><small>Maximum 2,000 characters.</small><button class="btn">Save Review</button>
  </form>
</section>
