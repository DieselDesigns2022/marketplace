<header class="dashboard-heading"><div><h1>Account settings</h1><p class="muted">Manage your profile, sign-in details, and optional marketplace emails.</p></div></header>

<form method="post" class="card form" id="profile" style="max-width:760px">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="profile">
    <fieldset><legend><strong>Profile / Name</strong></legend>
        <label for="account-name">Name</label><input id="account-name" name="name" maxlength="120" required autocomplete="name" value="<?=H::e($account['name'])?>">
    </fieldset><button>Save name</button>
</form>

<form method="post" class="card form" id="email-address" style="max-width:760px">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="email">
    <fieldset><legend><strong>Email Address</strong></legend>
        <p class="muted">Current email: <strong><?=H::e($account['email'])?></strong></p>
        <label for="account-email">New email</label><input id="account-email" type="email" name="email" maxlength="190" required autocomplete="email">
        <label for="email-current-password">Current password</label><input id="email-current-password" type="password" name="current_password" required autocomplete="current-password">
    </fieldset><button>Change email</button>
</form>

<form method="post" class="card form" id="change-password" style="max-width:760px">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="password">
    <fieldset><legend><strong>Change Password</strong></legend>
        <label for="password-current">Current password</label><input id="password-current" type="password" name="current_password" required autocomplete="current-password">
        <label for="password-new">New password</label><input id="password-new" type="password" name="new_password" minlength="8" required autocomplete="new-password"><small class="muted">Use at least 8 characters.</small>
        <label for="password-confirm">Confirm new password</label><input id="password-confirm" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
    </fieldset><button>Change password</button>
</form>

<form method="post" class="card form" id="email-preferences" style="max-width:760px">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="action" value="preferences">
    <fieldset><legend><strong>Email Preferences</strong></legend>
        <p class="muted">Choose each optional marketing email independently. Receipts, security, order, refund, payment, and download messages are always unaffected.</p>
        <label><input type="checkbox" name="weekly_emails" value="1" <?=!empty($preferences['weekly_emails'])?'checked':''?>> Weekly Emails</label><small class="muted">A weekly digest of real, currently available marketplace products.</small>
        <label><input type="checkbox" name="monthly_emails" value="1" <?=!empty($preferences['monthly_emails'])?'checked':''?>> Monthly Emails</label><small class="muted">A monthly digest of real, currently available marketplace products.</small>
        <label><input type="checkbox" name="favorite_shop_emails" value="1" <?=!empty($preferences['favorite_shop_emails'])?'checked':''?>> Emails From Favorite/Followed Shops</label><small class="muted">Updates only when shops you currently follow have eligible new products.</small>
    </fieldset><button>Save email preferences</button>
</form>
