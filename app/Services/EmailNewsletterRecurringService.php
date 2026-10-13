<?php
namespace App\Services;

use App\Core\Database as DB;

final class EmailNewsletterRecurringService
{
    public const FREQUENCIES=[
        'weekly',
        'monthly'
    ];

    public static function get(string $frequency): ?array
    {
        if(!in_array($frequency,self::FREQUENCIES,true)){
            return null;
        }

        return DB::row(
            'select *
             from email_newsletter_recurring_definitions
             where frequency=?',
            [$frequency]
        ) ?: null;
    }

    public static function all(): array
    {
        return DB::rows(
            'select *
             from email_newsletter_recurring_definitions
             order by field(frequency,"weekly","monthly"),id'
        );
    }

    public static function validate(array $input): array
    {
        $errors=[];

        $frequency=
            trim((string)($input['frequency']??''));

        $name=
            trim((string)($input['name']??''));

        $audience=
            trim((string)($input['audience']??''));

        $subject=
            trim((string)($input['subject']??''));

        $preheader=
            trim((string)($input['preheader']??''));

        $body=
            trim((string)($input['body']??''));

        $bodyFormat=
            (string)($input['body_format']??'rich_html');

        $timezone=
            trim((string)($input['timezone']??''));

        $sendHour=
            filter_var(
                $input['send_hour']??null,
                FILTER_VALIDATE_INT
            );

        $sendMinute=
            filter_var(
                $input['send_minute']??null,
                FILTER_VALIDATE_INT
            );

        if(
            !in_array(
                $frequency,
                self::FREQUENCIES,
                true
            )
        ){
            $errors[]='Choose weekly or monthly.';
        }

        if(
            $name==='' ||
            mb_strlen($name)>190
        ){
            $errors[]=
                'Newsletter name is required and must be 190 characters or fewer.';
        }

        if(
            !in_array(
                $audience,
                ['users_weekly','users_monthly'],
                true
            )
        ){
            $errors[]='Choose a valid recurring newsletter audience.';
        }

        if(
            $frequency==='weekly' &&
            $audience!=='users_weekly'
        ){
            $errors[]=
                'Weekly newsletters must use the weekly email preference audience.';
        }

        if(
            $frequency==='monthly' &&
            $audience!=='users_monthly'
        ){
            $errors[]=
                'Monthly newsletters must use the monthly email preference audience.';
        }

        if(
            $subject==='' ||
            mb_strlen($subject)>190 ||
            preg_match('/[\r\n]/',$subject)
        ){
            $errors[]=
                'Subject is required, must be one line, and must be 190 characters or fewer.';
        }

        if(
            mb_strlen($preheader)>255 ||
            preg_match('/[\r\n]/',$preheader)
        ){
            $errors[]=
                'Preheader must be one line and 255 characters maximum.';
        }

        if(
            $body==='' ||
            mb_strlen($body)>10000
        ){
            $errors[]=
                'Newsletter content is required and must be 10,000 characters or fewer.';
        }

        if(
            !in_array(
                $bodyFormat,
                ['plain','rich_html'],
                true
            )
        ){
            $errors[]='Choose a valid newsletter body format.';
        }

        if(
            !in_array(
                $timezone,
                \DateTimeZone::listIdentifiers(),
                true
            )
        ){
            $errors[]='Choose a valid timezone.';
        }

        if(
            $sendHour===false ||
            $sendHour<0 ||
            $sendHour>23
        ){
            $errors[]='Send hour must be between 0 and 23.';
        }

        if(
            $sendMinute===false ||
            $sendMinute<0 ||
            $sendMinute>59
        ){
            $errors[]='Send minute must be between 0 and 59.';
        }

        if($frequency==='weekly'){
            $weeklyDay=
                filter_var(
                    $input['weekly_day']??null,
                    FILTER_VALIDATE_INT
                );

            if(
                $weeklyDay===false ||
                $weeklyDay<1 ||
                $weeklyDay>7
            ){
                $errors[]=
                    'Weekly send day must be Monday through Sunday.';
            }
        }

        if($frequency==='monthly'){
            $monthlyDay=
                filter_var(
                    $input['monthly_day']??null,
                    FILTER_VALIDATE_INT
                );

            if(
                $monthlyDay===false ||
                $monthlyDay<1 ||
                $monthlyDay>28
            ){
                $errors[]=
                    'Monthly send day must be between 1 and 28.';
            }
        }

        return $errors;
    }

