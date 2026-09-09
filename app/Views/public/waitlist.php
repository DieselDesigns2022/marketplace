<?php use App\Core\Helpers as H;$selected=$values['interest_type']??[];?><section class="panel narrow waitlist-form-panel">
<?php if($success):?>
<div class="notice success"><?php if($already):?>You're already on the waitlist! We'll let you know when we're ready.<?php else:?>Thanks! Your waitlist preferences have been saved.<?php endif;?></div>
<?php else:?>
<form method="post">
<fieldset>
<legend>Waitlist form</legend>
<?php foreach($errors as $e):?><div class="notice warning"><?=H::e($e)?></div><?php endforeach;?>
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Name<input required maxlength="120" name="name" value="<?=H::e($values['name'])?>"></label>
<label>Email<input required maxlength="190" type="email" name="email" value="<?=H::e($values['email'])?>"></label>

<fieldset>
<legend>I am interested as <span class="muted">(choose one or more)</span></legend>
<label><input type="checkbox" name="interest_type[]" value="seller" <?=in_array('seller',$selected,true)?'checked':''?>> Seller</label>
<label><input type="checkbox" name="interest_type[]" value="buyer" <?=in_array('buyer',$selected,true)?'checked':''?>> Buyer</label>
<label><input type="checkbox" name="interest_type[]" value="tester" <?=in_array('tester',$selected,true)?'checked':''?>> Tester</label>
</fieldset>

<label>Business name (optional)<input maxlength="190" name="business_name" value="<?=H::e($values['business_name'])?>"></label>
<input type="hidden" name="source" value="<?=H::e($values['source'])?>">
<div class="hp" aria-hidden="true"><label>Website<input tabindex="-1" autocomplete="off" name="website"></label></div>
<button class="btn">Join waitlist</button>
</fieldset>
</form>

<script>
(()=>{const boxes=[...document.querySelectorAll('input[name="interest_type[]"]')];if(!boxes.length)return;const sync=()=>{boxes[0].required=!boxes.some(b=>b.checked);};boxes.forEach(b=>b.addEventListener('change',sync));sync();})();
</script>
<?php endif;?>
</section>
