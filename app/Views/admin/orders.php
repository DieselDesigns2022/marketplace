<h1>Orders</h1>
<table>
    <tr>
        <th>Order</th>
        <th>Buyer</th>
        <th>Status</th>
        <th>Processor</th>
        <th>Total</th>
        <th>Date</th>
        <th>
        </th>
    </tr>
    <?php foreach($orders as $o):?>
        <tr>
           <td>#<?=$o['id']?>
           </td>
           <td>
           <?=H::e($o['buyer_email'])?>
           </td>
           <td>
           <?=H::e($o['status'])?>
           </td>
           <td>
           <?=H::e($o['payment_processor']??$o['payment_mode']??'mock')?>
           </td>
           <td>
           <?=H::money($o['total'])?>
           </td>
           <td>
           <?=$o['created_at']?>
           </td>
           <td>
           <a href="/admin/order/<?=$o['id']?>">View</a>
           </td>
        </tr>
    <?php endforeach;?>
</table>
