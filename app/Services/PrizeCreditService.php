<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PrizeCreditService
{
    public const SELLER_MODES = [
        'none'       => 'Do not accept Prize Credit',
        'full'       => 'Accept up to the full eligible order amount',
        'fixed_5'    => 'Accept up to $5 per order',
        'fixed_10'   => 'Accept up to $10 per order',
        'percent_25' => 'Accept up to 25% per order',
        'percent_50' => 'Accept up to 50% per order',
    ];

    public static function sellerLimitCents(
        string $mode,
        int $eligibleCents
    ): int {
        $eligibleCents=max(0,$eligibleCents);

        return match($mode){
            'full'       => $eligibleCents,
            'fixed_5'    => min($eligibleCents,500),
            'fixed_10'   => min($eligibleCents,1000),
            'percent_25' => min(
                $eligibleCents,
                intdiv($eligibleCents*25,100)
            ),
            'percent_50' => min(
                $eligibleCents,
                intdiv($eligibleCents*50,100)
            ),
            default      => 0,
        };
    }

    public function balances(int $userId): array
    {
        $row=DB::row(
            'select
                coalesce(sum(
                    case
                        when status="active"
                         and remaining_amount>0
                         and (expires_on is null or expires_on>=current_date())
                        then remaining_amount
                        else 0
                    end
                ),0.00) total_balance,
                coalesce(sum(
                    case
                        when status="active"
                         and remaining_amount>0
                         and (expires_on is null or expires_on>=current_date())
                        then reserved_amount
                        else 0
                    end
                ),0.00) reserved_balance
             from prize_credit_grants
             where user_id=?',
            [$userId]
        )??[];

        $total=CreditService::parseCents(
            (string)($row['total_balance']??'0.00')
        );

        $reserved=CreditService::parseCents(
            (string)($row['reserved_balance']??'0.00')
        );

        if($reserved<0 || $total<0 || $reserved>$total){
            throw new DomainException(
                'Prize Credit balance is inconsistent.'
            );
        }

        return [
            'total'=>CreditService::formatCents($total),
            'reserved'=>CreditService::formatCents($reserved),
            'available'=>CreditService::formatCents(
                $total-$reserved
            ),
        ];
    }

    private function normalizeExpiry(?string $value): ?string
    {
        $value=trim((string)$value);

        if($value===''){
            return null;
        }

        $date=\DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        if(
            !$date ||
            $date->format('Y-m-d')!==$value
        ){
            throw new InvalidArgumentException(
                'Prize Credit expiration date is invalid.'
            );
        }

        if($value<date('Y-m-d')){
            throw new InvalidArgumentException(
                'Prize Credit expiration date cannot be in the past.'
            );
        }

        return $value;
    }

    public function adjust(
        int $userId,
        string $amount,
        string $idempotencyKey,
        array $meta=[]
    ): array {
        $cents=CreditService::parseCents($amount);

        if($cents===0){
            throw new InvalidArgumentException(
                'Prize Credit adjustment cannot be zero.'
            );
        }

        $idempotencyKey=trim($idempotencyKey);

        if(
            $idempotencyKey==='' ||
            mb_strlen($idempotencyKey)>190
        ){
            throw new InvalidArgumentException(
                'Prize Credit adjustment key is invalid.'
            );
        }

        $owns=!DB::pdo()->inTransaction();

        if($owns){
            DB::begin();
        }

        try{
            $existing=DB::row(
                'select *
                 from prize_credit_transactions
                 where idempotency_key=?
                 limit 1
                 for update',
                [$idempotencyKey]
            );

            if($existing){
                if(
                    (int)$existing['user_id']!==$userId ||
                    CreditService::parseCents(
                        (string)$existing['amount']
                    )!==$cents
                ){
                    throw new DomainException(
                        'Prize Credit idempotency conflict.'
                    );
                }

                if($owns){
                    DB::commit();
                }

                return [
                    'transaction_id'=>(int)$existing['id'],
                    'replay'=>true,
                    'balance'=>$this->balances($userId),
                ];
            }

            $user=DB::row(
                'select id,status
                 from users
                 where id=?
                 limit 1
                 for update',
                [$userId]
            );

            if(
                !$user ||
                ($user['status']??'')!=='active'
            ){
                throw new InvalidArgumentException(
                    'Prize Credit requires an active user.'
                );
            }

            $adminId=!empty($meta['admin_user_id'])
                ? (int)$meta['admin_user_id']
                : null;

            $description=mb_substr(
                trim((string)($meta['description']??'')),
                0,
                500
            );

            $grantId=null;
            $allocations=[];

            if($cents>0){
                $expiresOn=$this->normalizeExpiry(
                    $meta['expires_on']??null
                );

                $formatted=CreditService::formatCents(
                    $cents
                );

                DB::exec(
                    'insert into prize_credit_grants
                     (
                        user_id,
                        original_amount,
                        remaining_amount,
                        reserved_amount,
                        expires_on,
                        source_type,
                        source_reference,
                        admin_user_id,
                        description,
                        status
                     )
                     values (?,?,?,0.00,?,?,?,?,?,"active")',
                    [
                        $userId,
                        $formatted,
                        $formatted,
                        $expiresOn,
                        mb_substr(
                            trim(
                                (string)(
                                    $meta['source_type']
                                    ??'admin'
                                )
                            ),
                            0,
                            80
                        ),
                        ($meta['source_reference']??null)
                            ? mb_substr(
                                trim(
                                    (string)$meta[
                                        'source_reference'
                                    ]
                                ),
                                0,
                                190
                            )
                            : null,
                        $adminId,
                        $description!==''?$description:null,
                    ]
                );

                $grantId=(int)DB::id();
                $allocations[]=[
                    'grant_id'=>$grantId,
                    'cents'=>$cents,
                ];
            } else {
                $needed=abs($cents);

                $grants=DB::rows(
                    'select *
                     from prize_credit_grants
                     where user_id=?
                       and status="active"
                       and remaining_amount>reserved_amount
                       and (
                            expires_on is null
                            or expires_on>=current_date()
                       )
                     order by
                        expires_on is null,
                        expires_on,
                        id
                     for update',
                    [$userId]
                );

                foreach($grants as $grant){
                    if($needed<=0){
                        break;
                    }

                    $remaining=CreditService::parseCents(
                        (string)$grant['remaining_amount']
                    );

                    $reserved=CreditService::parseCents(
                        (string)$grant['reserved_amount']
                    );

                    $available=max(
                        0,
                        $remaining-$reserved
                    );

                    if($available<=0){
                        continue;
                    }

                    $take=min($available,$needed);
                    $newRemaining=$remaining-$take;

                    DB::exec(
                        'update prize_credit_grants
                         set remaining_amount=?,
                             status=case
                                when ?=0
                                 and reserved_amount=0
                                then "exhausted"
                                else status
                             end
                         where id=?',
                        [
                            CreditService::formatCents(
                                $newRemaining
                            ),
                            $newRemaining,
                            (int)$grant['id'],
                        ]
                    );

                    $allocations[]=[
                        'grant_id'=>(int)$grant['id'],
                        'cents'=>$take,
                    ];

                    $needed-=$take;
                }

                if($needed>0){
                    throw new InvalidArgumentException(
                        'Prize Credit deduction exceeds the available balance.'
                    );
                }
            }

            DB::exec(
                'insert into prize_credit_transactions
                 (
                    user_id,
                    grant_id,
                    amount,
                    type,
                    status,
                    idempotency_key,
                    admin_user_id,
                    description,
                    finalized_at
                 )
                 values (
                    ?,?,?,"admin_adjustment",
                    "finalized",?,?,?,now()
                 )',
                [
                    $userId,
                    $grantId,
                    CreditService::formatCents($cents),
                    $idempotencyKey,
                    $adminId,
                    $description!==''?$description:null,
                ]
            );

            $transactionId=(int)DB::id();

            foreach($allocations as $allocation){
                DB::exec(
                    'insert into
                     prize_credit_transaction_allocations
                     (
                        transaction_id,
                        grant_id,
                        amount
                     )
                     values (?,?,?)',
                    [
                        $transactionId,
                        $allocation['grant_id'],
                        CreditService::formatCents(
                            $allocation['cents']
                        ),
                    ]
                );
            }

            if($owns){
                DB::commit();
            }

            return [
                'transaction_id'=>$transactionId,
                'replay'=>false,
                'balance'=>$this->balances($userId),
            ];
        }catch(Throwable $error){
            if(
                $owns &&
                DB::pdo()->inTransaction()
            ){
                DB::rollBack();
            }

            throw $error;
        }
    }
}
