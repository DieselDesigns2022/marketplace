<?php

namespace App\Services;

use App\Core\Database as DB;
use Throwable;

class StripeService
{
    public static function secretKey(): string { return trim((string)($_ENV['STRIPE_SECRET_KEY'] ?? '')); }
    public static function webhookSecret(): string { return trim((string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '')); }
    public static function connectWebhookSecret(): string { return trim((string)($_ENV['STRIPE_CONNECT_WEBHOOK_SECRET'] ?? '')); }
    public static function currency(): string { return strtolower(trim((string)($_ENV['STRIPE_CURRENCY'] ?? 'usd')) ?: 'usd'); }
    public static function commissionRate(): float { return max(0, min(100, (float)($_ENV['PLATFORM_COMMISSION_PERCENT'] ?? 18))) / 100; }
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


    public static function createConnectedAccount(array $designer, array $user): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('POST', '/v1/accounts', [
            'type' => 'express',
            'email' => $user['email'] ?? null,
            'capabilities' => ['transfers' => ['requested' => true]],
            'business_profile' => ['name' => $designer['display_name'] ?? 'Asset Moth seller'],
            'metadata' => ['designer_id' => (string)$designer['id'], 'user_id' => (string)$user['id'], 'platform' => 'asset_moth'],
        ], 'asset_moth_connect_designer_' . (int)$designer['id']);
    }

    public static function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('POST', '/v1/account_links', [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    public static function retrieveConnectedAccount(string $accountId): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        return self::request('GET', '/v1/accounts/' . rawurlencode($accountId));
    }

    public static function syncConnectedAccountStatus(int $designerId, array $account): void
    {
        $charges = !empty($account['charges_enabled']) ? 1 : 0;
        $payouts = !empty($account['payouts_enabled']) ? 1 : 0;
        $details = !empty($account['details_submitted']) ? 1 : 0;
        $status = 'onboarding_incomplete';
        if (empty($account['id'])) $status = 'not_connected';
        elseif ($details && $payouts) $status = 'payout_ready';
        elseif ($details) $status = 'details_submitted';
        DB::exec('update designers set stripe_connect_account_id=coalesce(?,stripe_connect_account_id),stripe_charges_enabled=?,stripe_payouts_enabled=?,stripe_details_submitted=?,stripe_account_status=?,stripe_onboarding_completed_at=case when ?=1 then coalesce(stripe_onboarding_completed_at,now()) else stripe_onboarding_completed_at end,updated_at=now() where id=?', [$account['id'] ?? null,$charges,$payouts,$details,$status,($details && $payouts)?1:0,$designerId]);
    }

    public static function createTransfer(string $accountId, int $amountCents, string $currency, array $metadata, ?string $idempotencyKey = null, ?string $sourceTransaction = null, ?string $transferGroup = null): array
    {
        if (!self::configured()) throw new \RuntimeException('Stripe is not configured.');
        $params = ['amount' => $amountCents, 'currency' => $currency, 'destination' => $accountId, 'metadata' => $metadata];
        if (trim((string)$sourceTransaction) !== '') $params['source_transaction'] = trim((string)$sourceTransaction);
        if (trim((string)$transferGroup) !== '') $params['transfer_group'] = trim((string)$transferGroup);
        return self::request('POST', '/v1/transfers', $params, $idempotencyKey);
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
        $secrets = array_values(array_unique(array_filter([self::webhookSecret(), self::connectWebhookSecret()])));
        if (!$secrets) throw new \RuntimeException('Stripe webhook secret is not configured.');
        $parts = [];
        foreach (explode(',', $sigHeader) as $pair) { [$k,$v] = array_pad(explode('=', trim($pair), 2), 2, ''); $parts[$k][] = $v; }
        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || !$signatures) throw new \RuntimeException('Invalid Stripe signature header.');
        if (abs(time() - (int)$timestamp) > 300) throw new \RuntimeException('Stripe webhook timestamp outside tolerance.');
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            foreach ($signatures as $sig) if (hash_equals($expected, $sig)) return json_decode($payload, true) ?: [];
        }
        throw new \RuntimeException('Invalid Stripe webhook signature.');
    }


    public static function attemptPendingTransfersForDesigner(int $designerId): array
    {
        $summary = ['attempted' => 0, 'transferred' => 0, 'failed' => 0, 'skipped' => 0];
        $designer = DB::row('select * from designers where id=?', [$designerId]);
        $ready = $designer && !empty($designer['stripe_connect_account_id']) && !empty($designer['stripe_details_submitted']) && !empty($designer['stripe_payouts_enabled']);
        if (!$ready) return $summary;

        DB::exec('update seller_payouts sp join orders o on o.id=sp.order_id set sp.payout_status="pending_transfer",sp.updated_at=now() where sp.designer_id=? and sp.payout_status="pending_stripe_onboarding" and o.payment_status="paid" and o.status not in ("failed","cancelled","refunded") and coalesce(o.manual_review_required,0)=0', [$designerId]);
        DB::exec('update order_items oi join orders o on o.id=oi.order_id set oi.seller_payout_status="pending_transfer" where oi.designer_id=? and oi.seller_payout_status="pending_stripe_onboarding" and o.payment_status="paid" and o.status not in ("failed","cancelled","refunded") and coalesce(o.manual_review_required,0)=0', [$designerId]);

        $rows = DB::rows('select sp.*,o.payment_status,o.status order_status,o.manual_review_required,o.stripe_charge_id from seller_payouts sp join orders o on o.id=sp.order_id where sp.designer_id=? and sp.payout_status in ("pending_transfer","transfer_failed") order by sp.id', [$designerId]);
        foreach ($rows as $row) {
            if (($row['payment_status'] ?? '') !== 'paid' || in_array(($row['order_status'] ?? ''), ['failed','cancelled','refunded'], true) || !empty($row['manual_review_required'])) {
                $summary['skipped']++;
                continue;
            }
            if ((float)$row['seller_payout_amount'] <= 0) {
                $summary['skipped']++;
                continue;
            }
            $chargeId = trim((string)($row['stripe_charge_id'] ?? ''));
            if ($chargeId === '') {
                DB::exec('update seller_payouts set payout_status="pending_transfer",updated_at=now() where id=? and payout_status<>"transferred"', [$row['id']]);
                DB::exec('update order_items set seller_payout_status="pending_transfer" where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [(int)$row['order_id'],$designerId]);
                $summary['skipped']++;
                continue;
            }
            $summary['attempted']++;
            $orderId = (int)$row['order_id'];
            $idempotencyKey = 'asset_moth_payout_order_' . $orderId . '_designer_' . $designerId;
            try {
                $transfer = self::createTransfer($designer['stripe_connect_account_id'], self::cents($row['seller_payout_amount']), strtolower((string)($row['currency'] ?: self::currency())), ['order_id'=>(string)$orderId,'designer_id'=>(string)$designerId,'seller_payout_id'=>(string)$row['id']], $idempotencyKey, $chargeId, 'order_' . $orderId);
                $transferId = $transfer['id'] ?? null;
                DB::exec('update seller_payouts set payout_status="transferred",stripe_transfer_id=coalesce(?,stripe_transfer_id),stripe_transfer_error=null,updated_at=now() where id=?', [$transferId,$row['id']]);
                DB::exec('update order_items set seller_payout_status="transferred",stripe_transfer_id=coalesce(?,stripe_transfer_id),stripe_transfer_error=null where order_id=? and designer_id=?', [$transferId,$orderId,$designerId]);
                $summary['transferred']++;
            } catch (Throwable $e) {
                $error = mb_substr($e->getMessage(),0,1000);
                DB::exec('update seller_payouts set payout_status="transfer_failed",stripe_transfer_error=?,updated_at=now() where id=? and payout_status<>"transferred"', [$error,$row['id']]);
                DB::exec('update order_items set seller_payout_status="transfer_failed",stripe_transfer_error=? where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [$error,$orderId,$designerId]);
                $summary['failed']++;
            }
        }
        return $summary;
    }

    public static function logTransaction(int $orderId, ?string $eventId, string $type, string $status, $amount, string $currency, array $refs = [], string $message = '', bool $review = false): void
    {
        DB::exec('insert into payment_transactions (order_id,stripe_event_id,transaction_type,payment_status,amount,currency,stripe_checkout_session_id,stripe_payment_intent_id,stripe_charge_id,message,manual_review_required) values (?,?,?,?,?,?,?,?,?,?,?)', [$orderId,$eventId,$type,$status,$amount,$currency,$refs['session'] ?? null,$refs['intent'] ?? null,$refs['charge'] ?? null,$message,$review ? 1 : 0]);
    }
}
