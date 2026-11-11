<h1>Seller Dashboard → Products</h1>
<p><a class="btn" href="/seller/product/new">Create Product</a> <a class="btn" href="/seller/product-batches">Bulk Upload / Create Multiple Products</a> <a class="btn" href="/seller/products/import">Import Products</a></p>
<p class="muted">Archive hides a product from public browsing while preserving order history. Permanent delete is only available for draft/test products with no completed orders.</p>

<div class="card seller-featured-help">
    <strong>⭐ Storefront Featured Products</strong>
    <p>
        Choose up to 4 published products to feature at the top of your public store.
        This does not affect Creative Moth marketplace search or ranking.
    </p>
</div>
<form method="get" action="/seller/products" class="card">
    <?php if(($status??'all')!=='all'): ?>
        <input type="hidden" name="status" value="<?=H::e($status)?>">
    <?php endif; ?>
    <label>
        Search your products
        <input type="search" name="q" value="<?=H::e($q??'')?>" placeholder="Search products or tags">
    </label>
    <label>
        Collection / Shop Section
        <select name="section">
            <option value="">All Collections</option>
            <option value="unassigned"
                <?=($section??'')==='unassigned'?'selected':''?>>
                Unassigned Products
            </option>
            <?php foreach(($shopSections??[]) as $shopSection):?>
                <option value="<?=(int)$shopSection['id']?>"
                    <?=(string)($section??'')===(string)$shopSection['id']?'selected':''?>>
                    <?=H::e($shopSection['name'])?>
                </option>
            <?php endforeach;?>
        </select>
    </label>
    <button class="btn" type="submit">Filter Products</button>
    <?php if(($q??'')!==''): ?>
        <a class="btn alt" href="/seller/products<?=($status??'all')==='all'?'':'?status='.rawurlencode($status)?>">Clear Search</a>
    <?php endif; ?>
</form>

<nav class="tabs">
    <?php foreach(['all'=>'All','draft'=>'Draft','pending_review'=>'Pending Review','scheduled'=>'Scheduled','approved'=>'Published','published'=>'Published Legacy','rejected'=>'Rejected','disabled'=>'Disabled','archived'=>'Archived'] as $key=>$label):
        $tabParams=[];
        if($key!=='all')$tabParams['status']=$key;
        if(($q??'')!=='')$tabParams['q']=$q;
        if(($section??'')!=='')$tabParams['section']=$section;
        $tabUrl='/seller/products'.($tabParams?'?'.http_build_query($tabParams):'');
    ?>
    <a class="<?=($status??'all')===$key?'active':''?>" href="<?=H::e($tabUrl)?>"><?=H::e($label)?></a>
<?php endforeach; ?>
</nav>
<?php if(!$products): ?>
<div class="card empty"><h2>No products found.</h2><p><?=($q??'')!==''?'No products match your search and selected status filter.':'Create a product draft and submit it for review when it is ready.'?></p><?php if(($q??'')===''):?><a class="btn" href="/seller/product/new">Create Product</a><?php endif;?></div>
<?php else: ?>

<style>
.bulk-collection-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 16px;
    padding: 14px 16px;
    margin: 12px 0;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
}
.bulk-collection-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.bulk-collection-buttons button {
    margin: 0;
}
.bulk-collection-controls {
    flex: 1 1 280px;
    min-width: 0;
}
.bulk-collection-controls label {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
    font-weight: 700;
}
.bulk-collection-inputs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.bulk-collection-inputs select {
    width: auto;
    flex: 1 1 160px;
    min-width: 0;
    margin: 0;
}
.bulk-collection-inputs button {
    margin: 0;
    white-space: nowrap;
}
</style>

    <form id="bulk-product-form" method="post" action="/seller/products/bulk-delete">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
        <div class="bulk-collection-toolbar">
        <div class="bulk-collection-buttons">
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
        </div>

        <div class="bulk-collection-controls">
            <label for="bulk-section-select">Move selected to collection</label>
            <div class="bulk-collection-inputs">
                <select id="bulk-section-select" name="section_id">
                    <option value="">All Listings / No Collection</option>
                    <?php foreach(($shopSections??[]) as $shopSection):?>
                        <option value="<?=(int)$shopSection['id']?>">
                            <?=H::e($shopSection['name'])?>
                        </option>
                    <?php endforeach;?>
                </select>
            <button type="submit"
                    formaction="/seller/products/bulk-section"
                    onclick="const n=document.querySelectorAll('.bulk-product-pick:checked').length;if(!n){alert('Select at least one product.');return false;}return confirm('Update the collection for '+n+' selected product(s)?');">
                Apply Collection
            </button>
            </div>
        </div>
        </div>

        <p class="muted">
            Check the boxes beside products below, then choose a bulk action.
            Applying a collection only changes the selected products' shop section.
            Archive hides selected products from public listings, and permanent
            delete only removes eligible products.
        </p>

        <p class="muted" hidden>
            Select any products for bulk actions. Archive hides selected products from public listings. Permanent delete only removes eligible draft/test products with no completed orders.
        </p>
    </form>

    <style>
