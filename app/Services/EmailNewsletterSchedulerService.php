<?php
namespace App\Services;

use App\Core\Database as DB;

final class EmailNewsletterSchedulerService
{
    public static function run(int $limit=20): array
    {
        $limit=max(1,min(100,$limit));

        return [
            'recurring_created'=>
                self::createDueRecurring($limit),

            'recurring_queued'=>
                self::queueDueRecurring($limit),

            'one_off_queued'=>
                self::queueDueOneOff($limit)
        ];
    }

    public static function createDueRecurring(
        int $limit=20
    ): int {
        $created=0;

        for($i=0;$i<$limit;$i++){
            DB::begin();

            try {
                $definition=DB::row(
                    'select *
                     from email_newsletter_recurring_definitions
                     where enabled=1
                       and next_run_at is not null
                       and next_run_at<=now()
                     order by next_run_at,id
                     limit 1
                     for update skip locked'
                );

                if(!$definition){
                    DB::commit();
                    break;
                }

                $occurrence=
                    (string)$definition['next_run_at'];

                $existing=DB::row(
                    'select id
                     from email_campaigns
                     where recurring_definition_id=?
                       and scheduled_for=?',
                    [
                        (int)$definition['id'],
                        $occurrence
                    ]
                );

                if(!$existing){
                    DB::exec(
                        'insert into email_campaigns
                         (
                            campaign_type,
                            newsletter_type,
                            recurring_definition_id,
                            audience,
                            subject,
                            preheader,
                            body,
                            body_format,
                            cta_label,
                            cta_url,
                            status,
                            scheduled_for,
                            schedule_timezone,
                            created_by
                         )
                         values
                         (
                            "promotional",
                            ?,?,?,?,?,?,?,?,?,
                            "scheduled",
                            ?,?,?
                         )',
                        [
                            $definition['frequency'],
                            (int)$definition['id'],
                            $definition['audience'],
                            $definition['subject'],
                            $definition['preheader'],
                            $definition['body'],
                            $definition['body_format'],
                            $definition['cta_label'],
                            $definition['cta_url'],
                            $occurrence,
                            $definition['timezone'],
                            (int)$definition['created_by']
                        ]
                    );

                    $created++;
                }

                $after=
                    new \DateTimeImmutable(
                        $occurrence,
                        new \DateTimeZone('UTC')
                    );

                $now=
                    new \DateTimeImmutable(
                        'now',
                        new \DateTimeZone('UTC')
                    );

                $next=
                    EmailNewsletterRecurringService::
                        nextRun(
                            $definition,
                            $after>$now ? $after : $now
                        )
                        ->format('Y-m-d H:i:s');

                DB::exec(
                    'update email_newsletter_recurring_definitions
                     set last_run_at=?,
                         next_run_at=?
                     where id=?',
                    [
                        $occurrence,
                        $next,
                        (int)$definition['id']
                    ]
                );

                DB::commit();
            } catch(\Throwable $e) {
                if(DB::pdo()->inTransaction()){
                    DB::rollBack();
                }

                throw $e;
            }
        }

        return $created;
    }

    public static function queueDueRecurring(
        int $limit=20
    ): int {
        $rows=DB::rows(
            'select id
             from email_campaigns
             where status="scheduled"
               and recurring_definition_id is not null
               and scheduled_for<=now()
             order by scheduled_for,id
             limit '.(int)$limit
        );

        $queued=0;

        foreach($rows as $row){
            $queued+=
                EmailDigestService::queueCampaign(
                    (int)$row['id']
                );
        }

        return $queued;
    }

    public static function queueDueOneOff(
        int $limit=20
    ): int {
        $rows=DB::rows(
            'select id
             from email_campaigns
             where status="scheduled"
               and recurring_definition_id is null
               and scheduled_for<=now()
             order by scheduled_for,id
             limit '.(int)$limit
        );

        $queued=0;

        foreach($rows as $row){
            $queued+=
                EmailCampaignService::queue(
                    (int)$row['id']
                );
        }

        return $queued;
    }
}
