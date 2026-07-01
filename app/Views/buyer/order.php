<h1>Order #<?=$order['id']?>
</h1>
<p>Status: <?=H::e($order['status'])?> · Total: <?=H::money($order['total'])?> · Date: <?=$order['created_at']?>
</p>
<table>
    <tr>
        <th>Product</th>
        <th>License</th>
        <th>Price</th>
        <th>Download</th>
    </tr>
    <?php foreach($items as $i):?>
        <tr>
           <td>
           <a href="/product/<?=H::e($i['slug'])?>">
           <?=H::e($i['title'])?>
           </a>
           </td>
           <td>
           <?=H::e($i['license_name'] ?: $i['license_type'])?><?php if(!empty($i['license_description'])):?><br><span class="muted"><?=H::e($i['license_description'])?></span><?php endif;?>
           </td>
           <td>
           <?=H::money($i['total_price'])?>
           </td>
           <td>
           <?php if($i['file_id']):?>
               <a class="btn" href="/download/<?=$i['file_id']?>">Download</a>
           <?php else:?>
               <span class="muted">No file</span>
           <?php endif;?>
           </td>
        </tr>
    <?php endforeach;?>
</table>
<p>
<a href="/dashboard/purchases">Back to purchases</a>
</p>
