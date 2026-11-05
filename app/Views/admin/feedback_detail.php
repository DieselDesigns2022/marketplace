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

<section class="card">
    <h2>Creative Moth Support Conversation</h2>
    <?php if(empty($replyMessages)): ?>
        <p class="muted">No responses yet.</p>
    <?php else: ?>
        <?php foreach($replyMessages as $reply): ?>
            <article class="card">
                <strong><?=H::e((int)$reply['sender_user_id']===(int)$submission['user_id']
                    ? $submission['user_name_snapshot']:'Creative Moth Support')?></strong>
                <small><?=H::e($reply['created_at'])?></small>
                <p><?=nl2br(H::e($reply['body']))?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php if(H::canAdmin('feedback.manage')): ?>
<form method="post" action="/admin/feedback/<?=(int)$submission['id']?>" class="card">
    <h2>Send an Inbox Response</h2>
    <p class="muted">Sent through Creative Moth messaging. No email is sent.</p>
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <input type="hidden" name="action" value="reply">
    <input type="hidden" name="reply_token" value="<?=bin2hex(random_bytes(16))?>">
    <label>Response
        <textarea name="body" maxlength="10000" rows="6" required></textarea>
    </label>
    <button class="btn" type="submit">Send Response</button>
</form>
<?php endif; ?>
