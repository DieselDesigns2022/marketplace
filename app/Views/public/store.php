<article class="storefront" itemscope itemtype="https://schema.org/ProfilePage">
    <nav class="store-links">
        <a href="/browse">← Browse designs</a>
        <a href="#custom-designs">Custom Designs</a>
        <a href="#newest-products">Newest products</a>
        <a href="#store-categories">Store categories</a>
    </nav>
    <section class="storefront-hero">
        <div class="storefront-banner" <?php if(!empty($d['banner_path'])):?>style="background-image:url('<?=H::e($d['banner_path'])?>')"<?php endif; ?>>
           <?php if(empty($d['banner_path'])):?>
               <span>Store banner artwork has not been added yet.</span>
           <?php endif;?>
        </div>
        <div class="storefront-profile">
           <div class="avatar large" itemprop="image">
               <?php if(!empty($d['avatar_path'])):?>
                   <img src="<?=H::e($d['avatar_path'])?>" alt="<?=H::e($d['display_name'])?> logo">
               <?php else:?>
                   <span>
                   <?=H::e(substr($d['display_name']??'S',0,1))?>
                   </span>
               <?php endif;?>
           </div>
           <div class="storefront-title">
               <h1 itemprop="name">
               <?=H::e($d['display_name'])?>
               </h1>
               <span class="badge rank">
               <?=H::e($d['creator_rank'] ?: 'Bronze')?>
               </span>
               <?php if(!empty($d['founder_active'])&&!empty($d['founder_position'])):?><span class="badge founder" aria-label="Active Founder badge, position <?=(int)$d['founder_position']?>">Founder #<?=(int)$d['founder_position']?></span><?php endif;?>
               <span class="badge ok">Approved seller</span>
           </div>
           <div class="follow-box">
               <?php if($isOwner): ?>
               <span class="badge">This is your store</span>
           <?php else: ?>
               <form method="post" action="/store/<?=$d['id']?>/follow">
                   <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                   <button class="btn">
                   <?= $isFollowing ? 'Unfollow Designer' : 'Follow Designer' ?>
                   </button>
               </form>
               <small>Follow this designer to find them again later.</small>
           <?php endif; ?>
        </div>
    </div>
</section>
<section class="stats-row">
    <div class="card">
        <strong>
        <?=$followers?>
        </strong>
        <span>Followers</span>
    </div>
    <div class="card">
        <strong>
        <?=$productCount?>
        </strong>
        <span>Products</span>
    </div>
    <div class="card">
        <strong>
        <?=H::e((string)($salesCount ?? 0))?>
        </strong>
        <span>Sales</span>
    </div>
    <div class="card"><a href="#seller-reviews"><strong><?=empty($d['review_count'])?'No reviews yet':str_repeat('★',5).' '.number_format((float)$d['average_rating'],1).' ('.(int)$d['review_count'].' reviews)'?></strong></a>
        <span>Seller rating</span>
    </div>
</section>
<?php if(!empty($prizeWheelVisible)): ?>
<section
    id="creative-moth-prize-wheel"
    class="card page-section prize-wheel-storefront"
