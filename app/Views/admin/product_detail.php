<h1>Product Review: <?=H::e($p['title'])?>
</h1>
<section class="card">
    <p>Status: <span class="badge">
    <?=H::e(ucwords(str_replace('_',' ',$p['status'])))?>
    </span>
    </p>
    <p>Designer: <a href="/store/<?=H::e($p['store_slug'])?>">
    <?=H::e($p['display_name'])?>
    </a> (<?=H::e($p['designer_email'])?>)</p>
    <p>Category: <?=H::e($p['category_name']??'Uncategorized')?>
    </p>
    <p>Tags: <?php if($tags): ?>
    <?=H::e(implode(', ', array_column($tags, 'name')))?>
<?php else: ?>None<?php endif; ?>
    </p>
    <p>AI Disclosure: <?=H::e($p['ai_disclosure'])?>
    </p>
    <p>SEO Title: <?=H::e($p['seo_title']?:'Fallback to product title')?>
    </p>
    <p>SEO Description: <?=H::e($p['seo_description']?:'Fallback to product description')?>
    </p>
    <?php if($p['rejection_reason']):?>
        <p>Rejection Reason: <?=H::e($p['rejection_reason'])?>
        </p>
    <?php endif;?>
    <h2>Enabled licenses</h2>
    <?php if(empty($licenses)):?>
        <p class="muted">No custom license records were found; the product falls back to its base personal license.</p>
    <?php else:?>
        <ul>
        <?php foreach($licenses as $license):?>
            <li><strong><?=H::e($license['name'])?></strong> <?php if($license['license_key']==='personal'):?><span class="muted">included/free</span><?php else:?><span class="muted"><?=H::money($license['price'])?> add-on</span><?php endif;?><?php if($license['description']):?><br><span class="muted"><?=H::e($license['description'])?></span><?php endif;?></li>
        <?php endforeach;?>
        </ul>
    <?php endif;?>
    <h2>Product information</h2>
    <p>
    <?=nl2br(H::e($p['description']))?>
    </p>
    <h2>Preview images</h2>
    <?php foreach($images as $img):?>
        <img class="thumb" src="<?=H::e($img['image_path'])?>" alt="<?=H::e($img['alt_text']??'Product preview')?>">
    <?php endforeach;?>
    <h2>Product file metadata</h2>
    <ul>
        <?php foreach($files as $file):?>
           <li>
           <?=H::e($file['original_name'])?> — <?=number_format(($file['file_size']??0)/1024,1)?> KB — <?=H::e($file['mime_type'])?>
           </li>
        <?php endforeach;?>
    </ul>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <label>Rejection Reason<input name="reason">
        </label>
        <button class="btn" name="action" value="approve">Approve</button>
        <button name="action" value="reject">Reject</button>
        <button name="action" value="disable">Disable</button>
    </form>
</section>