    public static function nextRun(
        array $definition,
        ?\DateTimeImmutable $after=null
    ): \DateTimeImmutable {
        $frequency=
            (string)$definition['frequency'];

        if(
            !in_array(
                $frequency,
                self::FREQUENCIES,
                true
            )
        ){
            throw new \InvalidArgumentException(
                'Invalid recurring newsletter frequency.'
            );
        }

        $timezone=
            new \DateTimeZone(
                (string)$definition['timezone']
            );

        $utc=
            new \DateTimeZone('UTC');

        $afterUtc=
            $after
            ? $after->setTimezone($utc)
            : new \DateTimeImmutable('now',$utc);

        $local=
            $afterUtc->setTimezone($timezone);

        $hour=(int)$definition['send_hour'];
        $minute=(int)$definition['send_minute'];

        if($frequency==='weekly'){
            $weekday=(int)$definition['weekly_day'];

            if($weekday<1 || $weekday>7){
                throw new \InvalidArgumentException(
                    'Invalid weekly newsletter day.'
                );
            }

            $candidate=
                $local->setTime($hour,$minute,0);

            $currentWeekday=(int)$candidate->format('N');
            $daysForward=
                ($weekday-$currentWeekday+7)%7;

            if(
                $daysForward===0 &&
                $candidate<=$local
            ){
                $daysForward=7;
            }

            if($daysForward>0){
                $candidate=
                    $candidate->modify(
                        '+'.$daysForward.' days'
                    );
            }

            return $candidate->setTimezone($utc);
        }

        $day=(int)$definition['monthly_day'];

        if($day<1 || $day>28){
            throw new \InvalidArgumentException(
                'Invalid monthly newsletter day.'
            );
        }

        $candidate=
            $local
            ->setDate(
                (int)$local->format('Y'),
                (int)$local->format('m'),
                $day
            )
            ->setTime($hour,$minute,0);

        if($candidate<=$local){
            $candidate=
                $candidate
                ->modify('first day of next month')
                ->setDate(
                    (int)$candidate
                        ->modify('first day of next month')
                        ->format('Y'),
                    (int)$candidate
                        ->modify('first day of next month')
                        ->format('m'),
                    $day
                )
                ->setTime($hour,$minute,0);
        }

        return $candidate->setTimezone($utc);
    }

    public static function save(
        array $input,
        int $adminId
    ): int {
        $errors=self::validate($input);

        if($errors){
            throw new \InvalidArgumentException(
                implode(' ',$errors)
            );
        }

        $frequency=(string)$input['frequency'];

        $bodyFormat=
            (string)($input['body_format']??'rich_html');

        $body=
            $bodyFormat==='rich_html'
            ? EmailCampaignService::sanitizeRichBody(
                (string)$input['body']
            )
            : trim((string)$input['body']);

        $definition=[
            'frequency'=>$frequency,
            'name'=>trim((string)$input['name']),
            'audience'=>trim((string)$input['audience']),
            'subject'=>trim((string)$input['subject']),
            'preheader'=>
                trim((string)($input['preheader']??'')) ?: null,
            'body'=>$body,
            'body_format'=>$bodyFormat,
            'cta_label'=>
                trim((string)($input['cta_label']??'')) ?: null,
            'cta_url'=>
                EmailCampaignService::safeCta(
                    $input['cta_url']??null
                ),
            'timezone'=>trim((string)$input['timezone']),
            'send_hour'=>(int)$input['send_hour'],
            'send_minute'=>(int)$input['send_minute'],
            'weekly_day'=>
                $frequency==='weekly'
                    ? (int)$input['weekly_day']
                    : null,
            'monthly_day'=>
                $frequency==='monthly'
                    ? (int)$input['monthly_day']
                    : null,
            'enabled'=>
                !empty($input['enabled']) ? 1 : 0,
            'created_by'=>$adminId
        ];

        $definition['next_run_at']=
            $definition['enabled']
            ? self::nextRun($definition)
                ->format('Y-m-d H:i:s')
            : null;

        $existing=self::get($frequency);

        if($existing){
            DB::exec(
                'update email_newsletter_recurring_definitions
                 set name=?,
                     audience=?,
                     subject=?,
                     preheader=?,
                     body=?,
                     body_format=?,
                     cta_label=?,
                     cta_url=?,
                     timezone=?,
                     send_hour=?,
                     send_minute=?,
                     weekly_day=?,
                     monthly_day=?,
                     enabled=?,
                     next_run_at=?
                 where id=?',
                [
                    $definition['name'],
                    $definition['audience'],
                    $definition['subject'],
                    $definition['preheader'],
                    $definition['body'],
                    $definition['body_format'],
                    $definition['cta_label'],
                    $definition['cta_url'],
                    $definition['timezone'],
                    $definition['send_hour'],
                    $definition['send_minute'],
                    $definition['weekly_day'],
                    $definition['monthly_day'],
                    $definition['enabled'],
                    $definition['next_run_at'],
                    (int)$existing['id']
                ]
            );

            return (int)$existing['id'];
        }

        DB::exec(
            'insert into email_newsletter_recurring_definitions (
                frequency,
                name,
                audience,
                subject,
                preheader,
                body,
                body_format,
                cta_label,
                cta_url,
                timezone,
                send_hour,
                send_minute,
                weekly_day,
                monthly_day,
                enabled,
                next_run_at,
                created_by
             ) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $definition['frequency'],
                $definition['name'],
                $definition['audience'],
                $definition['subject'],
                $definition['preheader'],
                $definition['body'],
                $definition['body_format'],
                $definition['cta_label'],
                $definition['cta_url'],
                $definition['timezone'],
                $definition['send_hour'],
                $definition['send_minute'],
                $definition['weekly_day'],
                $definition['monthly_day'],
                $definition['enabled'],
                $definition['next_run_at'],
                $definition['created_by']
            ]
        );

        return (int)DB::id();
    }
}
