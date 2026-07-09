<h1>Create account</h1>
<?php if (!empty($_SESSION['seller_intent'])): ?>
<section class="notice warning">Creating an account is Step 1. After registration, you still need to complete and submit the seller application before admin approval.</section>
<?php endif; ?>
<form method="post" class="card form">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label>Name<input name="name" required>
    </label>
    <label>Email<input type="email" name="email" required>
    </label>
    <label>Password<input type="password" name="password" required>
    </label>
    <button class="btn">Register</button>
</form>
