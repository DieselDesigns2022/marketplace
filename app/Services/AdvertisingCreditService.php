<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;

final class AdvertisingCreditService
{
    public function balances(int $userId): array
    {
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

        return DB::row(
            'select * from advertising_credits where user_id=? for update',
            [$userId]
        ) ?? throw new DomainException('Advertising credit account unavailable.');
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

        return true;
    }

    public function createRecurring(
        int $userId,
        string $amount,
        int $day,
        int $adminId,
        string $description,
        bool $grantNow
    ): int {
        $this->requireApprovedSeller($userId);

        $cents=CreditService::parseCents($amount,false);
        if($cents<=0){
            throw new InvalidArgumentException('Recurring amount must be greater than zero.');
        }

        if($day<1||$day>28){
            throw new InvalidArgumentException('Recurring day must be between 1 and 28.');
        }

        if(mb_strlen($description)<3||mb_strlen($description)>500){
            throw new InvalidArgumentException('Provide a reason between 3 and 500 characters.');
        }

        $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        $next=$now
            ->modify('first day of next month')
            ->setTime(0,0)
            ->modify('+'.($day-1).' days');

        DB::begin();
        try {
            DB::exec(
                'insert into advertising_credit_recurring_grants
                 (user_id,amount,frequency,day_of_month,starts_at,next_run_at,is_active,created_by_admin_id,description)
                 values (?,?,"monthly",?,now(),?,1,?,?)',
                [
                    $userId,
                    CreditService::formatCents($cents),
                    $day,
                    $next->format('Y-m-d H:i:s'),
                    $adminId,
                    $description
                ]
            );

            $id=(int)DB::id();

            if($grantNow){
                $this->lock($userId);

                $amountFormatted=CreditService::formatCents($cents);

                DB::exec(
                    'update advertising_credits
                     set total_balance=total_balance+?
                     where user_id=?',
                    [$amountFormatted,$userId]
                );

                DB::exec(
                    'insert into advertising_credit_transactions
                     (user_id,amount,type,status,idempotency_key,recurring_grant_id,admin_user_id,description,finalized_at)
                     values (?,?,"recurring_grant","finalized",?,?,?,?,now())',
                    [
                        $userId,
                        $amountFormatted,
                        'advertising-recurring:'.$id.':initial',
                        $id,
                        $adminId,
                        $description
                    ]
                );

                DB::exec(
                    'update advertising_credit_recurring_grants
                     set last_granted_at=now()
                     where id=?',
                    [$id]
                );
            }

            DB::commit();
            return $id;

        } catch(\Throwable $e){
            if(DB::pdo()->inTransaction()){
                DB::rollBack();
            }
            throw $e;
        }
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

                    $processed++;
                }

                $current=new \DateTimeImmutable(
                    $grant['next_run_at'],
                    new \DateTimeZone('UTC')
                );

                $next=$current
                    ->modify('first day of next month')
                    ->setTime(0,0)
                    ->modify('+'.((int)$grant['day_of_month']-1).' days');

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
                        $next->format('Y-m-d H:i:s'),
                        $next->format('Y-m-d H:i:s'),
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
