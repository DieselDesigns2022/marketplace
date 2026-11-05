<?php
use App\Core\Helpers as H;

$canManage = H::canAdmin('ip_risk.manage');
?>
<header class="dashboard-heading">
    <div>
        <h1>IP Audit &amp; Seller Warnings</h1>
        <p class="muted">
            Review possible IP risks before taking action.
            Scanning never removes products or issues warnings.
        </p>
    </div>
</header>

<section class="card">
    <h2>Marketplace IP Audit</h2>

    <p>
        Total findings: <strong><?= (int)($totals['total'] ?? 0) ?></strong>
        &nbsp; | &nbsp;
        Awaiting review:
        <strong><?= (int)($totals['pending'] ?? 0) ?></strong>
        &nbsp; | &nbsp;
        Confirmed violations:
        <strong><?= (int)($totals['violations'] ?? 0) ?></strong>
        &nbsp; | &nbsp;
        Dismissed:
        <strong><?= (int)($totals['dismissed'] ?? 0) ?></strong>
    </p>

    <?php if($canManage): ?>
    <form method="post" action="/admin/ip-audit/rescan">
        <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">
        <button class="btn" type="submit">
            Rescan All Products
        </button>
        <small class="muted">
            Text, tags, SEO fields, and file names.
            No automatic takedowns or warnings.
        </small>
    </form>
    <?php endif; ?>
</section>

<nav class="tabs">
    <?php foreach([
        'pending'=>'Needs Review',
        'violation'=>'Confirmed Violations',
        'dismissed'=>'Dismissed',
        'all'=>'All Findings'
    ] as $key=>$label): ?>
        <a class="<?= $status===$key?'active':'' ?>"
           href="/admin/ip-audit?status=<?= H::e($key) ?>">
            <?= H::e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>


<?php
$pendingBySeller = [];
if ($canManage) {
    foreach ($findings as $candidate) {
        if (($candidate['review_status'] ?? '') === 'pending') {
            $pendingBySeller[(int)$candidate['designer_id']]['shop'] =
                $candidate['shop_name'];
            $pendingBySeller[(int)$candidate['designer_id']]['items'][] =
                $candidate;
        }
    }
}
?>

<?php foreach($pendingBySeller as $group): ?>
<?php if(count($group['items']) >= 2): ?>
<section class="card" style="margin-bottom:1rem;">
    <h2>Issue ONE Warning — <?= H::e($group['shop']) ?></h2>
    <p class="muted">
        Select products from this seller that belong to the same
        confirmed incident. One warning will cover all selected
        products. Each selected product will return to Draft.
    </p>
    <form method="post" action="/admin/ip-audit/confirm-group">
        <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">
        <?php foreach($group['items'] as $item): ?>
        <label style="display:block;margin:.5rem 0;">
            <input type="checkbox" name="product_ids[]"
                   value="<?= (int)$item['product_id'] ?>">
            #<?= (int)$item['product_id'] ?>
            — <?= H::e($item['title']) ?>
            (<?= H::e($item['product_status']) ?>)
        </label>
        <?php endforeach; ?>
        <label>
            Reason for this official warning
            <textarea name="note" rows="3" required
              maxlength="2000"
              placeholder="Describe the confirmed protected-content violation."></textarea>
        </label>
        <button class="btn" type="submit"
          onclick="return confirm(
            'Issue ONE formal warning for these selected products and move all selected products to Draft?'
          );">
            Confirm Selected Violations + Issue ONE Warning
        </button>
    </form>

    <h3>Dismiss Individual Products</h3>
    <p class="muted">
        Dismiss a false-positive IP finding without issuing
        a warning or changing the product's current status.
    </p>

    <?php foreach($group['items'] as $item): ?>
    <form method="post"
          action="/admin/ip-audit/<?= (int)$item['product_id'] ?>/decision"
          style="margin:.75rem 0;padding:.75rem;border:1px solid var(--line);border-radius:10px;">
        <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">
        <input type="hidden" name="decision" value="dismiss">

        <strong>
            #<?= (int)$item['product_id'] ?>
            — <?= H::e($item['title']) ?>
        </strong>

        <label>
            Optional dismissal note
            <input name="note"
                   maxlength="2000"
                   placeholder="Example: No protected character or branding used.">
        </label>

        <button type="submit"
                onclick="return confirm('Dismiss this product\'s IP finding without changing its listing status?');">
            Dismiss — No Warning
        </button>
    </form>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php foreach($findings as $f):
    $matches = json_decode(
        (string)$f['matches_json'],true
    ) ?: [];
    $reviewStatus = (string)$f['review_status'];