>
    <h2>
        <?=H::e(
            $prizeWheelSettings['heading']
            ??'Grand Opening Prize Wheel'
        )?>
    </h2>

    <?php if(!empty($prizeWheelSettings['description'])): ?>
        <p>
            <?=nl2br(
                H::e($prizeWheelSettings['description'])
            )?>
        </p>
    <?php endif; ?>

    <div class="creative-wheel-wrap">
        <div class="creative-wheel-pointer" aria-hidden="true">▼</div>

        <div
            class="creative-wheel-disc"
            id="creative-wheel-disc"
            aria-label="Creative Moth Prize Wheel"
        >
            <div class="creative-wheel-center">
                SPIN
            </div>
        </div>
    </div>

    <?php if(!empty($prizeWheelResult)): ?>
        <div class="notice success prize-wheel-result">
            <h3>🎉 You won!</h3>

            <p>
                <strong>
                    <?=H::e($prizeWheelResult['title'])?>
                </strong>
            </p>

            <p>
                Prize awarded from
                <strong>
                    <?=H::e(
                        $prizeWheelResult['seller_name']
                        ??'this Creative Moth store'
                    )?>
                </strong>
            </p>

            <?php if(!empty($prizeWheelResult['coupon_code'])): ?>
                <p>
                    <strong>Your prize code:</strong><br>
                    <code><?=H::e($prizeWheelResult['coupon_code'])?></code>
                </p>
            <?php endif; ?>

            <?php if(!empty($prizeWheelResult['reward_message'])): ?>
                <p>
                    <?=H::e($prizeWheelResult['reward_message'])?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if(
        (int)($prizeWheelState['available_prizes']??0)<1
    ): ?>
        <p class="notice warning">
            Prize Wheel prizes are temporarily unavailable.
        </p>

    <?php elseif(!H::user()): ?>
        <p>
            <a class="btn" href="/login">
                Log in to Spin
            </a>
        </p>

        <p class="help-text">
            A Creative Moth account is required so your prize can be
            securely attached to you.
        </p>

    <?php elseif(
        (int)($prizeWheelState['spins_remaining']??0)<1
    ): ?>
        <p class="notice">
            Make a qualifying purchase of at least
            <strong>
                $<?=number_format(
                    ((int)($prizeWheelState['minimum_purchase_cents']??500))/100,
                    2
                )?>
            </strong>
            of this store's merchandise after all coupons, credits,
            and discounts to unlock a spin.
            Sales tax does not count toward the minimum.
        </p>

    <?php else: ?>
        <p>
            You have
            <strong>
                <?=(int)$prizeWheelState['spins_remaining']?>
            </strong>
            spin<?=((int)$prizeWheelState['spins_remaining']===1)?'':'s'?>
            remaining.
        </p>

        <form
            method="post"
            action="/store/<?=(int)$d['id']?>/prize-wheel/spin"
            id="creative-wheel-spin-form"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::csrf()?>"
            >

            <button
                class="btn"
                type="submit"
                id="creative-wheel-spin-button"
            >
                Spin the Prize Wheel
            </button>
        </form>
    <?php endif; ?>
</section>

<style>
.creative-wheel-wrap{
    position:relative;
    width:min(320px,90vw);
    margin:24px auto;
}
.creative-wheel-pointer{
    position:absolute;
    z-index:3;
    top:-18px;
    left:50%;
    transform:translateX(-50%);
    font-size:38px;
}
.creative-wheel-disc{
    width:min(320px,90vw);
    aspect-ratio:1;
    border-radius:50%;
    border:8px solid currentColor;
    position:relative;
    display:grid;
    place-items:center;
    background:
        conic-gradient(
            #7d3cff 0deg 45deg,
            #ed4d9a 45deg 90deg,
            #26b6a4 90deg 135deg,
            #f4a640 135deg 180deg,
            #7d3cff 180deg 225deg,
            #ed4d9a 225deg 270deg,
            #26b6a4 270deg 315deg,
            #f4a640 315deg 360deg
        );
    box-shadow:0 8px 28px rgba(0,0,0,.22);
}
.creative-wheel-center{
    width:34%;
    aspect-ratio:1;
    border-radius:50%;
    background:#fff;
    color:#111;
    display:grid;
    place-items:center;
    font-weight:800;
    border:5px solid currentColor;
    letter-spacing:.06em;
}
.creative-wheel-disc.is-spinning{
    transition:transform 1.8s cubic-bezier(.17,.67,.19,1);
    transform:rotate(1800deg);
}
.prize-wheel-result{
    margin-top:22px;
    text-align:center;
}
</style>

<div
    id="prize-wheel-promo-modal"
    style="
        display:none;
        position:fixed;
        inset:0;
        z-index:9999;
        background:rgba(0,0,0,.72);
        align-items:center;
        justify-content:center;
        padding:20px;
    "
