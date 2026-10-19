<?php use App\Core\Helpers as H; ?>

<header class="dashboard-heading">
    <div>
        <h1>Prize Donations</h1>
        <p class="muted">
            Donate digital prizes for Creative Moth Grand Opening activities.
            You may submit as many separate prizes as you want.
        </p>
    </div>
</header>

<section class="card">
    <h2>Donate a Prize</h2>

    <p>
        Accepted prize types include finished products, bundles,
        custom designs, seller/store credit, exclusives,
        services or other marketplace-compliant digital prizes.
    </p>

    <p class="notice warning">
        <strong>Submitting more than one prize?</strong>
        Please submit this form separately for each prize.
        Do not combine multiple different prizes into one submission.
        This allows Creative Moth to correctly track each prize's inventory,
        value, approved uses, restrictions, winner, and fulfillment.
    </p>

    <p class="muted">
        Seller/store credit here means credit or value fulfilled by your
        store. It is separate from Creative Moth Prize Credit.
    </p>

    <form method="post" action="/seller/prize-donations" enctype="multipart/form-data" class="form prize-donation-form">
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <label>
            Prize type
            <select name="prize_type" required>
                <option value="">Choose type</option>
                <?php foreach($types as $key=>$label): ?>
                    <option value="<?=H::e($key)?>">
                        <?=H::e($label)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Prize title
            <input
                name="title"
                maxlength="190"
                required
            >
        </label>

        <label>
            Description
            <textarea
                name="description"
                maxlength="5000"
                required
            ></textarea>
        </label>

        <div class="notice">
            <strong>Prize files &amp; promotional preview</strong>
            <p>
                Every donation needs both an actual prize file and a
                preview image so Creative Moth can promote and award it.
            </p>
            <ul>
                <li>
                    <strong>Store credit / gift card:</strong>
                    place the code or redemption information on an image
                    and upload that image as the Prize File.
                </li>
                <li>
                    <strong>Custom prize:</strong>
                    upload an image or PDF explaining how the winner
                    should contact you and how to order/redeem the custom.
                </li>
                <li>
                    <strong>Digital files:</strong>
                    upload the finished file, or ZIP multiple files together.
                </li>
                <li>
                    <strong>Preview image:</strong>
                    upload an image we can use to advertise the prize.
                    If you do not have a specific preview, your store logo
                    is perfectly fine.
                </li>
            </ul>
        </div>

        <label>
            Prize File
            <input
                type="file"
                name="prize_file"
                accept=".zip,.pdf,.jpg,.jpeg,.png,.webp"
                required
            >
            <small class="help-text">
                Required. ZIP, PDF, JPG, PNG, or WEBP.
                This file is stored privately and is not publicly exposed.
            </small>
        </label>

        <label>
            Preview Image
            <input
                type="file"
                name="preview_image"
                accept="image/jpeg,image/png,image/webp"
                required
            >
            <small class="help-text">
                Required. JPG, PNG, or WEBP. This is the image Creative
                Moth may use to visually promote your donated prize.
                Your logo is acceptable if you do not have a prize preview.
            </small>
        </label>

        <label>
            Quantity donated
            <input
                type="number"
                name="quantity"
                min="1"
                max="10000"
                value="1"
                required
            >
            <small class="help-text">
                You can donate multiple copies of the same product if you wish.
                Enter the total number of identical prizes you are donating.
            </small>
        </label>

        <label>
            Approximate retail value
            <input
                type="number"
                name="approximate_retail_value"
                min="0"
                step="0.01"
                placeholder="Optional"
            >
        </label>

        <fieldset>
            <legend>Creative Moth may use this prize for</legend>

            <?php foreach($usages as $key=>$label): ?>
                <label>
                    <input
                        type="checkbox"
                        name="usage_designations[]"
                        value="<?=H::e($key)?>"
                    >
                    <?=H::e($label)?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <label>
            Fulfillment instructions
            <textarea
                name="fulfillment_instructions"
                maxlength="5000"
                required
                placeholder="Explain exactly how the winner receives the prize and what you need from Creative Moth or the winner."
            ></textarea>
        </label>

        <label>
            Restrictions
            <textarea
                name="restrictions"
                maxlength="3000"
                placeholder="Optional restrictions, expiration, exclusions, turnaround time, etc."
            ></textarea>
        </label>

        <label class="prize-authorization">
            <input
                type="checkbox"
                name="seller_authorized"
                value="1"
                required
            >
            <span>
                I authorize Creative Moth to award this donated prize
                according to the uses I selected above.
            </span>
        </label>

        <button class="btn">
            Submit Prize Donation
        </button>
    </form>
