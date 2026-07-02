<h1>Order #<?=$order['id']?>
</h1>
<p>Buyer: <?=H::e($order['buyer_name'])?> (<?=H::e($order['buyer_email'])?>)</p>
<p>Status: <?=H::e($order['status'])?> · Total: <?=H::money($order['total'])?>
</p>
<table>
    <tr>
        <th>Product</th>
        <th>Designer</th>
        <th>Purchased permissions</th>
        <th>Item Total</th>
        <th>Commission</th>
        <th>Seller Earning</th><th>Fulfillment</th><th>Admin override</th>
    </tr>
    <?php foreach($items as $i):?>
        <tr>
           <td>
           <?=H::e($i['title'])?>
           </td>
           <td>
           <?=H::e($i['designer_name'])?> (<?=H::e($i['designer_email'])?>)</td>
           <td>
           <?=H::e($i['license_name'] ?: $i['license_type'])?><?php if(!empty($i['license_description'])):?><br><span class="muted"><?=H::e($i['license_description'])?></span><?php endif;?>
           </td>
           <td>
           <?=H::money($i['total_price'])?>
           </td>
           <td>
           <?=H::money($i['commission_amount']??($i['total_price']*$i['commission_rate']))?>
           </td>
           <td>
           <?=H::money($i['seller_earning']??($i['total_price']-($i['total_price']*$i['commission_rate'])))?>
           </td>
           <td><?=H::e($i['fulfillment_type'] ?? 'downloadable')?><?php if(($i['fulfillment_type'] ?? '')==='google_drive'):?><br>Email: <?=H::e($i['buyer_google_drive_email'] ?: 'Needed')?><br>Status: <?=H::e(str_replace('_',' ', $i['manual_delivery_status']))?><?php endif;?></td>
           <td><?php if(($i['fulfillment_type'] ?? '')==='google_drive'):?><form method="post"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="override_fulfillment"><input type="hidden" name="order_item_id" value="<?=$i['id']?>"><select name="manual_delivery_status"><?php foreach(['pending_delivery','buyer_email_needed','ready_for_seller_delivery','delivered','cancelled_refunded'] as $st):?><option value="<?=$st?>" <?=$i['manual_delivery_status']===$st?'selected':''?>><?=H::e(str_replace('_',' ',$st))?></option><?php endforeach;?></select><input name="delivery_notes" value="<?=H::e($i['delivery_notes'] ?? '')?>"><button>Update</button></form><?php endif;?></td>
        </tr>
    <?php endforeach;?>
</table>
<p>
<a href="/admin/orders">Back to orders</a>
</p>
