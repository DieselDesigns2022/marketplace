<?php use App\Core\Helpers as H; ?>

<h1>Feedback &amp; Suggestions</h1>

<p class="muted">
    Have an idea or something you want us to know? Send it directly to the Creative Moth team.
</p>

<?php foreach($errors as $error): ?>
    <div class="notice error"><?=H::e($error)?></div>
<?php endforeach; ?>

<form method="post" action="/feedback" class="card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <input type="hidden" name="context_url" value="<?=H::e($context??'')?>">

    <label>
        What are you sending?
        <select name="submission_type" required>
            <option value="feedback" <?=$type==='feedback'?'selected':''?>>Feedback</option>
            <option value="suggestion" <?=$type==='suggestion'?'selected':''?>>Suggestion</option>
        </select>
    </label>

    <label>
        Message
        <textarea
            name="message"
            rows="9"
            maxlength="5000"
            required
        ><?=H::e($message)?></textarea>
    </label>

    <button class="btn" type="submit">Send to Creative Moth</button>
</form>
