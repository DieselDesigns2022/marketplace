<h1>Bulk Product Upload</h1>

<div class="card">
    <h2>Create several products without starting over each time</h2>

    <p>
        Enter the information your products have in common once.
        Then Asset Moth will walk you through each product one at a time
        with that information already filled in.
    </p>

    <ol>
        <li>Enter your shared product information.</li>
        <li>Tell us how many products you're creating.</li>
        <li>Review Product 1, make any individual changes, upload its images/files, then click Next.</li>
        <li>Repeat for Product 2, Product 3, and so on.</li>
        <li>Review the completed batch and submit the products that are ready.</li>
    </ol>

    <a
        class="btn"
        href="/seller/product-bulk/template?reset=1"
    >
        Create Bulk Products
    </a>
</div>

<h2>Saved Bulk Batches</h2>

<?php if (!$batches): ?>
    <div class="card empty">
        You don't have any saved bulk batches yet.
    </div>
<?php endif; ?>

<?php foreach ($batches as $batch): ?>
    <article class="card">
        <h3><?=H::e($batch['name'])?></h3>

        <p>
            <strong><?=(int)$batch['product_count']?></strong> products
            · <?=(int)$batch['draft_count']?> drafts
            · <?=(int)$batch['submitted_count']?> pending review
        </p>

        <a
            class="btn"
            href="/seller/product-batch/<?=$batch['id']?>"
        >
            Continue Editing Bulk Draft
        </a>

        <form
            method="post"
            action="/seller/product-batch/<?=$batch['id']?>"
            class="inline"
            onsubmit="return confirm('Delete this bulk batch? Draft products inside it will be permanently deleted. Submitted or published products will remain in your shop.');"
        >
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
            <input type="hidden" name="action" value="delete_batch">

            <button type="submit">
                Delete Bulk Batch
            </button>
        </form>

        <?php
        $isEntirelyDraft =
            (int)$batch['product_count'] > 0
            && (int)$batch['draft_count'] === (int)$batch['product_count'];
        ?>

        <?php if ($isEntirelyDraft): ?>
            <form
                method="post"
                action="/seller/product-batch/<?=$batch['id']?>"
                class="inline"
                onsubmit="return confirm('Permanently delete this entire bulk draft and all products inside it? This cannot be undone.');"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?=H::csrf()?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="delete_batch"
                >

                <button type="submit">
                    Delete Bulk Draft
                </button>
            </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
