<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;

final class AdvertisingCreditService
{
    public function balances(int $userId): array
    {
        $own=!DB::pdo()->inTransaction();
        if ($own) DB::begin();
        try {
            AdvertisingCreditAllocationService::expire($userId);
            if ($own) DB::commit();
        } catch (\Throwable $e) { if($own && DB::pdo()->inTransaction())DB::rollBack();throw $e; }
        $row=DB::row(
            'select total_balance,reserved_balance from advertising_credits where user_id=?',
            [$userId]
        );

        $total=CreditService::parseCents((string)($row['total_balance']??'0.00'));
        $reserved=CreditService::parseCents((string)($row['reserved_balance']??'0.00'));

        if($total<0||$reserved<0||$reserved>$total){
            throw new DomainException('Advertising credit balance invariant failed.');
        }

        return [
            'total_cents'=>$total,
            'reserved_cents'=>$reserved,
            'available_cents'=>$total-$reserved,
            'total'=>CreditService::formatCents($total),
            'reserved'=>CreditService::formatCents($reserved),
            'available'=>CreditService::formatCents($total-$reserved),
        ];
    }

    private function requireApprovedSeller(int $userId): void
    {
        if(!DB::row(
            'select d.id from designers d join users u on u.id=d.user_id
             where d.user_id=? and d.status="approved" and u.status="active"',
            [$userId]
        )){
            throw new InvalidArgumentException(
                'Advertising Credit can only be issued to an active approved seller.'
            );
        }
    }

    private function lock(int $userId): array
    {
        if(!DB::pdo()->inTransaction()){
            throw new DomainException('A database transaction is required.');
        }

        DB::exec(
            'insert ignore into advertising_credits(user_id,total_balance,reserved_balance)
             values (?,0.00,0.00)',
            [$userId]
        );

        $row=DB::row('select * from advertising_credits where user_id=? for update',[$userId]);
        if (!$row) throw new DomainException('Advertising credit account unavailable.');
        AdvertisingCreditAllocationService::synchronize($userId);
        return $row;
    }

    public function adjust(
        int $userId,
        string|int $amount,
        string $key,
        array $meta=[]
    ): bool {
        $this->requireApprovedSeller($userId);

        $cents=CreditService::parseCents($amount);
        if($cents===0){
            throw new InvalidArgumentException('Advertising Credit amount cannot be zero.');
        }

        $expires=AdvertisingCreditAllocationService::expiration($meta['expires_on']??null);
        if ($cents<0 && $expires) throw new DomainException('Expiration applies only to positive grants.');
        $this->lock($userId);

        $existing=DB::row(
            'select * from advertising_credit_transactions where idempotency_key=?',
            [$key]
        );

        if($existing){
            if(
                (int)$existing['user_id']!==$userId ||
                $existing['type']!=='admin_adjustment' ||
                CreditService::parseCents((string)$existing['amount'])!==$cents
            ){
                throw new DomainException('Advertising Credit idempotency conflict.');
            }
            return false;
        }

        $amountFormatted=CreditService::formatCents($cents);

        $stmt=DB::pdo()->prepare(
            'update advertising_credits
             set total_balance=total_balance+?
             where user_id=?
               and total_balance+?>=reserved_balance
               and total_balance+?>=0'
        );
        $stmt->execute([
            $amountFormatted,
            $userId,
            $amountFormatted,
            $amountFormatted
        ]);

        if($stmt->rowCount()!==1){
            throw new DomainException(
                'Adjustment would create a negative Advertising Credit available balance.'
            );
        }

        DB::exec(
            'insert into advertising_credit_transactions
             (user_id,amount,type,status,idempotency_key,admin_user_id,description,finalized_at)
             values (?,?,"admin_adjustment","finalized",?,?,?,now())',
            [
                $userId,
                $amountFormatted,
                $key,
                $meta['admin_user_id']??null,
                $meta['description']??null
            ]
        );

        $transaction=(int)DB::id();
        if ($cents>0) AdvertisingCreditAllocationService::grant($userId,$transaction,$cents,$expires);
        else AdvertisingCreditAllocationService::allocate($userId,$transaction,-$cents,false);
        return true;
    }

