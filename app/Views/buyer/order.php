<h1>Order #<?=$order['id']?>
</h1>
<p>Status: <?=H::e($order['status'])?> · Total: <?=H::money($order['total'])?> · Date: <?=$order['created_at']?>
</p>
<table>
    <tr>
        <th>Product</th>
        <th>Purchased permissions</th>
        <th>Price</th>
        <th>Fulfillment</th><th>Download / Delivery</th>
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
           <td><?=($i['fulfillment_type']==='google_drive')?'Google Drive / Manual Delivery':'Downloadable Product'?></td>
           <td>
           <?php if(($i['fulfillment_type'] ?? 'downloadable')==='downloadable'):?>
             <?php if($i['file_id'] && in_array($order['status'], ['paid','fulfilled','completed'], true)):?><a class="btn" href="/download/<?=$i['file_id']?>">Download</a><?php else:?><span class="muted">Download access begins after future Phase 10 payment completion.</span><?php endif;?>
             <br><span class="muted">Downloads: <?=number_format((int)($i['download_count'] ?? 0))?></span>
           <?php else:?>
             <span>Status: <?=H::e(str_replace('_',' ', $i['manual_delivery_status']))?></span><br>
             <span class="muted">Google Drive email: <?=H::e($i['buyer_google_drive_email'] ?: 'Needed')?></span>
           <?php endif;?>
           </td>
        </tr>
    <?php endforeach;?>
</table>
<p>
<a href="/dashboard/purchases">Back to purchases</a>
</p>
