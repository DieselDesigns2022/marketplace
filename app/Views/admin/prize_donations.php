<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Prize Donations</h1>
        <p class="muted">
            Review donated prizes, manage finite inventory,
            assign prizes to Grand Opening events, record winners,
            and track fulfillment.
        </p>
    </div>
</header>

<section class="dashboard-grid">
    <div class="card">
        <strong><?=number_format($totals['donation_count'])?></strong>
        <span>Active donations</span>
    </div>

    <div class="card">
        <strong><?=number_format($totals['quantity_donated'])?></strong>
        <span>Total units donated</span>
    </div>

    <div class="card">
        <strong><?=number_format($totals['quantity_remaining'])?></strong>
        <span>Available units</span>
    </div>

    <div class="card">
        <strong><?=number_format($totals['pending_review'])?></strong>
        <span>Waiting for review</span>
    </div>
</section>


<section>
    <h2>Prize Inventory</h2>

    <?php if(!$donations): ?>
        <div class="card empty-state">
            No prize donations have been submitted.
        </div>
    <?php else: ?>
        <?php foreach($donations as $d): ?>
            <article class="card">
                <div class="dashboard-heading">
                    <div>
                        <h3>
                            #<?=(int)$d['id']?>
                            <?=H::e($d['title'])?>
                        </h3>

                        <?php if(!empty($d['preview_image_path'])): ?>
                            <div style="margin:.75rem 0">
                                <img
                                    src="<?=H::e(H::assetUrl($d['preview_image_path']))?>"
                                    alt="<?=H::e($d['title'])?> prize preview"
                                    style="max-width:220px;max-height:220px;object-fit:contain"
                                >
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($d['prize_file_storage_path'])): ?>
                            <p>
                                <a
                                    class="btn"
                                    href="/admin/prize-donations/<?=(int)$d['id']?>/file"
                                >
                                    Download Prize File
                                </a>
                                <?php if(!empty($d['prize_file_original_name'])): ?>
                                    <span class="muted">
                                        <?=H::e($d['prize_file_original_name'])?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <p>
                            Donated by
                            <strong><?=H::e($d['seller_name'])?></strong>
                            ·
                            <?=H::e($types[$d['prize_type']]??$d['prize_type'])?>
                        </p>
                    </div>

                    <span class="badge">
                        <?=H::e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $d['status']
                                )
                            )
                        )?>
                    </span>
                </div>

                <p><?=nl2br(H::e($d['description']))?></p>

                <p>
                    <strong>Inventory:</strong>
                    <?=number_format((int)$d['quantity_remaining'])?>
                    available /
                    <?=number_format((int)$d['quantity_donated'])?>
                    donated
                    ·
                    <?=number_format((int)$d['assigned_quantity'])?>
                    assigned
                    ·
                    <?=number_format((int)$d['awarded_quantity'])?>
                    awarded
                    ·
                    <?=number_format((int)$d['fulfilled_quantity'])?>
                    fulfilled
                </p>

                <?php if($d['approximate_retail_value']!==null): ?>
                    <p>
                        <strong>Approximate retail value:</strong>
                        <?=H::money($d['approximate_retail_value'])?>
                    </p>
                <?php endif; ?>

                <?php
                $useKeys=array_filter(
                    explode(
                        ',',
                        (string)$d['usage_designations']
                    )
                );
                ?>

                <p>
                    <strong>Approved uses:</strong>
                    <?=H::e(
                        implode(
                            ', ',
                            array_map(
                                fn($key)=>$usages[$key]??$key,
                                $useKeys
                            )
                        )
                    )?>
                </p>

                <p>
                    <strong>Fulfillment:</strong><br>
                    <?=nl2br(H::e($d['fulfillment_instructions']))?>
                </p>

                <?php if(!empty($d['restrictions'])): ?>
                    <p>
                        <strong>Restrictions:</strong><br>
                        <?=nl2br(H::e($d['restrictions']))?>
                    </p>
                <?php endif; ?>

                <?php if($canManage): ?>
                    <div class="quick-actions">
                        <?php if($d['status']==='submitted'): ?>
                            <form
                                method="post"
                                action="/admin/prize-donations/<?=(int)$d['id']?>/approve"
                            >
                                <input
                                    type="hidden"
                                    name="_csrf"
                                    value="<?=H::csrf()?>"
                                >
                                <button class="btn">
                                    Approve
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if($d['status']==='available' && (int)$d['quantity_remaining']>0): ?>
                            <form
                                method="post"
                                action="/admin/prize-donations/<?=(int)$d['id']?>/assign"
                                class="card"
                            >
                                <strong>Assign inventory</strong>

                                <label>
                                    Quantity
                                    <input
                                        type="number"
                                        name="quantity"
                                        min="1"
                                        max="<?=(int)$d['quantity_remaining']?>"
                                        value="1"
                                        required
                                    >
                                </label>

                                <label>
                                    Event
                                    <select
                                        name="event_type"
                                        required
                                    >
                                        <?php foreach($useKeys as $key): ?>
                                            <option value="<?=H::e($key)?>">
                                                <?=H::e($usages[$key]??$key)?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label>
                                    Event reference
                                    <input
                                        name="event_reference"
                                        maxlength="190"
                                        placeholder="Optional: wheel, giveaway name, battle, post, etc."
                                    >
                                </label>

                                <input
                                    type="hidden"
                                    name="_csrf"
                                    value="<?=H::csrf()?>"
                                >

                                <button class="btn">
                                    Assign
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if(in_array($d['status'],['submitted','available','approved'],true)): ?>
                            <form
                                method="post"
                                action="/admin/prize-donations/<?=(int)$d['id']?>/cancel"
                                onsubmit="return confirm('Cancel this prize donation?');"
                            >
                                <input
                                    type="hidden"
                                    name="_csrf"
                                    value="<?=H::csrf()?>"
                                >

                                <button class="danger">
                                    Cancel Donation
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>


