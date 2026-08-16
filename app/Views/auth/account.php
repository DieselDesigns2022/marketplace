<header class="dashboard-heading"><div><h1>Account settings</h1><p class="muted">Update your profile and optional marketplace email choices.</p></div></header>
<form method="post" class="card form" style="max-width:760px">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label for="account-name">Name</label>
    <input id="account-name" name="name" maxlength="120" required value="<?=H::e(H::user()['name'])?>">
    <fieldset id="email-preferences">
        <legend><strong>Email Preferences</strong></legend>
        <p class="muted">Choose each optional marketing email independently. Receipts, security, order, refund, payment, and download messages are always unaffected.</p>
        <label><input type="checkbox" name="weekly_emails" value="1" <?=!empty($preferences['weekly_emails'])?'checked':''?>> Weekly Emails</label>
        <small class="muted">A weekly digest of real, currently available marketplace products.</small>
        <label><input type="checkbox" name="monthly_emails" value="1" <?=!empty($preferences['monthly_emails'])?'checked':''?>> Monthly Emails</label>
        <small class="muted">A monthly digest of real, currently available marketplace products.</small>
        <label><input type="checkbox" name="favorite_shop_emails" value="1" <?=!empty($preferences['favorite_shop_emails'])?'checked':''?>> Emails From Favorite/Followed Shops</label>
        <small class="muted">Updates only when shops you currently follow have eligible new products.</small>
    </fieldset>
    <button>Save account settings</button>
</form>
