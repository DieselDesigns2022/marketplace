<?php
$isTemplate = ($bulkMode ?? '') === 'template';

$title = $isTemplate
    ? 'Bulk Products — Shared Information'
    : 'Product ' . (int)$step . ' of ' . (int)$total;

$licenseMap = $configuredLicenses ?? [];
?>

<h1><?=H::e($title)?></h1>

<?php if ($isTemplate): ?>
    <div class="card">
        <p><strong>Step 1 of 3 — Enter the information your products have in common.</strong></p>

        <p>
            Everything entered here will already be filled in when you review
            Product 1, Product 2, Product 3, and the rest.
        </p>

        <p>
            <strong>Images and downloadable files are added on each individual product screen</strong>
            because those normally change from product to product.
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <p>
            <strong>Product <?=$step?> of <?=$total?></strong>
        </p>

        <p>
            The information from your first screen is already filled in below.
            Change only what is different for this product, upload this product's
            preview image(s) and file(s), then click Next.
        </p>
    </div>
<?php endif; ?>

<?php foreach ($errors ?? [] as $error): ?>
    <div class="notice error">
        <?=H::e($error)?>
    </div>
<?php endforeach; ?>

<form
    method="post"
    enctype="multipart/form-data"
    class="form card"
>
    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <h2>Basic Information</h2>

    <label>
        Product Title
        <input
            name="title"
            required
            maxlength="190"
            value="<?=H::e($_POST['title'] ?? $values['title'] ?? '')?>"
            data-slug-source
            data-character-counter
        >
    </label>

    <?php if ($isTemplate): ?>
        <p class="help-text">
            This title will be used as the starting title on every product.
            You can change it on each individual product screen.
        </p>
    <?php endif; ?>

    <label>
        Short Description
        <textarea name="short_description"><?=H::e($_POST['short_description'] ?? $values['short_description'] ?? '')?></textarea>
    </label>

    <label>
        Full Description
        <textarea name="description" required><?=H::e($_POST['description'] ?? $values['description'] ?? '')?></textarea>
    </label>

    <?php if (!$isTemplate): ?>
        <h2>Product Preview Images</h2>

        <p class="help-text">
            Upload the preview image(s) for Product <?=$step?>.
            These are not copied between products.
        </p>

        <label>
            Upload preview images
            <input
                type="file"
                name="preview_images[]"
                multiple
                accept=".jpg,.jpeg,.png,.webp"
                data-preview-images
                required
            >
        </label>

        <div data-preview-alt-fields>
            <p class="muted">
                Select preview images to add separate alt text for each image.
            </p>
        </div>
    <?php endif; ?>

    <h2>Fulfillment</h2>

    <?php
    $fulfillment =
        $_POST['fulfillment_type']
        ?? $values['fulfillment_type']
        ?? 'downloadable';
    ?>

    <label>
        Fulfillment type
        <select name="fulfillment_type">
            <option
                value="downloadable"
                <?=$fulfillment === 'downloadable' ? 'selected' : ''?>
            >
                Downloadable Product
            </option>

            <option
                value="google_drive"
                <?=$fulfillment === 'google_drive' ? 'selected' : ''?>
            >
                Google Drive / Manual Delivery Product
            </option>
        </select>
    </label>

    <label>
        Manual delivery instructions
        <textarea name="manual_delivery_instructions"><?=H::e($_POST['manual_delivery_instructions'] ?? $values['manual_delivery_instructions'] ?? '')?></textarea>
    </label>

    <?php if (!$isTemplate): ?>
        <h2>Product Files</h2>

        <p class="help-text">
            Upload the protected downloadable file(s) for Product <?=$step?>.
            These are never copied to another product.
        </p>

        <label>
            Protected downloadable files
            <input
                type="file"
                name="product_files[]"
                multiple
            >
        </label>
    <?php endif; ?>

    <h2>Pricing and Licenses</h2>

    <label>
        Base Price
        <input
            type="number"
            step="0.01"
            min="0"
            name="price"
            value="<?=H::e($_POST['price'] ?? $values['price'] ?? '5.00')?>"
        >
    </label>

    <p class="help-text">
        Your normal seller license settings and preset prices are available here.
    </p>

    <table>
        <tr>
            <th>Enabled</th>
            <th>License type</th>
            <th>Add-on price</th>
        </tr>

        <?php foreach ($licenseTypes as $type):
            $key = $type['license_key'];

            $existing = $licenseMap[$key] ?? null;

            $postedEnabled = $_POST['license_enabled'] ?? null;

            $enabled = $postedEnabled !== null
                ? isset($postedEnabled[$key])
                : (
                    $key === 'personal'
                    || $existing !== null
                );

            $price = $key === 'personal'
                ? '0.00'
                : (
                    $_POST['license_price'][$key]
                    ?? $existing['price']
                    ?? '0.00'
                );

            $description =
                $_POST['license_description'][$key]
                ?? $existing['description']
                ?? $type['description']
                ?? '';
        ?>
            <tr>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="license_enabled[<?=H::e($key)?>]"
                            value="1"
                            <?=$enabled ? 'checked' : ''?>
                            <?=$key === 'personal' ? 'disabled' : ''?>
                        >

                        <?=$key === 'personal'
                            ? 'Always included'
                            : 'Enable'
                        ?>
                    </label>

                    <?php if ($key === 'personal'): ?>
                        <input
                            type="hidden"
                            name="license_enabled[personal]"
                            value="1"
                        >
                    <?php endif; ?>
                </td>

                <td>
                    <strong><?=H::e($type['name'])?></strong>

                    <br>

                    <span class="muted">
                        <?=H::e($key)?>
                        <?=$key === 'personal'
                            ? ' · included/free'
                            : ' · optional add-on'
                        ?>
                    </span>
                </td>

                <td>
                    <?php if ($key === 'personal'): ?>
                        <span class="muted">$0.00 included</span>

                        <input
                            type="hidden"
                            name="license_price[personal]"
                            value="0.00"
                        >
                    <?php else: ?>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="license_price[<?=H::e($key)?>]"
                            value="<?=H::e((string)$price)?>"
                        >
                    <?php endif; ?>

                    <input
                        type="hidden"
                        name="license_description[<?=H::e($key)?>]"
                        value="<?=H::e((string)$description)?>"
                    >
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p>
        Digital Resale: always prohibited.
    </p>

    <h2>Product Details</h2>

    <label>
        Category
        <select name="category_id" required>
            <option value="">Choose a category</option>

            <?php foreach ($cats as $c):
                $selected =
                    (string)(
                        $_POST['category_id']
                        ?? $values['category_id']
                        ?? ''
                    )
                    ===
                    (string)$c['id'];
            ?>
                <option
                    value="<?=$c['id']?>"
                    <?=$selected ? 'selected' : ''?>
                >
                    <?=H::e($c['name'])?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Tags
        <input
            name="tags"
            value="<?=H::e($_POST['tags'] ?? $values['tags'] ?? '')?>"
        >
    </label>

    <label>
        AI Disclosure
        <select name="ai_disclosure" required>
            <?php
            $currentAi =
                $_POST['ai_disclosure']
                ?? $values['ai_disclosure']
                ?? 'No AI Used';

            foreach (
                ['No AI Used', 'AI Assisted', 'AI Generated']
                as $ai
            ):
            ?>
                <option
                    value="<?=H::e($ai)?>"
                    <?=$currentAi === $ai ? 'selected' : ''?>
                >
                    <?=H::e($ai)?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <?php
    $handDrawnChecked =
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ? isset($_POST['is_hand_drawn'])
        : !empty($values['is_hand_drawn']);
    ?>

    <label class="checkbox-option">
        <input
            type="checkbox"
            name="is_hand_drawn"
            value="1"
            <?=$handDrawnChecked ? 'checked' : ''?>
        >

        <strong>✏️ Hand Drawn</strong>
    </label>

    <details class="advanced-panel">
        <summary>Advanced SEO (optional)</summary>

        <p class="help-text">
            Leave these blank to use the product title and short description automatically.
        </p>

        <label>
            SEO Title
            <input
                name="seo_title"
                maxlength="70"
                value="<?=H::e($_POST['seo_title'] ?? $values['seo_title'] ?? '')?>"
                data-character-counter
            >
        </label>

        <label>
            SEO Description
            <textarea
                name="seo_description"
                maxlength="170"
                data-character-counter
            ><?=H::e($_POST['seo_description'] ?? $values['seo_description'] ?? '')?></textarea>
        </label>
    </details>

    <?php if ($isTemplate): ?>
        <button>
            Next → How Many Products?
        </button>
    <?php elseif ($step < $total): ?>
        <button>
            Next → Product <?=$step + 1?>
        </button>
    <?php else: ?>
        <button>
            Finish Bulk Products
        </button>
    <?php endif; ?>
</form>

<?php if (!$isTemplate): ?>
    <p class="help-text">
        Product <?=$step?> will not be created until you click Next.
        Asset Moth does not create blank product listings ahead of you.
    </p>
<?php endif; ?>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
