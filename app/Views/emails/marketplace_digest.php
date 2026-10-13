<?php
use App\Core\Helpers as H;

$hasCustom=
    array_key_exists(
        'newsletter_body',
        $data
    );

$format=
    (string)(
        $data['newsletter_body_format']
        ?? 'plain'
    );
?>

<div
    style="
        padding:34px 32px 18px;
        text-align:center;
    "
>
    <div
        style="
            font-size:12px;
            font-weight:700;
            letter-spacing:1.5px;
            text-transform:uppercase;
            color:#ff6b9f;
        "
    >
        <?=H::e(
            ucfirst(
                (string)$data['frequency']
            )
        )?>
        Creative Moth
    </div>

    <?php if($hasCustom): ?>
        <h1
            style="
                margin:10px 0 18px;
                font-size:30px;
                line-height:1.2;
                color:#231942;
            "
        >
            <?=H::e(
                (string)(
                    $data['newsletter_subject']
                    ?? ''
                )
            )?>
        </h1>

        <div
            style="
                text-align:left;
                font-size:16px;
                line-height:1.65;
                color:#231942;
            "
        >
            <?php if($format==='rich_html'): ?>
                <?=$data['newsletter_body']?>
            <?php else: ?>
                <?=nl2br(
                    H::e(
                        (string)$data[
                            'newsletter_body'
                        ]
                    )
                )?>
            <?php endif; ?>
        </div>

        <?php if(
            !empty($data['cta_label']) &&
            !empty($data['cta_url'])
        ): ?>
            <div
                style="
                    margin-top:24px;
                    text-align:center;
                "
            >
                <a
                    href="<?=H::e(
                        $data['cta_url']
                    )?>"
                    style="
                        display:inline-block;
                        padding:12px 20px;
                        border-radius:999px;
                        background:#ff5f99;
                        color:#ffffff;
                        text-decoration:none;
                        font-weight:700;
                    "
                >
                    <?=H::e(
                        $data['cta_label']
                    )?>
                </a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <h1
            style="
                margin:10px 0 12px;
                font-size:30px;
                line-height:1.2;
                color:#231942;
            "
        >
            Fresh finds for your next idea
        </h1>

        <p
            style="
                margin:0;
                color:#6b6478;
                font-size:16px;
                line-height:1.6;
            "
        >
            Hello <?=H::e(
                $data['name']??'there'
            )?> —
            <?=
                ($data['frequency']??'')==='weekly'
                ? 'discover digital goods'
                : 'discover the newest digital goods'
            ?>
            from Creative Moth's independent designers.
        </p>
    <?php endif; ?>
</div>

<?php
if(
    ($data['frequency']??'')==='weekly' &&
    !empty($data['paid_promos'])
){
    require app_path(
        'app/Views/emails/paid_promos.php'
    );
}

require app_path(
    'app/Views/emails/product_cards.php'
);
?>

<div
    style="
        padding:8px 32px 32px;
        text-align:center;
        font-size:13px;
        line-height:1.7;
        color:#6b6478;
    "
>
    <a
        href="<?=H::e(
            $data['manage_preferences_url']
        )?>"
        style="
            color:#6d28d9;
            font-weight:700;
        "
    >
        Manage Email Preferences
    </a>
    <br>

    <a
        href="<?=H::e(
            $data['unsubscribe_url']
        )?>"
        style="color:#6b6478;"
    >
        Unsubscribe from
        <?=H::e($data['frequency'])?>
        emails
    </a>
</div>
