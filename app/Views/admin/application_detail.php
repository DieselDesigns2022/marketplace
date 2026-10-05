<?php $referral = $referral ?? null; ?>

<h1>Review Designer Application</h1>

<style>
.application-review-card {
    padding: 28px;
}

.application-review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.application-review-header h2 {
    margin: 0;
}

.application-review-subtitle {
    margin: 6px 0 0;
    color: #6b6780;
    font-size: 0.96rem;
}

.application-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 26px;
}

.application-meta-item {
    background: #faf8ff;
    border: 1px solid #e5dcf7;
    border-radius: 14px;
    padding: 14px 16px;
}

.application-meta-item.wide {
    grid-column: 1 / -1;
}

.application-meta-label {
    display: block;
    font-size: 0.86rem;
    font-weight: 700;
    color: #6b53a6;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.application-meta-value {
    font-size: 1rem;
    line-height: 1.45;
    color: #2e2350;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.application-meta-value a {
    word-break: break-word;
    overflow-wrap: anywhere;
}

.application-referral-name {
    font-weight: 700;
}

.application-referral-note {
    color: #6b6780;
    font-size: 0.94rem;
}

.application-section {
    margin-top: 22px;
}

.application-section h3 {
    margin-bottom: 8px;
}

.application-copy {
    margin: 0;
    line-height: 1.65;
    word-break: break-word;
    overflow-wrap: anywhere;
}
</style>

<section class="card status-card status-<?=H::e($app['status'])?> application-review-card">

  <div class="application-review-header">
    <div>
      <h2>
        <?=H::e($app['display_name'])?>
        <span class="badge"><?=H::e($app['status'])?></span>
      </h2>
      <p class="application-review-subtitle">
        Seller application overview
      </p>
    </div>
  </div>

  <div class="application-meta-grid">
    <div class="application-meta-item">
      <span class="application-meta-label">User name</span>
      <div class="application-meta-value"><?=H::e($app['user_name'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">User email</span>
      <div class="application-meta-value"><?=H::e($app['user_email'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Display name</span>
      <div class="application-meta-value"><?=H::e($app['display_name'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Desired slug</span>
      <div class="application-meta-value">/store/<?=H::e($app['desired_slug'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Portfolio URL</span>
      <div class="application-meta-value">
        <?php if(trim((string)$app['portfolio_url']) !== ''): ?>
          <a
            href="<?=H::e($app['portfolio_url'])?>"
            target="_blank"
            rel="noopener noreferrer"
          ><?=H::e($app['portfolio_url'])?></a>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">AI usage</span>
      <div class="application-meta-value"><?=H::e($app['uses_ai'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Agreement confirmation</span>
      <div class="application-meta-value"><?=$app['agreement'] ? 'Yes' : 'No'?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Current status</span>
      <div class="application-meta-value"><?=H::e($app['status'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Created</span>
      <div class="application-meta-value"><?=H::e($app['created_at'])?></div>
    </div>

    <div class="application-meta-item">
      <span class="application-meta-label">Updated</span>
      <div class="application-meta-value"><?=H::e($app['updated_at'])?></div>
    </div>

    <div class="application-meta-item wide">
      <span class="application-meta-label">Referral</span>
      <div class="application-meta-value">
        <?php if(!empty($referral)): ?>
          <div class="application-referral-name">
            <?=H::e($referral['referrer_store_name'] ?: $referral['referrer_name'])?>
          </div>

          <?php if(!empty($referral['referrer_store_slug'])): ?>
            <div class="application-referral-note">
              /store/<?=H::e($referral['referrer_store_slug'])?>
            </div>
          <?php endif; ?>

          <div><?=H::e($referral['referrer_email'])?></div>

          <div class="application-referral-note" style="margin-top:6px">
            Status: <?=H::e($referral['referral_status'])?>
            <?php if(!empty($referral['seller_status'])): ?>
              · Seller: <?=H::e($referral['seller_status'])?>
            <?php endif; ?>
          </div>
        <?php else: ?>
          No referral attached
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="application-section">
    <h3>Bio</h3>
    <p class="application-copy"><?=nl2br(H::e($app['bio']))?></p>
  </div>

  <div class="application-section">
    <h3>Social Links</h3>
    <p class="application-copy"><?=nl2br(H::e($app['social_links']))?></p>
  </div>

  <div class="application-section">
    <h3>Design Types</h3>
    <p class="application-copy"><?=nl2br(H::e($app['design_types']))?></p>
  </div>

  <?php if($app['denial_reason']): ?>
    <div class="application-section">
      <h3>Denial Reason</h3>
      <p class="application-copy"><?=nl2br(H::e($app['denial_reason']))?></p>
    </div>
  <?php endif; ?>

  <?php if($app['admin_notes']): ?>
    <div class="application-section">
      <h3>Admin Notes</h3>
      <p class="application-copy"><?=nl2br(H::e($app['admin_notes']))?></p>
    </div>
  <?php endif; ?>

</section>

<section class="grid">
  <?php if(H::canAdmin('applications.manage')):?><form method="post" class="card form">
    <h2>Approve Application</h2>
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="id" value="<?=$app['id']?>">
    <button class="btn" name="action" value="approve">Approve Application</button>
  </form><?php endif;?>
  <?php if(H::canAdmin('applications.manage')):?><form method="post" class="card form">
    <h2>Deny Application</h2>
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="id" value="<?=$app['id']?>">
    <label>Denial reason <textarea name="reason" required minlength="5"><?=H::e($app['denial_reason'])?></textarea></label>
    <label>Admin notes <textarea name="admin_notes"><?=H::e($app['admin_notes'])?></textarea></label>
    <button class="btn alt" name="action" value="deny">Deny Application</button>
  </form><?php endif;?>
</section>
