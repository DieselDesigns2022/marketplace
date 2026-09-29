<?php
$standardBriefFields = \App\Services\CustomDesignService::BRIEF_FIELDS;

$briefFieldConfig = [];

if (array_key_exists('brief_fields_present', $_POST)) {
    foreach ($standardBriefFields as $key => $label) {
        if (!empty($_POST['brief_fields'][$key])) {
            $briefFieldConfig[$key] = [
                'required' => !empty($_POST['brief_required'][$key]),
                'allow_upload' => !empty($_POST['brief_allow_upload'][$key]),
                'upload_required' => !empty($_POST['brief_upload_required'][$key]),
            ];
        }
    }
} elseif ($service && !empty($service['brief_fields'])) {
    $savedBriefFields = json_decode(
        (string)$service['brief_fields'],
        true
    );

    if (is_array($savedBriefFields)) {
        foreach ($savedBriefFields as $field) {
            $key = (string)($field['key'] ?? '');

            if (isset($standardBriefFields[$key])) {
                $briefFieldConfig[$key] = [
                    'required' => !empty($field['required']),
                    'allow_upload' => !empty($field['allow_upload']),
                    'upload_required' => !empty($field['upload_required']),
                ];
            }
        }
    }
}

$configuredLicenses = [];

foreach (($serviceLicenses ?? []) as $license) {
    $configuredLicenses[$license['license_key']] = $license;
}

$postedLicenseEnabled = $_POST['license_enabled'] ?? null;
$customExisting = $configuredLicenses['custom'] ?? null;

$customLicenseEnabled = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    ? isset($_POST['custom_license_enabled'])
    : !empty($customExisting);

$customLicenseName = $_POST['custom_license_name']
    ?? ($customExisting['name'] ?? '');

$customLicensePrice = $_POST['custom_license_price']
    ?? ($customExisting['price'] ?? '0.00');

$customLicenseTerms = $_POST['custom_license_terms']
    ?? ($customExisting['description'] ?? '');

$postedQuestions = array_key_exists('questions', $_POST)
    ? (array)$_POST['questions']
    : null;

$postedRequired = (array)($_POST['question_required'] ?? []);
$postedAllowUpload = (array)($_POST['question_allow_upload'] ?? []);
$postedUploadRequired = (array)($_POST['question_upload_required'] ?? []);

if ($postedQuestions !== null) {
    $questionRows = [];
    foreach ($postedQuestions as $i => $text) {
        $questionRows[] = [
            'question_text' => (string)$text,
            'is_required' => !empty($postedRequired[$i]),
            'allow_upload' => !empty($postedAllowUpload[$i]),
            'upload_required' => !empty($postedUploadRequired[$i]),
        ];
    }
} else {
    $questionRows = $questions ?: [];
}

$selectedQuestionCount = isset($_POST['question_count'])
    ? max(0, min(20, (int)$_POST['question_count']))
    : count($questionRows);

