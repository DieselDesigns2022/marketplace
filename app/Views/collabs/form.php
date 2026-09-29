<?php
$collabHostLocal = null;

$hostTimezoneName =
    !empty($collab['host_timezone'])
        ? (string)$collab['host_timezone']
        : 'America/New_York';

$utc = new \DateTimeZone('UTC');
$hostTimezoneObject = new \DateTimeZone($hostTimezoneName);

if ($collab) {
    $collabHostLocal = $collab;

    $collabHostLocal['upload_deadline'] =
        (new \DateTimeImmutable($collab['upload_deadline'], $utc))
            ->setTimezone($hostTimezoneObject)
            ->format('Y-m-d H:i:s');

    $collabHostLocal['sale_starts_at'] =
        (new \DateTimeImmutable($collab['sale_starts_at'], $utc))
            ->setTimezone($hostTimezoneObject)
            ->format('Y-m-d H:i:s');
}

$timezoneChoices = \DateTimeZone::listIdentifiers();
?>
<h1><?=$collab?'Edit':'Create'?> a Collab Bundle</h1>

<?php foreach(($errors??[]) as $error):?>
    <p class="notice warning"><?=H::e($error)?></p>
<?php endforeach;?>

<form method="post" class="card" id="collab-form">

<input type="hidden" name="_csrf" value="<?=H::csrf()?>">

<label>
    Title
    <input required minlength="3" name="title" value="<?=H::e($collab['title']??'')?>">
</label>

<label>
    Slug (optional)
    <input name="slug" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?=H::e($collab['slug']??'')?>">
</label>

<label>
    Description
    <textarea required name="description"><?=H::e($collab['description']??'')?></textarea>
</label>

<label>
    Host Notes / Instructions (optional)
    <textarea
        name="host_notes"
        rows="5"
        placeholder="Add any special rules, theme instructions, file requirements, naming rules, or other information participants should know."
    ><?=H::e($collab['host_notes']??'')?></textarea>
</label>

<label>
    Sale price
    <input required type="text" inputmode="decimal" name="price"
           value="<?=H::e(isset($collab)?\App\Services\CreditService::formatCents((int)$collab['price_cents']):'10.00')?>">
</label>

<label>
    Quantity available (leave blank for unlimited)
    <input type="number" min="1" name="quantity_limit"
           value="<?=H::e(isset($collab) && !empty($collab['quantity_limit']) ? (string)$collab['quantity_limit'] : '')?>">
</label>

<label>
    Participation
    <select name="participation_type">
        <option value="open" <?=($collab['participation_type']??'')==='open'?'selected':''?>>Open</option>
        <option value="closed" <?=($collab['participation_type']??'')==='closed'?'selected':''?>>Closed</option>
    </select>
</label>

<label>
    Minimum contribution files
    <input
        required
        type="number"
        min="1"
        name="minimum_file_count"
        value="<?=intval($collab['minimum_file_count']??1)?>"
    >
</label>

<label style="display:flex;gap:10px;align-items:center;margin:12px 0">
    <input
        type="checkbox"
        name="require_mockup"
        value="1"
        <?=!empty($collab['require_mockup'])?'checked':''?>
        style="width:auto;flex:0 0 auto"
    >
    <span>Require at least 1 mockup from each participant</span>
</label>

<label>
    Host Time Zone
    <select name="host_timezone" id="collab-host-timezone" required>
        <?php foreach($timezoneChoices as $timezoneChoice):?>
            <option
                value="<?=H::e($timezoneChoice)?>"
                <?=$timezoneChoice===$hostTimezoneName?'selected':''?>
            >
                <?=H::e($timezoneChoice)?>
            </option>
        <?php endforeach;?>
    </select>
</label>

<label>
    File Upload Deadline — Host Time Zone
    <input required type="datetime-local" name="upload_deadline"
           value="<?=H::e($collabHostLocal?str_replace(' ','T',substr($collabHostLocal['upload_deadline'],0,16)):'')?>">
</label>

<label>
    Sale Start — Host Time Zone
    <input required type="datetime-local" name="sale_starts_at"
           value="<?=H::e($collabHostLocal?str_replace(' ','T',substr($collabHostLocal['sale_starts_at'],0,16)):'')?>">
</label>

<label>
    Sale Close Date
    <input required type="date" name="sale_close_date"
           value="<?=H::e($collab['sale_close_date']??'')?>">
</label>

<button><?=$collab?'Save changes':'Create collab'?></button>

</form>


<?php if(!$collab):?>
<script>
(() => {
    const timezoneSelect = document.getElementById('collab-host-timezone');
    const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (
        timezoneSelect
        && detected
        && [...timezoneSelect.options].some(option => option.value === detected)
    ) {
        timezoneSelect.value = detected;
    }
})();
</script>
<?php endif;?>
