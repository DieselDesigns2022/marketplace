<?php
$zone = new \DateTimeZone($timezone);
$utc = new \DateTimeZone('UTC');

$localDate = static function (?string $date) use ($zone,$utc): string {
    if (!$date) return '';
    return (new \DateTimeImmutable($date,$utc))
        ->setTimezone($zone)
        ->format('Y-m-d\TH:i');
};

$hasSale = !empty($sale);
$enabled = $hasSale && (int)$sale['is_active'] === 1;
?>

<header class="dashboard-heading">
    <div>
        <h1>Individual Product Sale</h1>
        <p class="muted"><?=H::e($product['title'])?></p>
    </div>
</header>

<p>
    <a href="/seller/product/<?=(int)$product['id']?>">
        ← Back to Edit Product
    </a>
</p>

<section class="card">
    <p><strong>Regular base price:</strong>
        <?=H::money($product['price']??0)?>
    </p>

    <p><strong>Sale status:</strong>
        <?php if (!$hasSale): ?>
            Not configured
        <?php elseif (!$enabled): ?>
            Turned off
        <?php elseif ($sale['starts_at'] > gmdate('Y-m-d H:i:s')): ?>
            Scheduled
        <?php elseif ($sale['ends_at'] <= gmdate('Y-m-d H:i:s')): ?>
            Ended
        <?php else: ?>
            Configured — awaiting pricing activation
        <?php endif; ?>
    </p>

    <p class="help-text">
        Sale pricing is not active on the marketplace yet.
        These controls let us configure and test sales safely.
    </p>
</section>

<form method="post" class="form card">
    <input type="hidden" name="_csrf" value="<?=H::csrf()?>">

    <h2>Sale Discount</h2>

    <label>
        Discount Type
        <select name="discount_type" required>
            <option value="percent"
                <?=($sale['discount_type']??'percent')==='percent'?'selected':''?>>
                Percentage Off (%)
            </option>
            <option value="fixed"
                <?=($sale['discount_type']??'')==='fixed'?'selected':''?>>
                Dollar Amount Off ($)
            </option>
        </select>
    </label>

    <label>
        Discount Amount
        <input type="number" name="discount_value"
               min="0.01" step="0.01" required
               value="<?=H::e($sale['discount_value']??'10.00')?>">
    </label>

    <h2>Sale Schedule</h2>

    <p class="help-text">
        All dates are shown in your seller timezone:
        <strong><?=H::e($timezone)?></strong>.
        The sale begins and ends automatically.
    </p>

    <label>
        Start Option
        <select name="start_mode">
            <option value="now">Start Now</option>
            <option value="scheduled">Schedule Start</option>
        </select>
    </label>

    <label>
        Scheduled Start Date and Time
        <input type="datetime-local" name="starts_local"
               value="<?=H::e($localDate($sale['starts_at']??null))?>">
    </label>

    <label>
        End Date and Time
        <input type="datetime-local" name="ends_local" required
               value="<?=H::e($localDate($sale['ends_at']??null))?>">
    </label>

    <button type="submit" name="sale_action" value="save">
        Save Individual Sale
    </button>

    <?php if ($enabled): ?>
        <button type="submit" name="sale_action" value="disable"
                formnovalidate
                onclick="return confirm('Turn off this product sale?');">
            Turn Off Sale
        </button>
    <?php endif; ?>
</form>
