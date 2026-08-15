<h1><?=H::e($batch['name'])?></h1>

<?php
$drafts = $submitted = $errors = $created = 0;

foreach ($products as $x) {
    $drafts += (int)($x['status'] === 'draft');
    $submitted += (int)($x['status'] === 'pending_review');
    $created += (int)in_array(
        $x['status'],
        ['approved','published'],
        true
    );
    $errors += (int)!empty($x['batch_errors']);
}
?>

<div class="card">
    <h2><?=$drafts ? 'Continue Editing Your Bulk Draft' : 'Bulk Products Created ✅'?></h2>

    <?php if ($drafts): ?>
        <p>
            This batch is still a draft. You can leave at any time and come back later.
            Your products will remain saved here until you finish them.
        </p>

        <p>
            Click <strong>Edit Product</strong> on any draft below to continue working on it.
        </p>
    <?php else: ?>
        <p>
            All products in this batch have finished the draft stage.
        </p>
    <?php endif; ?>

    <p>
        <?=$drafts?> drafts
        · <?=$submitted?> pending review
        · <?=$created?> published
        <?php if ($errors): ?>
            · <?=$errors?> needing attention
        <?php endif; ?>
    </p>
</div>

<form method="post">
    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <?php foreach ($products as $index => $p): ?>
        <article class="card">
            <h3>
                Product <?=$index + 1?>:
                <?=H::e($p['title'])?>
            </h3>

            <p>
                Status:
                <strong>
                    <?=H::e(
                        ucwords(
                            str_replace('_', ' ', $p['status'])
                        )
                    )?>
                </strong>
            </p>

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
                <p>
                    <span class="badge">
                        Ready to Submit
                    </span>
                </p>
            <?php endif; ?>

            <p>
                <a
                    class="btn"
                    href="/seller/product/<?=$p['id']?>"
                >
                    <?=$p['status'] === 'draft' ? 'Continue Editing This Product' : 'View / Edit Product'?>
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

                    If this product is flagged during the IP scan,
                    I confirm I have the legal right to sell it.
                </label>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if ($drafts): ?>
        <div class="card">
            <h2>Submit Finished Products</h2>

            <button
                name="action"
                value="submit_selected"
            >
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
    <?php endif; ?>
</form>

<?php
$isEntirelyDraft =
    count($products) > 0
    && $drafts === count($products);
?>

<?php if ($isEntirelyDraft): ?>
    <?php endif; ?>

<?php
$isEntirelyDraft =
    count($products) > 0
    && $drafts === count($products);
?>

<?php if ($isEntirelyDraft): ?>
    <?php endif; ?>


<div class="card">
    <h2>Delete This Bulk Batch</h2>

    <p class="help-text">
        This removes the saved bulk batch.
        Products that are still drafts will be permanently deleted.
        Products already submitted or published will remain in your shop.
    </p>

    <form
        method="post"
        action="/seller/product-batch/<?=$batch['id']?>"
        onsubmit="return confirm('Delete this bulk batch? Draft products will be permanently deleted. Submitted or published products will remain.');"
    >
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <input type="hidden" name="action" value="delete_batch">

        <button type="submit">
            Delete Bulk Batch
        </button>
    </form>
</div>

<p>
    <a
        class="btn"
        href="/seller/product-batches"
    >
        ← Back to Bulk Products
    </a>
</p>
