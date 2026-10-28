<?php

use App\Core\Helpers as H;
use App\Services\EmailCampaignService;

$editing=(bool)($editing??false);
$audience=$campaign['audience']??'users_opted_in';
$bodyFormat=$campaign['body_format']??'rich_html';
$body=(string)($campaign['body']??'');

$isCustomHtml=
    $bodyFormat==='rich_html' &&
    str_contains(
        $body,
        'data-cm-layout="custom"'
    );

$sourceBody=$body;

if(
    $isCustomHtml &&
    preg_match(
        '/^<div data-cm-layout="custom">(.*)<\/div>$/s',
        $body,
        $customMatch
    )
){
    $sourceBody=$customMatch[1];
}

if($bodyFormat!=='rich_html'){
    $body=nl2br(H::e($body));
    $sourceBody=$body;
}

$scheduleTimezone=
    (string)($campaign['schedule_timezone']??'America/Detroit');

$scheduledLocal='';

if(
    !empty($campaign['scheduled_for']) &&
    $scheduleTimezone!==''
){
    try {
        $scheduledLocal=
            (new DateTimeImmutable(
                $campaign['scheduled_for'],
                new DateTimeZone('UTC')
            ))
            ->setTimezone(
                new DateTimeZone($scheduleTimezone)
            )
            ->format('Y-m-d\TH:i');
    } catch(Throwable $ignored) {
        $scheduledLocal='';
    }
}
?>

