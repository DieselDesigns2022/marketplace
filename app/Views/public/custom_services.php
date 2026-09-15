<section>
    <h1>Custom Designs</h1>

    <div class="grid products custom-design-grid">
        <?php foreach($services as $s): ?>
            <article class="product card">
                <?php if($s['preview_image']): ?>
                    <a href="/custom-design/<?=H::e($s['slug'])?>">
                        <img
                            class="thumb"
                            src="<?=H::e($s['preview_image'])?>"
                            alt="<?=H::e($s['title'])?>"
                        >
                    </a>
                <?php endif; ?>

                <h3>
                    <a href="/custom-design/<?=H::e($s['slug'])?>">
                        <?=H::e($s['title'])?>
                    </a>
                </h3>

                <p>
                    by <?=H::e($s['display_name'])?>
                    · <?=H::money($s['price'])?>
                    · <?=$s['turnaround_days']?> days
                </p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if(!$services): ?>
        <p>No custom-design services are currently available.</p>
    <?php endif; ?>
</section>