$selectedQuestionCount = max(
    $selectedQuestionCount,
    count($questionRows)
);
?>
<section class="custom-service-editor">

    <div class="custom-service-heading">
        <div>
            <p class="eyebrow"><?= $service ? 'Edit service' : 'New service' ?></p>
            <h1><?= $service ? 'Manage Custom Design' : 'Create Custom Design' ?></h1>
            <p class="muted">
                Set up the service details buyers will see before placing a custom order.
            </p>
        </div>
    </div>

    <?php foreach($errors as $e): ?>
        <div class="notice error"><?=H::e($e)?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="custom-service-form">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

        <section class="card custom-form-section">
            <div class="custom-section-heading">
                <h2>Service Details</h2>
                <p>Tell buyers what you offer and how the custom order works.</p>
            </div>

            <div class="custom-form-grid">
                <label class="custom-field custom-field-full">
                    <span>Title</span>
                    <input
                        name="title"
                        required
                        maxlength="190"
                        value="<?=H::e($_POST['title'] ?? $service['title'] ?? '')?>"
                    >
                </label>

                <label class="custom-field custom-field-full">
                    <span>Description</span>
                    <textarea
                        name="description"
                        required
                        rows="6"
                    ><?=H::e($_POST['description'] ?? $service['description'] ?? '')?></textarea>
                </label>

                <label class="custom-field">
                    <span>Price</span>
                    <input
                        type="number"
                        min=".50"
                        step=".01"
                        name="price"
                        required
                        value="<?=H::e($_POST['price'] ?? $service['price'] ?? '')?>"
                    >
                </label>

                <div class="custom-field">
                    <span>Expected turnaround</span>

                    <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">

                        <label style="flex:1;min-width:120px">
                            <span class="muted">Minimum days</span>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                name="turnaround_min_days"
                                required
                                value="<?=H::e(
                                    $_POST['turnaround_min_days']
                                    ??$service['turnaround_min_days']
                                    ??$service['turnaround_days']
                                    ??''
                                )?>"
                            >
                        </label>

                        <span style="padding-bottom:10px">to</span>

                        <label style="flex:1;min-width:120px">
                            <span class="muted">Maximum days</span>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                name="turnaround_max_days"
                                required
                                value="<?=H::e(
                                    $_POST['turnaround_max_days']
                                    ??$service['turnaround_days']
                                    ??''
                                )?>"
                            >
                        </label>

                    </div>

                    <p class="help-text">
                        Example: 3 to 5 days.
                        Use the same number in both boxes for a single-day estimate.
                    </p>
                </div>

                <label class="custom-field">
                    <span>Included revisions</span>
                    <input
                        type="number"
                        min="0"
                        max="100"
                        name="included_revisions"
                        value="<?=H::e($_POST['included_revisions'] ?? $service['included_revisions'] ?? 0)?>"
                    >
                </label>

                <label class="custom-field custom-field-full">
                    <span>Buyer instructions</span>
                    <textarea
                        name="buyer_instructions"
                        rows="4"
                        placeholder="Anything the buyer should know before submitting their request."
                    ><?=H::e($_POST['buyer_instructions'] ?? $service['buyer_instructions'] ?? '')?></textarea>
                </label>
            </div>
        </section>

        <section class="card custom-form-section">
            <div class="custom-section-heading">
                <h2>Pricing and Licenses</h2>
                <p>
                    Personal use is included with the base custom-design price.
                    Enable any additional permissions you want to offer.
                    Add-on licenses may be free or have an additional price.
                </p>
            </div>

            <p class="help-text license-help-note">
                Hover over ? for a quick preview or click ? to open the full license details.
            </p>

            <table>
                <tr>
                    <th>Enabled</th>
                    <th>License type</th>
                    <th>Add-on price</th>
                </tr>

                <?php foreach(($licenseTypes ?? []) as $type): ?>
                    <?php
                    $key = $type['license_key'];
                    $existing = $configuredLicenses[$key] ?? null;

                    $enabled = $postedLicenseEnabled !== null
                        ? isset($postedLicenseEnabled[$key])
                        : ($key === 'personal' || !empty($existing));

                    $licensePrice = $key === 'personal'
                        ? '0.00'
                        : ($_POST['license_price'][$key]
                            ?? ($existing['price'] ?? '0.00'));

                    $licenseDescription =
                        (string)($type['description'] ?? '');
                    ?>

                    <tr>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="license_enabled[<?=H::e($key)?>]"
                                    value="1"
                                    <?=$enabled ? 'checked' : ''?>
                                    <?=$key === 'personal'
                                        ? 'disabled'
                                        : ''?>
                                >

                                <?=$key === 'personal'
                                    ? 'Always included'
                                    : 'Enable'?>
                            </label>

                            <?php if($key === 'personal'): ?>
                                <input
                                    type="hidden"
                                    name="license_enabled[personal]"
                                    value="1"
                                >
                            <?php endif; ?>
                        </td>

                        <td>
                            <strong><?=H::e($type['name'])?></strong>

                            <?php if($licenseDescription !== ''): ?>
                                <span
                                    class="license-help"
                                    role="button"
                                    tabindex="0"
                                    aria-label="<?=H::e($type['name'])?> license details"
                                >
                                    <span class="license-help-icon">?</span>
                                    <span class="license-help-text"><?=H::e($licenseDescription)?></span>
                                </span>
                            <?php endif; ?>

                            <br>

                            <span class="muted">
                                <?=H::e($key)?>
                                <?=$key === 'personal'
                                    ? ' · included/free'
                                    : ' · optional add-on'?>
                            </span>
                        </td>

                        <td>
                            <?php if($key === 'personal'): ?>
                                <span class="muted">$0.00 included</span>
                            <?php else: ?>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="license_price[<?=H::e($key)?>]"
                                    value="<?=H::e($licensePrice)?>"
                                >
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="card">
                <label class="checkbox-option">
                    <input
                        type="checkbox"
                        name="custom_license_enabled"
                        value="1"
                        <?=$customLicenseEnabled ? 'checked' : ''?>
                    >
                    <strong>Add a Custom License</strong>
                </label>

                <p class="help-text">
                    Use this if the standard licenses do not cover the permission you want to offer.
                </p>

                <label class="custom-field">
                    <span>Custom license name</span>
                    <input
                        name="custom_license_name"
                        maxlength="120"
                        value="<?=H::e($customLicenseName)?>"
                        placeholder="Example: Exclusive Business Use"
                    >
                </label>

                <label class="custom-field">
                    <span>Custom license add-on price</span>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="custom_license_price"
                        value="<?=H::e($customLicensePrice)?>"
                    >
                </label>

                <label class="custom-field">
                    <span>Custom license terms</span>
                    <textarea
                        name="custom_license_terms"
                        rows="7"
                        maxlength="10000"
                        placeholder="Enter the complete terms for this custom license."
                    ><?=H::e($customLicenseTerms)?></textarea>
                </label>
            </div>
        </section>

        <section class="card custom-form-section">
            <div class="custom-section-heading">
                <h2>Standard Buyer Brief Fields</h2>
                <p>
                    Choose which built-in fields buyers should see.
                    Each included field can be required or optional.
                </p>
            </div>

            <input
                type="hidden"
                name="brief_fields_present"
                value="1"
            >

            <div class="brief-field-list">
                <?php foreach($standardBriefFields as $key => $label): ?>
                    <?php
                    $included = isset($briefFieldConfig[$key]);
                    $required = $included
                        && !empty($briefFieldConfig[$key]['required']);

                    $allowUpload = $included
                        && !empty($briefFieldConfig[$key]['allow_upload']);

                    $uploadRequired = $allowUpload
                        && !empty($briefFieldConfig[$key]['upload_required']);
                    ?>

                    <div class="brief-field-row">
                        <label class="brief-field-option">
                            <input
                                type="checkbox"
                                class="brief-field-include"
                                name="brief_fields[<?=H::e($key)?>]"
                                value="1"
                                <?=$included ? 'checked' : ''?>
                            >
                            <span><?=H::e($label)?></span>
                        </label>

                        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">

                            <label class="brief-field-required">
                                <input
                                    type="checkbox"
                                    class="brief-required-checkbox"
                                    name="brief_required[<?=H::e($key)?>]"
                                    value="1"
                                    <?=$required ? 'checked' : ''?>
                                    <?=$included ? '' : 'disabled'?>
                                >
                                <span>Require text</span>
                            </label>

                            <label class="brief-field-required">
                                <input
                                    type="checkbox"
                                    class="brief-upload-checkbox"
                                    name="brief_allow_upload[<?=H::e($key)?>]"
                                    value="1"
                                    <?=$allowUpload ? 'checked' : ''?>
                                    <?=$included ? '' : 'disabled'?>
                                >
                                <span>Allow file upload</span>
                            </label>

                            <label class="brief-field-required">
                                <input
                                    type="checkbox"
                                    class="brief-upload-required-checkbox"
                                    name="brief_upload_required[<?=H::e($key)?>]"
                                    value="1"
                                    <?=$uploadRequired ? 'checked' : ''?>
                                    <?=$allowUpload ? '' : 'disabled'?>
                                >
                                <span>Require upload</span>
                            </label>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card custom-form-section">
            <div class="custom-section-heading">
                <h2>Buyer Questions</h2>
                <p>
                    Choose how many additional questions buyers must answer.
                    You can add anywhere from 0 to 20.
                </p>
            </div>

            <label class="custom-field question-count-field">
                <span>Number of buyer questions</span>
                <select id="question-count" name="question_count">
                    <?php for($i = 0; $i <= 20; $i++): ?>
                        <option
                            value="<?=$i?>"
                            <?=$selectedQuestionCount === $i ? 'selected' : ''?>
                        >
                            <?=$i?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>

            <div
                id="questions"
                class="buyer-question-list"
                data-existing='<?=H::e(json_encode($questionRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT))?>'
            ></div>
        </section>

        <section class="card custom-form-section">
            <div class="custom-section-heading">
                <h2>Example Images</h2>
                <p>
                    Add preview images that show buyers examples of the type of custom work you offer.
                </p>
            </div>

            <?php
            $extraProtectionChecked =
                ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
                    ? isset($_POST['extra_protection_watermark'])
                    : !empty($service['extra_protection_watermark']);
            ?>

            <label class="checkbox-option">
                <input
                    type="checkbox"
                    name="extra_protection_watermark"
                    value="1"
                    <?=$extraProtectionChecked ? 'checked' : ''?>
                >
                <strong>Extra preview protection</strong>
            </label>

            <p class="help-text">
                Adds the transparent full-image protection layer at 15%.
                The standard centered Creative Moth watermark remains on top.
                Final buyer files are never altered.
            </p>

            <?php if($images): ?>
                <div class="existing-custom-images">
                    <?php foreach($images as $image): ?>
                        <label class="custom-image-card">
                            <img
                                class="product-preview"
                                src="<?=H::e($image['image_path'])?>"
                                alt="Custom design example"
                            >
                            <span class="custom-remove-image">
                                <input
                                    type="checkbox"
                                    name="remove_images[]"
                                    value="<?=$image['id']?>"
                                >
                                Remove image
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label class="custom-field custom-upload-field">
                <span>Add example images</span>
                <input
                    type="file"
                    name="examples[]"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp"
                >
                <small>JPG, PNG or WEBP.</small>
            </label>
        </section>

        <section class="card custom-form-section custom-availability-section">
            <label class="custom-toggle">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?=!$service || $service['is_active'] ? 'checked' : ''?>
                >
                <span>
                    <strong>Active and available</strong>
                    <small>Buyers can see and purchase this custom design while enabled.</small>
                </span>
            </label>
        </section>

        <div class="custom-form-actions">

            <button
                class="btn"
                type="submit"
                name="form_action"
                value="save"
            >
                <?= $service ? 'Save Changes' : 'Publish Custom Design' ?>
            </button>

            <button
                class="btn secondary"
                type="submit"
                name="form_action"
                value="draft"
                formnovalidate
            >
                Save as Draft
            </button>

            <a class="btn secondary" href="/seller/custom-designs">
                Cancel
            </a>

            <?php if($service): ?>
                <button
                    class="btn custom-delete-button"
                    type="submit"
                    name="form_action"
                    value="delete"
                    formnovalidate
                    onclick="return confirm('Permanently delete this Custom Design? This cannot be undone.');"
                >
                    Delete Custom Design
                </button>
            <?php endif; ?>

        </div>
    </form>
