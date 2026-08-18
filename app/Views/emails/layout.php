<?php use App\Core\Helpers as H;$marketing=!empty($data['marketing_preference']);?><!doctype html>
<html lang="en"><body style="margin:0;padding:0;background:#f3f0f7;font-family:Arial,Helvetica,sans-serif;color:#292331;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f0f7;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 6px 24px rgba(45,31,65,.08);">
<tr><td style="padding:22px 28px;background:<?=$marketing?'#2f2043':'#ffffff'?>;text-align:center;border-bottom:1px solid <?=$marketing?'#2f2043':'#ece7f1'?>;">
<a href="<?=H::e(H::baseUrl())?>" style="text-decoration:none;color:<?=$marketing?'#ffffff':'#2f2043'?>;font-size:24px;font-weight:800;letter-spacing:.3px;">Asset <span style="color:<?=$marketing?'#cdb6ee':'#7656a8'?>;">Moth</span></a>
</td></tr><tr><td><?=$content?></td></tr>
<tr><td style="padding:24px 28px;background:#faf8fc;border-top:1px solid #ece7f1;text-align:center;font-size:12px;line-height:1.6;color:#776f80;">Made for creative people and independent designers.<br>Asset Moth · <a href="<?=H::e(H::baseUrl())?>" style="color:#62428f;">Visit the marketplace</a></td></tr>
</table></td></tr></table></body></html>
