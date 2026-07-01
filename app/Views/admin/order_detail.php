<h1>Order #<?=$order['id']?>
</h1>
<p>Buyer: <?=H::e($order['buyer_name'])?> (<?=H::e($order['buyer_email'])?>)</p>
<p>Status: <?=H::e($order['status'])?> · Total: <?=H::money($order['total'])?>
</p>
<table>
    <tr>
        <th>Product</th>
        <th>Designer</th>
        <th>Included permissions</th>
        <th>Item Total</th>
        <th>Commission</th>
        <th>Seller Earning</th>
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
        </tr>
    <?php endforeach;?>
</table>
<p>
<a href="/admin/orders">Back to orders</a>
</p>