</section>


<section>
    <h2>My Donated Prizes</h2>

    <?php if(!$donations): ?>
        <div class="card empty-state">
            You have not submitted any prize donations yet.
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Prize</th>
                        <th>Uses</th>
                        <th>Inventory</th>
                        <th>Status</th>
                        <th>Fulfillment</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($donations as $d): ?>
                        <tr>
                            <td>
                                <?php if(!empty($d['preview_image_path'])): ?>
                                    <div style="margin:0 0 .75rem">
                                        <img
                                            src="<?=H::e(H::assetUrl($d['preview_image_path']))?>"
                                            alt="<?=H::e($d['title'])?> prize preview"
                                            style="max-width:160px;max-height:160px;object-fit:contain"
                                        >
                                    </div>
                                <?php endif; ?>

                                <strong><?=H::e($d['title'])?></strong>
                                <br>
                                <?=H::e($types[$d['prize_type']]??$d['prize_type'])?>

                                <?php if(!empty($d['prize_file_storage_path'])): ?>
                                    <br>
                                    <a href="/seller/prize-donations/<?=(int)$d['id']?>/file">
                                        Download submitted prize file
                                    </a>
                                <?php endif; ?>

                                <?php if($d['approximate_retail_value']!==null): ?>
                                    <br>
                                    Approx. value:
                                    <?=H::money($d['approximate_retail_value'])?>
                                <?php endif; ?>

                                <br>
                                <span class="muted">
                                    <?=H::e($d['description'])?>
                                </span>
                            </td>

                            <td>
                                <?php
                                $useKeys=array_filter(
                                    explode(
                                        ',',
                                        (string)$d['usage_designations']
                                    )
                                );
                                ?>
                                <?=H::e(
                                    implode(
                                        ', ',
                                        array_map(
                                            fn($key)=>$usages[$key]??$key,
                                            $useKeys
                                        )
                                    )
                                )?>
                            </td>

                            <td>
                                Donated:
                                <?=number_format((int)$d['quantity_donated'])?>
                                <br>

                                Available:
                                <?=number_format((int)$d['quantity_remaining'])?>
                                <br>

                                Assigned:
                                <?=number_format((int)$d['assigned_quantity'])?>
                                <br>

                                Fulfilled:
                                <?=number_format((int)$d['fulfilled_quantity'])?>
                            </td>

                            <td>
                                <?=H::e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $d['status']
                                        )
                                    )
                                )?>
                            </td>

                            <td>
                                <?=nl2br(H::e($d['fulfillment_instructions']))?>

                                <?php if(!empty($d['restrictions'])): ?>
                                    <br><br>
                                    <strong>Restrictions:</strong>
                                    <br>
                                    <?=nl2br(H::e($d['restrictions']))?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if($d['status']==='submitted'): ?>
                                    <form
                                        method="post"
                                        action="/seller/prize-donations/<?=(int)$d['id']?>/cancel"
                                        onsubmit="return confirm('Withdraw this submitted prize donation?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="_csrf"
                                            value="<?=H::csrf()?>"
                                        >

                                        <button class="danger">
                                            Withdraw
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