    public function reserve(
        int $userId,
        int $adId,
        int $requestedCents,
        string $key
    ): int {
        $this->requireApprovedSeller($userId);

        if($requestedCents<=0){
            return 0;
        }

        $this->lock($userId);

        $existing=DB::row(
            'select * from advertising_credit_transactions where idempotency_key=?',
            [$key]
        );

        if($existing){
            if(
                (int)$existing['user_id']!==$userId ||
                (int)($existing['ad_id']??0)!==$adId ||
                $existing['type']!=='reservation'
            ){
                throw new DomainException('Advertising Credit idempotency conflict.');
            }

            return abs(
                CreditService::parseCents((string)$existing['amount'])
            );
        }

        $balance=$this->balances($userId);
        $cents=min($requestedCents,$balance['available_cents']);

        if($cents<=0){
            return 0;
        }

        $amount=CreditService::formatCents($cents);

        $stmt=DB::pdo()->prepare(
            'update advertising_credits
             set reserved_balance=reserved_balance+?
             where user_id=? and reserved_balance+?<=total_balance'
        );
        $stmt->execute([$amount,$userId,$amount]);

        if($stmt->rowCount()!==1){
            throw new DomainException('Advertising Credit reservation failed.');
        }

        DB::exec(
            'insert into advertising_credit_transactions
             (user_id,amount,type,status,idempotency_key,ad_id,description)
             values (?,?,"reservation","reserved",?,?,"Advertising Credit reserved for promotion")',
            [$userId,CreditService::formatCents(-$cents),$key,$adId]
        );

        AdvertisingCreditAllocationService::allocate($userId,(int)DB::id(),$cents,true);
        return $cents;
    }

    public function reserveExact(
        int $userId,
        int $adId,
        int $requestedCents,
        string $key
    ): int {
        $this->requireApprovedSeller($userId);

        if($requestedCents<=0){
            return 0;
        }

        $this->lock($userId);

        $existing=DB::row(
            'select * from advertising_credit_transactions where idempotency_key=?',
            [$key]
        );

        if($existing){
            if(
                (int)$existing['user_id']!==$userId ||
                (int)($existing['ad_id']??0)!==$adId ||
                $existing['type']!=='reservation'
            ){
                throw new DomainException(
                    'Advertising Credit idempotency conflict.'
                );
            }

            return abs(
                CreditService::parseCents((string)$existing['amount'])
            );
        }

        $balance=$this->balances($userId);

        if($balance['available_cents']<$requestedCents){
            return 0;
        }

        $amount=CreditService::formatCents($requestedCents);

        $stmt=DB::pdo()->prepare(
            'update advertising_credits
             set reserved_balance=reserved_balance+?
             where user_id=?
               and reserved_balance+?<=total_balance'
        );

        $stmt->execute([$amount,$userId,$amount]);

        if($stmt->rowCount()!==1){
            throw new DomainException(
                'Advertising Credit exact reservation failed.'
            );
        }

        DB::exec(
            'insert into advertising_credit_transactions
             (user_id,amount,type,status,idempotency_key,ad_id,description)
             values (?,?,"reservation","reserved",?,?,
                     "Advertising Credit re-reserved for verified late promotion payment")',
            [
                $userId,
                CreditService::formatCents(-$requestedCents),
                $key,
                $adId
            ]
        );

        AdvertisingCreditAllocationService::allocate($userId,(int)DB::id(),$requestedCents,true);
        return $requestedCents;
    }