.cm-product-menu {
    width: 145px;
    max-width: 100%;
}
.cm-product-menu > summary {
    cursor: pointer;
    list-style: none;
    background: #261842;
    color: #fff;
    padding: 10px 12px;
    text-align: center;
    border-radius: 10px;
    font-weight: 600;
}
.cm-product-menu > summary::-webkit-details-marker {
    display: none;
}
.cm-product-menu > summary:focus-visible {
    outline: 3px solid #a855f7;
    outline-offset: 2px;
}
.cm-product-menu .product-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 100%;
    padding: 6px 0;
}
.cm-product-menu .product-actions > a,
.cm-product-menu .product-actions > form,
.cm-product-menu .product-actions > form > button {
    box-sizing: border-box;
    width: 100%;
}
.cm-product-menu .product-actions > a,
.cm-product-menu .product-actions > form > button {
    font-size: 12px;
    padding: 7px 8px;
    min-height: 34px;
}
</style>
<div class="responsive-table"><table>
        <tr><th>Select</th><th>Thumbnail</th><th>Product Name</th><th>Status</th><th>Store Featured</th><th>Orders</th><th>Price</th><th>Category</th><th>Updated Date</th><th>Actions</th></tr>
        <?php foreach($products as $p): $safeDelete = ((int)($p['completed_order_count'] ?? 0) === 0) && in_array($p['status'], ['draft','rejected','archived','disabled','deleted'], true); ?>
           <tr>
               <td><input class="bulk-product-pick" type="checkbox" name="product_ids[]" value="<?=(int)$p['id']?>" form="bulk-product-form" aria-label="Select <?=H::e($p['title'])?>"></td>
               <td><?php if($p['thumbnail']):?><img class="thumb" src="<?=H::e($p['thumbnail'])?>" alt="<?=H::e($p['title'])?> thumbnail"><?php else:?><span class="thumb">No image</span><?php endif;?></td>
               <td><?=H::e($p['title'])?><?php if($p['rejection_reason']):?><br><small>Rejected: <?=H::e($p['rejection_reason'])?></small><?php endif;?><?php if((int)($p['completed_order_count'] ?? 0)>0):?><br><small class="muted">Cannot be permanently deleted because completed orders reference it.</small><?php endif;?></td>
               <td><span class="badge"><?=H::e(($p['status']==='approved'||$p['status']==='published')?'Published':ucwords(str_replace('_',' ',$p['status'])))?></span><?php if(!empty($p['scheduled_publish_at'])):?><br><small><?=H::e((new \DateTimeImmutable($p['scheduled_publish_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($p['publication_timezone']?:'UTC'))->format('Y-m-d H:i T'))?></small><?php endif;?><?php if(!empty($p['schedule_error'])):?><br><small class="notice warning"><?=H::e($p['schedule_error'])?></small><?php endif;?></td>
               <td>
                  <?php if(!empty($p['store_featured'])): ?>
                      <span class="badge ok">⭐ Featured</span>
                  <?php elseif(in_array($p['status'],['approved','published'],true)): ?>
                      <span class="muted">Not featured</span>
                  <?php else: ?>
                      <span class="muted">Publish first</span>
                  <?php endif; ?>
              </td>
              <td><?= (int)($p['completed_order_count'] ?? 0) ?></td>
               <td><?=$p['price']===null?'Needs review':H::money($p['price'])?></td>
               <td><?=H::e($p['category_name']??'Uncategorized')?></td>
               <td><?=H::e($p['updated_at'])?></td>
               <td>
                   <details class="cm-product-menu">
                      <summary>Actions ▾</summary>
                      <div class="product-actions">
                    <a class="btn" href="/seller/product/<?=$p['id']?>">Edit</a>
                    <?php if(in_array($p['status'],['approved','published','scheduled'],true)):?>
                        <form method="post"
                              action="/seller/product/<?=(int)$p['id']?>/unpublish"
                              onsubmit="return confirm('Unpublish this product and save it as a draft? It will no longer be available for new purchases.');">
                            <input type="hidden"
                                   name="_csrf"
                                   value="<?=H::csrf()?>">
                            <button type="submit">
                                Unpublish → Draft
                            </button>
                        </form>
                    <?php endif;?>
                  <?php if(in_array($p['status'],['approved','published'],true)): ?>
                  <form method="post" action="/seller/product/<?=$p['id']?>/store-featured">
                      <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                      <button>
                          <?=!empty($p['store_featured'])
                              ? 'Remove Featured'
                              : 'Feature on Store'?>
                      </button>
                  </form>
                  <?php endif; ?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/duplicate" onsubmit="return confirm('Create a draft copy of this product? Product files and preview images will not be copied.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Duplicate</button></form>
                   <?php if(in_array($p['status'], ['approved','published'], true)):?><a class="btn" href="/product/<?=H::e($p['slug'])?>">View Public Page</a><?php endif;?>
                   <?php if(!in_array($p['status'], ['archived','deleted'], true)):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/archive" onsubmit="return confirm('Archive this product and hide it from public listings? Order history will remain available.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Archive / Hide</button></form>
                   <?php endif;?>
                   <?php if(in_array($p['status'], ['archived','deleted'], true)):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/restore" onsubmit="return confirm('Restore this product as a draft? It must be reviewed before publishing again.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Restore as Draft</button></form>
                   <?php endif;?>
                   <?php if(!in_array($p['status'], ['approved','scheduled','published','archived','deleted'], true)):?><form method="post" action="/seller/product/<?=$p['id']?>/submit"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Submit For Review</button></form><?php endif;?>
                   <?php if($safeDelete):?>
                   <form method="post" action="/seller/product/<?=$p['id']?>/delete" onsubmit="return confirm('Permanently delete this product? This cannot be undone.');"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button>Permanent Delete</button></form>
                   <?php else:?><small class="muted">Permanent delete unavailable; archive instead.</small><?php endif;?>
                   </details></div>
               </td>
           </tr>
        <?php endforeach;?>
    </table></div>
<?php endif; ?>


<style>
.cm-native-product-actions {
    width: 145px;
    max-width: 100%;
    min-height: 40px;
    padding: 9px 12px;
    border: 1px solid #382456;
    border-radius: 10px;
    background: #261842;
    color: #fff;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}
.cm-native-product-actions:focus-visible {
    outline: 3px solid #a855f7;
    outline-offset: 2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('details.cm-product-menu').forEach(function (menu) {
        const actions = menu.querySelector('.product-actions');
        if (!actions) return;

        const items = Array.from(actions.children).filter(function (element) {
            return element.matches('a[href], form') &&
                (element.matches('a') || element.querySelector('button'));
        });

        if (!items.length) return;

        const select = document.createElement('select');
        select.className = 'cm-native-product-actions';
        select.setAttribute('aria-label', 'Product actions');

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Actions ▾';
        select.appendChild(placeholder);

        items.forEach(function (item, index) {
            const control = item.matches('form')
                ? item.querySelector('button')
                : item;

            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = control.textContent.trim();
            select.appendChild(option);
        });

        select.addEventListener('change', function () {
            const index = Number(select.value);
            if (select.value === '') return;

            const item = items[index];
            select.value = '';

            if (item.matches('a')) {
                window.location.assign(item.href);
            } else {
                const button = item.querySelector('button');
                item.requestSubmit(button);
            }
        });

        menu.parentNode.insertBefore(select, menu);
        menu.style.display = 'none';
    });
});
</script>
