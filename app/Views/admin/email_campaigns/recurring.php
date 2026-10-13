<?php

use App\Core\Helpers as H;

$isWeekly=$frequency==='weekly';

$hour=(int)($definition['send_hour']??10);
$minute=(int)($definition['send_minute']??0);

$timeValue=
    str_pad((string)$hour,2,'0',STR_PAD_LEFT).
    ':'.
    str_pad((string)$minute,2,'0',STR_PAD_LEFT);

$body=(string)($definition['body']??'');

if(
    ($definition['body_format']??'rich_html')
    !=='rich_html'
){
    $body=nl2br(H::e($body));
}

$nextRun='';

if(!empty($definition['next_run_at'])){
    try {
        $tz=new DateTimeZone(
            (string)($definition['timezone']??'America/Detroit')
        );

        $nextRun=
            (new DateTimeImmutable(
                $definition['next_run_at'],
                new DateTimeZone('UTC')
            ))
            ->setTimezone($tz)
            ->format('M j, Y g:i A T');
    } catch(Throwable $ignored) {
        $nextRun=
            (string)$definition['next_run_at'].' UTC';
    }
}
?>

<section class="panel">
    <div class="section-head">
        <div>
            <h1>
                <?=$isWeekly
                    ? 'Weekly Newsletter'
                    : 'Monthly Newsletter'?>
            </h1>

            <p class="muted">
                Edit the recurring Creative Moth
                <?=$isWeekly?'weekly':'monthly'?>
                marketplace email.
            </p>
        </div>

        <div>
            <a
                class="btn"
                href="/admin/email-campaigns"
            >
                Email Newsletters
            </a>

            <a
                class="btn"
                href="/admin/email-campaigns/recurring/<?=
                    $isWeekly?'monthly':'weekly'
                ?>"
            >
                <?=$isWeekly
                    ? 'Monthly Newsletter'
                    : 'Weekly Newsletter'?>
            </a>
        </div>
    </div>

    <?php foreach($errors as $error): ?>
        <div class="notice warning">
            <?=H::e($error)?>
        </div>
    <?php endforeach; ?>

    <?php if($nextRun!==''): ?>
        <div class="notice">
            <strong>Next scheduled run:</strong>
            <?=H::e($nextRun)?>
        </div>
    <?php endif; ?>

    <?php if($preview): ?>
        <?php
        $previewProducts=
            is_array($previewContent['products']??null)
                ? $previewContent['products']
                : [];

        $previewPromos=
            is_array($previewContent['paid_promos']??null)
                ? $previewContent['paid_promos']
                : [];

        $data=[
            'frequency'=>$frequency,
            'marketing_preference'=>$frequency,
            'products'=>$previewProducts,
            'paid_promos'=>$previewPromos,
            'manage_preferences_url'=>'#',
            'unsubscribe_url'=>'#'
        ];

        ob_start();
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
                <?=H::e(ucfirst($frequency))?>
                Creative Moth
            </div>

            <h1
                style="
                    margin:10px 0 12px;
                    font-size:30px;
                    line-height:1.2;
                    color:#231942;
                "
            >
                <?=H::e($definition['subject']??'')?>
            </h1>

            <?php if(!empty($definition['preheader'])): ?>
                <p
                    style="
                        margin:0 0 18px;
                        color:#6b6478;
                        font-size:14px;
                        line-height:1.5;
                    "
                >
                    <?=H::e($definition['preheader'])?>
                </p>
            <?php endif; ?>

            <div
                style="
                    text-align:left;
                    font-size:16px;
                    line-height:1.65;
                    color:#231942;
                "
            >
                <?=$definition['body']??''?>
            </div>

            <?php if(
                !empty($definition['cta_label']) &&
                !empty($definition['cta_url'])
            ): ?>
                <div
                    style="
                        margin-top:24px;
                        text-align:center;
                    "
                >
                    <a
                        href="<?=H::e($definition['cta_url'])?>"
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
                        <?=H::e($definition['cta_label'])?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if(
            $isWeekly &&
            $previewPromos
        ): ?>
            <?php require app_path(
                'app/Views/emails/paid_promos.php'
            ); ?>
        <?php endif; ?>

        <?php if($previewProducts): ?>
            <?php require app_path(
                'app/Views/emails/product_cards.php'
            ); ?>
        <?php else: ?>
            <div
                style="
                    padding:28px 32px;
                    text-align:center;
                    color:#6b6478;
                "
            >
                No eligible marketplace products are
                available for this preview period.
            </div>
        <?php endif; ?>

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
                href="#"
                style="
                    color:#6d28d9;
                    font-weight:700;
                "
            >
                Manage Email Preferences
            </a>
            <br>

            <a
                href="#"
                style="color:#6b6478;"
            >
                Unsubscribe from
                <?=H::e($frequency)?>
                emails
            </a>
        </div>

        <?php
        $content=(string)ob_get_clean();

        ob_start();
        require app_path(
            'app/Views/emails/layout.php'
        );
        $previewHtml=(string)ob_get_clean();
        ?>

        <h2>Email Preview</h2>

        <p class="muted">
            This preview is isolated from the Admin website
            styles so it matches the customer email layout.
        </p>

        <iframe
            id="newsletter-email-preview"
            title="Newsletter email preview"
            srcdoc="<?=H::e($previewHtml)?>"
            style="
                display:block;
                width:100%;
                min-height:1100px;
                border:1px solid #ddd;
                border-radius:12px;
                background:#fff;
            "
        ></iframe>

        <script>
        (() => {
            const frame=
                document.getElementById(
                    'newsletter-email-preview'
                );

            if(!frame){
                return;
            }

            const resizePreview=() => {
                try {
                    const doc=frame.contentDocument;

                    if(!doc){
                        return;
                    }

                    const height=Math.max(
                        doc.body?.scrollHeight||0,
                        doc.documentElement?.scrollHeight||0
                    );

                    if(height>0){
                        frame.style.height=
                            (height+20)+'px';
                    }
                } catch(error) {
                    // Keep minimum height if iframe measurement
                    // is unavailable.
                }
            };

            frame.addEventListener(
                'load',
                resizePreview
            );

            window.setTimeout(
                resizePreview,
                300
            );
        })();
        </script>
    <?php endif; ?>

    <form
        method="post"
        id="recurring-newsletter-form"
        style="display:grid;gap:18px"
    >
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <input
            type="hidden"
            name="frequency"
            value="<?=H::e($frequency)?>"
        >

        <input
            type="hidden"
            name="audience"
            value="<?=H::e(
                $isWeekly
                    ? 'users_weekly'
                    : 'users_monthly'
            )?>"
        >

        <input
            type="hidden"
            name="body_format"
            value="rich_html"
        >

        <label>
            Internal Name

            <input
                type="text"
                name="name"
                maxlength="190"
                required
                value="<?=H::e(
                    $definition['name']??''
                )?>"
            >
        </label>

        <label>
            Audience

            <input
                type="text"
                value="<?=H::e(
                    $isWeekly
                        ? 'Users subscribed to Weekly Emails'
                        : 'Users subscribed to Monthly Emails'
                )?>"
                disabled
            >

            <small class="muted">
                This audience is locked to the matching
                email preference.
            </small>
        </label>

        <label>
            Subject

            <input
                type="text"
                name="subject"
                maxlength="190"
                required
                value="<?=H::e(
                    $definition['subject']??''
                )?>"
            >
        </label>

        <label>
            Preheader

            <input
                type="text"
                name="preheader"
                maxlength="255"
                value="<?=H::e(
                    $definition['preheader']??''
                )?>"
            >
        </label>

        <div>
            <label for="recurring-editor">
                Editable Newsletter Content
            </label>

            <div
                id="recurring-toolbar"
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:6px;
                    margin:7px 0;
                "
            >
                <button type="button" data-command="bold">
                    <strong>B</strong>
                </button>

                <button type="button" data-command="italic">
                    <em>I</em>
                </button>

                <button type="button" data-command="underline">
                    <u>U</u>
                </button>

                <select
                    id="recurring-heading-tool"
                    aria-label="Text style"
                    style="
                        width:auto;
                        min-width:145px;
                        flex:0 0 auto;
                        margin:0;
                    "
                >
                    <option value="">Text Style</option>
                    <option value="p">Paragraph</option>
                    <option value="h2">Heading</option>
                    <option value="h3">Subheading</option>
                </select>

                <button
                    type="button"
                    data-command="insertUnorderedList"
                >
                    • List
                </button>

                <button
                    type="button"
                    data-command="insertOrderedList"
                >
                    1. List
                </button>

                <button
                    type="button"
                    id="recurring-link-tool"
                >
                    🔗 Link
                </button>

                <button type="button" class="emoji-tool">🦋</button>
                <button type="button" class="emoji-tool">✨</button>
                <button type="button" class="emoji-tool">💕</button>
                <button type="button" class="emoji-tool">🎉</button>
            </div>

            <div
                id="recurring-editor"
                contenteditable="true"
                style="
                    min-height:300px;
                    padding:16px;
                    border:1px solid #ccc;
                    border-radius:8px;
                    background:#fff;
                "
            ><?=$body?></div>

            <textarea
                id="recurring-body"
                name="body"
                hidden
            ><?=H::e($body)?></textarea>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:14px;
            "
        >
            <label>
                Button / CTA Text

                <input
                    type="text"
                    name="cta_label"
                    maxlength="80"
                    value="<?=H::e(
                        $definition['cta_label']??''
                    )?>"
                >
            </label>

            <label>
                Button / CTA Link

                <input
                    type="text"
                    name="cta_url"
                    maxlength="500"
                    value="<?=H::e(
                        $definition['cta_url']??''
                    )?>"
                >
            </label>
        </div>

        <div
            style="
                padding:16px;
                border:1px solid #ddd;
                border-radius:10px;
            "
        >
            <h3 style="margin-top:0">
                Recurring Schedule
            </h3>

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr 1fr 1fr;
                    gap:14px;
                "
            >
                <?php if($isWeekly): ?>
                    <label>
                        Day of Week

                        <select name="weekly_day">
                            <?php foreach([
                                1=>'Monday',
                                2=>'Tuesday',
                                3=>'Wednesday',
                                4=>'Thursday',
                                5=>'Friday',
                                6=>'Saturday',
                                7=>'Sunday'
                            ] as $number=>$label): ?>
                                <option
                                    value="<?=$number?>"
                                    <?=
                                        (int)(
                                            $definition['weekly_day']
                                            ??1
                                        )===$number
                                            ?'selected'
                                            :''
                                    ?>
                                >
                                    <?=H::e($label)?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input
                            type="hidden"
                            name="monthly_day"
                            value=""
                        >
                    </label>
                <?php else: ?>
                    <label>
                        Day of Month

                        <select name="monthly_day">
                            <?php for($day=1;$day<=28;$day++): ?>
                                <option
                                    value="<?=$day?>"
                                    <?=
                                        (int)(
                                            $definition['monthly_day']
                                            ??1
                                        )===$day
                                            ?'selected'
                                            :''
                                    ?>
                                >
                                    <?=$day?>
                                </option>
                            <?php endfor; ?>
                        </select>

                        <input
                            type="hidden"
                            name="weekly_day"
                            value=""
                        >
                    </label>
                <?php endif; ?>

                <label>
                    Send Time

                    <input
                        type="time"
                        id="send-time"
                        value="<?=H::e($timeValue)?>"
                        required
                    >

                    <input
                        type="hidden"
                        name="send_hour"
                        id="send-hour"
                        value="<?=$hour?>"
                    >

                    <input
                        type="hidden"
                        name="send_minute"
                        id="send-minute"
                        value="<?=$minute?>"
                    >
                </label>

                <label>
                    Timezone

                    <select name="timezone">
                        <?php foreach([
                            'America/Detroit'=>'Eastern — Detroit',
                            'America/Chicago'=>'Central',
                            'America/Denver'=>'Mountain',
                            'America/Los_Angeles'=>'Pacific'
                        ] as $value=>$label): ?>
                            <option
                                value="<?=H::e($value)?>"
                                <?=
                                    ($definition['timezone']
                                        ??'America/Detroit')
                                    ===$value
                                        ?'selected'
                                        :''
                                ?>
                            >
                                <?=H::e($label)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label
                style="
                    display:flex;
                    gap:8px;
                    align-items:center;
                    margin-top:16px;
                "
            >
                <input
                    type="checkbox"
                    name="enabled"
                    value="1"
                    <?=!empty($definition['enabled'])
                        ?'checked'
                        :''?>
                >

                Enable automatic
                <?=$isWeekly?'weekly':'monthly'?>
                newsletter
            </label>
        </div>

        <div
            style="
                padding:16px;
                border:1px solid #ddd;
                border-radius:10px;
            "
        >
            <label>
                Send Test Email

                <input
                    type="email"
                    name="test_email"
                    placeholder="Active admin email address"
                    value="<?=H::e(
                        $_POST['test_email']??''
                    )?>"
                >
            </label>

            <small class="muted">
                Test emails are sent only to an active Creative Moth administrator.
            </small>
        </div>

        <div
            style="
                display:flex;
                flex-wrap:wrap;
                gap:10px;
            "
        >
            <button
                type="submit"
                name="action"
                value="preview"
            >
                Preview Email
            </button>

            <button
                type="submit"
                name="action"
                value="test"
            >
                Send Test Email
            </button>

            <button
                type="submit"
                name="action"
                value="save"
            >
                Save Changes
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const editor=
        document.getElementById('recurring-editor');

    const body=
        document.getElementById('recurring-body');

    const form=
        document.getElementById(
            'recurring-newsletter-form'
        );

    const headingTool=
        document.getElementById('recurring-heading-tool');

    const sendTime=
        document.getElementById('send-time');

    const sendHour=
        document.getElementById('send-hour');

    const sendMinute=
        document.getElementById('send-minute');

    function syncBody(){
        body.value=editor.innerHTML;
    }

    function syncTime(){
        const parts=(sendTime.value||'').split(':');

        if(parts.length===2){
            sendHour.value=parseInt(parts[0],10);
            sendMinute.value=parseInt(parts[1],10);
        }
    }

    document
        .querySelectorAll('[data-command]')
        .forEach(button => {
            button.addEventListener('click',() => {
                editor.focus();

                document.execCommand(
                    button.dataset.command,
                    false,
                    null
                );

                syncBody();
            });
        });

    headingTool.addEventListener('change',() => {
        if(!headingTool.value){
            return;
        }

        editor.focus();

        document.execCommand(
            'formatBlock',
            false,
            headingTool.value
        );

        headingTool.value='';
        syncBody();
    });

    document
        .querySelectorAll('.emoji-tool')
        .forEach(button => {
            button.addEventListener('click',() => {
                editor.focus();

                document.execCommand(
                    'insertText',
                    false,
                    button.textContent
                );

                syncBody();
            });
        });

    document
        .getElementById('recurring-link-tool')
        .addEventListener('click',() => {
            const url=window.prompt(
                'Paste the link you want to add:'
            );

            if(!url){
                return;
            }

            editor.focus();

            document.execCommand(
                'createLink',
                false,
                url
            );

            syncBody();
        });

    editor.addEventListener('input',syncBody);
    sendTime.addEventListener('change',syncTime);

    form.addEventListener('submit',() => {
        syncBody();
        syncTime();
    });

    syncBody();
    syncTime();
})();
</script>
