<h1>Importing products</h1>
<div class="card" data-import-progress data-process-url="/seller/products/import/<?=(int)$run['id']?>/process" data-summary-url="/seller/products/import/<?=(int)$run['id']?>/summary" data-csrf="<?=H::e(H::csrf())?>">
 <p><strong data-import-count><?=number_format((int)$processed)?> of <?=number_format((int)$run['selected_count'])?> products</strong></p>
 <progress value="<?=(int)$processed?>" max="<?=max(1,(int)$run['selected_count'])?>" aria-label="CSV import progress"></progress>
 <p class="muted" data-import-message>Creating draft products. You can safely reload this page to resume.</p>
</div>
