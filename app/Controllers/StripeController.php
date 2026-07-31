<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\StripeService;
use App\Services\EmailQueueService;
use App\Services\NotificationService;
use App\Services\OperationalErrorSanitizer;
use App\Services\OrderFinalizationService;
use App\Services\CreditService;
use Throwable;

class StripeController
{
    /** Allocate the cumulative Stripe refund across merchandise only, never tax. */
    public static function allocateSellerRefund(array $items,int $cumulativeRefundCents,int $taxCents): array
    {
        $normalized=[];$merchandise=0;
        foreach($items as $item){$cents=max(0,StripeService::cents($item['total_price']??0));$merchandise+=$cents;$normalized[]=['id'=>(int)($item['id']??0),'cents'=>$cents,'commission_rate'=>max(0.0,min(1.0,(float)($item['commission_rate']??0)))];}
        $total=$merchandise+max(0,$taxCents);$cumulative=max(0,min($cumulativeRefundCents,$total));
        $merchandiseRefund=$total>0?intdiv($cumulative*$merchandise+intdiv($total,2),$total):0;
        $allocated=0;$rows=[];
        foreach($normalized as $item){$numerator=$merchandiseRefund*$item['cents'];$gross=$merchandise>0?intdiv($numerator,$merchandise):0;$allocated+=$gross;$rows[]=['id'=>$item['id'],'gross_refund_cents'=>$gross,'remainder'=>$merchandise>0?$numerator%$merchandise:0,'commission_rate'=>$item['commission_rate']];}
        usort($rows,static fn($a,$b)=>$b['remainder']<=>$a['remainder']?:$a['id']<=>$b['id']);
        for($i=0,$left=$merchandiseRefund-$allocated;$i<$left&&$rows;$i++)$rows[$i%count($rows)]['gross_refund_cents']++;
        $result=[];foreach($rows as $row){$gross=$row['gross_refund_cents'];$result[$row['id']]=['gross_refund_cents'=>$gross,'seller_refund_cents'=>$gross-(int)round($gross*$row['commission_rate'])];}
        ksort($result);return $result;
    }

    private function reconcileRefundPayouts(int $orderId): void
    {
        $refund=DB::row('select max(amount) amount from payment_transactions where order_id=? and transaction_type in ("partial_refund","refund")',[$orderId]);$cumulative=StripeService::cents($refund['amount']??0);if($cumulative<=0)return;
        $order=DB::row('select tax_amount from orders where id=?',[$orderId]);$items=DB::rows('select id,designer_id,total_price,commission_rate,seller_payout_status from order_items where order_id=? order by id',[$orderId]);$allocation=self::allocateSellerRefund($items,$cumulative,StripeService::cents($order['tax_amount']??0));$bySeller=[];
        foreach($items as $item){$id=(int)$item['id'];$designer=(int)$item['designer_id'];$gross=StripeService::cents($item['total_price']);$originalSeller=$gross-(int)round($gross*(float)$item['commission_rate']);$refundShare=$allocation[$id]??['gross_refund_cents'=>0,'seller_refund_cents'=>0];$desiredSeller=max(0,$originalSeller-$refundShare['seller_refund_cents']);if(($item['seller_payout_status']??'')!=='transferred')DB::exec('update order_items set seller_payout_amount=? where id=? and seller_payout_status<>"transferred"',[$desiredSeller/100,$id]);$bySeller[$designer]['gross']=(($bySeller[$designer]['gross']??0)+max(0,$gross-$refundShare['gross_refund_cents']));$bySeller[$designer]['seller']=(($bySeller[$designer]['seller']??0)+$desiredSeller);}
        foreach($bySeller as $designer=>$amounts){$commission=max(0,$amounts['gross']-$amounts['seller']);DB::exec('update seller_payouts set gross_amount=?,platform_commission_amount=?,seller_payout_amount=?,updated_at=now() where order_id=? and designer_id=? and payout_status<>"transferred"',[$amounts['gross']/100,$commission/100,$amounts['seller']/100,$orderId,$designer]);}
    }

    public function success(): void
    {
        H::requireLogin();
        $order = $this->buyerOrder((int)($_GET['order_id'] ?? 0));
        H::view('buyer/payment_success', ['order' => $order]);
    }

