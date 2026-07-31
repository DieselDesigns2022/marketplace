<h1>Order #<?=$order['id']?>
</h1>
<section class="card"><h2>Payment breakdown</h2>
<p>Subtotal: <?=H::money($order['subtotal'])?><br>Coupon discount: −<?=H::money($order['coupon_discount']??0)?><br>Tax: +<?=H::money($order['tax_amount']??0)?><br>Store credit redeemed: −<?=H::money($order['credits_applied']??0)?><br>Stripe paid: <?=H::money($order['stripe_paid_amount']??0)?><br><strong>Final order total: <?=H::money($order['total'])?></strong></p>
<p>Credit status: <?=H::e(['none'=>'Not used','reserved'=>'Reserved','finalized'=>'Redeemed','released'=>'Released'][$order['credit_payment_status']??'none']??'Unknown')?> · Completion: <?=!empty($order['internally_completed'])?'Completed internally with store credit':'Completed through Stripe'?></p></section>
<p>Status: <?=H::e($order['status'])?> · Payment: <strong><?=H::e($order['payment_status'] ?? $order['status'])?></strong> · Total: <?=H::money($order['total'])?> · Date: <?=$order['created_at']?>
</p>
<?php if(!empty($order['coupon_code'])):?><p>Coupon <?=H::e($order['coupon_code'])?> saved <?=H::money($order['coupon_discount'] ?? 0)?>.</p><?php endif;?>
<?php foreach($sellerGroups as $group): $receiptImage=\App\Services\SellerReceiptService::safePublicPath($group['receipt_image_path']??null);?><section class="card seller-receipt-group"><h2>Items from <?=H::e($group['seller_name'])?></h2><?php if($receiptImage):?><img class="receipt-image" src="<?=H::e(H::assetUrl($receiptImage))?>" alt="Receipt image from <?=H::e($group['seller_name'])?>"><?php endif;?><?php if($group['receipt_note']):?><p><strong>Message from the seller (not Asset Moth)</strong><br><?=nl2br(H::e($group['receipt_note']))?></p><?php endif;?><div class="responsive-table"><table>
    <tr>
        <th>Product</th>
        <th>Purchased permissions</th>
        <th>Price</th>
        <th>Fulfillment</th><th>Download / Delivery</th>
    </tr>
    <?php foreach($group['items'] as $i):?>
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
             <?php $paymentStatus = $order['payment_status'] ?? ''; $downloadExpired = !empty($i['download_expires_at']) && strtotime($i['download_expires_at']) < time(); $downloadEligible = $i['file_id'] && !empty($i['file_available']) && $paymentStatus === 'paid' && !$downloadExpired; ?>
             <?php if($paymentStatus==='refunded'):?><span class="muted">Refunded — download unavailable.</span><?php elseif($downloadEligible):?><a class="btn" href="/download/<?=$i['file_id']?>">Download</a><?php elseif($downloadExpired):?><span class="muted">Download access has expired.</span><?php elseif($paymentStatus==='paid'):?><span class="muted">File unavailable.</span><?php else:?><span class="muted">Download access unlocks after Stripe webhook payment confirmation.</span><?php endif;?>
             <br><span class="muted">Downloads: <?=number_format((int)($i['download_count'] ?? 0))?></span>
           <?php else:?>
             <span>Status: <?=H::e(str_replace('_',' ', $i['manual_delivery_status']))?></span><br>
             <?php if(($order['payment_status'] ?? $order['status']) === 'paid'):?><span class="muted">Google Drive email: <?=H::e($i['buyer_google_drive_email'] ?: 'Needed')?></span><?php else:?><span class="muted">Google Drive delivery details unlock after payment clears.</span><?php endif;?>
           <?php endif;?>
           </td>
        </tr>
    <?php endforeach;?>
</table></div></section><?php endforeach;?>
<?php $orderPaymentStatus = $order['payment_status'] ?? $order['status']; ?>
<?php if($orderPaymentStatus === 'manual_review'):?>
  <p class="notice warning">This payment needs admin review before another payment attempt can be made.</p>
<?php elseif(!in_array($orderPaymentStatus, ['paid','refunded','partially_refunded'], true)):?>
  <form method="post" action="/checkout/retry/<?=$order['id']?>"><input type="hidden" name="_csrf" value="<?=H::csrf()?>"><button class="btn">Retry payment</button></form>
<?php endif;?>
<p>
<a href="/dashboard/purchases">Back to purchases</a>
</p>
