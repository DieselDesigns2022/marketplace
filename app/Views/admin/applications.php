<h1>Designer Applications</h1>
<nav class="tabs">
    <a class="badge <?=$status==='pending'?'ok':''?>" href="/admin/applications?status=pending">Pending</a>
    <a class="badge <?=$status==='approved'?'ok':''?>" href="/admin/applications?status=approved">Approved</a>
    <a class="badge <?=$status==='denied'?'ok':''?>" href="/admin/applications?status=denied">Denied</a>
    <a class="badge <?=$status==='all'?'ok':''?>" href="/admin/applications?status=all">All</a>
</nav>
<div class="application-list">
    <?php foreach($apps as $a): ?>
    <article class="card status-card status-<?=H::e($a['status'])?>">
        <h2>
        <?=H::e($a['display_name'])?>
        <span class="badge">
        <?=H::e($a['status'])?>
        </span>
        </h2>
        <p>
        <strong>Applicant:</strong>
        <?=H::e($a['user_name'])?> (<?=H::e($a['user_email'])?>)</p>
        <p>
        <strong>Desired slug:</strong> /store/<?=H::e($a['desired_slug'])?>
        </p>
        <p>
        <strong>Portfolio:</strong>
        <?= $a['portfolio_url'] ? '<a href="'.H::e($a['portfolio_url']).'">'.H::e($a['portfolio_url']).'</a>' : 'None' ?>
        </p>
        <p>
        <strong>Design types:</strong>
        <?=H::e($a['design_types'])?>
        </p>
        <p>
        <strong>AI usage:</strong>
        <?=H::e($a['uses_ai'])?> | <strong>Agreement:</strong>
        <?=$a['agreement']?'Yes':'No'?>
        </p>
        <p>
        <strong>Created:</strong>
        <?=H::e($a['created_at'])?>
        </p>
        <a class="btn" href="/admin/applications/<?=$a['id']?>">View details</a>
    </article>
<?php endforeach; if(!$apps): ?>
<p class="muted">No applications found for this filter.</p>
<?php endif; ?>
</div>
