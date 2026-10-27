<?php
use App\Core\Helpers as H;
use App\Services\GrandOpeningSaleService as Sale;
$percent=number_format((int)$sale['discount_basis_points']/100,2,'.','');
?>
<h1>Grand Opening Sale</h1>
<?php if($error): ?><p role="alert"><?=H::e($error)?></p><?php endif; ?>
<div class="card">
<p>Event: <strong><?=H::e(Sale::state($event))?></strong>.
<?php if($event['starts_at']): ?>
<?=H::e(str_replace('T',' ',Sale::localInput($event['starts_at'])))?> –
<?=H::e(str_replace('T',' ',Sale::localInput($event['ends_at'])))?> Eastern.
<?php else: ?>Dates will be announced.<?php endif; ?>
</p>
<p>Choose your discount and products. Coupons and sale discounts cannot be combined.
Products must be approved and available before they can appear in the sale.</p>
</div>
<form method="post" class="card form">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label><input type="checkbox" name="is_participating" value="1" <?=!empty($sale['is_participating'])?'checked':''?>> Join the Grand Opening Sale</label>
<label>Discount
<select id="go-discount-preset">
<?php foreach([10,15,20,25] as $p): ?>
<option value="<?=$p?>" <?=(float)$percent===(float)$p?'selected':''?>><?=$p?>%</option>
<?php endforeach; ?>
<option value="custom" <?=!in_array((float)$percent,[10.0,15.0,20.0,25.0],true)?'selected':''?>>Custom</option>
</select></label>
<label>Discount percentage
<input id="go-discount-percent" type="number" name="discount_percent" min="0.01" max="99.99" step="0.01" value="<?=H::e($percent)?>" required>
</label>
<label>Apply to
<select name="product_scope" id="go-product-scope">
<option value="shop" <?=$sale['product_scope']==='shop'?'selected':''?>>Entire shop</option>
<option value="selected" <?=$sale['product_scope']==='selected'?'selected':''?>>Selected products</option>
</select></label>
<div id="go-product-picker">
<label>Find products<input type="search" id="go-product-search" placeholder="Search your products"></label>
<div style="max-height:420px;overflow:auto">
<?php foreach($saleProducts as $product): ?>
<label class="go-product-choice" style="display:block;padding:8px">
<input type="checkbox" name="product_ids[]" value="<?=(int)$product['id']?>" <?=in_array((int)$product['id'],$sale['product_ids'],true)?'checked':''?>>
<?=H::e($product['title'])?> — <?=H::e($product['status'])?>
</label>
<?php endforeach; ?>
<?php if(!$saleProducts): ?><p>No products available to select yet.</p><?php endif; ?>
</div>
</div>
<button type="submit">Save sale settings</button>
</form>
<script>
(() => {
 const scope=document.getElementById('go-product-scope');
 const picker=document.getElementById('go-product-picker');
 const toggle=()=>{picker.hidden=scope.value!=='selected';};
 scope.addEventListener('change',toggle);toggle();
 document.getElementById('go-product-search').addEventListener('input',e=>{
  const term=e.target.value.toLowerCase();
  document.querySelectorAll('.go-product-choice').forEach(row=>{
   row.style.display=row.textContent.toLowerCase().includes(term)?'block':'none';
  });
 });
 document.getElementById('go-discount-preset').addEventListener('change',e=>{
  const input=document.getElementById('go-discount-percent');
  if(e.target.value!=='custom')input.value=e.target.value;
  input.focus();
 });
 document.getElementById('go-discount-percent').addEventListener('input',e=>{
  const value=Number(e.target.value);
  document.getElementById('go-discount-preset').value=
   [10,15,20,25].includes(value)?String(value):'custom';
 });
})();
</script>