>
    <div
        class="card"
        style="
            max-width:520px;
            width:100%;
            position:relative;
            text-align:center;
        "
    >
        <button
            type="button"
            id="prize-wheel-promo-close"
            aria-label="Close"
            style="
                position:absolute;
                right:14px;
                top:10px;
                border:0;
                background:none;
                font-size:28px;
                cursor:pointer;
            "
        >×</button>

        <h2>🎡 This seller is spinning!</h2>

        <p>
            <strong><?=H::e($d['display_name'])?></strong>
            is participating in Creative Moth's Prize Wheel promotion.
        </p>

        <?php if(
            H::user()
            && (int)($prizeWheelState['spins_remaining']??0)>0
        ): ?>
            <p>
                🎉 You already have an eligible spin waiting from a
                qualifying purchase at this store!
            </p>

            <p>
                <a
                    class="btn"
                    href="#creative-moth-prize-wheel"
                    id="prize-wheel-promo-spin-now"
                >
                    Spin Now
                </a>
            </p>
        <?php else: ?>
            <p>
                Spend at least
                <strong>
                    $<?=number_format(
                        ((int)(
                            $prizeWheelState[
                                'minimum_purchase_cents'
                            ]??500
                        ))/100,
                        2
                    )?>
                </strong>
                of this store's merchandise after all coupons,
                credits, and discounts and you'll unlock
                <strong>one spin</strong> on this seller's Prize Wheel
                after your purchase.
            </p>

            <p class="help-text">
                At least $5 of this store's merchandise must still be
                paid at checkout after coupons, Prize Credit,
                Marketplace Credit, and other discounts.
                Sales tax does not count toward the $5 minimum.
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
(() => {
    const modal=document.getElementById(
        'prize-wheel-promo-modal'
    );

    const close=document.getElementById(
        'prize-wheel-promo-close'
    );

    const spinNow=document.getElementById(
        'prize-wheel-promo-spin-now'
    );

    if(!modal || !close){
        return;
    }

    const key=
        'creative-moth-wheel-promo:'+
        <?=json_encode(
            (string)(
                $prizeWheelState['campaign_key']
                ??''
            )
        )?>+
        ':store:'+
        <?=json_encode((string)$d['id'])?>;

    if(
        !window.sessionStorage.getItem(key)
    ){
        modal.style.display='flex';
        window.sessionStorage.setItem(key,'1');
    }

    const hide=()=>{
        modal.style.display='none';
    };

    close.addEventListener('click',hide);

    modal.addEventListener('click',(event)=>{
        if(event.target===modal){
            hide();
        }
    });

    if(spinNow){
        spinNow.addEventListener('click',hide);
    }
})();
</script>

<script>
(() => {
    const form=document.getElementById('creative-wheel-spin-form');
    const wheel=document.getElementById('creative-wheel-disc');
    const button=document.getElementById('creative-wheel-spin-button');

    if(!form || !wheel || !button){
        return;
    }

    let submitting=false;

    form.addEventListener('submit',(event)=>{
        if(submitting){
            return;
        }

        event.preventDefault();
        submitting=true;
        button.disabled=true;
        button.textContent='Spinning…';
        wheel.classList.add('is-spinning');

        window.setTimeout(()=>{
            form.submit();
        },1850);
    });
})();
</script>
<?php endif; ?>

<?php if(!empty($d['announcement'])): ?>
<section class="card announcement">
    <h2>Store announcement</h2>
    <p>
    <?=nl2br(H::e($d['announcement']))?>
    </p>
</section>
<?php endif; ?>
<section class="card" itemprop="about">
    <h2>About this designer</h2>
    <p>
    <?=nl2br(H::e($d['bio'] ?: 'This approved designer has not added a public bio yet. Browse their available digital products below.'))?>
    </p>
    <?php if(!empty($socialLinks)):?>
        <h3>Connect with this seller</h3>
        <div class="social-link-list">
        <?php foreach($socialLinks as $link):?>
            <a class="social-link" target="_blank" rel="noopener noreferrer nofollow ugc" href="<?=H::e($link['url'])?>"><?=H::e($link['label'])?></a>
        <?php endforeach;?>
        </div>
    <?php endif; ?>
    <?php if(!empty($d['social_links'])):?>
        <h3>Social links</h3>
        <p>
        <?=nl2br(H::e($d['social_links']))?>
        </p>
    <?php endif; ?>
</section>
<?php if(H::user() && !$isOwner):?><form method="post" action="/messages/start/store/<?=$d['id']?>"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="btn secondary">Message seller</button></form><?php endif;?>
<?php if(!empty($collabs)):?>
<section id="collab-bundles" class="page-section">
    <h2>Active Collab Bundles</h2>

    <div class="grid products">
        <?php foreach($collabs as $c):?>
            <?php
            $collabStorefrontId = (int)$d['id'];
            include app_path(
                'app/Views/public/collab_card.php'
            );
            ?>
        <?php endforeach;?>
    </div>
