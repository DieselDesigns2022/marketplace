<h1>Seller Dashboard → Products</h1>
<p><a class="btn" href="/seller/product/new">Create Product</a> <a class="btn" href="/seller/product-batches">Bulk Upload / Create Multiple Products</a> <a class="btn" href="/seller/products/import">Import Products</a></p>
<p class="muted">Archive hides a product from public browsing while preserving order history. Permanent delete is only available for draft/test products with no completed orders.</p>
<nav class="tabs">
    <?php foreach(['all'=>'All','draft'=>'Draft','pending_review'=>'Pending Review','approved'=>'Published','published'=>'Published Legacy','rejected'=>'Rejected','disabled'=>'Disabled','archived'=>'Archived'] as $key=>$label): ?>
    <a class="<?=($status??'all')===$key?'active':''?>" href="/seller/products<?=$key==='all'?'':'?status='.$key?>"><?=H::e($label)?></a>
<?php endforeach; ?>
</nav>
<?php if(!$products): ?>
<div class="card empty"><h2>No products found.</h2><p>Create a product draft and submit it for review when it is ready.</p><a class="btn" href="/seller/product/new">Create Product</a></div>
<?php else: ?>
    <form id="bulk-product-form" method="post" action="/seller/products/bulk-delete">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <p>
            <button
                type="button"
                onclick="document.querySelectorAll('.bulk-product-pick').forEach(x=>x.checked=true)"
            >Select All</button>

            <button
                type="button"
                onclick="document.querySelectorAll('.bulk-product-pick').forEach(x=>x.checked=false)"
            >Deselect All</button>

            <button
                type="submit"
                formaction="/seller/products/bulk-archive"
                onclick="const n=document.querySelectorAll('.bulk-product-pick:checked').length;if(!n){alert('Select at least one product to archive.');return false;}return confirm('Archive '+n+' selected product'+(n===1?'':'s')+' and hide them from public listings?');"
            >Archive Selected</button>

            <button
                type="submit"
                formaction="/seller/products/bulk-delete"
                onclick="const n=document.querySelectorAll('.bulk-product-pick:checked').length;if(!n){alert('Select at least one product to delete.');return false;}return confirm('Permanently delete all eligible products from the '+n+' selected product'+(n===1?'':'s')+'? Products that are not eligible for permanent deletion will be skipped.');"
            >Permanent Delete Selected</button>
        </p>

        <p class="muted">
            Select any products for bulk actions. Archive hides selected products from public listings. Permanent delete only removes eligible draft/test products with no completed orders.
        </p>
    </form>

    <div class="responsive-table"><table>
        <tr><th>Select</th><th>Thumbnail</th><th>Product Name</th><th>Status</th><th>Orders</th><th>Price</th><th>Category</th><th>Updated Date</th><th>Actions</th></tr>
        <?php foreach($products as $p): $safeDelete = ((int)($p['completed_order_count'] ?? 0) === 0) && in_array($p['status'], ['draft','rejected','archived','disabled','deleted'], true); ?>
           <tr>
               <td><input class="bulk-product-pick" type="checkbox" name="product_ids[]" value="<?=(int)$p['id']?>" form="bulk-product-form" aria-label="Select <?=H::e($p['title'])?>"></td>
               <td><?php if($p['thumbnail']):?><img class="thumb" src="<?=H::e($p['thumbnail'])?>" alt="<?=H::e($p['title'])?> thumbnail"><?php else:?><span class="thumb">No image</span><?php endif;?></td>
               <td><?=H::e($p['title'])?><?php if($p['rejection_reason']):?><br><small>Rejected: <?=H::e($p['rejection_reason'])?></small><?php endif;?><?php if((int)($p['completed_order_count'] ?? 0)>0):?><br><small class="muted">Cannot be permanently deleted because completed orders reference it.</small><?php endif;?></td>
               <td><span class="badge"><?=H::e(($p['status']==='approved'||$p['status']==='published')?'Published':ucwords(str_replace('_',' ',$p['status'])))?></span></td>
               <td><?= (int)($p['completed_order_count'] ?? 0) ?></td>
               <td><?=$p['price']===null?'Needs review':H::money($p['price'])?></td>
               <td><?=H::e($p['category_name']??'Uncategorized')?></td>
               <td><?=H::e($p['updated_at'])?></td>
               <td>
                   <div class="product-actions">
                   <a class="btn" href="/seller/product/<?=$p['id']?>">Edit</a>
                   <form method="post" action="/seller/product/<?=$p['id']?>/duplicate" onsubmit="return confirm('Create a draft copy of this product? Product files and preview images will not be copied.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Duplicate</button></form>
                   <?php if(in_array($p['status'], ['approved','published'], true)):?><a class="btn" href="/product/<?=H::e($p['slug'])?>">View Public Page</a><?php endif;?>
                   <?php if(!in_array($p['status'], ['archived','deleted'], true)):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/archive" onsubmit="return confirm('Archive this product and hide it from public listings? Order history will remain available.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Archive / Hide</button></form>
                   <?php endif;?>
                   <?php if(in_array($p['status'], ['archived','deleted'], true)):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/restore" onsubmit="return confirm('Restore this product as a draft? It must be reviewed before publishing again.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Restore as Draft</button></form>
                   <?php endif;?>
                   <?php if(!in_array($p['status'], ['approved','published','archived','deleted'], true)):?><form method="post" action="/seller/product/<?=$p['id']?>/submit"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Submit For Review</button></form><?php endif;?>
                   <?php if($safeDelete):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/delete" onsubmit="return confirm('Permanently delete this product? This cannot be undone.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Permanent Delete</button></form>
                   <?php else:?><small class="muted">Permanent delete unavailable; archive instead.</small><?php endif;?>
                   </div>
               </td>
           </tr>
        <?php endforeach;?>
    </table></div>
<?php endif; ?>
