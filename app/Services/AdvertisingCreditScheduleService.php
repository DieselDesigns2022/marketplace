<?php
namespace App\Services;
use App\Core\Database as DB;
use DomainException;

final class AdvertisingCreditScheduleService
{
    public static function localDate(?string $utc): string
    {
        return $utc ? (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Detroit'))->format('Y-m-d') : '';
    }
    public static function display(?string $utc): string
    {
        return $utc ? (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Detroit'))->format('M j, Y g:i A T') : '—';
    }
    public static function next(string $utc,int $day,string $zone): string
    {
        return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone($zone))->modify('first day of next month')
            ->setTime(0,0)->modify('+'.($day-1).' days')
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public function save(?int $id,int $userId,string $amount,int $day,int $actor,string $reason,bool $now,array $options=[]): int
    {
        $cents=CreditService::parseCents($amount,false);
        if ($cents<=0 || $day<1 || $day>28 || mb_strlen($reason)<3 || mb_strlen($reason)>500) throw new DomainException('Check the amount, day (1–28), and reason.');
        $tz=new \DateTimeZone('America/Detroit');$today=new \DateTimeImmutable('today',$tz);
        $start=trim((string)($options['starts_on']??''));
        if ($start==='') $start=$now?$today->format('Y-m-d'):$today->modify('first day of next month')->modify('+'.($day-1).' days')->format('Y-m-d');
        $first=\DateTimeImmutable::createFromFormat('!Y-m-d',$start,$tz);
        if (!$first || $first->format('Y-m-d')!==$start || $first<$today) throw new DomainException('Choose today or a future start date.');
        if (!$now && (int)$first->format('j')!==$day) throw new DomainException('First/next grant date must match the monthly day.');
        if ($now && ($id || $first!=$today)) throw new DomainException('Immediate credit is only allowed for a new schedule starting today.');
        $end=trim((string)($options['ends_on']??''));$endUtc=null;
        if ($end!=='') {
            $last=\DateTimeImmutable::createFromFormat('!Y-m-d',$end,$tz);
            if (!$last || $last->format('Y-m-d')!==$end || $last<$first) throw new DomainException('End date must be on or after the first/next grant.');
            $endUtc=$last->setTime(23,59,59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        $expiry=trim((string)($options['expiry_days']??''));
        if ($expiry!=='' && (!ctype_digit($expiry) || (int)$expiry<1 || (int)$expiry>3650)) throw new DomainException('Expiration must be blank or 1–3650 days.');
        $expiry=$expiry===''?null:(int)$expiry;
        $firstUtc=$first->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $next=$now?self::next($firstUtc,$day,'America/Detroit'):$firstUtc;
        DB::begin();
        try {
            if (!DB::row('select d.id from designers d join users u on u.id=d.user_id where d.user_id=? and d.status="approved" and u.status="active"',[$userId])) throw new DomainException('Select an active approved seller.');
            $before=null;
            if ($id) {
                $before=DB::row('select * from advertising_credit_recurring_grants where id=? for update',[$id]);
                if (!$before || (int)$before['user_id']!==$userId || !(int)$before['is_active']) throw new DomainException('Active schedule not found.');
                if ($before['next_run_at']<=gmdate('Y-m-d H:i:s')) throw new DomainException('This grant is due. Let the worker finish before editing.');
                DB::exec('update advertising_credit_recurring_grants set amount=?,day_of_month=?,starts_at=?,ends_at=?,next_run_at=?,expiry_days=?,schedule_timezone="America/Detroit",description=? where id=?',[
                    CreditService::formatCents($cents),$day,$before['last_granted_at']?$before['starts_at']:$firstUtc,$endUtc,$next,$expiry,$reason,$id
                ]);
            } else {
                DB::exec('insert into advertising_credit_recurring_grants(user_id,amount,frequency,day_of_month,starts_at,ends_at,next_run_at,is_active,created_by_admin_id,description,expiry_days,schedule_timezone) values(?,?,"monthly",?,?,?,?,1,?,?,?,"America/Detroit")',[$userId,CreditService::formatCents($cents),$day,$firstUtc,$endUtc,$next,$actor,$reason,$expiry]);
                $id=(int)DB::id();
                if ($now) {
                    $expires=$expiry?$today->modify('+'.($expiry-1).' days')->format('Y-m-d'):null;
                    (new AdvertisingCreditService())->adjust($userId,CreditService::formatCents($cents),'advertising-recurring:'.$id.':initial',['admin_user_id'=>$actor,'description'=>$reason,'expires_on'=>$expires]);
                    DB::exec('update advertising_credit_transactions set type="recurring_grant",recurring_grant_id=? where idempotency_key=?',[$id,'advertising-recurring:'.$id.':initial']);
                    DB::exec('update advertising_credit_recurring_grants set last_granted_at=utc_timestamp() where id=?',[$id]);
                }
            }
            DB::exec('insert into admin_logs(admin_user_id,action,entity_type,entity_id,metadata) values(?,?,?,?,?)',[$actor,$before?'advertising_credit_schedule_edited':'advertising_credit_schedule_created','advertising_credit_recurring_grant',$id,json_encode(['before'=>$before,'after'=>DB::row('select * from advertising_credit_recurring_grants where id=?',[$id])],JSON_THROW_ON_ERROR)]);
            DB::commit();return $id;
        } catch (\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();throw $e; }
    }
}
