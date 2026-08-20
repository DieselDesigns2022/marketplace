<?php

use App\Core\Helpers as H;

$products = is_array($data['products'] ?? null) ? $data['products'] : [];
$favoriteShop = ($data['marketing_preference'] ?? '') === 'favorite_shop';

$designers = [];

foreach ($products as $product) {
    $designerId = (int)($product['designer_id'] ?? 0);

    if (!isset($designers[$designerId])) {
        $designers[$designerId] = [
            'display_name' => $product['display_name'] ?? 'Asset Moth Designer',
            'store_slug'   => $product['store_slug'] ?? '',
            'products'     => [],
        ];
    }

    if (count($designers[$designerId]['products']) < 9) {
        $designers[$designerId]['products'][] = $product;
    }
}

foreach ($designers as $designer):
    $designerName = (string)$designer['display_name'];
    $storeSlug = (string)$designer['store_slug'];
    $shopUrl = H::baseUrl() . '/store/' . rawurlencode($storeSlug);
?>
<div style="padding:22px 24px 6px;">

    <div style="
        margin:0 0 16px;
        padding-bottom:10px;
        border-bottom:3px solid #67e8c9;
        font-size:18px;
        font-weight:800;
        letter-spacing:.6px;
        color:#231942;
        text-transform:uppercase;
    ">
        <?php if ($favoriteShop && $storeSlug !== ''): ?>
            <a href="<?=H::e($shopUrl)?>" style="color:#231942;text-decoration:none;">
                NEW FROM <?=H::e($designerName)?>
            </a>
        <?php else: ?>
            NEW FROM <?=H::e($designerName)?>
        <?php endif; ?>
    </div>

    <table role="presentation"
           width="100%"
           cellspacing="0"
           cellpadding="0"
           border="0"
           style="width:100%;border-collapse:collapse;">
        <?php
        $designerProducts = $designer['products'];
        $chunks = array_chunk($designerProducts, 3);

        foreach ($chunks as $row):
        ?>
        <tr>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <?php if (isset($row[$i])):
                    $product = $row[$i];

                    $productUrl = H::baseUrl()
                        . '/product/'
                        . rawurlencode((string)$product['slug']);

                    $imageUrl = H::assetUrl($product['preview_image'] ?? null);

                    $hasSecureImage =
                        $imageUrl !== ''
                        && str_starts_with(strtolower($imageUrl), 'https://');
                ?>
                <td width="33.33%"
                    valign="top"
                    style="width:33.33%;padding:0 6px 14px;">

                    <table role="presentation"
                           width="100%"
                           cellspacing="0"
                           cellpadding="0"
                           border="0"
                           style="
                               width:100%;
                               border:1px solid #eeeeee;
                               border-radius:12px;
                               overflow:hidden;
                               background:#ffffff;
                           ">

                        <tr>
                            <td style="background:#fffafc;text-align:center;">
                                <a href="<?=H::e($productUrl)?>" style="display:block;text-decoration:none;">
                                    <?php if ($hasSecureImage): ?>
                                        <img
                                            src="<?=H::e($imageUrl)?>"
                                            alt="<?=H::e($product['title'])?> preview"
                                            width="180"
                                            style="
                                                display:block;
                                                width:100%;
                                                max-width:180px;
                                                height:150px;
                                                object-fit:cover;
                                                border:0;
                                                margin:0 auto;
                                            "
                                        >
                                    <?php else: ?>
                                        <div style="
                                            padding:54px 8px;
                                            color:#ff6b9f;
                                            font-size:12px;
                                            font-weight:700;
                                            line-height:1.4;
                                        ">
                                            ASSET MOTH<br>
                                            <span style="font-weight:400;color:#6b6478;">
                                                Preview coming soon
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:12px 10px 14px;text-align:center;">

                                <div style="
                                    min-height:40px;
                                    margin-bottom:8px;
                                    font-size:14px;
                                    font-weight:700;
                                    line-height:1.35;
                                ">
                                    <a href="<?=H::e($productUrl)?>"
                                       style="color:#231942;text-decoration:none;">
                                        <?=H::e($product['title'])?>
                                    </a>
                                </div>

                                <div style="
                                    margin-bottom:10px;
                                    font-size:15px;
                                    font-weight:800;
                                    color:#231942;
                                ">
                                    <?=H::money($product['price'])?>
                                </div>

                                <a href="<?=H::e($productUrl)?>"
                                   style="
                                       display:inline-block;
                                       padding:8px 12px;
                                       border-radius:999px;
                                       background:#ff6b9f;
                                       color:#ffffff;
                                       text-decoration:none;
                                       font-size:11px;
                                       font-weight:700;
                                   ">
                                    View Product
                                </a>

                            </td>
                        </tr>

                    </table>
                </td>

                <?php else: ?>

                <td width="33.33%"
                    style="width:33.33%;padding:0 6px 14px;">
                    &nbsp;
                </td>

                <?php endif; ?>
            <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
    </table>

</div>
<?php endforeach; ?>