    public function cancel(): void
    {
        H::requireLogin();
        $order = $this->buyerOrder((int)($_GET['order_id'] ?? 0));
        if (!in_array($order['payment_status'] ?? $order['status'], ['paid','refunded','partially_refunded'], true)) {
            DB::exec('update orders set payment_status="canceled",status="cancelled",canceled_at=coalesce(canceled_at,now()) where id=? and user_id=?', [$order['id'], H::user()['id']]);
            (new OrderFinalizationService)->release((int)$order['id'],'buyer-cancel:'.$order['id'].':credit-release');
            $order = $this->buyerOrder((int)$order['id']);
        }
        H::view('buyer/payment_cancel', ['order' => $order]);
    }

    public function retry($id): void
    {
        H::requireLogin();
        $order = $this->buyerOrder((int)$id);
        $paymentStatus = $order['payment_status'] ?? $order['status'];
        if ($paymentStatus === 'manual_review') {
            H::flash('warning', 'This payment needs admin review before another payment attempt can be made.');
            H::redirect('/dashboard/order/' . (int)$order['id']);
        }
        if (in_array($paymentStatus, ['paid','refunded','partially_refunded'], true)) {
            H::flash('warning', 'This order is already paid or refunded and cannot be paid again.');
            H::redirect('/dashboard/order/' . (int)$order['id']);
        }
        try {
            if (($order['credit_payment_status'] ?? '') === 'released' && CreditService::parseCents((string)($order['credit_reserved'] ?? '0.00')) > 0) {
                DB::begin();
                $gross = CreditService::parseCents((string)$order['subtotal']) - CreditService::parseCents((string)$order['coupon_discount']) + CreditService::parseCents((string)$order['tax_amount']);
                $requested = min(CreditService::parseCents((string)$order['credit_reserved']), $gross);
                $reserved = (new CreditService)->reserve((int)$order['user_id'], CreditService::formatCents($requested), (int)$order['id'], 'order:' . $order['id'] . ':credit:retry:' . ((int)($order['payment_retry_count'] ?? 0) + 1));
                $remaining = $gross - CreditService::parseCents($reserved);
                DB::exec('update orders set credits_applied=?,credit_payment_status="reserved",total=?,stripe_amount_total=? where id=?', [$reserved, CreditService::formatCents($remaining), $remaining, $order['id']]);
                DB::commit();
                $order = $this->buyerOrder((int)$order['id']);
            }
            $items = DB::rows('select * from order_items where order_id=?', [$order['id']]);
            $session = StripeService::createCheckoutSession($order, $items);
            DB::exec('update orders set payment_provider="stripe",payment_processor="stripe",payment_mode="checkout",payment_status="pending",status="pending",stripe_checkout_session_id=?,stripe_currency=?,stripe_amount_total=?,payment_retry_count=coalesce(payment_retry_count,0)+1,payment_error=null where id=?', [$session['id'] ?? null, StripeService::currency(), StripeService::cents($order['total']), $order['id']]);
            header('Location: ' . $session['url'], true, 303); exit;
        } catch (Throwable $e) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            (new OrderFinalizationService)->release((int)$order['id'], 'retry-failed:' . $order['id'] . ':' . ((int)($order['payment_retry_count'] ?? 0) + 1));
            $safeError=OperationalErrorSanitizer::sanitize($e->getMessage(),500);
            DB::exec('update orders set payment_error=? where id=?', [$safeError, $order['id']]);
            H::flash('error', 'Payment could not be started. Please try again or contact support.');
            H::redirect('/dashboard/order/' . (int)$order['id']);
        }
    }

    public function webhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        try { $event = StripeService::verifyWebhook($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''); }
        catch (Throwable $e) { http_response_code(400); echo 'invalid'; return; }
        $eventId = (string)($event['id'] ?? ''); $type = (string)($event['type'] ?? 'unknown');
        if ($eventId === '') { $this->notifyWebhookIssue(self::webhookIssueKey('', $type, $payload),$type,'missing_event_id');http_response_code(400); echo 'missing id'; return; }
        $category='event_recording_failed';
        try {
            $existing = DB::row('select * from stripe_events where stripe_event_id=?', [$eventId]);
            if ($existing && $existing['processing_status'] === 'processed') {
                $this->communicationAttempt('processed_webhook_communication_recovery', fn() => $this->recoverProcessedEventCommunications($event, $type));
                echo 'already processed'; return;
            }
            if (!$existing) DB::exec('insert into stripe_events (stripe_event_id,event_type,processing_status,payload_json) values (?,?,"processing",?)', [$eventId,$type,$payload]);
            $category='event_processing_failed';
            $this->processEvent($event, $eventId, $type);
            $category='status_recording_failed';
            DB::exec('update stripe_events set processing_status="processed",processed_at=now(),processing_error=null where stripe_event_id=?', [$eventId]);
            echo 'ok';
        } catch (Throwable $e) {
            $safe=self::webhookIssueMessage($e->getMessage());
            try{DB::exec('update stripe_events set processing_status="failed",processing_error=?,processed_at=now() where stripe_event_id=?', [$safe,$eventId]);}catch(Throwable $logError){$this->reportWebhookFailure('stripe_event_failure_log',$logError);}
            $this->notifyWebhookIssue(self::webhookIssueKey($eventId,$type,$payload),$type,$category);
            http_response_code($category==='event_recording_failed'?500:200); echo $category==='event_recording_failed'?'error':'logged';
        }
    }

    public static function webhookIssueKey(string $eventId,string $type,string $verifiedPayload=''):string{$safeId=preg_replace('/[^a-zA-Z0-9_.:-]/','',mb_substr($eventId,0,140));return $safeId!==''?'stripe:webhook-issue:'.$safeId:'stripe:webhook-issue:missing-id:'.substr(hash('sha256',self::webhookEventType($type)."\0".hash('sha256',$verifiedPayload)),0,40);}
    public static function webhookEventType(string $type):string{$safe=preg_replace('/[^a-zA-Z0-9_.:-]/','',mb_substr($type,0,80));return $safe!==''?$safe:'unknown';}
    public static function webhookFailureCategory(string $category):string{return in_array($category,['missing_event_id','event_recording_failed','event_processing_failed','status_recording_failed'],true)?$category:'event_processing_failed';}
    public static function webhookAlertCopy(string $type,string $category,string $untrustedText=''):string{return 'Verified Stripe event '.self::webhookEventType($type).' encountered '.self::webhookFailureCategory($category).'. Inspect protected admin payment logs and server logs.';}
    public static function webhookIssueMessage(string $message):string{return OperationalErrorSanitizer::sanitize($message,240);}
    private function notifyWebhookIssue(string $eventKey,string $type,string $category):void{try{NotificationService::admins('webhook_issue','Stripe webhook processing issue',self::webhookAlertCopy($type,$category),$eventKey,'/admin/payment-logs');}catch(Throwable $alertError){$this->reportWebhookFailure('stripe_webhook_issue_alert',$alertError);}}
    private function reportWebhookFailure(string $context,Throwable $error):void{try{NotificationService::reportFailure($context,$error);}catch(Throwable $reportError){error_log('Asset Moth webhook operational reporting failed for '.OperationalErrorSanitizer::context($context).'.');}}

    private function buyerOrder(int $id): array { return DB::row('select * from orders where id=? and user_id=?', [$id, H::user()['id']]) ?? H::abort(404); }

    private function processEvent(array $event, string $eventId, string $type): void
    {
        $object = $event['data']['object'] ?? [];
        if (str_starts_with($type, 'checkout.session.')) $this->processCheckoutSession($object, $eventId, $type);
        elseif ($type === 'payment_intent.payment_failed') $this->markFailedByIntent($object, $eventId, $object['last_payment_error']['message'] ?? 'Payment failed.');
        elseif ($type === 'payment_intent.succeeded') $this->markPaidByIntent($object, $eventId);
        elseif (in_array($type, ['charge.refunded','charge.updated'], true)) $this->processChargeRefund($object, $eventId, $type);
        elseif ($type === 'account.updated') $this->processAccountUpdated($object);
    }


    private function processAccountUpdated(array $account): void
    {
        $designerId = (int)($account['metadata']['designer_id'] ?? 0);
        if (!$designerId && !empty($account['id'])) {
            $row = DB::row('select id from designers where stripe_connect_account_id=? limit 1', [$account['id']]);
            $designerId = (int)($row['id'] ?? 0);
        }
        if (!$designerId) return;
        StripeService::syncConnectedAccountStatus($designerId, $account);
        $fresh = DB::row('select * from designers where id=?', [$designerId]);
        if ($fresh && !empty($fresh['stripe_connect_account_id']) && !empty($fresh['stripe_details_submitted']) && !empty($fresh['stripe_payouts_enabled'])) {
            try { NotificationService::admins('seller_stripe_ready','Seller Stripe setup is ready','A seller completed Stripe Connect setup and is payout-ready.', 'seller:'.$designerId.':stripe-payout-ready', '/admin/designers'); } catch(Throwable $e) { NotificationService::reportFailure('seller_stripe_ready',$e); }
            StripeService::attemptPendingTransfersForDesigner($designerId);
        }
    }

    private function orderFromObject(array $object): ?array
    {
        $orderId = (int)($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);
        if ($orderId) return DB::row('select * from orders where id=?', [$orderId]);
        if (!empty($object['id'])) return DB::row('select * from orders where stripe_checkout_session_id=? or stripe_payment_intent_id=? limit 1', [$object['id'], $object['id']]);
        return null;
    }

    public static function refundDecision(string $storedStatus,int $priorCumulativeCents,int $incomingCumulativeCents,int $totalCents):array
    {
        $prior=max(0,$priorCumulativeCents);$incoming=max(0,$incomingCumulativeCents);$total=max(1,$totalCents);
        $alreadyFull=$storedStatus==='refunded'||$prior>=$total;
        $meaningful=!$alreadyFull&&$incoming>$prior;
        $status=$alreadyFull?'refunded':($meaningful?($incoming>=$total?'refunded':'partially_refunded'):$storedStatus);
        $communicationRecovery=!$meaningful&&$incoming>$prior&&$storedStatus==='refunded'&&$incoming>=$total;
        return ['meaningful'=>$meaningful,'communication_recovery'=>$communicationRecovery,'status'=>$status,'cumulative_cents'=>$incoming,'prior_cents'=>$prior];
    }
    public static function refundTransitionKey(int $orderId,string $status,int $cumulativeCents):string
    { return 'order:'.$orderId.':refund:'.($status==='refunded'?'full':'partial').':'.max(0,$cumulativeCents); }
    public static function paidCommunicationEligible(array $order):bool
    { return ($order['payment_status']??'')==='paid'&&(int)($order['manual_review_required']??0)===0; }

    private function highestRefundCents(int $orderId):int
    {
        $row=DB::row('select coalesce(max(amount),0) amount from payment_transactions where order_id=? and transaction_type in ("partial_refund","refund")',[$orderId]);
        return (int)round(((float)($row['amount']??0))*100);
    }

    private function recoverProcessedEventCommunications(array $event,string $type):void
    {
        $object=$event['data']['object']??[];
        if(!is_array($object))return;
        if((str_starts_with($type,'checkout.session.')&&in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true))||$type==='payment_intent.succeeded'){
            $order=$this->orderFromObject($object);
            if($order&&self::paidCommunicationEligible($order))(new OrderFinalizationService)->communicate((int)$order['id']);
            return;
        }
        if(!in_array($type,['charge.refunded','charge.updated'],true)||(int)($object['amount_refunded']??0)<=0)return;
        $order=!empty($object['payment_intent'])?DB::row('select * from orders where stripe_payment_intent_id=? limit 1',[$object['payment_intent']]):null;
        if(!$order)return;
        $incoming=(int)$object['amount_refunded'];$highest=$this->highestRefundCents((int)$order['id']);$total=(int)($object['amount']??StripeService::cents($order['total']));
        if($incoming!==$highest)return;
        $status=$incoming>=$total||($order['payment_status']??'')==='refunded'?'refunded':'partially_refunded';
        if(($order['payment_status']??'')!==$status)return;
        $this->notifyRefundTransition($order,$status,$incoming);
    }

    private function processCheckoutSession(array $session, string $eventId, string $type): void
    {
        $order = $this->orderFromObject($session); if (!$order) throw new \RuntimeException('No matching order for session.');
        if ($type === 'checkout.session.completed') {
            if (($session['payment_status'] ?? '') === 'paid') {
                $this->markPaid($order, $eventId, $session, 'checkout_completed');
            } else {
                $this->markCheckoutPending($order, $eventId, $session);
            }
        }
        elseif ($type === 'checkout.session.async_payment_succeeded') $this->markPaid($order, $eventId, $session, 'checkout_async_payment_succeeded');
        elseif ($type === 'checkout.session.async_payment_failed') $this->markFailed($order, $eventId, $session, 'Async payment failed.');
        elseif ($type === 'checkout.session.expired') $this->markCanceled($order, $eventId, $session, 'expired');
    }


    private function markCheckoutPending(array $order, string $eventId, array $session): void
    {
        $amount = (int)($session['amount_total'] ?? 0);
        $currency = strtolower((string)($session['currency'] ?? StripeService::currency()));
        DB::exec('update orders set payment_status="pending",status=case when status="failed" then "pending" else status end,payment_provider="stripe",payment_processor="stripe",stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id),stripe_customer_id=coalesce(?,stripe_customer_id),stripe_payment_status=?,stripe_amount_total=coalesce(?,stripe_amount_total),stripe_currency=coalesce(?,stripe_currency) where id=? and payment_status<>"paid"', [$session['id'] ?? null, $session['payment_intent'] ?? null, $session['customer'] ?? null, $session['payment_status'] ?? 'pending', $amount ?: null, $currency ?: null, $order['id']]);
        StripeService::logTransaction((int)$order['id'], $eventId, 'checkout_completed_pending', 'pending', $amount / 100, $currency, ['session' => $session['id'] ?? null, 'intent' => $session['payment_intent'] ?? null], 'Checkout completed, but Stripe payment_status is not paid yet. Delivery remains locked.');
    }

    private function markPaidByIntent(array $intent, string $eventId): void
    {
        $order = $this->orderFromObject($intent);
        if (!$order) return;
        $taxEnabled = (($order['tax_provider'] ?? '') === 'stripe_tax');
        $hasTaxDetails = isset($intent['total_details']['amount_tax']) || isset($intent['automatic_tax']);
        if ($taxEnabled && !$hasTaxDetails) {
            if(self::paidCommunicationEligible($order)){
                $this->communicationAttempt('paid_intent_communication_recovery',fn()=>(new OrderFinalizationService)->communicate((int)$order['id']));
                return;
            }
            $amount = (int)($intent['amount_received'] ?? $intent['amount'] ?? 0);
            $currency = strtolower((string)($intent['currency'] ?? StripeService::currency()));
            DB::exec('update orders set stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id),stripe_payment_status=coalesce(?,stripe_payment_status),stripe_amount_total=coalesce(?,stripe_amount_total),stripe_currency=coalesce(?,stripe_currency) where id=? and payment_status<>"paid"', [$intent['id'] ?? null,$intent['status'] ?? 'succeeded',$amount ?: null,$currency ?: null,$order['id']]);
            StripeService::logTransaction((int)$order['id'], $eventId, 'payment_intent_succeeded_waiting_for_checkout_session', 'pending', $amount / 100, $currency, ['intent' => $intent['id'] ?? null, 'charge' => $intent['latest_charge'] ?? null], 'PaymentIntent succeeded, waiting for Checkout Session Stripe Tax and customer-location confirmation before unlocking delivery.');
            return;
        }
        $this->markPaid($order, $eventId, $intent, 'payment_intent_succeeded');
    }
    private function markFailedByIntent(array $intent, string $eventId, string $message): void { $order = $this->orderFromObject($intent); if ($order) $this->markFailed($order, $eventId, $intent, $message); }

    private function stripeTaxData(array $object): array
    {
        $taxCents = (int)($object['total_details']['amount_tax'] ?? 0);
        $automaticTax = is_array($object['automatic_tax'] ?? null) ? $object['automatic_tax'] : [];
        $status = (string)($automaticTax['status'] ?? ($taxCents > 0 ? 'complete' : 'not_collected'));
        $liability = $automaticTax['liability'] ?? null;
        $liabilityOwner = 'platform';
        if (is_array($liability) && !empty($liability['type'])) {
            $liabilityOwner = ((string)$liability['type']) === 'account' ? 'connected_account' : 'platform';
        }
        return [
            'amount' => round($taxCents / 100, 2),
            'cents' => $taxCents,
            'provider' => 'stripe_tax',
            'status' => $status,
            'has_status' => array_key_exists('status', $automaticTax),
            'liability_owner' => $liabilityOwner,
            'snapshot' => json_encode([
                'checkout_session_id' => ($object['object'] ?? '') === 'checkout.session' ? ($object['id'] ?? null) : null,
                'automatic_tax' => $automaticTax,
                'total_details' => $object['total_details'] ?? null,
                'currency' => $object['currency'] ?? null,
                'customer_details' => $object['customer_details'] ?? null,
            ]),
        ];
    }

    private function markPaid(array $order, string $eventId, array $object, string $source): void
    {
        $objectType = (string)($object['object'] ?? '');
        $isCheckoutSession = $objectType === 'checkout.session' || array_key_exists('payment_intent', $object);
        $isPaymentIntent = $objectType === 'payment_intent' || array_key_exists('amount_received', $object);
        $sessionId = $isCheckoutSession ? ($object['id'] ?? null) : null;
        $paymentIntentId = $isPaymentIntent ? ($object['id'] ?? null) : ($object['payment_intent'] ?? null);
        $chargeId = $object['latest_charge'] ?? $object['charge'] ?? null;
        if (!$chargeId && !empty($object['charges']['data'][0]['id'])) $chargeId = $object['charges']['data'][0]['id'];
        $amount = (int)($object['amount_total'] ?? $object['amount_received'] ?? 0);
        $currency = strtolower((string)($object['currency'] ?? StripeService::currency()));
        $finalizer = new OrderFinalizationService();
        $captured = false;
        try {
            DB::begin();
            $locked = DB::row('select * from orders where id=? for update', [$order['id']]);
            if (!$locked) throw new \RuntimeException('Order disappeared during payment finalization.');
            if (!empty($locked['finalization_key'])) {
                DB::commit();
                $finalizer->communicate((int)$locked['id']);
                return;
            }
            $expected = CreditService::parseCents((string)$locked['total']);
            $expectedCurrency = strtolower((string)($locked['stripe_currency'] ?: StripeService::currency()));
            $metadataOrderId = (int)($object['metadata']['order_id'] ?? $locked['id']);
            $review = $amount !== $expected || $currency !== $expectedCurrency || $metadataOrderId !== (int)$locked['id'];
            $reason = $review ? 'Stripe amount, currency, or metadata did not match the order snapshot.' : null;
            if (($locked['tax_status'] ?? '') !== 'calculated' || empty($locked['tax_calculation_id'])) {
                $review = true;
                $reason = 'The authoritative Stripe Tax Calculation is unavailable.';
            }
            $returnedAddress = is_array($object['customer_details']['address'] ?? null) ? $object['customer_details']['address'] : [];
            $authoritativeAddress = json_decode((string)($locked['billing_address_snapshot'] ?? ''), true) ?: [];
            if ($isCheckoutSession && !StripeService::billingAddressMatches($authoritativeAddress, $returnedAddress)) {
                $review = true;
                $reason = 'Stripe billing location differs from the authoritative tax address.';
            }
            $captured = true;
            DB::exec('update orders set status="pending",payment_status=?,payment_provider="stripe",payment_processor="stripe",stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id),stripe_customer_id=coalesce(?,stripe_customer_id),stripe_charge_id=coalesce(?,stripe_charge_id),stripe_payment_status=?,stripe_amount_total=?,stripe_paid_amount=?,stripe_currency=?,manual_review_required=?,manual_review_reason=? where id=?', [$review ? 'manual_review' : 'captured_pending_finalization',$sessionId,$paymentIntentId,$object['customer'] ?? null,$chargeId,$object['payment_status'] ?? $object['status'] ?? 'paid',$amount,CreditService::formatCents($amount),$currency,$review ? 1 : 0,$reason,$locked['id']]);
            StripeService::logTransaction((int)$locked['id'], $eventId, $source, $review ? 'manual_review' : 'captured_pending_finalization', CreditService::formatCents($amount), $currency, ['session'=>$sessionId ?? $locked['stripe_checkout_session_id'],'intent'=>$paymentIntentId,'charge'=>$chargeId], $reason ?? 'Stripe payment captured; atomic finalization started.', $review);
            if ($review) {
                DB::commit();
                return;
            }
            $finalizer->finalize((int)$locked['id'], 'stripe:' . $eventId, false);
            DB::commit();
            $finalizer->communicate((int)$locked['id']);
            $this->attemptPendingTransfers((int)$locked['id'], $currency);
        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            if ($captured) {
                DB::exec('update orders set status="pending",payment_status="manual_review",manual_review_required=1,manual_review_reason=?,payment_error=?,stripe_checkout_session_id=coalesce(?,stripe_checkout_session_id),stripe_payment_intent_id=coalesce(?,stripe_payment_intent_id),stripe_charge_id=coalesce(?,stripe_charge_id),stripe_amount_total=?,stripe_paid_amount=?,stripe_currency=?,tax_transaction_status=case when ? like "%Tax Transaction%" then "failed" else tax_transaction_status end where id=? and finalization_key is null', ['Stripe payment was captured but atomic finalization failed. Replay the webhook or recover from payment review.', OperationalErrorSanitizer::sanitize($error->getMessage(), 1000), $sessionId, $paymentIntentId, $chargeId, $amount, CreditService::formatCents($amount), $currency, $error->getMessage(), $order['id']]);
                StripeService::logTransaction((int)$order['id'], $eventId . ':recovery', 'captured_finalization_failed', 'manual_review', CreditService::formatCents($amount), $currency, ['session'=>$sessionId,'intent'=>$paymentIntentId,'charge'=>$chargeId], 'Captured payment requires idempotent finalization recovery.', true);
            }
            throw $error;
        }
    }

    private function markFailed(array $order, string $eventId, array $object, string $message): void
    { $safeMessage=OperationalErrorSanitizer::sanitize($message,1000);DB::exec('update orders set status="failed",payment_status="failed",failed_at=coalesce(failed_at,now()),payment_error=? where id=? and payment_status<>"paid"', [$safeMessage,$order['id']]); (new OrderFinalizationService)->release((int)$order['id'],'stripe:'.$eventId.':credit-release'); StripeService::logTransaction((int)$order['id'],$eventId,'payment_failed','failed',($object['amount_total'] ?? $object['amount'] ?? 0)/100,strtolower($object['currency'] ?? StripeService::currency()),['session'=>$object['id'] ?? null,'intent'=>$object['payment_intent'] ?? $object['id'] ?? null],$safeMessage); try{NotificationService::admins('payment_failed','Payment needs attention','A payment failed for order #'.(int)$order['id'].'.',"stripe:$eventId:failed",'/admin/order/'.(int)$order['id']);}catch(Throwable $e){NotificationService::reportFailure('payment_failed',$e);} }
    private function markCanceled(array $order, string $eventId, array $object, string $status): void
    { DB::exec('update orders set status="cancelled",payment_status=?,canceled_at=coalesce(canceled_at,now()) where id=? and payment_status<>"paid"', [$status,$order['id']]); (new OrderFinalizationService)->release((int)$order['id'],'stripe:'.$eventId.':credit-release'); StripeService::logTransaction((int)$order['id'],$eventId,'checkout_'.$status,$status,($object['amount_total'] ?? 0)/100,strtolower($object['currency'] ?? StripeService::currency()),['session'=>$object['id'] ?? null], 'Checkout session '.$status.'.'); }

    private function processChargeRefund(array $charge, string $eventId, string $type): void
    {
        $order = !empty($charge['payment_intent']) ? DB::row('select * from orders where stripe_payment_intent_id=? limit 1', [$charge['payment_intent']]) : null; if (!$order) return;
        if (!empty($charge['id'])) DB::exec('update orders set stripe_charge_id=coalesce(stripe_charge_id,?) where id=?', [$charge['id'], $order['id']]);
        $order = DB::row('select * from orders where id=?', [$order['id']]) ?: $order;
        $refunded = (int)($charge['amount_refunded'] ?? 0); $total = (int)($charge['amount'] ?? StripeService::cents($order['total']));
        if ($refunded <= 0) {
            if (($order['payment_status'] ?? '') === 'paid' && !empty($charge['id'])) $this->attemptPendingTransfers((int)$order['id'], strtolower($charge['currency'] ?? $order['stripe_currency'] ?? StripeService::currency()));
            return;
        }
        $prior=$this->highestRefundCents((int)$order['id']);$decision=self::refundDecision((string)($order['payment_status']??''),$prior,$refunded,$total);$status=$decision['status'];$partial=$status==='partially_refunded';
        if($decision['meaningful']){
            DB::exec('update orders set payment_status=?,status=?,stripe_charge_id=coalesce(?,stripe_charge_id),refunded_at=case when ?="refunded" then coalesce(refunded_at,now()) else refunded_at end,partially_refunded_at=case when ?="partially_refunded" then coalesce(partially_refunded_at,now()) else partially_refunded_at end where id=?', [$status,$partial?'paid':'refunded',$charge['id'] ?? null,$status,$status,$order['id']]);
            if(!$partial)DB::exec('update order_items set manual_delivery_status=case when fulfillment_type="google_drive" then "cancelled_refunded" else manual_delivery_status end where order_id=?',[$order['id']]);
        }
        StripeService::logTransaction((int)$order['id'],$eventId,$refunded<$total?'partial_refund':'refund',$refunded<$total?'partially_refunded':'refunded',$refunded/100,strtolower($charge['currency'] ?? StripeService::currency()),['charge'=>$charge['id'] ?? null,'intent'=>$charge['payment_intent'] ?? null],$decision['meaningful']?'Refund status received from Stripe.':'Refund observation recorded without a state transition.');
        $this->reconcileRefundPayouts((int)$order['id']);
        if($decision['meaningful']||$decision['communication_recovery'])$this->communicationAttempt('refund_status',fn()=>$this->notifyRefundTransition(array_merge($order,['payment_status'=>$status]),$status,$refunded));
    }

    private function notifyRefundTransition(array $order,string $status,int $cumulativeCents):void
    {
        $key=self::refundTransitionKey((int)$order['id'],$status,$cumulativeCents);$label=$status==='refunded'?'refunded':'partially refunded';$amount=number_format($cumulativeCents/100,2);
        $this->communicationAttempt('buyer_refund_notification',fn()=>NotificationService::create((int)$order['user_id'],'refund_status','buyer','Refund status updated','Your order #'.(int)$order['id'].' is '.$label.' (cumulative refund $'.$amount.').',$key.':notification','/dashboard/order/'.(int)$order['id']));
        $this->communicationAttempt('buyer_refund_email',fn()=>EmailQueueService::refund((int)$order['id'],$label,$cumulativeCents,$key));
    }

    private function communicationAttempt(string $context,callable $operation):void{try{$operation();}catch(Throwable $e){try{NotificationService::reportFailure($context,$e);}catch(Throwable $ignored){error_log('Asset Moth communication failure reporting failed for '.OperationalErrorSanitizer::context($context).'.');}}}

    private function attemptPendingTransfers(int $orderId, string $currency): void
    {
        $rows = DB::rows('select sp.*,d.stripe_connect_account_id,d.stripe_charges_enabled,d.stripe_payouts_enabled,d.stripe_details_submitted,o.stripe_charge_id,o.payment_status,o.status order_status,o.manual_review_required from seller_payouts sp join designers d on d.id=sp.designer_id join orders o on o.id=sp.order_id where sp.order_id=? and sp.payout_status in ("pending_transfer","pending_stripe_onboarding","transfer_failed")', [$orderId]);
        foreach ($rows as $row) {
            $ready = !empty($row['stripe_connect_account_id']) && (int)$row['stripe_details_submitted'] === 1 && (int)$row['stripe_payouts_enabled'] === 1 && (float)$row['seller_payout_amount'] > 0;
            if (!$ready) {
                $status = 'pending_stripe_onboarding';
                DB::exec('update seller_payouts set payout_status=?,updated_at=now() where id=? and payout_status<>"transferred"', [$status,$row['id']]);
                DB::exec('update order_items set seller_payout_status=? where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [$status,$orderId,$row['designer_id']]);
                continue;
            }
            if (($row['payment_status'] ?? '') !== 'paid' || in_array(($row['order_status'] ?? ''), ['failed','cancelled','refunded'], true) || !empty($row['manual_review_required'])) continue;
            $chargeId = trim((string)($row['stripe_charge_id'] ?? ''));
            if ($chargeId === '') {
                DB::exec('update seller_payouts set payout_status="pending_transfer",updated_at=now() where id=? and payout_status<>"transferred"', [$row['id']]);
                DB::exec('update order_items set seller_payout_status="pending_transfer" where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [$orderId,$row['designer_id']]);
                continue;
            }

            $idempotencyKey = 'asset_moth_payout_order_' . (int)$orderId . '_designer_' . (int)$row['designer_id'];
            try {
                $transfer = StripeService::createTransfer($row['stripe_connect_account_id'], StripeService::cents($row['seller_payout_amount']), $currency, ['order_id'=>(string)$orderId,'designer_id'=>(string)$row['designer_id'],'seller_payout_id'=>(string)$row['id']], $idempotencyKey, $chargeId, 'order_' . (int)$orderId);
                $transferId = $transfer['id'] ?? null;
                DB::exec('update seller_payouts set payout_status="transferred",stripe_transfer_id=coalesce(?,stripe_transfer_id),stripe_transfer_error=null,updated_at=now() where id=?', [$transferId,$row['id']]);
                DB::exec('update order_items set seller_payout_status="transferred",stripe_transfer_id=coalesce(?,stripe_transfer_id),stripe_transfer_error=null where order_id=? and designer_id=?', [$transferId,$orderId,$row['designer_id']]);
            } catch (Throwable $e) {
                $error = OperationalErrorSanitizer::sanitize($e->getMessage(),1000);
                DB::exec('update seller_payouts set payout_status="transfer_failed",stripe_transfer_error=?,updated_at=now() where id=? and payout_status<>"transferred"', [$error,$row['id']]);
                DB::exec('update order_items set seller_payout_status="transfer_failed",stripe_transfer_error=? where order_id=? and designer_id=? and seller_payout_status<>"transferred"', [$error,$orderId,$row['designer_id']]);
            }
        }
    }
}
