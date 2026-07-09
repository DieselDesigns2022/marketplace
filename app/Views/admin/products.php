<h1>Admin → Products</h1>
<p class="muted">Scan thumbnails and approve pending products quickly, or use cleanup tools for pre-live tester data. Archive hides products safely; permanent delete only removes selected draft/test products without completed orders.</p>
<nav class="tabs">
    <?php foreach(['all'=>'All','draft'=>'Draft','pending_review'=>'Pending Review','approved'=>'Published','published'=>'Published Legacy','rejected'=>'Rejected','disabled'=>'Disabled','archived'=>'Archived','deleted'=>'Deleted'] as $key=>$label):?>
        <a class="<?=($status??'pending_review')===$key?'active':''?>" href="/admin/products?status=<?=$key?>"><?=H::e($label)?></a>
    <?php endforeach;?>
</nav>
<form method="post" action="/admin/products?status=<?=H::e($status ?? 'pending_review')?>">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<p><button class="btn" name="action" value="bulk_approve">Approve selected pending products</button> <select name="bulk_action"><option value="archive">Bulk archive / hide selected</option><option value="delete">Bulk permanent delete safe selected products</option></select> <button class="btn alt" formaction="/admin/products/bulk-cleanup" onclick="return confirm('Run the selected cleanup action? Products with completed orders will be archived instead of permanently deleted.');">Run Cleanup</button></p>
<table>
    <tr><th>Select</th><th>Preview</th><th>Product</th><th>Designer</th><th>Category</th><th>Status</th><th>Completed Orders</th><th>Actions</th></tr>
    <?php foreach($products as $p):?>
        <tr>
           <td><input type="checkbox" name="product_ids[]" value="<?=$p['id']?>"></td>
           <td><?php if(!empty($p['thumbnail'])):?><img src="<?=H::e($p['thumbnail'])?>" alt="<?=H::e($p['title'])?> preview" style="width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid var(--line);"><?php else:?><span class="muted">No image</span><?php endif;?></td>
           <td><a href="/admin/products/<?=$p['id']?>"><?=H::e($p['title'])?></a><?php if((int)($p['completed_order_count'] ?? 0)>0):?><br><small class="muted">Order history protected; permanent delete blocked.</small><?php endif;?></td>
           <td><?=H::e($p['display_name'])?></td>
           <td><?=H::e($p['category_name']??'Uncategorized')?></td>
           <td><?=H::e(($p['status']==='approved'||$p['status']==='published')?'Published':ucwords(str_replace('_',' ',$p['status'])))?></td>
           <td><?=(int)($p['completed_order_count'] ?? 0)?></td>
           <td><a href="/admin/products/<?=$p['id']?>">Review</a><?php if($p['status']==='pending_review'):?><br><button class="btn" name="action" value="approve" onclick="this.form.elements['id'].value='<?=$p['id']?>'">Approve</button><?php endif;?></td>
        </tr>
    <?php endforeach;?>
    <?php if(!$products):?><tr><td colspan="8" class="muted">No products found for this status filter.</td></tr><?php endif;?>
</table>
<input type="hidden" name="id" value="">
</form>
