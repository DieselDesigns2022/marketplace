<section>
    <h1>Custom Designs</h1>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <a class="btn" href="/seller/custom-designs/new">
            Create Custom Design
        </a>

        <a class="btn alt" href="/seller/custom-orders">
            Custom Orders
        </a>
    </div>

    <?php foreach($services as $s):?>
        <article class="card">
            <h2><?=H::e($s['title'])?></h2>

            <p>
                <?=H::money($s['price'])?>
                ·
                <?=$s['is_active']?'Active':'Draft'?>
                ·
                <?=H::e(
                    \App\Services\CustomDesignService::turnaroundLabel($s)
                )?>
            </p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <a
                    class="btn"
                    href="/seller/custom-designs/<?=$s['id']?>"
                >
                    Manage
                </a>

                <form
                    method="post"
                    action="/seller/custom-designs/<?=$s['id']?>/duplicate"
                    style="margin:0"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?=H::csrf()?>"
                    >

                    <button
                        type="submit"
                        class="btn alt"
                    >
                        Duplicate
                    </button>
                </form>
            </div>
        </article>
    <?php endforeach?>

    <?php if(!$services):?>
        <p>No custom-design services yet.</p>
    <?php endif?>
</section>
