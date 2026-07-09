<h1>Login</h1>
<?php if (!empty($_SESSION['seller_intent'])): ?>
<section class="notice warning">Log in to continue applying to sell. Account access is Step 1; the seller application and onboarding still need to be completed before approval.</section>
<?php endif; ?>
<?php if(!empty($error)):?>
    <p class="error">
    <?=$error?>
    </p>
<?php endif;?>
<form method="post" class="card form">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label>Email<input type="email" name="email">
    </label>
    <label>Password<input type="password" name="password">
    </label>
    <button class="btn">Login</button>
    <div style="border-top:1px solid var(--line); padding-top:14px; text-align:center;">
        <p class="muted" style="margin:0 0 10px;">New to Asset Moth?</p>
        <a class="btn alt" href="/register">Create account</a>
    </div>
    <p style="margin:0; text-align:center;"><a href="/forgot-password">Forgot password?</a></p>
</form>