<section class="panel">
    <div class="section-head">
        <div>
            <h1>
                <?=$editing
                    ? 'Edit Newsletter'
                    : 'Create Newsletter'?>
            </h1>

            <p class="muted">
                Create, preview, save, schedule, and send
                branded Creative Moth emails.
            </p>
        </div>

        <div>
            <a class="btn" href="/admin/email-campaigns">
                Email History
            </a>

            <?php if($editing): ?>
                <a
                    class="btn"
                    href="/admin/email-campaigns/<?=(int)$campaign['id']?>"
                >
                    View Newsletter
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach($errors as $error): ?>
        <div class="notice warning">
            <?=H::e($error)?>
        </div>
    <?php endforeach; ?>

    <?php if($preview): ?>
        <h2>Email Preview</h2>

        <div
            style="
                background:#fff7fb;
                padding:24px 12px;
                margin-bottom:24px;
            "
        >
            <div
                style="
                    max-width:640px;
                    margin:auto;
                    background:#fff;
                    border:1px solid #eee;
                    border-radius:18px;
                    overflow:hidden;
                "
            >
                <div
                    style="
                        padding:18px 28px;
                        text-align:center;
                        border-top:6px solid #ff6b9f;
                        border-bottom:1px solid #eee;
                    "
                >
                    <img
                        src="<?=H::e(
                            H::assetUrl(
                                'assets/img/creative-moth-logo.png'
                            )
                        )?>"
                        alt="Creative Moth"
                        style="
                            max-width:220px;
                            width:100%;
                            height:auto;
                        "
                    >
                </div>

                <div style="padding:28px">
                    <h2 style="margin-top:0">
                        <?=H::e($campaign['subject']??'')?>
                    </h2>

                    <?php if(!empty($campaign['preheader'])): ?>
                        <p
                            style="
                                color:#777;
                                font-size:13px;
                                margin-top:-8px;
                            "
                        >
                            <?=H::e($campaign['preheader'])?>
                        </p>
                    <?php endif; ?>

                    <?php if(
                        !(
                            ($campaign['body_format']??'rich_html')
                            ==='rich_html' &&
                            str_contains(
                                (string)($campaign['body']??''),
                                'data-cm-layout="custom"'
                            )
                        )
                    ): ?>
                        <p>Hello Creative Moth member,</p>
                    <?php endif; ?>

                    <div>
                        <?php if(
                            ($campaign['body_format']??'rich_html')
                            ==='rich_html'
                        ): ?>
                            <?=$campaign['body']??''?>
                        <?php else: ?>
                            <?=nl2br(
                                H::e($campaign['body']??'')
                            )?>
                        <?php endif; ?>
                    </div>

                    <?php if(
                        !empty($campaign['cta_url']) &&
                        !empty($campaign['cta_label'])
                    ): ?>
                        <p style="margin-top:24px">
                            <a
                                href="<?=H::e($campaign['cta_url'])?>"
                                style="
                                    display:inline-block;
                                    padding:12px 18px;
                                    border-radius:8px;
                                    text-decoration:none;
                                    font-weight:700;
                                "
                            >
                                <?=H::e($campaign['cta_label'])?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

                <div
                    style="
                        padding:20px 28px;
                        background:#fffafc;
                        border-top:4px solid #67e8c9;
                        text-align:center;
                        font-size:12px;
                    "
                >
                    Made for creative people and independent designers.
                    <br>
                    Creative Moth
                    <br><br>
                    <?php if(
                        ($campaign['audience']??'')
                        ==='users_sellers_system'
                    ): ?>
                        Required seller system notice — no marketing
                        unsubscribe link is added.
                    <?php else: ?>
                        An unsubscribe link will be included automatically.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form
        method="post"
        id="newsletter-form"
        style="display:grid;gap:18px"
    >
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <input
            type="hidden"
            name="body_format"
            value="rich_html"
        >

        <label>
            Audience

            <select name="audience" required>
                <?php foreach(
                    EmailCampaignService::AUDIENCES
                    as $value
                ): ?>
                    <option
                        value="<?=H::e($value)?>"
                        <?=$audience===$value?'selected':''?>
                    >
                        <?=H::e(
                            EmailCampaignService::audienceLabel($value)
                        )?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Subject

            <input
                type="text"
                name="subject"
                maxlength="190"
                required
                value="<?=H::e($campaign['subject']??'')?>"
                placeholder="What's new at Creative Moth 🦋"
            >
        </label>

        <label>
            Preheader

            <input
                type="text"
                name="preheader"
                maxlength="255"
                value="<?=H::e($campaign['preheader']??'')?>"
                placeholder="The short preview text shown beside the subject line"
            >

            <small class="muted">
                Optional. Many inboxes display this beside or below
                the subject before the email is opened.
            </small>
        </label>

        <div>
            <label for="rich-editor">
                Email Content
            </label>

            <div
                style="
                    display:flex;
                    gap:8px;
                    margin:7px 0 10px;
                "
            >
                <button
                    type="button"
                    id="visual-mode-button"
                >
                    Visual Editor
                </button>

                <button
                    type="button"
                    id="html-mode-button"
                >
                    HTML Source
                </button>
            </div>

            <p class="muted" style="margin-top:0">
                Use Visual Editor for normal formatted emails.
                Use HTML Source when pasting a complete branded
                email layout with tables, images, buttons, colors,
                and inline styles.
            </p>

            <div
                id="email-toolbar"
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:6px;
                    margin:7px 0;
                    padding:8px;
                    border:1px solid #ddd;
                    border-radius:8px;
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
                    id="heading-tool"
                    aria-label="Text style"
                >
                    <option value="">Text style</option>
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
                    id="link-tool"
                >
                    🔗 Link
                </button>

                <button type="button" class="emoji-tool">🦋</button>
                <button type="button" class="emoji-tool">✨</button>
                <button type="button" class="emoji-tool">💕</button>
                <button type="button" class="emoji-tool">🎉</button>
                <button type="button" class="emoji-tool">👀</button>
            </div>

            <div
                id="rich-editor"
                contenteditable="true"
                style="
                    min-height:320px;
                    padding:16px;
                    border:1px solid #ccc;
                    border-radius:8px;
                    background:#fff;
                    overflow:auto;
                "
            ><?=$body?></div>

            <textarea
                id="html-source-editor"
                spellcheck="false"
                style="
                    display:none;
                    width:100%;
                    min-height:520px;
                    padding:14px;
                    border:1px solid #ccc;
                    border-radius:8px;
                    background:#111;
                    color:#f5f5f5;
                    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
                    font-size:13px;
                    line-height:1.45;
                    white-space:pre;
                    overflow:auto;
                "
            ><?=H::e($sourceBody)?></textarea>

            <textarea
                name="body"
                id="body-input"
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
                        $campaign['cta_label']??''
                    )?>"
                    placeholder="Shop Creative Moth"
                >
            </label>

            <label>
                Button / CTA Link

                <input
                    type="text"
                    name="cta_url"
                    maxlength="500"
                    value="<?=H::e(
                        $campaign['cta_url']??''
                    )?>"
                    placeholder="/browse"
                >
            </label>
        </div>

        <?php if($editing): ?>
            <div
                style="
                    padding:16px;
                    border:1px solid #ddd;
                    border-radius:10px;
                "
            >
                <h3 style="margin-top:0">
                    Schedule Newsletter
                </h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >
                    <label>
                        Send Date & Time

                        <input
                            type="datetime-local"
                            name="scheduled_local"
                            value="<?=H::e($scheduledLocal)?>"
                        >
                    </label>

                    <label>
                        Timezone

                        <select name="schedule_timezone">
                            <?php foreach([
                                'America/Detroit'=>'Eastern — Detroit',
                                'America/Chicago'=>'Central',
                                'America/Denver'=>'Mountain',
                                'America/Los_Angeles'=>'Pacific'
                            ] as $value=>$label): ?>
                                <option
                                    value="<?=H::e($value)?>"
                                    <?=$scheduleTimezone===$value
                                        ?'selected'
                                        :''?>
                                >
                                    <?=H::e($label)?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <?php if(
                    ($campaign['status']??'')==='scheduled'
                ): ?>
                    <p class="muted">
                        This newsletter is currently scheduled.
                        You can change the date/time and click
                        <strong>Reschedule</strong>, or move it
                        back to draft.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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

            <?php if($editing): ?>
                <button
                    type="submit"
                    name="action"
                    value="save"
                >
                    Save Changes
                </button>

                <button
                    type="submit"
                    name="action"
                    value="schedule"
                >
                    <?=($campaign['status']??'')==='scheduled'
                        ? 'Reschedule'
                        : 'Schedule Newsletter'?>
                </button>

                <?php if(
                    ($campaign['status']??'')==='scheduled'
                ): ?>
                    <button
                        type="submit"
                        name="action"
                        value="unschedule"
                    >
                        Unschedule / Return to Draft
                    </button>
                <?php endif; ?>

            <?php else: ?>
                <button
                    type="submit"
                    name="action"
                    value="save"
                >
                    Save Draft
                </button>

                <button
                    type="submit"
                    name="action"
                    value="queue"
                >
                    Save & Send Now
                </button>
            <?php endif; ?>
        </div>
    </form>
