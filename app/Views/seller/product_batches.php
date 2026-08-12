<h1>Bulk Upload / Product Batches</h1>
<p class="muted">Create separate draft listings, reuse selected details, then edit and submit each listing independently.</p>
<form method="post" action="/seller/product-batches" class="form card"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><label>Batch name<input name="name" maxlength="190" placeholder="Summer collection"></label><label>Starting products<input type="number" name="product_count" min="2" max="50" value="3"></label><button>Create Draft Batch</button></form>
<?php if (!$batches): ?><div class="card empty">No saved batches yet.</div><?php endif; ?>
<?php foreach ($batches as $batch): ?><article class="card"><h2><?=H::e($batch['name'])?></h2><p><?=(int)$batch['product_count']?> products · <?=(int)$batch['draft_count']?> drafts · <?=(int)$batch['submitted_count']?> submitted</p><a class="btn" href="/seller/product-batch/<?=$batch['id']?>">Reopen Batch</a></article><?php endforeach; ?>
