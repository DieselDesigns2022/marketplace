<?php use App\Core\Helpers as H;$marketing=!empty($data['marketing_preference']);?><!doctype html>
<html lang="en"><body style="margin:0;padding:0;background:#fff7fb;font-family:Arial,Helvetica,sans-serif;color:#231942;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#fff7fb;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #eeeeee;">
<tr><td style="padding:18px 28px;background:#ffffff;text-align:center;border-top:6px solid #ff6b9f;border-bottom:1px solid #eeeeee;">
<a href="<?=H::e(H::baseUrl())?>" style="text-decoration:none;">
<img src="<?=H::e(H::assetUrl('assets/img/asset-moth-logo.png'))?>" alt="Asset Moth" width="190" style="display:block;margin:0 auto;max-width:190px;width:100%;height:auto;border:0;">
</a>
</td></tr>
<tr><td><?=$content?></td></tr>
<tr><td style="padding:24px 28px;background:#fffafc;border-top:4px solid #67e8c9;text-align:center;font-size:12px;line-height:1.6;color:#6b6478;">Made for creative people and independent designers.<br>Asset Moth · <a href="<?=H::e(H::baseUrl())?>" style="color:#6d28d9;">Visit the marketplace</a></td></tr>
</table></td></tr></table></body></html>
