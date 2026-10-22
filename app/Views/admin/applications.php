<style>
.application-filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 16px 0 24px;
}

.application-filter-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 8px 16px;
    border: 1px solid #ddd6ea;
    border-radius: 999px;
    background: #ffffff;
    color: #2e2350;
    font-weight: 600;
    text-decoration: none;
    line-height: 1.2;
}

.application-filter-tab:hover {
    background: #f5efff;
    border-color: #b89adb;
    color: #2e2350;
    text-decoration: none;
}

.application-filter-tab.active {
    background: #dcfce7;
    border-color: #86efac;
    color: #166534;
}
</style>

<h1>Designer Applications</h1>
<p class="muted">Filter applications by status. Pending designers can be approved directly from this page, or opened for full review.</p>

<nav class="application-filter-tabs" aria-label="Application status filters">
    <a class="application-filter-tab <?=$status==='pending'?'active':''?>" href="/admin/applications?status=pending">Pending</a>
    <a class="application-filter-tab <?=$status==='approved'?'active':''?>" href="/admin/applications?status=approved">Approved</a>
    <a class="application-filter-tab <?=$status==='denied'?'active':''?>" href="/admin/applications?status=denied">Denied</a>
    <a class="application-filter-tab <?=$status==='all'?'active':''?>" href="/admin/applications?status=all">All</a>
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
            <strong>Location:</strong>
            <?php
                $country = trim((string)($a['country'] ?? ''));
                $region = trim((string)($a['region'] ?? ''));
            ?>
            <?=H::e(
                $country === ''
                    ? 'Not provided'
                    : ($region !== '' ? $region . ', ' . $country : $country)
            )?>
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
        <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
            <a class="btn alt" href="/admin/applications/<?=$a['id']?>">View details</a>

            <?php if($a['status']==='pending' && H::canAdmin('applications.manage')):?>
                <form method="post"
                      action="/admin/applications"
                      class="inline"
                      onsubmit="return confirm('Approve this designer application?');">
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <input type="hidden" name="id" value="<?=(int)$a['id']?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="return_status" value="<?=H::e($status)?>">
                    <button class="btn" type="submit">Approve Application</button>
                </form>
            <?php endif;?>
        </div>
    </article>
<?php endforeach; if(!$apps): ?>
<p class="muted">No applications found for this filter.</p>
<?php endif; ?>
</div>
