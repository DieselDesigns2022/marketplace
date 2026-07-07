<h1>Admin → Products</h1>
<p class="muted">Use these tools for pre-live tester cleanup and test data reset. Archive hides products safely; permanent delete only removes selected draft/test products without completed orders.</p>
<nav class="tabs">
    <?php foreach(['all'=>'All','draft'=>'Draft','pending_review'=>'Pending Review','approved'=>'Published','published'=>'Published Legacy','rejected'=>'Rejected','disabled'=>'Disabled','archived'=>'Archived','deleted'=>'Deleted'] as $key=>$label):?>
        <a class="<?=($status??'pending_review')===$key?'active':''?>" href="/admin/products?status=<?=$key?>"><?=H::e($label)?></a>
    <?php endforeach;?>
</nav>
<form method="post" action="/admin/products/bulk-cleanup" onsubmit="return confirm('Run the selected cleanup action? Products with completed orders will be archived instead of permanently deleted.');">
<input type="hidden" name="_csrf" value="<?=H::csrf()?>">
<p><select name="bulk_action"><option value="archive">Bulk archive / hide selected</option><option value="delete">Bulk permanent delete safe selected products</option></select> <button class="btn">Run Cleanup</button></p>
<table>
    <tr><th>Select</th><th>Product</th><th>Designer</th><th>Category</th><th>Status</th><th>Completed Orders</th><th>Actions</th></tr>
    <?php foreach($products as $p):?>
        <tr>
           <td><input type="checkbox" name="product_ids[]" value="<?=$p['id']?>"></td>
           <td><a href="/admin/products/<?=$p['id']?>"><?=H::e($p['title'])?></a><?php if((int)($p['completed_order_count'] ?? 0)>0):?><br><small class="muted">Order history protected; permanent delete blocked.</small><?php endif;?></td>
           <td><?=H::e($p['display_name'])?></td>
           <td><?=H::e($p['category_name']??'Uncategorized')?></td>
           <td><?=H::e(($p['status']==='approved'||$p['status']==='published')?'Published':ucwords(str_replace('_',' ',$p['status'])))?></td>
           <td><?=(int)($p['completed_order_count'] ?? 0)?></td>
           <td><a href="/admin/products/<?=$p['id']?>">Review</a></td>
        </tr>
    <?php endforeach;?>
    <?php if(!$products):?><tr><td colspan="7" class="muted">No products found for this status filter.</td></tr><?php endif;?>
</table>
</form>
