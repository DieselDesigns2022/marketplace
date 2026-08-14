<h1><?=H::e($batch['name'])?></h1>

<?php
$drafts = $submitted = $errors = $created = 0;

foreach ($products as $x) {
    $drafts += (int)($x['status'] === 'draft');
    $submitted += (int)($x['status'] === 'pending_review');
    $created += (int)in_array($x['status'], ['approved','published'], true);
    $errors += (int)!empty($x['batch_errors']);
}

$template = $products[0] ?? null;

$labels = [
    'short_description' => 'Short description',
    'description' => 'Full description',
    'price' => 'Base price',
    'category_id' => 'Category',
    'tags' => 'Tags / keywords',
    'ai_disclosure' => 'AI disclosure',
    'licenses' => 'Licenses, paid license prices, POD & permissions',
    'seo_title' => 'SEO title',
    'seo_description' => 'SEO description',
    'fulfillment_type' => 'Fulfillment type',
    'manual_delivery_instructions' => 'Manual delivery instructions',
    'is_hand_drawn' => 'Hand-drawn disclosure',
];
?>

<div class="card">
    <strong>Batch Progress</strong>

    <p>
        <?=$drafts?> still being worked on
        · <?=$submitted?> pending review
        · <?=$created?> published
        <?php if ($errors): ?>
            · <?=$errors?> with something that still needs fixed
        <?php endif; ?>
    </p>
</div>

<h2>Step 1 — Finish Product 1</h2>

<?php if ($template): ?>
    <div class="card">
        <h3>Product 1: <?=H::e($template['title'])?></h3>

        <p>
            Product 1 is your starting template.
            Fill this product out with the information your other products will share.
        </p>

        <p>
            <strong>Don't worry about copying anything yet.</strong>
            Finish Product 1 first, then come back here.
        </p>

        <?php if (!empty($template['batch_errors'])): ?>
            <div class="notice error">
                <strong>Product 1 still needs:</strong>
                <ul>
                    <?php foreach ($template['batch_errors'] as $error): ?>
                        <li><?=H::e($error)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p><span class="badge">Product 1 is ready</span></p>
        <?php endif; ?>

        <a class="btn" href="/seller/product/<?=$template['id']?>">
            Edit Product 1
        </a>
    </div>
<?php endif; ?>

<h2>Step 2 — Create Your Other Products</h2>

<div class="card">
    <p>
        Once Product 1 is set up, tell us how many <strong>additional products</strong>
        you want to create.
    </p>

    <p>
        The new products will automatically start with the shared information you choose below.
        You will then customize each product separately.
    </p>

    <p>
        <strong>These are NEVER copied:</strong>
        product title, product URL, preview images and downloadable files.
    </p>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <input type="hidden" name="action" value="create_copies">

        <label>
            How many MORE products do you want to create?
            <input
                type="number"
                name="copy_count"
                min="1"
                max="49"
                value="5"
                required
            >
        </label>

        <details>
            <summary><strong>Choose what information gets copied</strong></summary>

            <p class="muted">
                These shared fields are selected by default. Uncheck anything you want to enter separately.
            </p>

            <?php foreach (array_merge($copyFields, ['tags','licenses']) as $field): ?>
                <label class="checkbox-option">
                    <input
                        type="checkbox"
                        name="copy_fields[]"
                        value="<?=H::e($field)?>"
                        checked
                    >
                    <?=H::e($labels[$field] ?? $field)?>
                </label>
            <?php endforeach; ?>
        </details>

        <button
            onclick="return confirm('Create these new products using Product 1 as the starting template?');"
        >
            Create Products From Product 1
        </button>
    </form>
</div>

<?php if (count($products) > 1): ?>
    <h2>Step 3 — Customize Your Products</h2>

    <p class="muted">
        The shared information is already there. Now give each product its own title,
        preview images, downloadable files and anything else that should be different.
    </p>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

        <?php foreach ($products as $index => $p): ?>
            <article class="card">
                <h3>Product <?=$index + 1?>: <?=H::e($p['title'])?></h3>

                <p>
                    Status:
                    <strong><?=H::e(ucwords(str_replace('_', ' ', $p['status'])))?></strong>
                </p>

                <?php if ($index > 0): ?>
                    <p><span class="badge">Created from Product 1</span></p>
                <?php endif; ?>

                <?php if (!empty($p['batch_errors'])): ?>
                    <div class="notice error">
                        <strong>This product still needs:</strong>

                        <ul>
                            <?php foreach ($p['batch_errors'] as $error): ?>
                                <li><?=H::e($error)?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p><span class="badge">Ready to Submit</span></p>
                <?php endif; ?>

                <p>
                    <a class="btn" href="/seller/product/<?=$p['id']?>">
                        Edit Product <?=$index + 1?>
                    </a>
                </p>

                <?php if ($p['status'] === 'draft'): ?>
                    <label class="checkbox-option">
                        <input
                            type="checkbox"
                            name="products[]"
                            value="<?=$p['id']?>"
                        >
                        Select this product for submission
                    </label>

                    <label class="checkbox-option">
                        <input
                            type="checkbox"
                            name="ip_rights_confirmation[]"
                            value="<?=$p['id']?>"
                        >
                        If this product is flagged during the IP scan, I confirm I have the legal right to sell it.
                    </label>

                    <?php if ($index > 0): ?>
                        <button
                            type="button"
                            onclick="if(confirm('Permanently remove this draft product and its uploads?')) document.getElementById('remove-<?=$p['id']?>').requestSubmit();"
                        >
                            Remove This Draft
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <div class="card">
            <h2>Step 4 — Submit Finished Products</h2>

            <p>
                Submit only the products you selected, or submit every product that's ready.
                Anything incomplete stays as a draft so you can finish it later.
            </p>

            <button name="action" value="submit_selected">
                Submit My Selected Products
            </button>

            <button
                name="action"
                value="submit_all"
                onclick="return confirm('Submit every product in this batch that is ready?');"
            >
                Submit Every Product That's Ready
            </button>
        </div>
    </form>
<?php endif; ?>

<p>
    <a class="btn" href="/seller/product-batches">
        ← Back to My Batches
    </a>
</p>

<?php foreach ($products as $p): ?>
    <?php if ($p['status'] === 'draft'): ?>
        <form method="post" id="remove-<?=$p['id']?>">
            <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="product_id" value="<?=$p['id']?>">
        </form>
    <?php endif; ?>
<?php endforeach; ?>
