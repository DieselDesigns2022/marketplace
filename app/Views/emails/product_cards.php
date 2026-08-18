<?php
use App\Core\Helpers as H;
$favoriteShop = ($data['marketing_preference'] ?? '') === 'favorite_shop';
?>
<div style="padding:20px 24px;">
<?php foreach ($data['products'] as $product):
    $productUrl=H::baseUrl().'/product/'.rawurlencode((string)$product['slug']);
    $shopUrl=H::baseUrl().'/store/'.rawurlencode((string)($product['store_slug']??''));
    $imageUrl=H::assetUrl($product['preview_image']??null);
    $hasSecureImage=$imageUrl!==''&&str_starts_with(strtolower($imageUrl),'https://');
?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 18px;border:1px solid #e8e1ee;border-radius:14px;overflow:hidden;background:#ffffff;">
<tr>
<td width="42%" valign="middle" style="width:42%;background:#eee8f4;text-align:center;">
<a href="<?=H::e($productUrl)?>" style="text-decoration:none;display:block;">
<?php if($hasSecureImage):?><img src="<?=H::e($imageUrl)?>" width="245" alt="<?=H::e($product['title'])?> preview" style="display:block;width:100%;max-width:245px;height:190px;object-fit:cover;border:0;">
<?php else:?><span style="display:block;padding:72px 10px;color:#7656a8;font-size:13px;font-weight:700;line-height:1.4;">ASSET MOTH<br><span style="font-weight:400;color:#756c7d;">Preview coming soon</span></span><?php endif;?>
</a></td>
<td valign="middle" style="padding:22px 20px;">
<div style="margin-bottom:7px;font-size:19px;font-weight:700;line-height:1.3;"><a href="<?=H::e($productUrl)?>" style="color:#292331;text-decoration:none;"><?=H::e($product['title'])?></a></div>
<div style="margin-bottom:12px;font-size:13px;color:#756c7d;">by <?php if($favoriteShop):?><a href="<?=H::e($shopUrl)?>" style="color:#62428f;font-weight:700;text-decoration:none;"><?=H::e($product['display_name'])?></a><?php else:?><?=H::e($product['display_name'])?><?php endif;?></div>
<div style="margin-bottom:16px;font-size:18px;font-weight:800;color:#3b2b4e;"><?=H::money($product['price'])?></div>
<a href="<?=H::e($productUrl)?>" style="display:inline-block;padding:11px 17px;border-radius:8px;background:#7656a8;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;">View Product</a>
</td></tr></table>
<?php endforeach;?>
</div>
