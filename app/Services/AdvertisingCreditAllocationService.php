<?php
namespace App\Services;
use App\Core\Database as DB;
use DomainException;

/** All mutations run under the Advertising Credit account row lock. */
final class AdvertisingCreditAllocationService
{
    private static function cents($value): int { return CreditService::parseCents((string)$value); }
    private static function money(int $value): string { return CreditService::formatCents($value); }

    public static function expiration(?string $date): ?string
    {
        if (!$date) return null;
        $tz=new \DateTimeZone('America/Detroit');
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,$tz);
        if (!$d || $d->format('Y-m-d')!==$date) throw new DomainException('Choose a valid expiration date.');
        $end=$d->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));
        if ($end->getTimestamp()<=time()) throw new DomainException('Expiration must be today or later.');
        return $end->format('Y-m-d H:i:s');
    }

    public static function synchronize(int $userId): void
    {
        if (!DB::pdo()->inTransaction()) throw new DomainException('Credit transaction required.');
        $account=DB::row('select * from advertising_credits where user_id=? for update',[$userId]);
        if (!$account) return;
        $sum=DB::row('select coalesce(sum(remaining_amount),0) total,coalesce(sum(reserved_amount),0) reserved from advertising_credit_lots where user_id=?',[$userId]);
        $delta=self::cents($account['total_balance'])-self::cents($sum['total']);
        $reserved=self::cents($account['reserved_balance'])-self::cents($sum['reserved']);
        if ($delta<0 || $reserved<0 || $reserved>$delta) throw new DomainException('Advertising Credit allocation mismatch.');
        if ($reserved) throw new DomainException('Unallocated historical reservations require reconciliation.');
        if ($delta) DB::exec('insert into advertising_credit_lots(user_id,remaining_amount,reserved_amount) values(?,?,0)',[$userId,self::money($delta)]);
        self::expire($userId);
    }

    public static function grant(int $userId,int $transaction,int $cents,?string $expires): void
    {
        DB::exec('insert into advertising_credit_lots(user_id,grant_transaction_id,remaining_amount,reserved_amount,expires_at) values(?,?,?,0,?)',[$userId,$transaction,self::money($cents),$expires]);
    }

    public static function allocate(int $userId,int $transaction,int $cents,bool $reserve): void
    {
        $lots=DB::rows('select * from advertising_credit_lots where user_id=? and remaining_amount>reserved_amount and (expires_at is null or expires_at>utc_timestamp()) order by expires_at is null,expires_at,id for update',[$userId]);
        foreach ($lots as $lot) {
            $take=min($cents,self::cents($lot['remaining_amount'])-self::cents($lot['reserved_amount']));
            if (!$take) continue;
            $column=$reserve?'reserved_amount':'remaining_amount';
            $operator=$reserve?'+':'-';
            DB::exec("update advertising_credit_lots set $column=$column $operator ? where id=?",[self::money($take),$lot['id']]);
            DB::exec('insert into advertising_credit_allocations(transaction_id,lot_id,amount) values(?,?,?)',[$transaction,$lot['id'],self::money($take)]);
            $cents-=$take;
            if (!$cents) return;
        }
        throw new DomainException('Advertising Credit allocation could not be completed.');
    }

    public static function settle(int $userId,int $reservation,int $transaction,bool $redeem): void
    {
        $rows=DB::rows('select a.*,l.user_id from advertising_credit_allocations a join advertising_credit_lots l on l.id=a.lot_id where a.transaction_id=? order by a.lot_id for update',[$reservation]);
        if (!$rows) throw new DomainException('Advertising Credit reservation allocations missing.');
        foreach ($rows as $row) {
            if ((int)$row['user_id']!==$userId) throw new DomainException('Credit owner mismatch.');
            $sql='update advertising_credit_lots set reserved_amount=reserved_amount-?';
            $args=[$row['amount']];
            if ($redeem) { $sql.=',remaining_amount=remaining_amount-?'; $args[]=$row['amount']; }
            $sql.=' where id=? and reserved_amount>=?';
            $args[]=$row['lot_id'];$args[]=$row['amount'];
            $stmt=DB::pdo()->prepare($sql);$stmt->execute($args);
            if ($stmt->rowCount()!==1) throw new DomainException('Credit reservation settlement failed.');
            DB::exec('insert into advertising_credit_allocations(transaction_id,lot_id,amount) values(?,?,?)',[$transaction,$row['lot_id'],$row['amount']]);
        }
        self::expire($userId);
    }

    public static function expire(int $userId): void
    {
        if (!DB::pdo()->inTransaction()) throw new DomainException('Credit transaction required.');
        DB::row('select user_id from advertising_credits where user_id=? for update',[$userId]);
        foreach (DB::rows('select * from advertising_credit_lots where user_id=? and expires_at<=utc_timestamp() and remaining_amount>reserved_amount order by id for update',[$userId]) as $lot) {
            $cents=self::cents($lot['remaining_amount'])-self::cents($lot['reserved_amount']);
            $amount=self::money($cents);
            DB::exec('update advertising_credit_lots set remaining_amount=reserved_amount where id=?',[$lot['id']]);
            $stmt=DB::pdo()->prepare('update advertising_credits set total_balance=total_balance-? where user_id=? and total_balance-?>=reserved_balance');
            $stmt->execute([$amount,$userId,$amount]);
            if ($stmt->rowCount()!==1) throw new DomainException('Credit expiration invariant failed.');
            DB::exec('insert into advertising_credit_transactions(user_id,amount,type,status,idempotency_key,related_transaction_id,description,finalized_at) values(?,?,"expiration","finalized",?,?,"Unused Advertising Credit expired",utc_timestamp())',[$userId,self::money(-$cents),'advertising-expire:'.$lot['id'].':'.bin2hex(random_bytes(12)),$lot['grant_transaction_id']]);
            DB::exec('insert into advertising_credit_allocations(transaction_id,lot_id,amount) values(?,?,?)',[(int)DB::id(),$lot['id'],$amount]);
        }
    }

    public static function processExpired(int $limit=100): int
    {
        $limit=max(1,min(500,$limit));$count=0;
        foreach (DB::rows("select distinct user_id from advertising_credit_lots where expires_at<=utc_timestamp() and remaining_amount>reserved_amount order by user_id limit $limit") as $row) {
            DB::begin();
            try { self::expire((int)$row['user_id']);DB::commit();++$count; }
            catch (\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();throw $e; }
        }
        return $count;
    }
}
