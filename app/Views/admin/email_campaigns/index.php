<?php

use App\Core\Helpers as H;
use App\Services\EmailCampaignService;

function newsletterAdminScheduledLabel(array $campaign): string
{
    if(empty($campaign['scheduled_for'])){
        return '—';
    }

    $timezone=
        (string)($campaign['schedule_timezone']??'America/Detroit');

    try {
        return
            (new DateTimeImmutable(
                $campaign['scheduled_for'],
                new DateTimeZone('UTC')
            ))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('M j, Y g:i A T');
    } catch(Throwable $ignored) {
        return (string)$campaign['scheduled_for'].' UTC';
    }
}

function newsletterAdminTypeLabel(string $type): string
{
    return match($type){
        'weekly'=>'Weekly',
        'monthly'=>'Monthly',
        default=>'One-off'
    };
}
?>

<section class="panel">
    <div class="section-head">
        <div>
            <h1>Email Newsletters</h1>

            <p class="muted">
                Create, schedule, send, and review Creative Moth emails.
            </p>
        </div>

        <?php if(H::canAdmin('email_campaigns.manage')): ?>
            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:8px;
                "
            >
                <a
                    class="btn"
                    href="/admin/email-campaigns/recurring/weekly"
                >
                    Weekly Newsletter
                </a>

                <a
                    class="btn"
                    href="/admin/email-campaigns/recurring/monthly"
                >
                    Monthly Newsletter
                </a>

                <a
                    class="btn"
                    href="/admin/email-campaigns/new"
                >
                    Create One-Off Newsletter
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if(!$campaigns): ?>
        <p class="muted">
            No newsletters have been created yet.
        </p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Sent / Total</th>
                        <th>Failed</th>
                        <?php if(
                            H::canAdmin('email_campaigns.manage')
                        ): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($campaigns as $campaign): ?>
                        <tr>
                            <td>
                                <a
                                    href="/admin/email-campaigns/<?=
                                        (int)$campaign['id']
                                    ?>"
                                >
                                    <?=H::e($campaign['subject'])?>
                                </a>
                            </td>

                            <td>
                                <?=H::e(
                                    newsletterAdminTypeLabel(
                                        (string)(
                                            $campaign['newsletter_type']
                                            ?? 'one_off'
                                        )
                                    )
                                )?>
                            </td>

                            <td>
                                <?=H::e(
                                    EmailCampaignService::audienceLabel(
                                        $campaign['audience']
                                    )
                                )?>
                            </td>

                            <td>
                                <strong>
                                    <?=H::e($campaign['status'])?>
                                </strong>
                            </td>

                            <td>
                                <?=H::e(
                                    newsletterAdminScheduledLabel(
                                        $campaign
                                    )
                                )?>
                            </td>

                            <td>
                                <?=(int)$campaign['sent']?>
                                /
                                <?=(int)$campaign['total']?>
                            </td>

                            <td>
                                <?=(int)$campaign['failed']?>
                            </td>

                            <?php if(
                                H::canAdmin(
                                    'email_campaigns.manage'
                                )
                            ): ?>
                                <td>
                                    <?php if(
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
                                            Edit
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
