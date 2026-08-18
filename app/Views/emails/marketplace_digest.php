<?php use App\Core\Helpers as H;?>
<div style="padding:34px 32px 12px;text-align:center;">
    <div style="font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#7656a8;"><?=H::e(ucfirst((string)$data['frequency']))?> edit</div>
    <h1 style="margin:10px 0 12px;font-size:30px;line-height:1.2;color:#211a2e;">Fresh finds for your next idea</h1>
    <p style="margin:0;color:#625b6d;font-size:16px;line-height:1.6;">Hello <?=H::e($data['name']??'there')?> — discover the newest digital goods from Asset Moth's independent designers.</p>
</div>
<?php require app_path('app/Views/emails/product_cards.php');?>
<div style="padding:8px 32px 32px;text-align:center;font-size:13px;line-height:1.7;color:#766f7e;">
    <a href="<?=H::e($data['manage_preferences_url'])?>" style="color:#62428f;font-weight:700;">Manage Email Preferences</a><br>
    <a href="<?=H::e($data['unsubscribe_url'])?>" style="color:#766f7e;">Unsubscribe from <?=H::e($data['frequency'])?> emails</a>
</div>
