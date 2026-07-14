<h1>Admin → IP Risk Terms</h1>
<p class="muted">Advisory matching terms for seller warnings and admin review. Matches are not legal findings.</p>
<p><a class="btn" href="/admin/ip-risk-terms/create">Create IP Risk Term</a></p>
<table>
    <tr><th>Term</th><th>Category</th><th>Enabled</th><th>Aliases</th><th>Actions</th></tr>
    <?php foreach ($terms as $term): ?>
        <tr>
            <td><?=H::e($term['term'])?></td>
            <td><?=H::e($term['category'])?></td>
            <td><?=!empty($term['is_enabled']) ? 'Enabled' : 'Disabled'?></td>
            <td><?=(int)$term['alias_count']?></td>
            <td>
                <a href="/admin/ip-risk-terms/<?=$term['id']?>/edit">Edit</a>
                <form method="post" action="/admin/ip-risk-terms/<?=$term['id']?>/<?=!empty($term['is_enabled']) ? 'disable' : 'enable'?>" class="inline">
                    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">
                    <button><?=!empty($term['is_enabled']) ? 'Disable' : 'Enable'?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$terms): ?><tr><td colspan="5" class="muted">No IP risk terms configured.</td></tr><?php endif; ?>
</table>
