<?php
use App\Core\Helpers as H;
use App\Services\GrandOpeningSaleService as Sale;
$hasDates = !empty($event['starts_at']) && !empty($event['ends_at']);
$formatEventDate = static function(string $value): string {
    return (new \DateTimeImmutable($value,new \DateTimeZone('UTC')))
        ->setTimezone(new \DateTimeZone('America/Detroit'))
        ->format('M j, Y \a\t g:i A T');
};
?>
<h1>Grand Opening</h1>

<?php if($dateError): ?>
<div class="notice error" role="alert" id="date-save-error" tabindex="-1">
<strong>Dates not saved</strong>
<p><?=H::e($dateError)?></p>
</div>
<?php endif; ?>

<?php if($savedNotice): ?>
<div class="notice success" role="status" id="date-save-success" tabindex="-1">
Grand Opening dates saved successfully.
</div>
<?php endif; ?>

<section class="card" id="saved-event" aria-labelledby="saved-event-heading">
<h2 id="saved-event-heading">Saved event</h2>
<?php if($hasDates): ?>
<h3>Creative Moth Grand Opening</h3>
<p><strong>Starts:</strong> <?=H::e($formatEventDate($event['starts_at']))?></p>
<p><strong>Ends:</strong> <?=H::e($formatEventDate($event['ends_at']))?></p>
<p><strong>Status:</strong> <?=H::e(ucfirst(Sale::state($event)))?></p>
<?php if(empty($event['is_enabled'])): ?>
<p>Your dates are saved. The event has not been activated.</p>
<?php endif; ?>
<?php else: ?>
<p>No event dates have been saved yet. Enter the dates below to schedule the Grand Opening.</p>
<?php endif; ?>
</section>

<?php if(H::canAdmin('homepage.manage')): ?>
<form method="post" action="/admin/grand-opening" class="card form" id="grand-opening-dates">
<h2><?=$hasDates?'Edit event dates':'Set event dates'?></h2>
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<label>Start — Eastern time
<input type="datetime-local" name="starts_at" value="<?=H::e($formDates['starts_at'])?>" required>
</label>
<label>End — Eastern time
<input type="datetime-local" name="ends_at" value="<?=H::e($formDates['ends_at'])?>" required>
</label>
<p>The event must run at least seven days from the selected start time.</p>
<button type="submit">Save event dates</button>
</form>
<?php endif; ?>

<h2>Participating sellers (<?=count($saleSellers)?>)</h2>
<?php if(!$saleSellers): ?>
<div class="card"><p>No sellers have joined the Grand Opening Sale yet.</p></div>
<?php else: ?>
<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));max-width:1000px">
<?php foreach($saleSellers as $seller): ?>
<article class="card">
<h3><?=H::e($seller['display_name'])?></h3>
<p><strong><?=number_format((int)$seller['discount_basis_points']/100,2)?>% off</strong>
— <?=$seller['product_scope']==='shop'?'Entire shop':(int)$seller['selected_count'].' selected products'?></p>
<p>Store status: <?=H::e($seller['status'])?></p>
<a href="/store/<?=H::e($seller['store_slug'])?>">View store</a>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<script>
(() => {
 const feedback=document.getElementById('date-save-error')
     ||document.getElementById('date-save-success');
 if(feedback){
  feedback.focus({preventScroll:true});
  feedback.scrollIntoView({block:'center'});
 }
 const form=document.getElementById('grand-opening-dates');
 if(form){
  form.addEventListener('submit',()=>{
   const button=form.querySelector('button[type="submit"]');
   button.disabled=true;
   button.textContent='Saving dates…';
  });
 }
})();
</script>
