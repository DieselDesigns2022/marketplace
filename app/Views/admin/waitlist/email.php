<?php

use App\Core\Helpers as H;
use App\Services\EmailCampaignService;

$audience = $campaign['audience'] ?? 'waitlist_all';
$body = $safeBody ?? '';
?>

<section class="panel">
    <div class="section-head">
        <div>
            <h1>Send Waitlist Email</h1>
            <p class="muted">
                Create, preview, test, and send a branded Creative Moth email.
            </p>
        </div>

        <div>
            <a class="btn" href="/admin/waitlist">Back to Waitlist</a>
            <a class="btn" href="/admin/email-campaigns">Email History</a>
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
                        <?=H::e($campaign['subject'] ?? '')?>
                    </h2>

                    <p>Hello Test Recipient,</p>

                    <div>
                        <?=$body?>
                    </div>
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
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form
        method="post"
        id="waitlist-email-form"
        style="display:grid;gap:18px"
    >
        <input
            type="hidden"
            name="_csrf"
            value="<?=H::csrf()?>"
        >

        <label>
            Recipients

            <select
                name="audience"
                id="audience"
                required
            >
                <?php foreach(
                    EmailCampaignService::WAITLIST_COMPOSE_AUDIENCES
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

        <div
            class="notice"
            id="recipient-count-box"
            style="margin:0"
        >
            This email will currently go to
            <strong id="recipient-count">0</strong>
            eligible waitlist subscriber(s).
        </div>

        <label>
            Subject

            <input
                type="text"
                name="subject"
                maxlength="190"
                required
                value="<?=H::e($campaign['subject'] ?? '')?>"
                placeholder="Beta testing is opening this week 🦋"
            >
        </label>

        <div>
            <label for="rich-editor">
                Email
            </label>

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
                <button
                    type="button"
                    data-command="bold"
                    title="Bold"
                >
                    <strong>B</strong>
                </button>

                <button
                    type="button"
                    data-command="italic"
                    title="Italic"
                >
                    <em>I</em>
                </button>

                <button
                    type="button"
                    data-command="underline"
                    title="Underline"
                >
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
                    min-height:300px;
                    padding:16px;
                    border:1px solid #ccc;
                    border-radius:8px;
                    background:#fff;
                    overflow:auto;
                "
            ><?=$body?></div>

            <textarea
                name="body"
                id="body-input"
                hidden
            ><?=H::e($body)?></textarea>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:1fr auto;
                gap:10px;
                align-items:end;
            "
        >
            <label style="margin:0">
                Send a test first

                <input
                    type="email"
                    name="test_email"
                    value="<?=H::e(
                        $campaign['test_email'] ??
                        (H::user()['email'] ?? '')
                    )?>"
                    placeholder="your@email.com"
                >
            </label>

            <button
                type="submit"
                name="action"
                value="test"
            >
                Send Test Email
            </button>
        </div>

        <div
            style="
                display:flex;
                gap:10px;
                flex-wrap:wrap;
            "
        >
            <button
                type="submit"
                name="action"
                value="preview"
            >
                Preview Email
            </button>
        </div>

        <div
            style="
                padding:16px;
                border:1px solid #ddd;
                border-radius:10px;
            "
        >
            <label>
                <input
                    type="checkbox"
                    name="confirm_send"
                    value="1"
                    id="confirm-send"
                >

                I confirm I want to send this email to
                <strong id="confirmation-count">0</strong>
                eligible waitlist subscriber(s).
            </label>

            <input
                type="hidden"
                name="confirmed_count"
                id="confirmed-count"
                value="0"
            >

            <p style="margin-bottom:0;margin-top:10px">
                <button
                    type="submit"
                    name="action"
                    value="send"
                    id="send-email-button"
                    class="btn"
                >
                    Send Email
                </button>
            </p>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Recent Waitlist Emails</h2>

    <?php if(!$recentCampaigns): ?>
        <p class="muted">
            No waitlist campaigns have been sent yet.
        </p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($recentCampaigns as $row): ?>
                        <tr>
                            <td>
                                <a href="/admin/email-campaigns/<?=(int)$row['id']?>">
                                    <?=H::e($row['subject'])?>
                                </a>
                            </td>

                            <td>
                                <?=H::e(
                                    EmailCampaignService::audienceLabel(
                                        $row['audience']
                                    )
                                )?>
                            </td>

                            <td><?=H::e($row['status'])?></td>

                            <td>
                                <?=(int)$row['sent']?>
                                /
                                <?=(int)$row['total']?>
                            </td>

                            <td><?=H::e($row['created_at'])?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
(() => {
    const counts = <?=json_encode(
        $audienceCounts,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    )?>;

    const audience = document.getElementById('audience');
    const count = document.getElementById('recipient-count');
    const confirmationCount =
        document.getElementById('confirmation-count');
    const confirmedCount =
        document.getElementById('confirmed-count');

    const editor = document.getElementById('rich-editor');
    const bodyInput = document.getElementById('body-input');
    const form = document.getElementById('waitlist-email-form');

    function syncBody() {
        bodyInput.value = editor.innerHTML;
    }

    function refreshCount() {
        const current =
            Number(counts[audience.value] || 0);

        count.textContent =
            current.toLocaleString();

        confirmationCount.textContent =
            current.toLocaleString();

        confirmedCount.value =
            String(current);
    }

    audience.addEventListener(
        'change',
        refreshCount
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

    document
        .getElementById('heading-tool')
        .addEventListener('change', event => {
            if (!event.target.value) {
                return;
            }

            editor.focus();

            document.execCommand(
                'formatBlock',
                false,
                event.target.value
            );

            event.target.value = '';

            syncBody();
        });

    document
        .getElementById('link-tool')
        .addEventListener('click', () => {
            const url = window.prompt(
                'Paste the link URL:'
            );

            if (!url) {
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

    editor.addEventListener(
        'input',
        syncBody
    );

    form.addEventListener(
        'submit',
        syncBody
    );

    refreshCount();
})();
</script>
