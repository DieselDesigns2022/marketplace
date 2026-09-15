<?php
$standardBriefFields = \App\Services\CustomDesignService::BRIEF_FIELDS;

$briefConfig = json_decode(
    (string)($service['brief_fields'] ?? '[]'),
    true
);

if (!is_array($briefConfig)) {
    $briefConfig = [];
}

$enabledBriefFields = [];
foreach ($briefConfig as $field) {
    $key = (string)($field['key'] ?? '');

    if (
        isset($standardBriefFields[$key]) &&
        !isset($enabledBriefFields[$key])
    ) {
        $enabledBriefFields[$key] = [
            'label' => $standardBriefFields[$key],
            'required' => !empty($field['required']),
        ];
    }
}
$licenses = $licenses ?? [];

$isOwnService = H::user()
    && (int)H::user()['id'] === (int)$service['seller_user_id'];

$postedLicenseKeys = array_map(
    'strval',
    (array)($_POST['license_type'] ?? [])
);

$globalLicenseTerms = str_replace(
    '\\n',
    "\n",
    'All licenses are non-exclusive and non-transferable. Purchasing a file gives you permission to use the file under the license purchased. It does not give you ownership of the design.\\n\\nAll designs remain the intellectual property of the original designer or seller.\\n\\nYou may not share, gift, trade, copy, upload, transfer, resell, modify for resale, or distribute the digital files unless the purchased license specifically allows it.\\n\\nYou may not claim the design as your own, copyright it, trademark it, register it, or use it as a logo or main brand identity.\\n\\nFiles must remain private and protected at all times.'
);
?>

<nav class="breadcrumbs">
    <a href="/">Home</a> /
    <a href="/browse">Browse</a> /
    <a href="/category/customs-personalized">Customs / Personalized</a> /
    <?=H::e($service['title'])?>
</nav>

<h1><?=H::e($service['title'])?></h1>

<div class="detail custom-design-detail">

    <div class="gallery custom-design-gallery">

        <?php if($images): ?>

            <div class="custom-design-main-preview">
                <img
                    id="custom-design-main-image"
                    src="<?=H::e($images[0]['image_path'])?>"
                    alt="<?=H::e($service['title'])?> preview"
                >
            </div>

            <?php if(count($images) > 1): ?>

                <div class="custom-design-preview-thumbs">

                    <?php foreach($images as $index => $image): ?>

                        <button
                            type="button"
                            class="custom-design-preview-thumb <?=$index === 0 ? 'active' : ''?>"
                            data-custom-preview="<?=H::e($image['image_path'])?>"
                            data-custom-preview-alt="<?=H::e($service['title'])?> preview <?=($index + 1)?>"
                            aria-label="View preview <?=($index + 1)?>"
                        >
                            <img
                                src="<?=H::e($image['image_path'])?>"
                                alt=""
                            >
                        </button>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php else: ?>

            <div class="thumb big">
                Preview images are not available for this Custom Design yet.
            </div>

        <?php endif; ?>

    </div>

    <aside class="card custom-design-summary">

        <p>
            by <strong><?=H::e($service['display_name'])?></strong>
        </p>

        <p>
            Category:
            <a href="/category/customs-personalized">
                Customs / Personalized
            </a>
        </p>

        <h2><?=H::money($service['price'])?></h2>

        <p>
            <?=$service['turnaround_days']?> day turnaround
            · <?=$service['included_revisions']?> revisions included
        </p>

        <div class="custom-design-contact">

            <p>
                <strong>Have a question about this Custom Design?</strong>
            </p>

            <?php if(H::user() && !$isOwnService): ?>

                <form
                    method="post"
                    action="/messages/start/custom-design/<?=(int)$service['id']?>"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <button class="btn secondary" type="submit">
                        Contact Seller
                    </button>
                </form>

            <?php elseif(!H::user()): ?>

                <a class="btn secondary" href="/login">
                    Contact Seller
                </a>

                <p class="help-text">
                    Sign in to message the seller before purchasing.
                </p>

            <?php else: ?>

                <p class="help-text">
                    This is your Custom Design listing.
                </p>

            <?php endif; ?>

        </div>


<div class="custom-design-purchase-options">
        <h2>Customize &amp; Order</h2>

