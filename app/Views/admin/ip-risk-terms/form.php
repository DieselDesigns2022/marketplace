<?php
$editing = !empty($term['id']);
$aliasText = isset($term['aliases']) ? $term['aliases'] : implode("\n", array_column($aliases ?? [], 'alias'));
?>
<h1><?= $editing ? 'Edit' : 'Create' ?> IP Risk Term</h1>
<p class="muted">Entries create advisory matches only. They are not legal findings and do not prove infringement or legal safety.</p>
<?php foreach ($errors ?? [] as $error): ?>
    <div class="notice error"><?=H::e($error)?></div>
<?php endforeach; ?>
<form method="post" class="form card" action="<?= $editing ? '/admin/ip-risk-terms/' . (int)$term['id'] : '/admin/ip-risk-terms' ?>">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
    <label>Canonical term<input name="term" required value="<?=H::e($term['term'] ?? '')?>"></label>
    <label>Category<select name="category">
        <?php foreach ($categories as $category): ?>
            <option value="<?=H::e($category)?>" <?=($term['category'] ?? '') === $category ? 'selected' : ''?>><?=H::e($category)?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Aliases <span class="muted">one per line or comma-separated</span><textarea name="aliases"><?=H::e($aliasText)?></textarea></label>
    <label>Internal note<textarea name="internal_note"><?=H::e($term['internal_note'] ?? '')?></textarea></label>
    <label><input type="checkbox" name="is_enabled" value="1" <?=($term['is_enabled'] ?? 1) ? 'checked' : ''?>> Enabled</label>
    <button class="btn">Save Term</button> <a href="/admin/ip-risk-terms">Cancel</a>
</form>
