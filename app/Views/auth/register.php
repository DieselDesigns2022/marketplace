<h1>Create account</h1>
<?php if (!empty($_SESSION['seller_intent'])): ?>
<section class="notice warning">Creating an account is Step 1. After registration, you still need to complete and submit the seller application before admin approval.</section>
<?php endif; ?>
<form method="post" class="card form">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <input type="hidden" name="ref" value="<?=H::e($referralCode ?? '')?>">
    <?php if(!empty($referralValid)):?><p class="notice success">A referral has been securely attached. Rewards apply after your first eligible paid order.</p><?php elseif(!empty($referralInvalid)):?><p class="notice warning">That referral code is invalid and was discarded. You may still register.</p><?php endif?>
    <label>Name<input name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<span class="password-field"><input id="register-password" type="password" name="password" required><button type="button" class="password-toggle" data-password-toggle data-password-target="register-password" aria-controls="register-password" aria-label="Show password" aria-pressed="false">Show</button></span></label>
    <button class="btn">Register</button>
</form>