    public function finalizeReservation(
        int $userId,
        int $adId,
        string $key
    ): bool {
        $this->lock($userId);

        $reservation=DB::row(
            'select r.*
             from advertising_credit_transactions r
             where r.user_id=? and r.ad_id=? and r.type="reservation"
               and not exists(
                   select 1
                   from advertising_credit_transactions x
                   where x.related_transaction_id=r.id
                     and x.type in ("redemption","release")
               )
             order by r.id desc limit 1 for update',
            [$userId,$adId]
        );

        $existing=DB::row(
            'select * from advertising_credit_transactions where idempotency_key=?',
            [$key]
        );

        if(!$reservation){
            if($existing && $existing['type']==='redemption'){
                return false;
            }
            return false;
        }

        $cents=abs(
            CreditService::parseCents((string)$reservation['amount'])
        );

        if($existing){
            if(
                (int)$existing['user_id']!==$userId ||
                (int)($existing['ad_id']??0)!==$adId ||
                $existing['type']!=='redemption'
            ){
                throw new DomainException('Advertising Credit idempotency conflict.');
            }
            return false;
        }

        $amount=CreditService::formatCents($cents);

        $stmt=DB::pdo()->prepare(
            'update advertising_credits
             set total_balance=total_balance-?,
                 reserved_balance=reserved_balance-?
             where user_id=?
               and total_balance>=?
               and reserved_balance>=?'
        );
        $stmt->execute([$amount,$amount,$userId,$amount,$amount]);

        if($stmt->rowCount()!==1){
            throw new DomainException('Advertising Credit redemption failed.');
        }

        DB::exec(
            'insert into advertising_credit_transactions
             (user_id,amount,type,status,idempotency_key,ad_id,related_transaction_id,description,finalized_at)
             values (?,?,"redemption","finalized",?,?,?,"Advertising Credit redeemed for promotion",now())',
            [
                $userId,
                CreditService::formatCents(-$cents),
                $key,
                $adId,
                $reservation['id']
            ]
        );

        AdvertisingCreditAllocationService::settle($userId,(int)$reservation['id'],(int)DB::id(),true);
        return true;
    }

    public function releaseReservation(
        int $userId,
        int $adId,
        string $key
    ): bool {
        $this->lock($userId);

        $reservation=DB::row(
            'select r.*
             from advertising_credit_transactions r
             where r.user_id=? and r.ad_id=? and r.type="reservation"
               and not exists(
                   select 1
                   from advertising_credit_transactions x
                   where x.related_transaction_id=r.id
                     and x.type in ("redemption","release")
               )
             order by r.id desc limit 1 for update',
            [$userId,$adId]
        );

        if(!$reservation){
            return false;
        }

        if(DB::row(
            'select id from advertising_credit_transactions where idempotency_key=?',
            [$key]
        )){
            return false;
        }

        $cents=abs(
            CreditService::parseCents((string)$reservation['amount'])
        );
        $amount=CreditService::formatCents($cents);

        $stmt=DB::pdo()->prepare(
            'update advertising_credits
             set reserved_balance=reserved_balance-?
             where user_id=? and reserved_balance>=?'
        );
        $stmt->execute([$amount,$userId,$amount]);

        if($stmt->rowCount()!==1){
            throw new DomainException('Advertising Credit release failed.');
        }

        DB::exec(
            'insert into advertising_credit_transactions
             (user_id,amount,type,status,idempotency_key,ad_id,related_transaction_id,description,released_at)
             values (?,?,"release","released",?,?,?,"Promotion Advertising Credit released",now())',
            [$userId,$amount,$key,$adId,$reservation['id']]
        );

        AdvertisingCreditAllocationService::settle($userId,(int)$reservation['id'],(int)DB::id(),false);
        return true;
    }

    public function createRecurring(int $userId,string $amount,int $day,int $adminId,string $description,bool $grantNow,array $options=[]): int
    {
        return (new AdvertisingCreditScheduleService())->save(null,$userId,$amount,$day,$adminId,$description,$grantNow,$options);
    }

