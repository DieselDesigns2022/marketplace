<h1>Apply to Sell</h1>
<section class="card application-intro">
    <h2>Become a designer on Asset Moth</h2>
    <p><strong>Step 1 is creating an account. Step 2 is completing this seller application.</strong> Your account is not submitted for seller approval until this form is complete and submitted.</p>
    <ul>
        <li>Designers can create a public storefront after approval.</li>
        <li>Approved designers can upload digital products for admin review.</li>
        <li>The application form collects your store name, bio, portfolio, design types, and AI-use disclosure.</li>
        <li>Applications are manually reviewed by an admin.</li>
        <li>Only upload original work or work you have the legal right to sell.</li>
        <li>AI disclosure is required on product submissions.</li>
    </ul>
</section>
<?php if (!empty($application) && $application['status'] === 'pending'): ?>
<section class="card status-card status-pending">
    <span class="badge pending">Pending Review</span>
    <h2>Application status: Pending Review</h2>
    <p>Your application is waiting for admin review.</p>
    <p>
    <strong>Display name:</strong>
    <?=H::e($application['display_name'])?>
    </p>
    <p>
    <strong>Desired store URL:</strong> /store/<?=H::e($application['desired_slug'])?>
    </p>
    <p>
    <strong>Submitted:</strong>
    <?=H::e($application['created_at'])?>
    </p>
</section>
<?php elseif (!empty($application) && $application['status'] === 'approved'): ?>
    <section class="card status-card status-approved">
        <span class="badge ok">Approved</span>
        <h2>Application status: Approved</h2>
        <p>Your designer application has been approved. You can now access your seller dashboard.</p>
        <a class="btn" href="/seller">Go to Seller Dashboard</a>
        <a class="btn alt" href="/store/<?=H::e($application['desired_slug'])?>">View public storefront</a>
    </section>
<?php else: ?>
    <?php if (!empty($application) && $application['status'] === 'denied'): ?>
    <section class="card status-card status-denied">
        <span class="badge no">Denied</span>
        <h2>Application status: Denied</h2>
        <p>Your application was denied. Please review the reason below before applying again.</p>
        <p>
        <strong>Reason:</strong>
        <?=H::e($application['denial_reason'] ?: 'No reason provided.')?>
        </p>
    </section>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="notice error">
    <strong>Please fix these issues:</strong>
    <ul>
        <?php foreach($errors as $error):?>
           <li>
           <?=H::e($error)?>
           </li>
        <?php endforeach;?>
    </ul>
</div>
<?php endif; ?>
<form method="post" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label>Display Name <small>Public designer/store name, e.g. Diesel Designs.</small>
    <input name="display_name" required value="<?=H::e($values['display_name']??'')?>">
    </label>
    <label>Store URL Name <small>This becomes your store link. Use lowercase letters, numbers, and hyphens only. Example: spooky-designs</small>
    <input name="desired_slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?=H::e($values['desired_slug']??'')?>">
    </label>
    <label>Bio <small>Minimum 25 characters. This helps us understand your work and seeds your storefront.</small>
    <textarea name="bio" required minlength="25">
    <?=H::e($values['bio']??'')?>
    </textarea>
    </label>
    <label>Portfolio URL <small>Website, Etsy, Shopify, Facebook group, Instagram, or other portfolio link.</small>
    <input name="portfolio_url" type="url" value="<?=H::e($values['portfolio_url']??'')?>">
    </label>
    <label>Social Links <small>Paste multiple links if you want.</small>
    <textarea name="social_links">
    <?=H::e($values['social_links']??'')?>
    </textarea>
    </label>
    <label>What type of designs do you sell? <small>Examples: SVG files, print-ready PNG designs, seamless patterns, Canva templates, fonts, Procreate brushes/stamps, clipart, mockups, digital papers, other.</small>
    <textarea name="design_types" required>
    <?=H::e($values['design_types']??'')?>
    </textarea>
    </label>
    <label>Do you use AI in your design process?<select name="uses_ai" required>
    <option value="">Select one</option>
    <?php foreach(['No','Yes, AI assisted','Yes, AI generated','Sometimes'] as $option):?>
        <option value="<?=$option?>" <?=($values['uses_ai']??'')===$option?'selected':''?>>
        <?=$option?>
        </option>
    <?php endforeach;?>
    </select>
    </label>
    <label class="check">
    <input type="checkbox" name="agreement" required <?=!empty($values['agreement'])?'checked':''?>> I understand that I may only sell original designs or designs I have the legal right to sell. I understand that products will require admin approval before going live.</label>
    <button class="btn">Submit Application</button>
</form>
<?php endif; ?>