</section>
<?php endif;?>
<section id="custom-designs">
    <h2>Custom Designs</h2>
    <?php if(empty($customServices)): ?>
    <div class="card empty-state">
        <p>This seller does not currently have any custom designs available.</p>
    </div>
    <?php else: ?>
    <div class="grid products">
        <?php foreach($customServices as $s):?>
        <article class="card product">
            <a href="/custom-design/<?=H::e($s['slug'])?>">
                <?php if(!empty($s['preview_image'])):?>
                    <img
                        class="thumb"
                        src="<?=H::e($s['preview_image'])?>"
                        alt="<?=H::e($s['title'])?> custom design preview"
                    >
                <?php else:?>
                    <div class="thumb">
                        Custom design preview unavailable
                    </div>
                <?php endif;?>
            </a>

            <h3>
                <a href="/custom-design/<?=H::e($s['slug'])?>">
                    <?=H::e($s['title'])?>
                </a>
            </h3>

            <div class="product-card-badges">
                <span class="badge">Custom Design</span>

                <?php if(!empty($s['turnaround_days'])):?>
                    <span class="badge">
                        <?=H::e(\App\Services\CustomDesignService::turnaroundLabel($s))?> turnaround
                    </span>
                <?php endif;?>
            </div>

            <a
                href="/custom-design/<?=H::e($s['slug'])?>"
                class="product-meta"
            >
                <p><strong><?=H::money($s['price'])?></strong></p>
            </a>
        </article>
        <?php endforeach;?>
    </div>
    <?php endif; ?>
</section>

<section id="featured-products">
    <h2>Featured products</h2>
    <?php if(empty($products)): ?>
    <div class="card empty-state">
        <p>This approved storefront does not have public products right now. Browse other designers while new products are reviewed.</p>
    </div>
<?php else: ?>
    <?php include app_path('app/Views/public/product_grid.php');?>
<?php endif; ?>
</section>
<section id="newest-products">
    <h2>Newest products</h2>
    <?php if(empty($products)): ?>
    <div class="card empty-state">
        <p>This store has no approved products available at the moment.</p>
    </div>
<?php else: ?>
    <?php include app_path('app/Views/public/product_grid.php');?>
<?php endif; ?>
</section>
<section id="store-categories">
    <h2>Store categories</h2>
    <?php $cats=[]; foreach($products as $p){ if(!empty($p['category_slug'])) $cats[$p['category_slug']]=$p['category_name']; } if(!$cats): ?>
    <div class="card empty-state">
        <p>Store category links appear after products are approved for this designer.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach($cats as $slug=>$name):?>
           <a class="card" href="/category/<?=H::e($slug)?>">
           <?=H::e($name)?>
           </a>
        <?php endforeach;?>
    </div>
<?php endif; ?>
</section>
</article>
<section id="seller-reviews" class="card"><h2>Seller Reviews</h2>
<?php if(empty($reviews)):?><p>No reviews yet</p><?php else:?><p><strong aria-label="<?=number_format((float)$d['average_rating'],1)?> out of 5 stars"><?=str_repeat('★',(int)round($d['average_rating']))?> <?=number_format((float)$d['average_rating'],1)?> (<?=(int)$d['review_count']?> reviews)</strong></p>
<div class="rating-distribution"><?php for($n=5;$n>=1;$n--):?><p><?=$n?> stars: <?=(int)$d['rating_'.$n.'_count']?></p><?php endfor;?></div>
<?php foreach($reviews as $r):?><article id="review-<?=(int)$r['id']?>"><h3><?=str_repeat('★',(int)$r['rating'])?> <span class="sr-only"><?=(int)$r['rating']?> out of 5</span></h3><p><?=H::e($r['buyer_name_snapshot'])?> · <strong>Verified Purchase</strong> · <?=H::e($r['product_title_snapshot'])?></p><?php if($r['review_text']):?><p><?=nl2br(H::e($r['review_text']))?></p><?php endif;?><small><?=H::e($r['reviewed_at'])?><?=$r['edited_at']?' · Edited':''?></small><?php if($r['reply_text']&&$r['reply_moderation_status']==='published'):?><aside><strong>Seller Response</strong><p><?=nl2br(H::e($r['reply_text']))?></p><small><?=H::e($r['reply_created_at'])?><?php if(strtotime($r['reply_updated_at'])>strtotime($r['reply_created_at'])):?> · Edited<?php endif;?></small></aside><?php endif;?></article><?php endforeach;?><?php endif;?></section>
