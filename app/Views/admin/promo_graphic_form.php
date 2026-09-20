<?php use App\Core\Helpers as H;$editing=!empty($graphic['id']);?>
<p><a href="/admin/promo-library">← Promotional graphics</a></p><h1><?=$editing?'Edit':'Upload'?> Promotional Graphic</h1>
<form method="post" enctype="multipart/form-data" class="card form-grid promo-graphic-form"><input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Platform / placement<select name="platform" required><option value="">Choose one</option><?php foreach($platforms as $value=>$label):?><option value="<?=H::e($value)?>" <?=($graphic['platform']??'')===$value?'selected':''?>><?=H::e($label)?></option><?php endforeach;?></select></label>
<label>Size<input name="size_label" maxlength="100" required placeholder="e.g. 1080 × 1080 px" value="<?=H::e($graphic['size_label']??'')?>"></label>
<label>Category<input name="category" maxlength="100" required placeholder="e.g. Seller invitation" value="<?=H::e($graphic['category']??'')?>"></label>
<label>Sort order<input type="number" name="sort_order" min="0" max="1000000" step="1" required value="<?=H::e((string)($graphic['sort_order']??0))?>"></label>
<label class="full">Useful alt text<textarea name="alt_text" maxlength="500" required rows="3"><?=H::e($graphic['alt_text']??'')?></textarea></label>
<label class="full">Suggested caption (optional)<textarea name="suggested_caption" maxlength="2000" rows="5"><?=H::e($graphic['suggested_caption']??'')?></textarea></label>
<label class="full"><?=$editing?'Replacement image (leave empty to keep current image)':'Image'?> <input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?=$editing?'':'required'?>><small>JPG, PNG, or WEBP; maximum 10 MB and 25 megapixels.</small></label>
<?php if($editing):?><div class="full"><img class="promo-form-preview" src="/admin/promo-library/<?=(int)$graphic['id']?>/image" alt="Current image: <?=H::e($graphic['alt_text'])?>"></div><?php endif;?>
<label class="check full"><input type="checkbox" name="is_active" value="1" <?=!empty($graphic['is_active'])?'checked':''?>> Active and publicly visible</label>
<div class="full"><button class="btn"><?=$editing?'Save changes':'Upload graphic'?></button></div></form>
