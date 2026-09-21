<?php
namespace App\Services;

final class SocialCredentialCipher
{
    private static function key(): string
    {
        $value = (string)($_ENV['SOCIAL_CREDENTIAL_ENCRYPTION_KEY'] ?? '');
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('Social credential encryption is not configured.');
        }
        return $decoded;
    }

    public static function encrypt(array $credentials): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox(json_encode($credentials, JSON_THROW_ON_ERROR), $nonce, self::key());
        return base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(string $payload): array
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('Stored social credentials are invalid.');
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        if ($plain === false) throw new \RuntimeException('Stored social credentials could not be decrypted.');
        return json_decode($plain, true, 16, JSON_THROW_ON_ERROR);
    }
}
