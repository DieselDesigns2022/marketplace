<?php

use App\Core\Helpers as H;
use App\Services\EmailCampaignService;

$typeLabel=match(
    (string)($campaign['newsletter_type']??'one_off')
){
    'weekly'=>'Weekly',
    'monthly'=>'Monthly',
    default=>'One-off'
};

$scheduledLabel='';

if(!empty($campaign['scheduled_for'])){
    $timezone=
        (string)(
            $campaign['schedule_timezone']
            ?? 'America/Detroit'
        );

    try {
        $scheduledLabel=
            (new DateTimeImmutable(
                $campaign['scheduled_for'],
                new DateTimeZone('UTC')
            ))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('M j, Y g:i A T');
    } catch(Throwable $ignored) {
        $scheduledLabel=
            (string)$campaign['scheduled_for'].' UTC';
    }
}
?>

<section class="panel">
    <div class="section-head">
        <div>
            <h1><?=H::e($campaign['subject'])?></h1>

            <p class="muted">
                <?=$typeLabel?> Newsletter
                · Status:
                <strong><?=H::e($campaign['status'])?></strong>
                · Audience:
                <?=H::e(
                    EmailCampaignService::audienceLabel(
                        $campaign['audience']
                    )
                )?>
            </p>
        </div>

        <div>
            <a
                class="btn"
                href="/admin/email-campaigns"
            >
                Email History
            </a>

            <?php if(
                H::canAdmin('email_campaigns.manage') &&
                in_array(
                    $campaign['status'],
                    ['draft','scheduled'],
                    true
                )
            ): ?>
                <a
                    class="btn"
                    href="/admin/email-campaigns/<?=
                        (int)$campaign['id']
                    ?>/edit"
                >
                    Edit Newsletter
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($scheduledLabel!==''): ?>
        <div class="notice">
            <strong>Scheduled:</strong>
            <?=H::e($scheduledLabel)?>

            <?php if(
                ($campaign['status']??'')==='scheduled'
            ): ?>
                <br>
                <span class="muted">
                    Use Edit Newsletter to reschedule or
                    return this newsletter to draft.
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($campaign['preheader'])): ?>
        <p>
            <strong>Preheader:</strong>
            <?=H::e($campaign['preheader'])?>
        </p>
    <?php endif; ?>

    <div class="email-preview">
        <?php if(
            ($campaign['body_format']??'plain')
            ==='rich_html'
        ): ?>
            <div><?=$campaign['body']?></div>
        <?php else: ?>
            <p>
                <?=nl2br(H::e($campaign['body']))?>
            </p>
        <?php endif; ?>

        <?php if(
            !empty($campaign['cta_url']) &&
            !empty($campaign['cta_label'])
        ): ?>
            <p>
                <a href="<?=H::e($campaign['cta_url'])?>">
                    <?=H::e($campaign['cta_label'])?>
                </a>
            </p>
        <?php endif; ?>
    </div>

    <p>
        Queued <?=(int)($counts['queued']??0)?>
        · Sent <?=(int)($counts['sent']??0)?>
        · Failed <?=(int)($counts['failed']??0)?>
        · Suppressed <?=(int)($counts['suppressed']??0)?>
        · Cancelled <?=(int)($counts['cancelled']??0)?>
        · Remaining <?=(int)($counts['remaining']??0)?>
    </p>

    <?php if(H::canAdmin('email_campaigns.manage')): ?>
        <form
            method="post"
            class="admin-actions"
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?=H::csrf()?>"
            >

            <?php if($campaign['status']==='draft'): ?>
                <button
                    name="action"
                    value="queue"
                >
                    Send Now
                </button>
            <?php endif; ?>

            <?php if(
                in_array(
                    $campaign['status'],
                    ['draft','queued','sending'],
                    true
                )
            ): ?>
                <button
                    name="action"
                    value="cancel"
                >
                    Cancel Unsent
                </button>
            <?php endif; ?>

            <input
                type="email"
                name="test_email"
                placeholder="Active admin email"
            >

            <button
                name="action"
                value="test"
            >
                Send Test Email
            </button>
        </form>
    <?php endif; ?>

    <h2>Recipients</h2>

    <?php if(!$recipients): ?>
        <p class="muted">
            No newsletter recipients have been selected yet.
        </p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Last Error</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($recipients as $recipient): ?>
                        <tr>
                            <td>
                                <?=H::e($recipient['email'])?>
                            </td>

                            <td>
                                <?=H::e($recipient['name'])?>
                            </td>

                            <td>
                                <?=H::e($recipient['status'])?>
                            </td>

                            <td>
                                <?=H::e(
                                    $recipient['last_error']
                                )?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
