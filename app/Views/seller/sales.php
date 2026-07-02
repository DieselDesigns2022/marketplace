<h1>Sales</h1>
<table>
    <tr>
        <th>Product</th>
        <th>Buyer</th>
        <th>Total</th>
        <th>Fulfillment</th>
        <th>Delivery</th>
        <th>Date</th>
    </tr>
    <?php foreach($sales as $s):?>
        <tr>
           <td>
           <?=H::e($s['product_title'] ?: ('Product #'.$s['product_id']))?>
           </td>
           <td>
           <?=H::e($s['email'])?>
           </td>
           <td>
           <?=H::money($s['total_price'])?>
           </td>
           <td><?=H::e(($s['fulfillment_type'] ?? '') === 'google_drive' ? 'Google Drive / Manual Delivery' : 'Downloadable Product')?></td>
           <td><?php if(($s['fulfillment_type'] ?? '')==='google_drive'):?><?=H::e(str_replace('_',' ',$s['manual_delivery_status'] ?? ''))?><?php else:?><span class="muted">Not manual delivery</span><?php endif;?></td>
           <td><?=$s['created_at']?> · <a href="/seller/order-item/<?=$s['id']?>">View</a></td>
        </tr>
    <?php endforeach;?>
</table>
