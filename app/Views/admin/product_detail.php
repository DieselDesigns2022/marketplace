<h1>Product Review: <?=H::e($p['title'])?>
</h1>
<section class="card">
    <p>Status: <span class="badge">
    <?=H::e(($p['status']==='approved'||$p['status']==='published')?'Published':ucwords(str_replace('_',' ',$p['status'])))?>
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
    <p>Completed Orders: <?= (int)($p['completed_order_count'] ?? 0) ?> <?php if((int)($p['completed_order_count'] ?? 0)>0):?><span class="muted">Permanent delete is blocked; archive to hide this product while preserving order history.</span><?php endif;?></p>
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
        <div class="admin-review-preview-item">
            <a class="admin-review-preview-link" href="<?=H::e($img['image_path'])?>" target="_blank" rel="noopener">
                <img class="admin-review-preview" src="<?=H::e($img['image_path'])?>" alt="<?=H::e($img['alt_text']??'Product preview')?>">
            </a>
            <p class="help-text"><a href="<?=H::e($img['image_path'])?>" target="_blank" rel="noopener">Open full-size preview in new tab</a></p>
            <p>
                <span><?=H::e($img['watermark_status'] ?? 'legacy preview')?><?php if(!empty($img['original_image_path'])):?> · private original retained<?php endif;?></span>
                <?php if(!empty($img['original_image_path'])):?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="image_id" value="<?=$img['id']?>"><button name="action" value="regenerate_watermark">Regenerate watermark</button></form><?php endif;?>
            </p>
            <?php if(!empty($img['watermark_error'])):?><small class="help-text">Watermark note: <?=H::e($img['watermark_error'])?></small><?php endif;?>
        </div>
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
        <button name="action" value="archive" onclick="return confirm('Archive this product and hide it from public listings?');">Archive / Hide</button>
        <?php if(in_array($p['status'], ['archived','deleted'], true)):?><button name="action" value="restore" onclick="return confirm('Restore this product as a draft?');">Restore as Draft</button><?php endif;?>
        <button name="action" value="mark_deleted" onclick="return confirm('Mark deleted? This hides the product but keeps records. Use bulk delete for safe permanent deletion only.');">Mark Deleted</button>
    </form>
</section>