    public function deactivateRecurring(int $id): bool
    {
        return DB::exec(
            'update advertising_credit_recurring_grants
             set is_active=0
             where id=? and is_active=1',
            [$id]
        );
    }

    public function processDue(int $limit=100): int
    {
        $limit=max(1,min(500,$limit));

        $rows=DB::rows(
            "select id
             from advertising_credit_recurring_grants
             where is_active=1 and next_run_at<=utc_timestamp()
             order by next_run_at,id
             limit $limit"
        );

        AdvertisingCreditAllocationService::processExpired($limit);
        $processed=0;

        foreach($rows as $candidate){
            DB::begin();

            try {
                $grant=DB::row(
                    'select * from advertising_credit_recurring_grants
                     where id=? for update',
                    [(int)$candidate['id']]
                );

                if(
                    !$grant ||
                    !(int)$grant['is_active'] ||
                    $grant['next_run_at']>gmdate('Y-m-d H:i:s')
                ){
                    DB::commit();
                    continue;
                }

                if (($grant['ends_at'] && $grant['next_run_at']>$grant['ends_at']) ||
                    !DB::row('select d.id from designers d join users u on u.id=d.user_id where d.user_id=? and d.status="approved" and u.status="active"',[$grant['user_id']])) {
                    DB::exec('update advertising_credit_recurring_grants set is_active=0 where id=?',[$grant['id']]);
                    DB::commit();continue;
                }
                if ($grant['next_run_at']<$grant['starts_at']) throw new DomainException('Recurring schedule precedes its start.');
                $runKey='advertising-recurring:'.
                    (int)$grant['id'].':'.
                    $grant['next_run_at'];

                $existing=DB::row(
                    'select id from advertising_credit_transactions
                     where idempotency_key=?',
                    [$runKey]
                );

                if(!$existing){
                    $this->lock((int)$grant['user_id']);

                    DB::exec(
                        'update advertising_credits
                         set total_balance=total_balance+?
                         where user_id=?',
                        [$grant['amount'],$grant['user_id']]
                    );

                    DB::exec(
                        'insert into advertising_credit_transactions
                         (user_id,amount,type,status,idempotency_key,recurring_grant_id,admin_user_id,description,finalized_at)
                         values (?,?,"recurring_grant","finalized",?,?,?,?,now())',
                        [
                            $grant['user_id'],
                            $grant['amount'],
                            $runKey,
                            $grant['id'],
                            $grant['created_by_admin_id'],
                            $grant['description']
                        ]
                    );

                    $transaction=(int)DB::id();
                    $expires=null;
                    if ($grant['expiry_days']) $expires=(new \DateTimeImmutable($grant['next_run_at'],new \DateTimeZone('UTC')))
                        ->setTimezone(new \DateTimeZone($grant['schedule_timezone']))
                        ->modify('+'.(int)$grant['expiry_days'].' days')
                        ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    AdvertisingCreditAllocationService::grant((int)$grant['user_id'],$transaction,CreditService::parseCents((string)$grant['amount']),$expires);
                    AdvertisingCreditAllocationService::expire((int)$grant['user_id']);
                    $processed++;
                }

                $next=AdvertisingCreditScheduleService::next($grant['next_run_at'],(int)$grant['day_of_month'],$grant['schedule_timezone']);

                DB::exec(
                    'update advertising_credit_recurring_grants
                     set last_granted_at=?,
                         next_run_at=?,
                         is_active=case
                           when ends_at is not null and ?>ends_at then 0
                           else is_active
                         end
                     where id=?',
                    [
                        $grant['next_run_at'],
                        $next,
                        $next,
                        $grant['id']
                    ]
                );

                DB::commit();

            } catch(\Throwable $e){
                if(DB::pdo()->inTransaction()){
                    DB::rollBack();
                }
                throw $e;
            }
        }

        return $processed;
    }
}
