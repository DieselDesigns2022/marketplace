<h1><?=$collab?'Edit':'Create'?> a Collab Bundle</h1>
<?php foreach(($errors??[]) as $error):?><p class="notice warning"><?=H::e($error)?></p><?php endforeach;?>
<form method="post" class="card" id="collab-form">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Title <input required minlength="3" name="title" value="<?=H::e($collab['title']??'')?>"></label>
<label>Slug <input name="slug" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?=H::e($collab['slug']??'')?>"></label>
<label>Description <textarea required name="description"><?=H::e($collab['description']??'')?></textarea></label>
<label>Sale price <input required id="calc-price" type="text" inputmode="decimal" name="price" value="<?=H::e(isset($collab)?\App\Services\CreditService::formatCents((int)$collab['price_cents']):'10.00')?>"></label>
<label>Participation <select name="participation_type"><option value="open" <?=($collab['participation_type']??'')==='open'?'selected':''?>>Open</option><option value="closed" <?=($collab['participation_type']??'')==='closed'?'selected':''?>>Closed</option></select></label>
<label>Minimum files <input required type="number" min="1" name="minimum_file_count" value="<?=(int)($collab['minimum_file_count']??1)?>"></label>
<label>File Upload Deadline <input required type="datetime-local" name="upload_deadline" value="<?=H::e(isset($collab)?str_replace(' ','T',substr($collab['upload_deadline'],0,16)):'')?>"></label>
<label>Sale Start <input required type="datetime-local" name="sale_starts_at" value="<?=H::e(isset($collab)?str_replace(' ','T',substr($collab['sale_starts_at'],0,16)):'')?>"></label>
<label>Sale Close Date <input required type="date" name="sale_close_date" value="<?=H::e($collab['sale_close_date']??'')?>"></label>
<fieldset><legend>Payout planning calculator</legend><label>Hypothetical designers <input id="calc-designers" type="number" min="1" value="5"></label><p id="calc-output" aria-live="polite">Enter a price and designer count.</p></fieldset>
<button><?=$collab?'Save changes':'Create collab'?></button>
</form>
<script>
const price=document.querySelector('#calc-price'), designers=document.querySelector('#calc-designers'), output=document.querySelector('#calc-output');
let estimateRequest;
async function calculateEstimate(){
 clearTimeout(estimateRequest);
 estimateRequest=setTimeout(async()=>{
  output.textContent='Calculating…';
  const body=new FormData();
  body.set('_csrf',document.querySelector('#collab-form input[name="_csrf"]').value);
  body.set('price',price.value);
  body.set('designers',designers.value);
  try{
   const response=await fetch('/seller/collabs/payout-estimate',{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}});
   const result=await response.json();
   if(!response.ok||!result.ok)throw new Error(result.error||'Estimate unavailable.');
   const remainder=result.remainder_cents>0?` · ${result.remainder_cents} remainder cent${result.remainder_cents===1?'':'s'} distributed deterministically`:'';
   output.textContent=`Creative Moth fee $${result.fee} · contributor pool $${result.pool} · base per designer $${result.per_designer}${remainder}`;
  }catch(error){output.textContent=error.message||'Estimate unavailable.';}
 },200);
}
price.addEventListener('input',calculateEstimate);designers.addEventListener('input',calculateEstimate);calculateEstimate();
</script>
