<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;

final class CreditService
{
    public const MAX_CENTS = 999999999999;

    public static function parseCents(string|int $amount, bool $allowNegative = true): int
    {
        $value = is_int($amount) ? (string)$amount : trim($amount);
        $pattern = $allowNegative ? '/^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$/' : '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/';
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Amount must be a plain decimal with no more than two decimal places.');
        }
        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > 10) {
            throw new InvalidArgumentException('Amount is outside the supported range.');
        }
        $cents = ((int)$whole * 100) + (int)$fraction;
        if ($cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('Amount is outside the supported range.');
        }
        return $negative ? -$cents : $cents;
    }

    public static function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        return $sign . intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function checkoutBreakdown(string $subtotal, string $discount, string $tax, string $availableCredit, bool $useCredit): array
    {
        $subtotalCents = self::parseCents($subtotal, false);
        $discountCents = self::parseCents($discount, false);
        $taxCents = self::parseCents($tax, false);
        $availableCents = self::parseCents($availableCredit, false);
        $beforeCredit = max(0, $subtotalCents - $discountCents + $taxCents);
        $credit = $useCredit ? min($availableCents, $beforeCredit) : 0;
        return ['subtotal_cents' => $subtotalCents, 'discount_cents' => $discountCents, 'tax_cents' => $taxCents, 'credit_cents' => $credit, 'final_cents' => $beforeCredit - $credit];
    }

    public function balances(int $userId): array
    {
        $row = DB::row('select total_balance,reserved_balance from marketplace_credits where user_id=?', [$userId]);
        $total = self::parseCents((string)($row['total_balance'] ?? '0.00'));
        $reserved = self::parseCents((string)($row['reserved_balance'] ?? '0.00'));
        if ($total < 0 || $reserved < 0 || $reserved > $total) {
            throw new DomainException('Credit balance invariant failed.');
        }
        return [
            'total_cents' => $total,
            'reserved_cents' => $reserved,
            'available_cents' => $total - $reserved,
            'total' => self::formatCents($total),
            'reserved' => self::formatCents($reserved),
            'available' => self::formatCents($total - $reserved),
        ];
    }

    public function ledger(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        return DB::rows("select * from credit_transactions where user_id=? order by id desc limit $limit offset $offset", [$userId]);
    }

    public function lock(int $userId): array
    {
        if (!DB::pdo()->inTransaction()) {
            throw new DomainException('A database transaction is required.');
        }
        DB::exec('insert ignore into marketplace_credits (user_id,total_balance,reserved_balance) values (?,0.00,0.00)', [$userId]);
        return DB::row('select * from marketplace_credits where user_id=? for update', [$userId])
            ?? throw new DomainException('Credit account unavailable.');
    }

    public function reserve(int $userId, string|int $requested, int $orderId, string $key): string
    {
        $requestedCents = self::parseCents($requested, false);
        if ($requestedCents === 0) {
            return '0.00';
        }
        $existing = DB::row('select * from credit_transactions where idempotency_key=?', [$key]);
        if ($existing) {
            $existingCents = abs(self::parseCents((string)$existing['amount']));
            if ((int)$existing['user_id'] !== $userId || (int)($existing['order_id'] ?? 0) !== $orderId || $existing['type'] !== 'reservation' || $existingCents > $requestedCents) {
                throw new DomainException('Idempotency key conflict.');
            }
            return self::formatCents($existingCents);
        }
        $row = $this->lock($userId);
        $available = self::parseCents((string)$row['total_balance']) - self::parseCents((string)$row['reserved_balance']);
        $cents = min($requestedCents, $available);
        if ($cents <= 0) {
            return '0.00';
        }
        $affected = $this->guardedUpdate(
            'update marketplace_credits set reserved_balance=reserved_balance+? where user_id=? and reserved_balance+?<=total_balance',
            [self::formatCents($cents), $userId, self::formatCents($cents)]
        );
        if ($affected !== 1) {
            throw new DomainException('Credit reservation failed.');
        }
        $this->insertEntry($userId, -$cents, 'reservation', 'reserved', $key, $orderId, null, null, null, 'Credit reserved for checkout');
        return self::formatCents($cents);
    }

    public function finalizeReservation(int $userId, int $orderId, string $key): bool
    {
        $this->lock($userId);
        $reservation = $this->openReservation($userId, $orderId);
        if (!$reservation) {
            $this->replayAnyAmount($key, $userId, $orderId, 'redemption');
            return false;
        }
        $cents = abs(self::parseCents((string)$reservation['amount']));
        if ($this->replay($key, $userId, $orderId, $cents, 'redemption')) {
            return false;
        }
        $affected = $this->guardedUpdate(
            'update marketplace_credits set total_balance=total_balance-?,reserved_balance=reserved_balance-? where user_id=? and total_balance>=? and reserved_balance>=?',
            [self::formatCents($cents), self::formatCents($cents), $userId, self::formatCents($cents), self::formatCents($cents)]
        );
        if ($affected !== 1) {
            throw new DomainException('Credit redemption failed.');
        }
        $this->insertEntry($userId, -$cents, 'redemption', 'finalized', $key, $orderId, null, null, (int)$reservation['id'], 'Credit redeemed');
        return true;
    }

    public function releaseReservation(int $userId, int $orderId, string $key): bool
    {
        $this->lock($userId);
        $reservation = $this->openReservation($userId, $orderId);
        if (!$reservation) {
            $this->replayAnyAmount($key, $userId, $orderId, 'release');
            return false;
        }
        $cents = abs(self::parseCents((string)$reservation['amount']));
        if ($this->replay($key, $userId, $orderId, $cents, 'release')) {
            return false;
        }
        $affected = $this->guardedUpdate(
            'update marketplace_credits set reserved_balance=reserved_balance-? where user_id=? and reserved_balance>=?',
            [self::formatCents($cents), $userId, self::formatCents($cents)]
        );
        if ($affected !== 1) {
            throw new DomainException('Credit release failed.');
        }
        $this->insertEntry($userId, $cents, 'release', 'released', $key, $orderId, null, null, (int)$reservation['id'], 'Checkout credit released');
        return true;
    }

    public function grant(int $userId, string|int $amount, string $key, array $meta = []): bool
    {
        $cents = self::parseCents($amount, false);
        if ($cents === 0) {
            throw new InvalidArgumentException('Credit amount cannot be zero.');
        }
        return $this->changeBalance($userId, $cents, 'grant', $key, $meta);
    }

    public function adjust(int $userId, string|int $amount, string $key, array $meta = []): bool
    {
        $cents = self::parseCents($amount);
        if ($cents === 0) {
            throw new InvalidArgumentException('Credit amount cannot be zero.');
        }
        return $this->changeBalance($userId, $cents, 'admin_adjustment', $key, $meta);
    }

    private function changeBalance(int $userId, int $cents, string $type, string $key, array $meta): bool
    {
        $this->lock($userId);
        if ($this->replay($key, $userId, (int)($meta['order_id'] ?? 0), abs($cents), $type, $cents)) {
            return false;
        }
        $affected = $this->guardedUpdate(
            'update marketplace_credits set total_balance=total_balance+? where user_id=? and total_balance+?>=reserved_balance and total_balance+?>=0',
            [self::formatCents($cents), $userId, self::formatCents($cents), self::formatCents($cents)]
        );
        if ($affected !== 1) {
            throw new DomainException('Adjustment would create a negative available balance.');
        }
        $this->insertEntry($userId, $cents, $type, 'finalized', $key, $meta['order_id'] ?? null, $meta['referral_id'] ?? null, $meta['admin_user_id'] ?? null, null, $meta['description'] ?? null);
        return true;
    }

    private function openReservation(int $userId, int $orderId): ?array
    {
        return DB::row(
            'select r.* from credit_transactions r where r.user_id=? and r.order_id=? and r.type="reservation" and not exists (select 1 from credit_transactions x where x.related_transaction_id=r.id and x.type in ("redemption","release")) order by r.id desc limit 1 for update',
            [$userId, $orderId]
        );
    }

    private function replay(string $key, int $userId, int $orderId, int $absoluteCents, string $type, ?int $signedCents = null): bool
    {
        $existing = DB::row('select * from credit_transactions where idempotency_key=?', [$key]);
        if (!$existing) {
            return false;
        }
        $expected = $signedCents ?? ($type === 'release' ? $absoluteCents : ($type === 'grant' || $type === 'admin_adjustment' ? $absoluteCents : -$absoluteCents));
        if ((int)$existing['user_id'] !== $userId || (int)($existing['order_id'] ?? 0) !== $orderId || $existing['type'] !== $type || self::parseCents((string)$existing['amount']) !== $expected) {
            throw new DomainException('Idempotency key conflict.');
        }
        return true;
    }

    private function replayAnyAmount(string $key, int $userId, int $orderId, string $type): bool
    {
        $existing = DB::row('select * from credit_transactions where idempotency_key=?', [$key]);
        if (!$existing) {
            return false;
        }
        if ((int)$existing['user_id'] !== $userId || (int)($existing['order_id'] ?? 0) !== $orderId || $existing['type'] !== $type) {
            throw new DomainException('Idempotency key conflict.');
        }
        return true;
    }

    private function guardedUpdate(string $sql, array $params): int
    {
        $statement = DB::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    private function insertEntry(int $userId, int $cents, string $type, string $status, string $key, ?int $orderId, ?int $referralId, ?int $adminId, ?int $relatedId, ?string $description): void
    {
        DB::exec(
            'insert into credit_transactions (user_id,amount,type,status,idempotency_key,order_id,referral_id,admin_user_id,related_transaction_id,description,finalized_at,released_at) values (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, self::formatCents($cents), $type, $status, $key, $orderId, $referralId, $adminId, $relatedId, $description, $status === 'finalized' ? date('Y-m-d H:i:s') : null, $status === 'released' ? date('Y-m-d H:i:s') : null]
        );
    }
}
