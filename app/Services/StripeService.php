<?php

namespace App\Services;

use App\Core\Database as DB;
use Throwable;

class StripeService
{
    public static function secretKey(): string { return trim((string)($_ENV['STRIPE_SECRET_KEY'] ?? '')); }
    public static function webhookSecret(): string { return trim((string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '')); }
    public static function currency(): string { return strtolower(trim((string)($_ENV['STRIPE_CURRENCY'] ?? 'usd')) ?: 'usd'); }
    public static function commissionRate(): float { return max(0, min(100, (float)($_ENV['PLATFORM_COMMISSION_PERCENT'] ?? 20))) / 100; }
    public static function appUrl(): string
    {
        $url = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        if ($url !== '') return $url;
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    public static function configured(): bool { return self::secretKey() !== ''; }
    public static function cents($amount): int { return (int)round(((float)$amount) * 100); }

    public static function createCheckoutSession(array $order, array $items): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY before creating live checkout sessions.');
        $currency = self::currency();
        $lineItems = [];
        foreach ($items as $item) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => self::cents($item['total_price']),
                    'product_data' => [
                        'name' => mb_substr((string)($item['product_title'] ?: 'Asset Moth product'), 0, 250),
                        'description' => mb_substr((string)($item['license_name'] ?: $item['license_type'] ?: 'License'), 0, 250),
                    ],
                ],
            ];
        }
        $base = self::appUrl();
        return self::request('POST', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => (string)$order['id'],
            'success_url' => $base . '/checkout/success?order_id=' . (int)$order['id'] . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base . '/checkout/cancel?order_id=' . (int)$order['id'],
            'metadata' => ['order_id' => (string)$order['id'], 'buyer_user_id' => (string)$order['user_id']],
            'payment_intent_data' => ['metadata' => ['order_id' => (string)$order['id'], 'buyer_user_id' => (string)$order['user_id']]],
            'line_items' => $lineItems,
        ]);
    }

    public static function createTransfer(string $accountId, int $amountCents, string $currency, array $metadata, ?string $idempotencyKey = null): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('POST', '/v1/transfers', ['amount' => $amountCents, 'currency' => $currency, 'destination' => $accountId, 'metadata' => $metadata], $idempotencyKey);
    }

    public static function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $ch = curl_init('https://api.stripe.com' . $path);
        $headers = [];
        if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => self::secretKey() . ':', CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30]);
        if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Stripe API request failed: ' . $error);
        $json = json_decode($body, true) ?: [];
        if ($code < 200 || $code >= 300) throw new \RuntimeException($json['error']['message'] ?? ('Stripe API returned HTTP ' . $code));
        return $json;
    }

    public static function verifyWebhook(string $payload, string $sigHeader): array
    {
        $secret = self::webhookSecret();
        if ($secret === '') throw new \RuntimeException('Stripe webhook secret is not configured.');
        $parts = [];
        foreach (explode(',', $sigHeader) as $pair) { [$k,$v] = array_pad(explode('=', trim($pair), 2), 2, ''); $parts[$k][] = $v; }
        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || !$signatures) throw new \RuntimeException('Invalid Stripe signature header.');
        if (abs(time() - (int)$timestamp) > 300) throw new \RuntimeException('Stripe webhook timestamp outside tolerance.');
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) if (hash_equals($expected, $sig)) return json_decode($payload, true) ?: [];
        throw new \RuntimeException('Invalid Stripe webhook signature.');
    }

    public static function logTransaction(int $orderId, ?string $eventId, string $type, string $status, $amount, string $currency, array $refs = [], string $message = '', bool $review = false): void
    {
        DB::exec('insert into payment_transactions (order_id,stripe_event_id,transaction_type,payment_status,amount,currency,stripe_checkout_session_id,stripe_payment_intent_id,stripe_charge_id,message,manual_review_required) values (?,?,?,?,?,?,?,?,?,?,?)', [$orderId,$eventId,$type,$status,$amount,$currency,$refs['session'] ?? null,$refs['intent'] ?? null,$refs['charge'] ?? null,$message,$review ? 1 : 0]);
    }
}
