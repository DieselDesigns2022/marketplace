<?php
use App\Core\Helpers as H;
?>

<p>
    <a href="/admin/promo-library">
        ← Promotional graphics
    </a>
</p>

<h1>Add Graphic Details</h1>

<p class="muted">
    Your graphics are uploaded. Add the description and optional caption
    for each one, then save the entire batch.
</p>

<form method="post" class="form-grid">
    <input
        type="hidden"
        name="_csrf"
        value="<?=H::csrf()?>"
    >

    <?php foreach($graphics as $graphic): ?>
        <article class="card promo-batch-card">

            <div class="promo-batch-preview">
                <img
                    src="/admin/promo-library/<?=(int)$graphic['id']?>/image"
                    alt=""
                >
            </div>

            <div class="promo-batch-fields">

                <p>
                    <strong>
                        <?=H::e($graphic['original_name'])?>
                    </strong>
                </p>

                <p class="muted">
                    <?=H::e($graphic['size_label'])?>
                </p>

                <label>
                    Category

                    <input
                        name="category[<?=(int)$graphic['id']?>]"
                        maxlength="100"
                        required
                        placeholder="e.g. Seller invitation"
                        value="<?=H::e(
                            ($graphic['category']??'')==='__PROMO_DRAFT__'
                                ? ''
                                : ($graphic['category']??'')
                        )?>"
                    >
                </label>

                <label>
                    Image description (for accessibility)

                    <textarea
                        name="alt_text[<?=(int)$graphic['id']?>]"
                        maxlength="500"
                        required
                        rows="3"
                    ><?=H::e($graphic['alt_text'])?></textarea>

                    <small>
                        Briefly describe what someone would see in this image.
                    </small>
                </label>

                <label>
                    Suggested caption (optional)

                    <textarea
                        name="suggested_caption[<?=(int)$graphic['id']?>]"
                        maxlength="2000"
                        rows="4"
                    ><?=H::e($graphic['suggested_caption']??'')?></textarea>
                </label>

            </div>
        </article>
    <?php endforeach; ?>

    <label class="check">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            checked
        >

        Make all of these graphics publicly visible after saving
    </label>

    <div>
        <button class="btn">
            Save all graphics
        </button>
    </div>
</form>
