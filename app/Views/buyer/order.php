<h1>Order #<?=$order['id']?>
</h1>
<p>Status: <?=H::e($order['status'])?> · Payment: <strong><?=H::e($order['payment_status'] ?? $order['status'])?></strong> · Total: <?=H::money($order['total'])?> · Date: <?=$order['created_at']?>
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
           <?php if(!empty($i['preview_image'])):?><a href="/product/<?=H::e($i['slug'])?>"><img src="<?=H::assetUrl($i['preview_image'])?>" alt="<?=H::e($i['title'])?>" style="width:72px;height:72px;object-fit:cover;border-radius:12px;display:block;margin-bottom:8px;"></a><?php endif;?>
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
             <?php $paymentStatus = $order['payment_status'] ?? ''; $downloadEligible = $i['file_id'] && $paymentStatus === 'paid'; ?>
             <?php if($downloadEligible):?><a class="btn" href="/download/<?=$i['file_id']?>">Download</a><?php else:?><span class="muted">Download access unlocks after Stripe webhook payment confirmation.</span><?php endif;?>
             <br><span class="muted">Downloads: <?=number_format((int)($i['download_count'] ?? 0))?></span>
           <?php else:?>
             <span>Status: <?=H::e(str_replace('_',' ', $i['manual_delivery_status']))?></span><br>
             <?php if(($order['payment_status'] ?? $order['status']) === 'paid'):?><span class="muted">Google Drive email: <?=H::e($i['buyer_google_drive_email'] ?: 'Needed')?></span><?php else:?><span class="muted">Google Drive delivery details unlock after payment clears.</span><?php endif;?>
           <?php endif;?>
           </td>
        </tr>
    <?php endforeach;?>
</table>
<?php $orderPaymentStatus = $order['payment_status'] ?? $order['status']; ?>
<?php if($orderPaymentStatus === 'manual_review'):?>
  <p class="notice warning">This payment needs admin review before another payment attempt can be made.</p>
<?php elseif(!in_array($orderPaymentStatus, ['paid','refunded','partially_refunded'], true)):?>
  <form method="post" action="/checkout/retry/<?=$order['id']?>"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="btn">Retry payment</button></form>
<?php endif;?>
<p>
<a href="/dashboard/purchases">Back to purchases</a>
</p>
