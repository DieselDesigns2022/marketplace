<?php

namespace App\Services;

use App\Core\Database as DB;

class AccountSecurityService
{
    public const MIN_PASSWORD_LENGTH = 8;

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function validEmail(string $email): bool
    {
        return mb_strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validPassword(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_PASSWORD_LENGTH;
    }

    public static function userWithPassword(int $userId): ?array
    {
        return DB::row('select id,name,email,password_hash,role from users where id=? and status="active" limit 1', [$userId]);
    }
}
