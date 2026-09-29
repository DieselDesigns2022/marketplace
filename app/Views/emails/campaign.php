<?php

use App\Core\Helpers as H;

$rich =
    ($data['body_format'] ?? 'plain') === 'rich_html';

?>

<div
    style="
        padding:28px;
        font-size:16px;
        line-height:1.65;
        color:#231942;
    "
>
    <p>
        Hello <?=H::e($data['name'] ?? '')?>,
    </p>

    <?php if($rich): ?>

        <div>
            <?=$data['body'] ?? ''?>
        </div>

    <?php else: ?>

        <p>
            <?=nl2br(H::e($data['body'] ?? ''))?>
        </p>

    <?php endif; ?>

    <?php if(!empty($data['cta_url'])): ?>
        <p style="margin-top:24px">
            <a
                href="<?=H::e($data['cta_url'])?>"
                style="
                    display:inline-block;
                    padding:12px 18px;
                    background:#231942;
                    color:#fff;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                "
            >
                <?=H::e(
                    $data['cta_label'] ?? 'Learn more'
                )?>
            </a>
        </p>
    <?php endif; ?>

    <?php if(!empty($data['unsubscribe_url'])): ?>
        <p
            style="
                margin-top:32px;
                font-size:12px;
                color:#6b6478;
            "
        >
            <a href="<?=H::e($data['unsubscribe_url'])?>">
                Unsubscribe from optional email
            </a>
        </p>
    <?php endif; ?>
</div>
