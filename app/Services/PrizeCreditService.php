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

    public static function allocateCart(
        array $items,
        array $couponAllocations,
        int $availableCents,
        bool $usePrizeCredit
    ): array {
        $empty = [
            'total_cents' => 0,
            'item_cents' => [],
            'seller_cents' => [],
        ];

        if (!$usePrizeCredit || $availableCents <= 0 || !$items) {
            return $empty;
        }

        $sellerItems = [];

        foreach ($items as $index => $item) {
            $designerId = (int)($item['designer_id'] ?? 0);

            if ($designerId <= 0) {
                continue;
            }

            $lineCents = CreditService::parseCents(
                (string)($item['line_total'] ?? '0.00'),
                false
            );

            $couponCents = CreditService::parseCents(
                (string)($couponAllocations[$index] ?? '0.00'),
                false
            );

            $eligible = max(0, $lineCents - $couponCents);

            if ($eligible <= 0) {
                continue;
            }

            if (!isset($sellerItems[$designerId])) {
                $sellerItems[$designerId] = [
                    'mode' => (string)($item['prize_credit_mode'] ?? 'none'),
                    'eligible_cents' => 0,
                    'items' => [],
                ];
            }

            $sellerItems[$designerId]['eligible_cents'] += $eligible;
            $sellerItems[$designerId]['items'][] = [
                'index' => $index,
                'eligible_cents' => $eligible,
            ];
        }

        $remainingBalance = $availableCents;
        $itemCents = [];
        $sellerCents = [];
        $total = 0;

        foreach ($sellerItems as $designerId => $seller) {
            if ($remainingBalance <= 0) {
                break;
            }

            $limit = self::sellerLimitCents(
                $seller['mode'],
                $seller['eligible_cents']
            );

            $sellerBudget = min(
                $limit,
                $remainingBalance
            );

            if ($sellerBudget <= 0) {
                continue;
            }

            $sellerUsed = 0;

            foreach ($seller['items'] as $item) {
                if ($sellerUsed >= $sellerBudget) {
                    break;
                }

                $take = min(
                    $item['eligible_cents'],
                    $sellerBudget - $sellerUsed
                );

                if ($take <= 0) {
                    continue;
                }

                $itemCents[$item['index']] = $take;
                $sellerUsed += $take;
            }

            if ($sellerUsed > 0) {
                $sellerCents[$designerId] = $sellerUsed;
                $total += $sellerUsed;
                $remainingBalance -= $sellerUsed;
            }
        }

        return [
            'total_cents' => $total,
            'item_cents' => $itemCents,
            'seller_cents' => $sellerCents,
        ];
    }

    public function reserveExact(
        int $userId,
        string $amount,
        int $orderId,
        string $idempotencyKey
    ): string {
        $cents = CreditService::parseCents($amount);

        if ($cents <= 0) {
            throw new InvalidArgumentException(
                'Prize Credit reservation must be positive.'
            );
        }

        $owns = !DB::pdo()->inTransaction();

        if ($owns) {
            DB::begin();
        }

        try {
            $existing = DB::row(
                'select *
                 from prize_credit_transactions
                 where idempotency_key=?
                 limit 1
                 for update',
                [$idempotencyKey]
            );

            if ($existing) {
                if (
                    (int)$existing['user_id'] !== $userId ||
                    (int)$existing['order_id'] !== $orderId ||
                    CreditService::parseCents(
                        (string)$existing['amount'],
                        false
                    ) !== $cents
                ) {
                    throw new DomainException(
                        'Prize Credit reservation idempotency conflict.'
                    );
                }

                if ($owns) {
                    DB::commit();
                }

                return CreditService::formatCents($cents);
            }

            $grants = DB::rows(
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

            $needed = $cents;
            $allocations = [];

            foreach ($grants as $grant) {
                if ($needed <= 0) {
                    break;
                }

                $remaining = CreditService::parseCents(
                    (string)$grant['remaining_amount']
                );

                $reserved = CreditService::parseCents(
                    (string)$grant['reserved_amount']
                );

                $available = max(
                    0,
                    $remaining - $reserved
                );

                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $needed);

                DB::exec(
                    'update prize_credit_grants
                     set reserved_amount=reserved_amount+?
                     where id=?
                       and reserved_amount+?<=remaining_amount',
                    [
                        CreditService::formatCents($take),
                        (int)$grant['id'],
                        CreditService::formatCents($take),
                    ]
                );

                $allocations[] = [
                    'grant_id' => (int)$grant['id'],
                    'cents' => $take,
                ];

                $needed -= $take;
            }

            if ($needed > 0) {
                throw new DomainException(
                    'Prize Credit balance changed before checkout could reserve it.'
                );
            }

            DB::exec(
                'insert into prize_credit_transactions
                 (
                    user_id,
                    order_id,
                    amount,
                    type,
                    status,
                    idempotency_key,
                    description
                 )
                 values (
                    ?,?,?,"reserve","reserved",?,
                    "Prize Credit reserved for checkout"
                 )',
                [
                    $userId,
                    $orderId,
                    CreditService::formatCents($cents),
                    $idempotencyKey,
                ]
            );

            $transactionId = (int)DB::id();

            foreach ($allocations as $allocation) {
                DB::exec(
                    'insert into prize_credit_transaction_allocations
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

            if ($owns) {
                DB::commit();
            }

            return CreditService::formatCents($cents);
        } catch (Throwable $error) {
            if ($owns && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }
    }

    public function finalizeReservation(
        int $userId,
        int $orderId,
        string $idempotencyKey
    ): bool {
        $owns = !DB::pdo()->inTransaction();

        if ($owns) {
            DB::begin();
        }

        try {
            $existing = DB::row(
                'select id
                 from prize_credit_transactions
                 where idempotency_key=?
                 limit 1',
                [$idempotencyKey]
            );

            if ($existing) {
                if ($owns) {
                    DB::commit();
                }

                return false;
            }

            $reserve = DB::row(
                'select *
                 from prize_credit_transactions
                 where user_id=?
                   and order_id=?
                   and type="reserve"
                   and status="reserved"
                 order by id desc
                 limit 1
                 for update',
                [$userId, $orderId]
            );

            if (!$reserve) {
                throw new DomainException(
                    'Prize Credit reservation was not found.'
                );
            }

            $allocations = DB::rows(
                'select *
                 from prize_credit_transaction_allocations
                 where transaction_id=?
                 order by id
                 for update',
                [(int)$reserve['id']]
            );

            $total = 0;

            foreach ($allocations as $allocation) {
                $grant = DB::row(
                    'select *
                     from prize_credit_grants
                     where id=?
                     for update',
                    [(int)$allocation['grant_id']]
                );

                if (!$grant) {
                    throw new DomainException(
                        'Prize Credit grant is unavailable.'
                    );
                }

                $amount = CreditService::parseCents(
                    (string)$allocation['amount']
                );

                $remaining = CreditService::parseCents(
                    (string)$grant['remaining_amount']
                );

                $reserved = CreditService::parseCents(
                    (string)$grant['reserved_amount']
                );

                if (
                    $amount <= 0 ||
                    $reserved < $amount ||
                    $remaining < $amount
                ) {
                    throw new DomainException(
                        'Prize Credit reservation is inconsistent.'
                    );
                }

                $newRemaining = $remaining - $amount;
                $newReserved = $reserved - $amount;

                DB::exec(
                    'update prize_credit_grants
                     set remaining_amount=?,
                         reserved_amount=?,
                         status=case
                            when ?=0 and ?=0
                            then "exhausted"
                            else status
                         end
                     where id=?',
                    [
                        CreditService::formatCents($newRemaining),
                        CreditService::formatCents($newReserved),
                        $newRemaining,
                        $newReserved,
                        (int)$grant['id'],
                    ]
                );

                $total += $amount;
            }

            DB::exec(
                'update prize_credit_transactions
                 set status="finalized",
                     finalized_at=now()
                 where id=?',
                [(int)$reserve['id']]
            );

            DB::exec(
                'insert into prize_credit_transactions
                 (
                    user_id,
                    order_id,
                    amount,
                    type,
                    status,
                    idempotency_key,
                    description,
                    finalized_at
                 )
                 values (
                    ?,?,?,"finalize","finalized",?,
                    "Prize Credit redeemed on paid order",
                    now()
                 )',
                [
                    $userId,
                    $orderId,
                    CreditService::formatCents(-$total),
                    $idempotencyKey,
                ]
            );

            $finalTransactionId = (int)DB::id();

            foreach ($allocations as $allocation) {
                DB::exec(
                    'insert into prize_credit_transaction_allocations
                     (
                        transaction_id,
                        grant_id,
                        amount
                     )
                     values (?,?,?)',
                    [
                        $finalTransactionId,
                        (int)$allocation['grant_id'],
                        $allocation['amount'],
                    ]
                );
            }

            if ($owns) {
                DB::commit();
            }

            return true;
        } catch (Throwable $error) {
            if ($owns && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }
    }

    public function releaseReservation(
        int $userId,
        int $orderId,
        string $idempotencyKey
    ): bool {
        $owns = !DB::pdo()->inTransaction();

        if ($owns) {
            DB::begin();
        }

        try {
            $existing = DB::row(
                'select id
                 from prize_credit_transactions
                 where idempotency_key=?
                 limit 1',
                [$idempotencyKey]
            );

            if ($existing) {
                if ($owns) {
                    DB::commit();
                }

                return false;
            }

            $reserve = DB::row(
                'select *
                 from prize_credit_transactions
                 where user_id=?
                   and order_id=?
                   and type="reserve"
                   and status="reserved"
                 order by id desc
                 limit 1
                 for update',
                [$userId, $orderId]
            );

            if (!$reserve) {
                if ($owns) {
                    DB::commit();
                }

                return false;
            }

            $allocations = DB::rows(
                'select *
                 from prize_credit_transaction_allocations
                 where transaction_id=?
                 order by id
                 for update',
                [(int)$reserve['id']]
            );

            $total = 0;

            foreach ($allocations as $allocation) {
                $amount = CreditService::parseCents(
                    (string)$allocation['amount']
                );

                $grant = DB::row(
                    'select reserved_amount
                     from prize_credit_grants
                     where id=?
                     for update',
                    [(int)$allocation['grant_id']]
                );

                if (!$grant) {
                    throw new DomainException(
                        'Prize Credit grant is unavailable.'
                    );
                }

                $reserved = CreditService::parseCents(
                    (string)$grant['reserved_amount']
                );

                if ($reserved < $amount) {
                    throw new DomainException(
                        'Prize Credit release exceeds reserved balance.'
                    );
                }

                DB::exec(
                    'update prize_credit_grants
                     set reserved_amount=reserved_amount-?
                     where id=?',
                    [
                        CreditService::formatCents($amount),
                        (int)$allocation['grant_id'],
                    ]
                );

                $total += $amount;
            }

            DB::exec(
                'update prize_credit_transactions
                 set status="released",
                     finalized_at=now()
                 where id=?',
                [(int)$reserve['id']]
            );

            DB::exec(
                'insert into prize_credit_transactions
                 (
                    user_id,
                    order_id,
                    amount,
                    type,
                    status,
                    idempotency_key,
                    description,
                    finalized_at
                 )
                 values (
                    ?,?,?,"release","released",?,
                    "Prize Credit checkout reservation released",
                    now()
                 )',
                [
                    $userId,
                    $orderId,
                    CreditService::formatCents($total),
                    $idempotencyKey,
                ]
            );

            $releaseTransactionId = (int)DB::id();

            foreach ($allocations as $allocation) {
                DB::exec(
                    'insert into prize_credit_transaction_allocations
                     (
                        transaction_id,
                        grant_id,
                        amount
                     )
                     values (?,?,?)',
                    [
                        $releaseTransactionId,
                        (int)$allocation['grant_id'],
                        $allocation['amount'],
                    ]
                );
            }

            if ($owns) {
                DB::commit();
            }

            return true;
        } catch (Throwable $error) {
            if ($owns && DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }
    }


}