<section>
    <h2>Assignments / Winners / Fulfillment</h2>

    <?php if(!$assignments): ?>
        <div class="card empty-state">
            No prize inventory has been assigned yet.
        </div>
    <?php else: ?>
        <?php foreach($assignments as $a): ?>
            <article class="card">
                <h3>
                    Assignment #<?=(int)$a['id']?>
                    ·
                    <?=H::e($a['prize_title'])?>
                </h3>

                <p>
                    Seller:
                    <?=H::e($a['seller_name'])?>
                    · Quantity:
                    <?=number_format((int)$a['quantity'])?>
                    · Event:
                    <?=H::e($usages[$a['event_type']]??$a['event_type'])?>

                    <?php if(!empty($a['event_reference'])): ?>
                        · <?=H::e($a['event_reference'])?>
                    <?php endif; ?>
                </p>

                <p>
                    Status:
                    <strong>
                        <?=H::e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $a['status']
                                )
                            )
                        )?>
                    </strong>
                </p>

                <?php if($a['status']==='awarded' || $a['status']==='fulfilled'): ?>
                    <p>
                        <strong>Winner:</strong>
                        <?=H::e(
                            $a['winner_account_name']
                            ?? $a['winner_name']
                            ?? 'Winner'
                        )?>

                        <?php
                        $email=
                            $a['winner_account_email']
                            ?? $a['winner_email']
                            ?? null;
                        ?>

                        <?php if($email): ?>
                            · <?=H::e($email)?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if($canManage): ?>
                    <?php if($a['status']==='assigned'): ?>
                        <div class="dashboard-columns">
                            <form
                                class="card"
                                method="post"
                                action="/admin/prize-assignments/<?=(int)$a['id']?>/award"
                            >
                                <h4>Record Winner</h4>

                                <label>
                                    Creative Moth user ID
                                    <input
                                        name="winner_user_id"
                                        inputmode="numeric"
                                        placeholder="Optional"
                                    >
                                </label>

                                <label>
                                    Winner name
                                    <input
                                        name="winner_name"
                                        maxlength="190"
                                        placeholder="Optional for account winner"
                                    >
                                </label>

                                <label>
                                    Winner email
                                    <input
                                        type="email"
                                        name="winner_email"
                                        maxlength="190"
                                        placeholder="Optional for account winner"
                                    >
                                </label>

                                <input
                                    type="hidden"
                                    name="_csrf"
                                    value="<?=H::csrf()?>"
                                >

                                <button class="btn">
                                    Award Prize
                                </button>
                            </form>

                            <form
                                class="card"
                                method="post"
                                action="/admin/prize-assignments/<?=(int)$a['id']?>/release"
                                onsubmit="return confirm('Return this assignment to available prize inventory?');"
                            >
                                <input
                                    type="hidden"
                                    name="_csrf"
                                    value="<?=H::csrf()?>"
                                >

                                <button>
                                    Return to Inventory
                                </button>
                            </form>
                        </div>
                    <?php elseif($a['status']==='awarded'): ?>
                        <form
                            class="card"
                            method="post"
                            action="/admin/prize-assignments/<?=(int)$a['id']?>/fulfill"
                        >
                            <label>
                                Fulfillment notes
                                <textarea
                                    name="fulfillment_notes"
                                    maxlength="5000"
                                    placeholder="Optional delivery confirmation, seller note, date, etc."
                                ></textarea>
                            </label>

                            <input
                                type="hidden"
                                name="_csrf"
                                value="<?=H::csrf()?>"
                            >

                            <button class="btn">
                                Mark Fulfilled
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if(!empty($a['fulfillment_notes'])): ?>
                    <p>
                        <strong>Fulfillment notes:</strong><br>
                        <?=nl2br(H::e($a['fulfillment_notes']))?>
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
