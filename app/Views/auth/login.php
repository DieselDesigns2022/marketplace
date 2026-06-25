<h1>Login</h1>
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
</form>
<a href="/forgot-password">Forgot password?</a>
