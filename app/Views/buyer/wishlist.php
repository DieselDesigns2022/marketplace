<h1>Wishlist</h1>

<?php if(!$products):?>
    <div class="card empty-state">
        Your wishlist is empty.
    </div>
<?php else:?>

    <div class="grid products">
        <?php foreach($products as $p):?>

            <article class="card product">

                <a href="/product/<?=H::e($p['slug'])?>">
                    <?php if(!empty($p['preview_image'])):?>
                        <img
                            class="thumb"
                            src="<?=H::e($p['preview_image'])?>"
                            alt="<?=H::e($p['title'])?> preview image"
                        >
                    <?php else:?>
                        <div class="thumb">
                            Digital design preview unavailable
                        </div>
                    <?php endif;?>
                </a>

                <h3>
                    <a href="/product/<?=H::e($p['slug'])?>">
                        <?=H::e($p['title'])?>
                    </a>
                </h3>

                <?php if(!empty($p['display_name'])):?>
                    <p>
                        by
                        <?php if(!empty($p['store_slug'])):?>
                            <a href="/store/<?=H::e($p['store_slug'])?>">
                                <?=H::e($p['display_name'])?>
                            </a>
                        <?php else:?>
                            <?=H::e($p['display_name'])?>
                        <?php endif;?>
                    </p>
                <?php endif;?>

                <?php if(!empty($p['category_slug'])):?>
                    <p>
                        <a href="/category/<?=H::e($p['category_slug'])?>">
                            <?=H::e($p['category_name'])?>
                        </a>
                    </p>
                <?php endif;?>

                <p>
                    <strong><?=H::money($p['price'])?></strong>
                </p>

                <form
                    method="post"
                    action="/product/<?=(int)$p['id']?>/wishlist"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >
                    <button type="submit" class="btn secondary">
                        Remove from Wishlist
                    </button>
                </form>

            </article>

        <?php endforeach;?>
    </div>

<?php endif;?>
