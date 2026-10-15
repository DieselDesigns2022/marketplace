<?php use App\Core\Helpers as H; ?>

<h1>Feedback &amp; Suggestions</h1>

<form method="get" action="/admin/feedback" class="card">
    <label>
        Search
        <input
            type="search"
            name="q"
            value="<?=H::e($q)?>"
            placeholder="Name, email, message, or page"
        >
    </label>

    <label>
        Status
        <select name="status">
            <?php foreach(['open'=>'Open','resolved'=>'Resolved','all'=>'All'] as $value=>$label): ?>
                <option value="<?=$value?>" <?=$status===$value?'selected':''?>><?=$label?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Type
        <select name="type">
            <?php foreach(['all'=>'All','feedback'=>'Feedback','suggestion'=>'Suggestion'] as $value=>$label): ?>
                <option value="<?=$value?>" <?=$type===$value?'selected':''?>><?=$label?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <button class="btn" type="submit">Filter</button>
</form>

<?php if(!$submissions): ?>
    <div class="card empty-state">No feedback submissions match these filters.</div>
<?php else: ?>
    <div class="responsive-table">
        <table>
            <tr>
                <th>Date</th>
                <th>From</th>
                <th>Type</th>
                <th>Status</th>
                <th>Message</th>
                <th></th>
            </tr>

            <?php foreach($submissions as $row): ?>
                <tr>
                    <td><?=H::e($row['created_at'])?></td>
                    <td>
                        <strong><?=H::e($row['user_name_snapshot'])?></strong><br>
                        <small><?=H::e($row['user_email_snapshot'])?></small>
                    </td>
                    <td><?=H::e(ucfirst($row['submission_type']))?></td>
                    <td><span class="badge"><?=H::e(ucfirst($row['status']))?></span></td>
                    <td><?=H::e(mb_strimwidth($row['message'],0,120,'…'))?></td>
                    <td><a class="btn" href="/admin/feedback/<?=(int)$row['id']?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