?>
<section class="card" style="margin-bottom:1rem;">
    <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <?php if(!empty($f['thumbnail'])): ?>
            <img
                src="<?= H::e($f['thumbnail']) ?>"
                alt="Product preview"
                style="width:110px;height:110px;object-fit:cover;border-radius:10px;">
        <?php endif; ?>

        <div style="flex:1;min-width:220px;">
            <h3 style="margin-top:0;">
                <a href="/admin/products/<?= (int)$f['product_id'] ?>">
                    #<?= (int)$f['product_id'] ?>
                    — <?= H::e($f['title']) ?>
                </a>
            </h3>

            <p>
                <strong>Shop:</strong>
                <?= H::e($f['shop_name']) ?>
                <br>
                <strong>Product:</strong>
                <?= H::e($f['product_status']) ?>
                <br>
                <strong>Audit:</strong>
                <?= H::e($reviewStatus) ?>
                <br>
                <strong>Last scan:</strong>
                <?= H::e($f['last_scanned_at']) ?>
            </p>
        </div>
    </div>

    <h4>Detected terms</h4>
    <ul>
    <?php foreach($matches as $m): ?>
        <li>
            <strong><?= H::e($m['matched_term'] ?? '') ?></strong>
            <?php if(!empty($m['matched_alias'])): ?>
                — alias:
                <?= H::e($m['matched_alias']) ?>
            <?php endif; ?>
            (<?= H::e($m['source_field'] ?? '') ?>)
        </li>
    <?php endforeach; ?>
    </ul>

    <?php if(!empty($f['admin_note'])): ?>
        <p><strong>Admin note:</strong>
            <?= H::e($f['admin_note']) ?>
        </p>
    <?php endif; ?>

    <?php if($canManage && $reviewStatus==='pending'): ?>
        <form method="post"
              action="/admin/ip-audit/<?= (int)$f['product_id'] ?>/decision">
            <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">

            <label>
                Admin review notes
                <textarea name="note" rows="3"
                    placeholder="Explain why this is or is not an IP violation."></textarea>
            </label>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <button name="decision" value="dismiss">
                    Dismiss — Not a Violation
                </button>

                <button class="btn" name="decision" value="confirm"
                    onclick="return confirm(
                    'Confirm violation, return product to Draft, and issue an official seller warning?'
                    );">
                    Confirm Violation + Issue Warning
                </button>
            </div>
        </form>
    <?php elseif($canManage && $reviewStatus==='violation'): ?>
        <form method="post"
              action="/admin/ip-audit/<?= (int)$f['product_id'] ?>/decision">
            <input type="hidden" name="_csrf" value="<?= H::csrf() ?>">
            <label>
                Reason for clearing restriction
                <textarea name="note" rows="2" required></textarea>
            </label>
            <button name="decision" value="clear"
                onclick="return confirm(
                    'Clear this IP restriction? The product will remain Draft.'
                );">
                Clear Restriction After Review
            </button>
        </form>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if(!$findings): ?>
<section class="card">
    <p>No findings in this category.
       Run the full rescan to populate the audit.</p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Seller Warning History</h2>
    <p class="muted">
        Permanent record for administrators.
        Clearing a restriction does not erase its warning.
    </p>

    <div class="responsive-table">
        <table>
            <tr>
                <th>Seller</th>
                <th>Warning</th>
                <th>Product</th>
                <th>Reason</th>
                <th>Issued By</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
            <?php
            $warningGroups = [];
            foreach ($warnings as $entry) {
                $key = $entry['designer_id'].'-'.$entry['warning_number'];
                if (!isset($warningGroups[$key])) {
                    $warningGroups[$key] = $entry;
                    $warningGroups[$key]['products'] = [];
                }
                $warningGroups[$key]['products'][] = $entry;
            }
            ?>
            <?php foreach($warningGroups as $w): ?>
            <tr>
                <td><?= H::e($w['shop_name']) ?></td>
                <td>#<?= (int)$w['warning_number'] ?></td>
                <td>
                    <?php foreach($w['products'] as $affected): ?>
                        <a href="/admin/products/<?= (int)$affected['product_id'] ?>">
                            #<?= (int)$affected['product_id'] ?>
                            <?= H::e($affected['title']) ?>
                        </a>
                        <br>
                    <?php endforeach; ?>
                </td>
                <td><?= nl2br(H::e($w['reason'])) ?></td>
                <td><?= H::e($w['admin_name']) ?></td>
                <td><?= H::e($w['issued_at']) ?></td>
                <td>
                    <?= $w['resolved_at']===null
                        ? 'Active restriction'
                        : 'Cleared — warning retained' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$warnings): ?>
            <tr>
                <td colspan="7">No official warnings issued.</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</section>
