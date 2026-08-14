<h1>Bulk Upload Products</h1>

<div class="card">
    <h2>Create several product listings faster</h2>

    <p>
        Start by creating <strong>one complete product</strong>.
        Once Product 1 is finished, you can create as many additional products as you need
        using Product 1 as the starting template.
    </p>

    <ol>
        <li>Create your batch.</li>
        <li>Complete Product 1.</li>
        <li>Choose how many additional products you want.</li>
        <li>The shared information from Product 1 is copied into those new products.</li>
        <li>Add each product's own title, preview images and downloadable files.</li>
    </ol>
</div>

<form method="post" action="/seller/product-batches" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

    <h2>Start a New Batch</h2>

    <label>
        Batch name
        <input
            name="name"
            maxlength="190"
            placeholder="Example: Halloween PNG Collection"
        >
    </label>

    <p class="muted">
        This name is only for you. Customers will not see it.
    </p>

    <button>Start My Batch</button>
</form>

<h2>Saved Batches</h2>

<?php if (!$batches): ?>
    <div class="card empty">
        <strong>You don't have any saved batches yet.</strong>
    </div>
<?php endif; ?>

<?php foreach ($batches as $batch): ?>
    <article class="card">
        <h3><?=H::e($batch['name'])?></h3>

        <p>
            <strong><?=(int)$batch['product_count']?></strong> products
            · <?=(int)$batch['draft_count']?> still being worked on
            · <?=(int)$batch['submitted_count']?> pending review
        </p>

        <a class="btn" href="/seller/product-batch/<?=$batch['id']?>">
            Continue This Batch
        </a>
    </article>
<?php endforeach; ?>
