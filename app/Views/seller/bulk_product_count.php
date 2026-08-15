<h1>Bulk Products</h1>

<div class="card">
    <p><strong>Step 2 of 3 — How many products are you creating?</strong></p>

    <p>
        Your shared product information has been saved.
        Next, Asset Moth will walk you through each individual product.
    </p>
</div>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="notice error">
        <?=H::e($error)?>
    </div>
<?php endforeach; ?>

<form method="post" class="form card">
    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <label>
        Number of products
        <input
            type="number"
            name="product_count"
            min="2"
            max="50"
            value="<?=H::e((string)$count)?>"
            required
        >
    </label>

    <p class="help-text">
        Example: If you are uploading 5 designs, enter 5.
        You will review all 5 products one at a time.
    </p>

    <button>
        Next → Product 1
    </button>
</form>

<p>
    <a href="/seller/product-bulk/template">
        ← Back to Shared Information
    </a>
</p>
