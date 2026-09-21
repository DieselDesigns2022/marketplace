<?php
use App\Core\Helpers as H;
$editing=!empty($graphic['id']);
?>

<p>
    <a href="/admin/promo-library">
        ← Promotional graphics
    </a>
</p>

<h1>
    <?=$editing?'Edit Promotional Graphic':'Upload Promotional Graphics'?>
</h1>

<form
    method="post"
    enctype="multipart/form-data"
    class="card form-grid promo-graphic-form"
>
    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <label>
        Platform / placement

        <select name="platform" required>
            <option value="">Choose one</option>

            <?php foreach($platforms as $value=>$label): ?>
                <option
                    value="<?=H::e($value)?>"
                    <?=($graphic['platform']??'')===$value?'selected':''?>
                >
                    <?=H::e($label)?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <?php if($editing): ?>
    <label>
        Category

        <input
            name="category"
            maxlength="100"
            required
            placeholder="e.g. Seller invitation"
            value="<?=H::e($graphic['category']??'')?>"
        >
    </label>
    <?php endif; ?>

    <?php if($editing): ?>

        <label class="full">
            Image description (for accessibility)

            <textarea
                name="alt_text"
                maxlength="500"
                required
                rows="3"
                placeholder="Describe what is visible in the graphic."
            ><?=H::e($graphic['alt_text']??'')?></textarea>

            <small>
                Briefly describe what someone would see in the image.
            </small>
        </label>

        <label class="full">
            Suggested caption (optional)

            <textarea
                name="suggested_caption"
                maxlength="2000"
                rows="5"
            ><?=H::e($graphic['suggested_caption']??'')?></textarea>
        </label>

        <label class="full">
            Replacement image
            (leave empty to keep current image)

            <input
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
            >

            <small>
                JPG, PNG, or WEBP; maximum 10 MB and 25 megapixels.
                Dimensions are detected automatically.
            </small>
        </label>

        <div class="full">
            <img
                class="promo-form-preview"
                src="/admin/promo-library/<?=(int)$graphic['id']?>/image"
                alt="Current image: <?=H::e($graphic['alt_text'])?>"
            >
        </div>

        <label class="check full">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                <?=!empty($graphic['is_active'])?'checked':''?>
            >

            Active and publicly visible
        </label>

        <div class="full">
            <button class="btn">
                Save changes
            </button>
        </div>

    <?php else: ?>

        <label class="full">
            Graphics

            <input
                type="file"
                name="images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
            >

            <small>
                Select one or many graphics.
                Dimensions are detected automatically.
                You will add each graphic's description and caption next.
            </small>
        </label>

        <div class="full">
            <button class="btn">
                Upload & add details
            </button>
        </div>

    <?php endif; ?>
</form>
