<h1>Import Products from CSV</h1>
<p>Upload product-listing metadata only. Imports are always editable drafts and are never submitted or published automatically.</p>
<?php foreach($errors as $error): ?><div class="alert error"><?=H::e($error)?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card">
 <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
 <label for="source_platform">CSV source</label>
 <select id="source_platform" name="source_platform" required><option value="">Choose a platform</option><?php foreach($sources as $key=>$label): ?><option value="<?=H::e($key)?>"><?=H::e($label)?></option><?php endforeach; ?></select>
 <label for="csv_file">Product CSV file (50 MB maximum)</label><input id="csv_file" type="file" name="csv_file" accept=".csv,text/csv" required>
 <label><input type="checkbox" name="review_mapping" value="1"> Review or override column mapping before preview</label>
 <button>Upload and preview</button>
</form>
<p><a href="/seller/products/import/payhip-template">Download the Asset Moth Payhip CSV template</a> · <a href="/seller/products/import/history">View import history</a></p>
<p class="muted">Payhip does not provide a standard native product-catalog export. Use our template or map equivalent columns from a product-related CSV.</p>
<p class="muted">Wix native store CSV exports do not include digital source products or their downloadable files. Asset Moth can only import listing data present in the CSV and never retrieves source-platform download files.</p>
