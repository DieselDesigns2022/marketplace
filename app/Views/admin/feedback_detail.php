<?php use App\Core\Helpers as H; ?>

<h1><?=H::e(ucfirst($submission['submission_type']))?> #<?=(int)$submission['id']?></h1>

<p><a href="/admin/feedback">← Back to Feedback</a></p>

<section class="card">
    <p>
        <strong>Submitted by:</strong>
        <?=H::e($submission['user_name_snapshot'])?>
        (<?=H::e($submission['user_email_snapshot'])?>)
    </p>

    <p>
        <strong>Submitted:</strong>
        <?=H::e($submission['created_at'])?>
    </p>

    <p>
        <strong>Status:</strong>
        <?=H::e(ucfirst($submission['status']))?>
    </p>

    <?php if(!empty($submission['context_url'])): ?>
        <p>
            <strong>Page / context:</strong>
            <a href="<?=H::e($submission['context_url'])?>">
                <?=H::e($submission['context_url'])?>
            </a>
        </p>
    <?php endif; ?>

    <hr>

    <p><?=nl2br(H::e($submission['message']))?></p>
</section>

<?php if(H::canAdmin('feedback.manage')): ?>
    <form method="post" action="/admin/feedback/<?=(int)$submission['id']?>" class="card">
        <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

        <label>
            Status
            <select name="status">
                <option value="open" <?=$submission['status']==='open'?'selected':''?>>Open</option>
                <option value="resolved" <?=$submission['status']==='resolved'?'selected':''?>>Resolved</option>
            </select>
        </label>

        <button class="btn" type="submit">Update Status</button>
    </form>
<?php endif; ?>
