<?php
namespace App\Services;

use App\Core\Helpers as H;

final class UnsubscribeService
{
    public static function issue(string $kind, int $id, string $nonce): string
    {
        if (!in_array($kind, ['w', 'u'], true) || $id < 1 || !preg_match('/^[a-f0-9]{64}$/', $nonce)) {
            throw new \InvalidArgumentException('Invalid unsubscribe identity.');
        }
        $payload = $kind . '.' . $id . '.' . $nonce;
        return self::encode($payload) . '.' . hash_hmac('sha256', $payload, self::secret());
    }

    public static function verify(string $token): ?array
    {
        if (strlen($token) > 300 || !preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $token, $parts)) return null;
        $payload = self::decode($parts[1]);
        if ($payload === null || !hash_equals(hash_hmac('sha256', $payload, self::secret()), $parts[2])) return null;
        $values = explode('.', $payload);
        if (count($values) !== 3 || !in_array($values[0], ['w','u'], true) || !ctype_digit($values[1]) || (int)$values[1] < 1 || !preg_match('/^[a-f0-9]{64}$/', $values[2])) return null;
        return ['kind'=>$values[0], 'id'=>(int)$values[1], 'nonce'=>$values[2]];
    }

    public static function url(string $kind, int $id, string $nonce): string
    { return H::baseUrl() . '/email/unsubscribe?token=' . rawurlencode(self::issue($kind, $id, $nonce)); }

    private static function secret(): string
    {
        $secret = (string)($_ENV['EMAIL_UNSUBSCRIBE_SECRET'] ?? '');
        if (strlen($secret) < 32 || str_contains($secret, 'replace_me')) throw new \RuntimeException('Email unsubscribe signing is not configured.');
        return $secret;
    }
    private static function encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function decode(string $value): ?string
    {
        $decoded=base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