<?php if(H::user() && !$isOwnService): ?>

        <form
            method="post"
            action="/custom-design/<?=H::e($service['slug'])?>/purchase"
            enctype="multipart/form-data"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::csrf()?>"
            >

            <h2>License</h2>

            <p>
                Personal use is included with the custom-design base price.
                Select any additional permissions you need.
            </p>

            <p>
                All licenses follow the marketplace global terms
                <span
                    class="license-help"
                    role="button"
                    tabindex="0"
                    aria-label="Global license terms"
                >
                    <span class="license-help-icon">?</span>
                    <span class="license-help-text"><?=H::e($globalLicenseTerms)?></span>
                </span>
            </p>

            <fieldset class="license-options" data-license-options>
                <legend>Select additional permissions</legend>

                <p class="help-text license-help-note">
                    Hover over ? for a quick preview or click ? to open the full license details.
                </p>

                <?php foreach($licenses as $license): ?>
                    <?php
                    $licenseKey = (string)$license['license_key'];

                    $checked = $licenseKey === 'personal'
                        || in_array(
                            $licenseKey,
                            $postedLicenseKeys,
                            true
                        );
                    ?>

                    <label>
                        <input
                            type="checkbox"
                            name="license_type[]"
                            value="<?=H::e($licenseKey)?>"
                            <?=$checked ? 'checked' : ''?>
                            <?=$licenseKey === 'personal'
                                ? 'disabled'
                                : ''?>
                        >

                        <?php if($licenseKey === 'personal'): ?>
                            <input
                                type="hidden"
                                name="license_type[]"
                                value="personal"
                            >
                        <?php endif; ?>

                        <strong><?=H::e($license['name'])?></strong>

                        <?php if($licenseKey === 'personal'): ?>
                            <span class="muted">included/free</span>
                        <?php else: ?>
                            <span class="muted">
                                +<?=H::money($license['price'])?>
                            </span>
                        <?php endif; ?>

                        <?php if(!empty($license['description'])): ?>
                            <span
                                class="license-help"
                                role="button"
                                tabindex="0"
                                aria-label="<?=H::e($license['name'])?> license details"
                            >
                                <span class="license-help-icon">?</span>
                                <span class="license-help-text"><?=H::e($license['description'])?></span>
                            </span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <p class="help-text">
                Your checkout total will be the custom-design base price
                plus any paid license add-ons you select.
            </p>

            <?php if(
                $enabledBriefFields ||
                $questions ||
                !empty($service['buyer_instructions'])
            ): ?>
                <h2>Your design brief</h2>
            <?php endif; ?>

            <?php foreach($enabledBriefFields as $key => $field): ?>
                <label>
                    <?=H::e($field['label'])?>
                    <?=$field['required'] ? ' *' : ''?>

                    <textarea
                        name="<?=H::e($key)?>"
                        <?=$field['required'] ? 'required' : ''?>
                    ><?=H::e($_POST[$key] ?? '')?></textarea>
                </label>
            <?php endforeach; ?>

            <?php if(!empty($service['buyer_instructions'])): ?>
                <div class="notice">
                    <?=nl2br(H::e($service['buyer_instructions']))?>
                </div>
            <?php endif; ?>

            <?php foreach($questions as $q): ?>
                <label>
                    <?=H::e($q['question_text'])?>
                    <?=$q['is_required'] ? ' *' : ''?>

                    <textarea
                        name="answers[<?=$q['id']?>]"
                        <?=$q['is_required'] ? 'required' : ''?>
                    ></textarea>
                </label>
            <?php endforeach; ?>

            <label>
                Reference files
                <span class="muted">
                    JPG, PNG, WEBP or PDF; 25MB each
                </span>

                <input
                    type="file"
                    name="references[]"
                    multiple
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                >
            </label>

            <button class="btn" type="submit">
                Continue to Secure Checkout
            </button>
        </form>

    <?php elseif($isOwnService): ?>

        <p class="help-text">
            This is your Custom Design listing. Sellers cannot purchase their own listings.
        </p>

    <?php else: ?>

        <p>
            <a href="/login">Sign in to request this design</a>.
        </p>

    <?php endif; ?>
</div>

    </aside>

</div>

<h2>Description</h2>

<p><?=nl2br(H::e($service['description']))?></p>

<?php foreach($errors as $e): ?>
    <p class="alert error"><?=H::e($e)?></p>
<?php endforeach; ?>


<script>
document.querySelectorAll('[data-custom-preview]').forEach(function(button) {
    button.addEventListener('click', function() {
        const main = document.getElementById('custom-design-main-image');

        if (!main) {
            return;
        }

        main.src = button.dataset.customPreview || '';
        main.alt = button.dataset.customPreviewAlt || '';

        document.querySelectorAll('[data-custom-preview]').forEach(function(item) {
            item.classList.remove('active');
        });

        button.classList.add('active');
    });
});
</script>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
