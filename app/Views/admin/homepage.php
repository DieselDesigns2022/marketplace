<h1>Homepage Management</h1>

<p class="muted">
    Add homepage features below. Drag existing items into the order you want,
    then save that section.
</p>

<form method="post" class="card form homepage-feature-add">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <input type="hidden" name="action" value="add">

    <label>
        Feature
        <select name="feature_target" required>
            <option value="">Choose a product, designer, or category</option>

            <?php if ($designers): ?>
                <optgroup label="Designers">
                    <?php foreach ($designers as $designer): ?>
                        <option value="designer:<?=$designer['id']?>">
                            <?=H::e($designer['label'])?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>

            <?php if ($products): ?>
                <optgroup label="Products">
                    <?php foreach ($products as $product): ?>
                        <option value="product:<?=$product['id']?>">
                            <?=H::e($product['label'])?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>

            <?php if ($categories): ?>
                <optgroup label="Categories">
                    <?php foreach ($categories as $category): ?>
                        <option value="category:<?=$category['id']?>">
                            <?=H::e($category['label'])?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>
    </label>

    <button type="submit">Add homepage feature</button>
</form>

<?php
$featureSections = [
    'product' => [
        'title' => 'Featured products',
        'description' => 'Drag products into the order they should appear on the homepage.',
    ],
    'designer' => [
        'title' => 'Featured designers',
        'description' => 'Drag designers into the order they should appear on the homepage.',
    ],
    'category' => [
        'title' => 'Featured categories',
        'description' => 'Drag categories into the order they should appear on the homepage.',
    ],
];
?>

<div class="homepage-feature-sections">
    <?php foreach ($featureSections as $type => $section): ?>
        <?php $items = $groupedFeatures[$type] ?? []; ?>

        <section class="card homepage-feature-section">
            <div class="homepage-feature-heading">
                <div>
                    <h2><?=H::e($section['title'])?></h2>
                    <p class="muted"><?=H::e($section['description'])?></p>
                </div>

                <?php if ($items): ?>
                    <span class="homepage-feature-count">
                        <?=count($items)?> item<?=count($items) === 1 ? '' : 's'?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!$items): ?>
                <div class="empty-state homepage-feature-empty">
                    <p>No <?=H::e(strtolower($section['title']))?> have been added.</p>
                </div>
            <?php else: ?>
                <form method="post" class="homepage-order-form">
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <input type="hidden" name="action" value="reorder">
                    <input type="hidden" name="feature_type" value="<?=H::e($type)?>">
                    <input
                        type="hidden"
                        name="feature_order"
                        class="homepage-feature-order"
                        value="<?=H::e(implode(',', array_column($items, 'id')))?>"
                    >

                    <div
                        class="homepage-sortable-list"
                        data-feature-type="<?=H::e($type)?>"
                    >
                        <?php foreach ($items as $feature): ?>
                            <article
                                class="homepage-sortable-item"
                                draggable="true"
                                data-feature-id="<?=$feature['id']?>"
                            >
                                <button
                                    type="button"
                                    class="homepage-drag-handle"
                                    aria-label="Drag to reorder <?=H::e($feature['feature_name'])?>"
                                    title="Drag to reorder"
                                >
                                    <span aria-hidden="true">☰</span>
                                </button>

                                <div class="homepage-feature-details">
                                    <strong><?=H::e($feature['feature_name'])?></strong>

                                    <span class="homepage-feature-type">
                                        <?=H::e(ucfirst($feature['feature_type']))?>
                                    </span>
                                </div>

                                <div class="homepage-feature-actions">
                                    <form method="post" class="homepage-visible-form">
                                        <input
                                            type="hidden"
                                            name="_csrf"
                                            value="<?=H::csrf()?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >
                                        <input
                                            type="hidden"
                                            name="feature_record_id"
                                            value="<?=$feature['id']?>"
                                        >

                                        <label class="homepage-visible-toggle">
                                            <input
                                                type="checkbox"
                                                name="is_active"
                                                value="1"
                                                <?=!empty($feature['is_active']) ? 'checked' : ''?>
                                                onchange="this.form.submit()"
                                            >
                                            <span>
                                                <?=!empty($feature['is_active'])
                                                    ? 'Visible'
                                                    : 'Hidden'?>
                                            </span>
                                        </label>
                                    </form>

                                    <form
                                        method="post"
                                        class="homepage-remove-form"
                                        onsubmit="return confirm('Remove this homepage feature?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="_csrf"
                                            value="<?=H::csrf()?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >
                                        <input
                                            type="hidden"
                                            name="feature_record_id"
                                            value="<?=$feature['id']?>"
                                        >

                                        <button type="submit" class="btn alt">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="homepage-save-order">
                        Save <?=H::e(strtolower($section['title']))?> order
                    </button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.homepage-sortable-list').forEach((list) => {
        let draggedItem = null;

        const updateOrderField = () => {
            const form = list.closest('.homepage-order-form');
            const orderField = form.querySelector('.homepage-feature-order');

            orderField.value = Array.from(
                list.querySelectorAll('.homepage-sortable-item')
            )
                .map((item) => item.dataset.featureId)
                .join('');

            orderField.value = Array.from(
                list.querySelectorAll('.homepage-sortable-item')
            )
                .map((item) => item.dataset.featureId)
                .join(',');
        };

        list.querySelectorAll('.homepage-sortable-item').forEach((item) => {
            item.addEventListener('dragstart', (event) => {
                draggedItem = item;
                item.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.featureId);
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('is-dragging');
                draggedItem = null;
                updateOrderField();
            });

            item.addEventListener('dragover', (event) => {
                event.preventDefault();

                if (!draggedItem || draggedItem === item) {
                    return;
                }

                const rectangle = item.getBoundingClientRect();
                const insertAfter =
                    event.clientY > rectangle.top + rectangle.height / 2;

                if (insertAfter) {
                    item.after(draggedItem);
                } else {
                    item.before(draggedItem);
                }
            });
        });

        updateOrderField();
    });
});
</script>