</section>

<script>
(() => {
    const countSelect = document.getElementById('question-count');
    const container = document.getElementById('questions');

    let existing = [];

    try {
        existing = JSON.parse(container.dataset.existing || '[]');
    } catch (e) {
        existing = [];
    }

    function renderQuestions() {
        const count = Math.max(
            0,
            Math.min(20, parseInt(countSelect.value || '0', 10))
        );

        const current = Array.from(
            container.querySelectorAll('.buyer-question-card')
        ).map((card) => ({
            question_text:
                card.querySelector('input[type="text"]')?.value || '',
            is_required:
                card.querySelector('.question-required-checkbox')?.checked || false,
            allow_upload:
                card.querySelector('.question-upload-checkbox')?.checked || false,
            upload_required:
                card.querySelector('.question-upload-required-checkbox')?.checked || false
        }));

        const values = current.length ? current : existing;

        container.innerHTML = '';

        if (count === 0) {
            const empty = document.createElement('div');
            empty.className = 'buyer-question-empty';
            empty.textContent =
                'No additional buyer questions will be included.';
            container.appendChild(empty);
            return;
        }

        for (let i = 0; i < count; i++) {
            const saved = values[i] || {
                question_text: '',
                is_required: false,
                allow_upload: false,
                upload_required: false
            };

            const card = document.createElement('div');
            card.className = 'buyer-question-card';

            const top = document.createElement('div');
            top.className = 'buyer-question-card-heading';

            const title = document.createElement('strong');
            title.textContent = 'Question ' + (i + 1);

            const requiredLabel = document.createElement('label');
            requiredLabel.className = 'question-required-toggle';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'question-required-checkbox';
            checkbox.name = 'question_required[' + i + ']';
            checkbox.value = '1';
            checkbox.checked = Boolean(saved.is_required);

            const requiredText = document.createElement('span');
            requiredText.textContent = 'Required';

            requiredLabel.appendChild(checkbox);
            requiredLabel.appendChild(requiredText);

            requiredText.textContent = 'Require text';

            const uploadLabel = document.createElement('label');
            uploadLabel.className = 'question-required-toggle';

            const uploadCheckbox = document.createElement('input');
            uploadCheckbox.type = 'checkbox';
            uploadCheckbox.className = 'question-upload-checkbox';
            uploadCheckbox.name = 'question_allow_upload[' + i + ']';
            uploadCheckbox.value = '1';
            uploadCheckbox.checked = Boolean(saved.allow_upload);

            const uploadText = document.createElement('span');
            uploadText.textContent = 'Allow file upload';

            uploadLabel.appendChild(uploadCheckbox);
            uploadLabel.appendChild(uploadText);

            const uploadRequiredLabel = document.createElement('label');
            uploadRequiredLabel.className = 'question-required-toggle';

            const uploadRequiredCheckbox = document.createElement('input');
            uploadRequiredCheckbox.type = 'checkbox';
            uploadRequiredCheckbox.className = 'question-upload-required-checkbox';
            uploadRequiredCheckbox.name = 'question_upload_required[' + i + ']';
            uploadRequiredCheckbox.value = '1';
            uploadRequiredCheckbox.checked = Boolean(saved.upload_required);
            uploadRequiredCheckbox.disabled = !uploadCheckbox.checked;

            const uploadRequiredText = document.createElement('span');
            uploadRequiredText.textContent = 'Require upload';

            uploadRequiredLabel.appendChild(uploadRequiredCheckbox);
            uploadRequiredLabel.appendChild(uploadRequiredText);

            uploadCheckbox.addEventListener('change', () => {
                uploadRequiredCheckbox.disabled = !uploadCheckbox.checked;

                if (!uploadCheckbox.checked) {
                    uploadRequiredCheckbox.checked = false;
                }
            });

            const controls = document.createElement('div');
            controls.style.display = 'flex';
            controls.style.gap = '14px';
            controls.style.flexWrap = 'wrap';

            controls.appendChild(requiredLabel);
            controls.appendChild(uploadLabel);
            controls.appendChild(uploadRequiredLabel);

            top.appendChild(title);
            top.appendChild(controls);

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'questions[' + i + ']';
            input.maxLength = 500;
            input.required = true;
            input.placeholder = 'Enter the question buyers should answer';
            input.value = saved.question_text || '';

            card.appendChild(top);
            card.appendChild(input);
            container.appendChild(card);
        }

        existing = [];
    }

    countSelect.addEventListener('change', renderQuestions);
    renderQuestions();

    document.querySelectorAll('.brief-field-row').forEach((row) => {
        const include = row.querySelector('.brief-field-include');
        const required = row.querySelector('.brief-required-checkbox');
        const upload = row.querySelector('.brief-upload-checkbox');
        const uploadRequired = row.querySelector('.brief-upload-required-checkbox');

        const syncOptions = () => {
            required.disabled = !include.checked;
            upload.disabled = !include.checked;
            uploadRequired.disabled = !include.checked || !upload.checked;

            if (!include.checked) {
                required.checked = false;
                upload.checked = false;
                uploadRequired.checked = false;
            }

            if (!upload.checked) {
                uploadRequired.checked = false;
            }
        };

        include.addEventListener('change', syncOptions);
        upload.addEventListener('change', syncOptions);
        syncOptions();
    });
})();
</script>

<?php require __DIR__.'/../partials/license_help_modal.php'; ?>
