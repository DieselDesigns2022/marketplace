<?php
namespace App\Services;

use App\Core\Database as DB;
use DomainException;

final class StripeFeeService
{
    /** No financial estimates: only a validated charge balance transaction. */
    public static function capture(array $charge): bool
    {
        $transaction = $charge['balance_transaction'] ?? null;
        if ($transaction === null || $transaction === '') return false;
        $chargeId = $charge['id'] ?? null;
        $intent = $charge['payment_intent'] ?? null;
        if (is_array($intent)) $intent = $intent['id'] ?? null;
        if (!is_string($chargeId) || !preg_match('/^ch_[A-Za-z0-9_]+$/D', $chargeId)
            || !is_string($intent) || !preg_match('/^pi_[A-Za-z0-9_]+$/D', $intent)) {
            throw new DomainException('Fee charge references are invalid.');
        }
        $order = DB::row(
            'select * from orders where stripe_charge_id=? and stripe_payment_intent_id=? limit 1',
            [$chargeId,$intent]
        );
        if (!$order) return false;
        if (($charge['paid'] ?? false) !== true
            || ($charge['captured'] ?? false) !== true
            || !is_int($charge['amount'] ?? null)
            || $charge['amount'] !== StripeService::cents($order['total'])
            || ($charge['currency'] ?? '') !== 'usd'
            || strtolower((string)$order['stripe_currency']) !== 'usd') {
            throw new DomainException('Fee charge does not match the USD order.');
        }
        if (DB::pdo()->inTransaction()) {
            throw new DomainException('Fee retrieval must run outside a financial transaction.');
        }
        $transactionId = is_array($transaction)
            ? ($transaction['id'] ?? null) : $transaction;
        if (!is_string($transactionId)
            || !preg_match('/^txn_[A-Za-z0-9_]+$/D', $transactionId)) {
            throw new DomainException('Fee transaction reference is invalid.');
        }
        if (!is_array($transaction)) {
            $transaction = StripeService::request(
                'GET','/v1/balance_transactions/'.rawurlencode($transactionId)
            );
        }
        $source = $transaction['source'] ?? null;
        if (is_array($source)) $source = $source['id'] ?? null;
        $cents = $transaction['fee'] ?? null;
        if (($transaction['id'] ?? null) !== $transactionId
            || ($transaction['object'] ?? '') !== 'balance_transaction'
            || !in_array($transaction['type'] ?? '', ['charge','payment'], true)
            || $source !== $chargeId
            || ($transaction['currency'] ?? '') !== 'usd'
            || !is_int($transaction['amount'] ?? null)
            || $transaction['amount'] !== $charge['amount']
            || !is_int($cents) || $cents < 0 || $cents > 9999999999
            || !is_int($transaction['net'] ?? null)
            || $transaction['net'] !== $transaction['amount']-$cents) {
            throw new DomainException('Stripe fee transaction failed validation.');
        }
        $amount = CreditService::formatCents($cents);
        DB::exec(
            'update orders set stripe_fee_total=?
             where id=? and stripe_charge_id=? and stripe_payment_intent_id=?
             and total=? and stripe_currency="usd"
             and (stripe_fee_total is null or stripe_fee_total=?)',
            [$amount,$order['id'],$chargeId,$intent,$order['total'],$amount]
        );
        $stored = DB::row('select stripe_fee_total from orders where id=?',[$order['id']]);
        if (!$stored || $stored['stripe_fee_total'] === null
            || StripeService::cents($stored['stripe_fee_total']) !== $cents) {
            throw new DomainException('Stored Stripe fee conflicts with retrieved fee.');
        }
        return true;
    }

    /** Explicit reconciliation entry point; never retries a payment or transfer. */
    public static function refresh(int $orderId): bool
    {
        if (DB::pdo()->inTransaction()) {
            throw new DomainException('Fee retrieval requires a closed transaction.');
        }
        $order = DB::row('select stripe_charge_id from orders where id=?',[$orderId]);
        $id = (string)($order['stripe_charge_id'] ?? '');
        if (!preg_match('/^ch_[A-Za-z0-9_]+$/D', $id)) return false;
        return self::capture(StripeService::request(
            'GET','/v1/charges/'.rawurlencode($id).'?expand%5B%5D=balance_transaction'
        ));
    }
}