</section>

<script>
(() => {
    const editor =
        document.getElementById('rich-editor');

    const bodyInput =
        document.getElementById('body-input');

    const form =
        document.getElementById('newsletter-form');

    const sourceEditor =
        document.getElementById('html-source-editor');

    const toolbar =
        document.getElementById('email-toolbar');

    const visualModeButton =
        document.getElementById('visual-mode-button');

    const htmlModeButton =
        document.getElementById('html-mode-button');

    let editorMode =
        <?= $isCustomHtml ? "'html'" : "'visual'" ?>;

    const headingTool =
        document.getElementById('heading-tool');

    const linkTool =
        document.getElementById('link-tool');

    function syncBody() {
        if(editorMode==='html'){
            bodyInput.value=
                '<div data-cm-layout="custom">' +
                sourceEditor.value +
                '</div>';
            return;
        }

        bodyInput.value=editor.innerHTML;
    }

    function showVisualMode() {
        if(editorMode==='html'){
            editor.innerHTML=sourceEditor.value;
        }

        editorMode='visual';
        toolbar.style.display='flex';
        editor.style.display='block';
        sourceEditor.style.display='none';

        visualModeButton.disabled=true;
        htmlModeButton.disabled=false;

        syncBody();
    }

    function showHtmlMode() {
        if(editorMode==='visual'){
            sourceEditor.value=editor.innerHTML;
        }

        editorMode='html';
        toolbar.style.display='none';
        editor.style.display='none';
        sourceEditor.style.display='block';

        visualModeButton.disabled=false;
        htmlModeButton.disabled=true;

        syncBody();
    }

    visualModeButton.addEventListener(
        'click',
        showVisualMode
    );

    htmlModeButton.addEventListener(
        'click',
        showHtmlMode
    );

    sourceEditor.addEventListener(
        'input',
        syncBody
    );

    document
        .querySelectorAll('[data-command]')
        .forEach(button => {
            button.addEventListener('click', () => {
                editor.focus();

                document.execCommand(
                    button.dataset.command,
                    false,
                    null
                );

                syncBody();
            });
        });

    headingTool.addEventListener('change', () => {
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

    linkTool.addEventListener('click', () => {
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

    document
        .querySelectorAll('.emoji-tool')
        .forEach(button => {
            button.addEventListener('click', () => {
                editor.focus();

                document.execCommand(
                    'insertText',
                    false,
                    button.textContent
                );

                syncBody();
            });
        });

    editor.addEventListener('input',syncBody);

    form.addEventListener('submit',syncBody);

    if(editorMode==='html'){
        showHtmlMode();
    }else{
        showVisualMode();
    }
})();
</script>
