<h1>Review Designer Application</h1>
<section class="card status-card status-<?=H::e($app['status'])?>">
  <h2><?=H::e($app['display_name'])?> <span class="badge"><?=H::e($app['status'])?></span></h2>
  <div class="detail-grid">
    <p><strong>User name:</strong> <?=H::e($app['user_name'])?></p>
    <p><strong>User email:</strong> <?=H::e($app['user_email'])?></p>
    <p><strong>Display name:</strong> <?=H::e($app['display_name'])?></p>
    <p><strong>Desired slug:</strong> /store/<?=H::e($app['desired_slug'])?></p>
    <p><strong>Portfolio URL:</strong> <?=H::e($app['portfolio_url'])?></p>
    <p><strong>AI usage:</strong> <?=H::e($app['uses_ai'])?></p>
    <p><strong>Agreement confirmation:</strong> <?=$app['agreement']?'Yes':'No'?></p>
    <p><strong>Current status:</strong> <?=H::e($app['status'])?></p>
    <p><strong>Created:</strong> <?=H::e($app['created_at'])?></p>
    <p><strong>Updated:</strong> <?=H::e($app['updated_at'])?></p>
  </div>
  <h3>Bio</h3><p><?=nl2br(H::e($app['bio']))?></p>
  <h3>Social Links</h3><p><?=nl2br(H::e($app['social_links']))?></p>
  <h3>Design Types</h3><p><?=nl2br(H::e($app['design_types']))?></p>
  <?php if($app['denial_reason']): ?><h3>Denial Reason</h3><p><?=nl2br(H::e($app['denial_reason']))?></p><?php endif; ?>
  <?php if($app['admin_notes']): ?><h3>Admin Notes</h3><p><?=nl2br(H::e($app['admin_notes']))?></p><?php endif; ?>
</section>
<section class="grid">
  <form method="post" class="card form">
    <h2>Approve Application</h2>
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="id" value="<?=$app['id']?>">
    <button class="btn" name="action" value="approve">Approve Application</button>
  </form>
  <form method="post" class="card form">
    <h2>Deny Application</h2>
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>"><input type="hidden" name="id" value="<?=$app['id']?>">
    <label>Denial reason <textarea name="reason" required minlength="5"><?=H::e($app['denial_reason'])?></textarea></label>
    <label>Admin notes <textarea name="admin_notes"><?=H::e($app['admin_notes'])?></textarea></label>
    <button class="btn alt" name="action" value="deny">Deny Application</button>
  </form>
</section>
