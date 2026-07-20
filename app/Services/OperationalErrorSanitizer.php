<?php

namespace App\Services;

final class OperationalErrorSanitizer
{
    public static function context(string $context): string
    {
        $context = strtolower((string) preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $context));
        $context = trim($context, '_.-');

        return mb_substr($context !== '' ? $context : 'operational_error', 0, 64);
    }

    public static function sanitize(string $message, int $maximumLength = 240): string
    {
        $maximumLength = max(32, min(1000, $maximumLength));
        $message = preg_split('/(?:\r?\n)?(?:Stack trace:|#\d+\s+[^\r\n]*)/i', $message, 2)[0] ?? '';
        $message = strip_tags($message);

        $patterns = [
            '/\b(?:Bearer|Basic)\s+[A-Za-z0-9+\/_=.-]+/i' => '[redacted-authorization]',
            '/\bStripe[\s_-]*Signature\s*(?:=|:)\s*(?:"[^"]*"|\'[^\']*\'|[^\s;]+)/i' => '[redacted-stripe-signature]',
            '/\b(?:X[\s_-]*)?API[\s_-]*Key\s*(?:=|:)\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i' => '[redacted-api-key]',
            '~\bhttps?://[^/\s:@]+(?::[^@\s/]*)?@[^\s<>"\']+~i' => '[redacted-url-credentials]',
            '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i' => '[redacted-email]',
            '/\b(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9_\-]+\b|\bwhsec_[A-Za-z0-9_\-]+\b/i' => '[redacted-stripe-secret]',
            '/\b(?:pi|cus|evt|ch|cs|pm|src|acct|tr|re|in|sub)_[A-Za-z0-9_\-]+\b/i' => '[redacted-stripe-id]',
            '/\b(?:password|passwd|pwd|secret|token|api_key|apikey|authorization|credential)\s*(?:=|:)\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i' => '[redacted-assignment]',
            '/\b(?:mysql|mariadb|pgsql|postgres(?:ql)?|sqlsrv):[^\s]+/i' => '[redacted-database-dsn]',
            '/\b(?:DB_PASS|DATABASE_PASSWORD)\s*(?:=|:)\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i' => '[redacted-database-password]',
            '~\bhttps?://[^\s<>"\']*[?#][^\s<>"\']*~i' => '[redacted-url]',
            '/\b[A-Za-z0-9_-]{16,}\.[a-f0-9]{64}\b/i' => '[redacted-unsubscribe-token]',
        ];
        $message = (string) preg_replace(array_keys($patterns), array_values($patterns), $message);
        $message = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message);
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        if ($message === '' || preg_match('/^(?:\[redacted-[a-z-]+\][\s,;:]*)+$/i', $message)) {
            $message = 'Sensitive operational error details were redacted.';
        }

        return mb_substr($message, 0, $maximumLength);
    }
}
