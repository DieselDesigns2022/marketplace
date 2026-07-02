<h1>Purchases</h1>
<?php if(!$orders):?>
    <p>You have not purchased anything yet.</p>
<?php else:?>
    <table>
        <tr>
           <th>Order</th>
           <th>Date</th>
           <th>Products</th>
           <th>Total</th><th>Payment</th>
           <th>
           </th>
        </tr>
        <?php foreach($orders as $o):?>
           <tr>
               <td>#<?=$o['id']?>
               </td>
               <td>
               <?=$o['created_at']?>
               </td>
               <td>
               <?=H::e($o['product_titles'])?>
               </td>
               <td>
               <?=H::money($o['total'])?>
               </td><td><?=H::e($o['payment_status'] ?? $o['status'])?></td>
               <td>
               <a class="btn" href="/dashboard/order/<?=$o['id']?>">View order</a>
               </td>
           </tr>
        <?php endforeach;?>
    </table>
<?php endif;?>
