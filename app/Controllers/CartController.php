<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\StripeService;
use App\Services\CouponService;
use App\Services\SellerReceiptService;
use App\Services\CreditService;
use App\Services\PrizeCreditService;
use App\Services\OrderFinalizationService;
use App\Services\MarketplaceFeeService;
use Throwable;

use App\Services\CheckoutOrderService;
class CartController
{
    private function owned(int $productId): bool
    {
        if (!H::user()) return false;
        return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where o.user_id=? and oi.product_id=? and o.payment_status="paid" limit 1',[H::user()['id'],$productId]);

    }

    private function guestCart(): array
    {
        $_SESSION['guest_cart'] ??= [];
        return $_SESSION['guest_cart'];

    }

    private function nextGuestCartId(): int
    {
        $_SESSION['guest_cart_next_id'] = (int)($_SESSION['guest_cart_next_id'] ?? 0) + 1;
        return $_SESSION['guest_cart_next_id'];

    }

    private function mergeGuestCart(): void
    {
        if (!H::user() || empty($_SESSION['guest_cart'])) return;

        foreach ($_SESSION['guest_cart'] as $cartItem) {
            $productId = (int)($cartItem['product_id'] ?? 0);
            $licenseType = (string)($cartItem['license_type'] ?? 'personal');
            if ($productId > 0) {
                DB::exec('insert ignore into cart_items (user_id,product_id,license_type,quantity) values (?,?,?,1)',[H::user()['id'],$productId,$licenseType]);
            }
        }

        unset($_SESSION['guest_cart'], $_SESSION['guest_cart_next_id']);

    }

    private function items(): array
    {
        if (H::user()) {
            $this->mergeGuestCart();
            return DB::rows('select ci.id cart_item_id,ci.license_type,ci.price_snapshot,ci.license_price_snapshot,ci.total_snapshot,p.*,d.display_name,d.store_slug,d.prize_credit_mode,d.receipt_note,d.receipt_image_path,(select image_path from product_images pi where pi.product_id=p.id order by sort_order,id limit 1) thumbnail from cart_items ci join products p on p.id=ci.product_id join designers d on d.id=p.designer_id where ci.user_id=? and p.status="approved" order by ci.created_at desc',[H::user()['id']]);
        }

        $items = [];
        foreach ($this->guestCart() as $cartId => $cartItem) {
            $p = DB::row('select p.*,d.display_name,d.store_slug,d.receipt_note,d.receipt_image_path,(select image_path from product_images pi where pi.product_id=p.id order by sort_order,id limit 1) thumbnail from products p join designers d on d.id=p.designer_id where p.id=? and p.status="approved"',[(int)($cartItem['product_id'] ?? 0)]);
            if (!$p) continue;
            $p['cart_item_id'] = (int)$cartId;
            $p['license_type'] = (string)($cartItem['license_type'] ?? 'personal');
            $items[] = $p;
        }
        return $items;

    }

    private array $pricing = [];

    private function totals(array $items, bool $lock=false): array
    {
        $this->pricing=(new \App\Services\GrandOpeningPricingService())->cart(
            $items,(string)($_SESSION['coupon_code']??''),(int)(H::user()['id']??0),$lock
        );
        if (!empty($this->pricing['coupon_candidate']) && empty($this->pricing['coupon_candidate']['ok'])) {
            unset($_SESSION['coupon_code']);
        }
        foreach ($this->pricing['items'] as $p) {
            if (!empty($p['cart_item_id']) && H::user() && empty($p['license_invalid'])) {
                DB::exec('update cart_items set quantity=1,price_snapshot=?,license_price_snapshot=?,total_snapshot=?,fulfillment_type_snapshot=? where id=? and user_id=?',[
                    $p['price'],$p['license_price'],$p['line_total'],$p['fulfillment_type'],$p['cart_item_id'],H::user()['id']
                ]);
            }
        }
        return [$this->pricing['items'],$this->pricing['subtotal_cents']/100];
    }

    public function show()
    {
        [$items,$subtotal]=$this->totals($this->items());
        $couponResult = $this->currentCoupon($items);
        H::view('buyer/cart',['items'=>$items,'subtotal'=>$subtotal,'couponResult'=>$couponResult,'pricing'=>$this->pricing]);

    }

    public function add($id)
    {
        $id=(int)$id;
        $p=DB::row('select * from products where id=? and status="approved"',[$id]);
        if(!$p)
        {
           H::flash('error','This product is not available for purchase.');
            H::redirect($_SERVER['HTTP_REFERER']??'/browse');

        }
        if($this->owned($id))
        {
           H::flash('warning','You already own this product.');
            H::redirect('/product/'.$p['slug']);

        }
        $licenses = LicenseService::purchasableLicenses($id, $_POST['license_type'] ?? []);
        if (!$licenses) {
            H::flash('error','Please choose an available license for this product.');
            H::redirect('/product/'.$p['slug']);
        }

        $keyList = LicenseService::keyList($licenses);

        if (H::user()) {
            DB::exec('insert ignore into cart_items (user_id,product_id,license_type,quantity,price_snapshot,license_price_snapshot,total_snapshot,fulfillment_type_snapshot) values (?,?,?,?,?,?,?,?)',[H::user()['id'],$id,$keyList,1,$p['price'],LicenseService::priceTotal($licenses),(float)$p['price']+LicenseService::priceTotal($licenses),$p['fulfillment_type'] ?? 'downloadable']);
        } else {
            $cart = $this->guestCart();
            foreach ($cart as $cartItem) {
                if ((int)($cartItem['product_id'] ?? 0) === $id && (string)($cartItem['license_type'] ?? '') === $keyList) {
                    H::redirect('/cart');
                }
            }
            $cart[$this->nextGuestCartId()] = ['product_id' => $id, 'license_type' => $keyList];
            $_SESSION['guest_cart'] = $cart;
        }

        H::redirect('/cart');

    }

    public function remove($id)
    {
        if (H::user()) {
            DB::exec('delete from cart_items where id=? and user_id=?',[(int)$id,H::user()['id']]);
        } else {
            $cart = $this->guestCart();
            unset($cart[(int)$id]);
            $_SESSION['guest_cart'] = $cart;
        }
        H::redirect('/cart');

    }

    public function update()
    {
        foreach(($_POST['license_type']??[]) as $cartId=>$license)
        {
            if (H::user()) {
                $item=DB::row('select ci.*,p.slug from cart_items ci join products p on p.id=ci.product_id where ci.id=? and ci.user_id=?',[(int)$cartId,H::user()['id']]);
                if($item)
               {
                   $selected = LicenseService::purchasableLicenses((int)$item['product_id'], $license);
                   if($selected)
                   {
                       $keyList = LicenseService::keyList($selected);
                       $duplicate = DB::row('select id from cart_items where user_id=? and product_id=? and license_type=? and id<>? limit 1',[H::user()['id'],$item['product_id'],$keyList,(int)$cartId]);
                       if($duplicate) DB::exec('delete from cart_items where id=? and user_id=?',[(int)$cartId,H::user()['id']]);
                       else DB::exec('update cart_items set license_type=? where id=? and user_id=?',[$keyList,(int)$cartId,H::user()['id']]);
                   }
                   else H::flash('error','One or more selected licenses are no longer available. Please choose available licenses before checkout.');

               }
            } else {
                $cart = $this->guestCart();
                if (isset($cart[(int)$cartId])) {
                    $selected = LicenseService::purchasableLicenses((int)$cart[(int)$cartId]['product_id'], $license);
                    if ($selected) {
                        $cart[(int)$cartId]['license_type'] = LicenseService::keyList($selected);
                        $_SESSION['guest_cart'] = $cart;
                    } else {
                        H::flash('error','One or more selected licenses are no longer available. Please choose available licenses before checkout.');
                    }
                }
            }

        }
        H::redirect('/cart');

    }

    private function currentCoupon(array $items): ?array
    {
        return $this->pricing['coupon_result']??null;
    }

    public function applyCoupon(): void
    {
        H::requireLogin();
        $_SESSION['coupon_code']=CouponService::normalizeCode((string)($_POST['coupon_code']??''));
        $this->totals($this->items());
        $candidate=$this->pricing['coupon_candidate'];
        if (empty($candidate['ok'])) {
            H::flash('error',$candidate['error']??'Enter a coupon code.');
            unset($_SESSION['coupon_code']);
        } else {
            H::flash('success',$this->pricing['mode']==='coupon'
                ? 'Coupon applied. It gives you the highest discount.'
                : 'The Grand Opening Sale gives you the highest discount and remains applied.');
        }
        H::redirect('/cart');
    }

    public function removeCoupon(): void
    {
        unset($_SESSION['coupon_code']);
        H::flash('success', 'Coupon removed.');
        H::redirect($_SERVER['HTTP_REFERER'] ?? '/cart');
    }

    public function checkout()
    {
        if (!H::user()) {
            $_SESSION['after_login_redirect'] = '/cart';
            H::flash('warning','Please log in or create an account to check out. Your cart is saved in this browser.');
            H::redirect('/login');
        }

        [$items,$total]=$this->totals($this->items());
        if($_POST && $items)
        {
            foreach($items as $p)
            {
                if (($p['fulfillment_type'] ?? 'downloadable') === 'google_drive' && !filter_var(trim($_POST['google_drive_email'] ?? ''), FILTER_VALIDATE_EMAIL)) { H::flash('error','A valid Google Drive email is required for manual delivery items.'); H::redirect('/checkout'); }
                if(!empty($p['license_invalid']))
                {
                    H::flash('error','A license in your cart is no longer available. Please choose an available license before checkout.');
                    H::redirect('/cart');
                }
            }
            try
           {
               DB::pdo()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
               DB::begin();
                $valid=[];
                foreach($items as $p)
               {
                    if(!$this->owned((int)$p['id'])) $valid[]=$p;

               }
                if(!$valid)
               {
                   DB::rollBack();
                    H::flash('warning','You already own the items in your cart.');
                    H::redirect('/cart');

               }
                [$valid,] = $this->totals($valid,true);
                foreach ($valid as $freshItem) {
                    if (($freshItem['fulfillment_type']??'downloadable')==='google_drive'
                        && !filter_var(trim($_POST['google_drive_email']??''),FILTER_VALIDATE_EMAIL)) {
                        throw new \DomainException('A valid Google Drive email is required.');
                    }
                }
                $subtotal=CreditService::formatCents($this->pricing['subtotal_cents']);
                $couponResult = $this->currentCoupon($valid);
                $coupon = ($couponResult && $couponResult['ok']) ? $couponResult['coupon'] : null;
                $couponDiscount = $coupon ? (float)$couponResult['discount'] : 0.0;
                $allocations = $coupon ? CouponService::allocateDiscount($coupon, $valid, $couponDiscount) : [];
                $billingAddress = [
                    'line1' => trim((string)($_POST['billing_line1'] ?? '')),
                    'line2' => trim((string)($_POST['billing_line2'] ?? '')),
                    'city' => trim((string)($_POST['billing_city'] ?? '')),
                    'state' => trim((string)($_POST['billing_state'] ?? '')),
                    'postal_code' => trim((string)($_POST['billing_postal_code'] ?? '')),
                    'country' => strtoupper(trim((string)($_POST['billing_country'] ?? 'US'))),
                ];
                $billingAddress = StripeService::normalizeBillingAddress($billingAddress);
                $taxableItems = [];
                foreach ($valid as $index => $product) {
                    $taxableItems[] = [
                        'id' => $product['id'],
                        'total_price' => CreditService::formatCents(
                            CreditService::parseCents((string)$product['line_total'])
                            - CreditService::parseCents((string)($allocations[$index] ?? '0.00'))
                        ),
                    ];
                }
                $taxCalculation = (new CheckoutOrderService)->calculateTax(
                    $taxableItems,
                    $billingAddress,
                    'tax-checkout:' . (int)H::user()['id'] . ':' . hash('sha256', json_encode([$taxableItems, $billingAddress]))
                );
                $tax = CreditService::formatCents($taxCalculation['tax_cents']);

                /*
                 * Prize Credit is seller-funded and merchandise-only.
                 * It is applied BEFORE Marketplace Credit and NEVER reduces tax.
                 */
                $usePrizeCredit = ($_POST['use_prize_credit'] ?? '') === '1';
                $prizeService = new PrizeCreditService();
                $prizeBalances = $prizeService->balances((int)H::user()['id']);

                $prizeAllocation = PrizeCreditService::allocateCart(
                    $valid,
                    $allocations,
                    CreditService::parseCents((string)$prizeBalances['available']),
                    $usePrizeCredit
                );

                $prizeCreditCents = (int)$prizeAllocation['total_cents'];

                $originalMerchandiseCents =
                    CreditService::parseCents((string)$subtotal)
                    - CreditService::parseCents((string)$couponDiscount, false);

                $originalMerchandiseCents = max(
                    0,
                    $originalMerchandiseCents
                );

                $merchandiseAfterPrizeCents = max(
                    0,
                    $originalMerchandiseCents - $prizeCreditCents
                );

                $grossCents =
                    $originalMerchandiseCents
                    + CreditService::parseCents($tax, false);

                if ((int)$taxCalculation['total_cents'] !== $grossCents) {
                    throw new \RuntimeException(
                        'Stripe Tax total did not match the authoritative merchandise snapshot.'
                    );
                }

                /*
                 * Marketplace Credit applies SECOND and retains its existing
                 * ability to cover the remaining merchandise plus tax.
                 */
                $useCredits = ($_POST['use_credits'] ?? '') === '1';
                $available = (new CreditService())
                    ->balances((int)H::user()['id'])['available'];

                $breakdown = CreditService::checkoutBreakdown(
                    CreditService::formatCents($merchandiseAfterPrizeCents),
                    '0.00',
                    $tax,
                    $available,
                    $useCredits
                );

                $creditCents = $breakdown['credit_cents'];
                $credits = CreditService::formatCents($creditCents);

                $totalCents = max(
                    0,
                    $grossCents
                    - $prizeCreditCents
                    - $creditCents
                );

                $total = CreditService::formatCents($totalCents);

                if (
                    $totalCents === 0
                    && $prizeCreditCents <= 0
                    && $creditCents <= 0
                    && $couponDiscount > 0
                ) {
                    DB::rollBack();
                    H::flash(
                        'error',
                        'Coupon-only free checkout is not available.'
                    );
                    H::redirect('/cart');
                }

                $commissionRate = StripeService::commissionRate();
                $feeInput = [];

                foreach ($valid as $idx => $p) {
                    $discountedLineCents = max(
                        0,
                        CreditService::parseCents(
                            (string)$p['line_total']
                        )
                        - CreditService::parseCents(
                            (string)($allocations[$idx] ?? '0.00'),
                            false
                        )
                    );

                    $itemPrizeCents = (int)(
                        $prizeAllocation['item_cents'][$idx] ?? 0
                    );

                    $feeInput[] = [
                        'id' => $idx + 1,
                        'seller_id' => (int)$p['designer_id'],
                        'gross_cents' => max(
                            0,
                            $discountedLineCents - $itemPrizeCents
                        ),
                    ];
                }

                $sellerFees = MarketplaceFeeService::configured()
                    ->calculate($feeInput);

                $platformCommissionCents = array_sum(
                    array_column($sellerFees, 'fee_cents')
                );

                $order = (new CheckoutOrderService())->createOrder([
                    'user_id' => H::user()['id'],
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'tax_snapshot' => $taxCalculation['snapshot'],
                    'tax_calculation_id' => $taxCalculation['id'],
                    'billing_snapshot' => json_encode(
                        $billingAddress,
                        JSON_THROW_ON_ERROR
                    ),
                    'credits' => $credits,
                    'coupon_discount' => $couponDiscount,
                    'coupon_id' => $coupon['id'] ?? null,
                    'coupon_code' => $coupon['code'] ?? null,
                    'coupon_snapshot' => $coupon
                        ? json_encode($coupon)
                        : null,
                    'total' => $total,
                    'currency' => StripeService::currency(),
                    'amount_cents' => $totalCents,
                    'stripe_paid_amount' => '0.00',
                    'commission_total' => CreditService::formatCents(
                        $platformCommissionCents
                    ),
                ]);

                DB::exec(
                    'update orders
                     set marketplace_fee_model="percentage_plus_fixed",
                         marketplace_fee_basis_points=?,
                         marketplace_fixed_fee_cents=?
                     where id=?',
                    [
                        StripeService::commissionBasisPoints(),
                        StripeService::commissionFixedCents(),
                        $order,
                    ]
                );

                if ($prizeCreditCents > 0) {
                    $prizeAmount = CreditService::formatCents(
                        $prizeCreditCents
                    );

                    $reservedPrize = $prizeService->reserveExact(
                        (int)H::user()['id'],
                        $prizeAmount,
                        (int)$order,
                        'order:' . $order . ':prize-credit:reserve'
                    );

                    if (
                        CreditService::parseCents($reservedPrize)
                        !== $prizeCreditCents
                    ) {
                        throw new \RuntimeException(
                            'Prize Credit reservation did not match checkout allocation.'
                        );
                    }

                    DB::exec(
                        'update orders
                         set prize_credit_applied=?,
                             prize_credit_reserved=?,
                             prize_credit_payment_status="reserved"
                         where id=?',
                        [
                            $prizeAmount,
                            $prizeAmount,
                            $order,
                        ]
                    );
                }

                if ($creditCents > 0) {
                    $credits = (new CreditService())->reserve(
                        (int)H::user()['id'],
                        $credits,
                        (int)$order,
                        'order:' . $order . ':credit:reserve'
                    );

                    $creditCents = CreditService::parseCents($credits);

                    $totalCents = max(
                        0,
                        $grossCents
                        - $prizeCreditCents
                        - $creditCents
                    );

                    $total = CreditService::formatCents($totalCents);

                    DB::exec(
                        'update orders
                         set credits_applied=?,
                             credit_reserved=?,
                             credit_payment_status=?,
                             total=?,
                             stripe_amount_total=?,
                             stripe_paid_amount=?
                         where id=?',
                        [
                            $credits,
                            $credits,
                            $creditCents > 0 ? 'reserved' : 'none',
                            $total,
                            $totalCents,
                            '0.00',
                            $order,
                        ]
                    );
                }
                foreach($valid as $idx=>$p)
               {
                   // Read receipt settings authoritatively inside this transaction; cart/request values are never trusted.
                   $receipt=DB::row('select receipt_note,receipt_image_path from designers where id=?',[(int)$p['designer_id']])??[];
                   $receiptSnapshot=SellerReceiptService::snapshotFromSeller($receipt);
                   $lineDiscount = (float)($allocations[$idx] ?? 0);
                   $discountedLine = max(0, round((float)$p['line_total'] - $lineDiscount, 2));
                   $fee=$sellerFees[(int)$p['designer_id']]['items'][$idx+1];
                    $manualEmail = trim($_POST['google_drive_email'] ?? '');
                    $isManualDelivery = ($p['fulfillment_type'] ?? 'downloadable') === 'google_drive';
                    $itemGoogleDriveEmail = $isManualDelivery ? ($manualEmail ?: null) : null;
                    $manualStatus = $isManualDelivery ? 'pending_delivery' : 'not_applicable';
                    DB::exec('insert into order_items (order_id,product_id,product_title,product_slug,product_image,designer_id,seller_name,license_type,license_name,license_price,license_description,license_snapshot,fulfillment_type,delivery_instructions_snapshot,buyer_google_drive_email,manual_delivery_status,unit_price,commercial_license_price,total_price,commission_rate,purchased_file_version,seller_receipt_note_snapshot,seller_receipt_image_path_snapshot) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$order,$p['id'],$p['title'],$p['slug'],$p['thumbnail'] ?? null,$p['designer_id'],$p['display_name'] ?? null,$p['license_key'],$p['license_name'],$p['license_price'],$p['license_description'],LicenseService::snapshot($p['go_paid_licenses']),$p['fulfillment_type'] ?? 'downloadable',$isManualDelivery ? ($p['manual_delivery_instructions'] ?? null) : null,$itemGoogleDriveEmail,$manualStatus,$p['price'],$p['license_price'],$discountedLine,$commissionRate,null,$receiptSnapshot['note'],$receiptSnapshot['image_path']]);
                    $itemId=(int)DB::id();
                    (new \App\Services\GrandOpeningPricingService())->snapshot(
                        $itemId,$p,CreditService::parseCents((string)$lineDiscount,false)
                    );
                    $itemPrizeCents = (int)(
                        $prizeAllocation['item_cents'][$idx] ?? 0
                    );

                    DB::exec(
                        'update order_items
                         set coupon_id=?,
                             coupon_code=?,
                             coupon_discount=?,
                             prize_credit_applied=?,
                             prize_credit_mode_snapshot=?,
                             platform_commission_amount=?,
                             marketplace_percentage_fee_amount=?,
                             marketplace_fixed_fee_amount=?,
                             seller_payout_amount=?
                         where id=?',
                        [
                            $coupon['id'] ?? null,
                            $coupon['code'] ?? null,
                            $lineDiscount,
                            CreditService::formatCents($itemPrizeCents),
                            (string)($p['prize_credit_mode'] ?? 'none'),
                            CreditService::formatCents($fee['fee_cents']),
                            CreditService::formatCents(
                                $fee['percentage_fee_cents']
                            ),
                            CreditService::formatCents(
                                $fee['fixed_fee_cents']
                            ),
                            CreditService::formatCents(
                                $fee['seller_earnings_cents']
                            ),
                            $itemId,
                        ]
                    );
                    DB::exec('insert into seller_earnings (order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,?,?,?,?,?,?,"pending_payment")',[$order,$p['id'],$p['designer_id'],H::user()['id'],CreditService::formatCents($fee['gross_cents']),CreditService::formatCents($fee['fee_cents']),CreditService::formatCents($fee['seller_earnings_cents'])]);
                    if($sellerFees[(int)$p['designer_id']]['capped'])DB::exec('insert ignore into seller_financial_adjustments(order_id,designer_id,adjustment_type,event_key,note) values (?,? ,"fee_cap_warning",?,?)',[$order,$p['designer_id'],'fee-cap:order:'.$order.':seller:'.$p['designer_id'],'Marketplace fee was capped at seller gross; seller earnings were not negative.']);

               }
                $createdOrder = DB::row('select * from orders where id=?', [$order]);
                $createdItems = DB::rows('select * from order_items where order_id=?', [$order]);
                if (CreditService::parseCents($total) === 0) {
                    $finalizer = new OrderFinalizationService();
                    $finalizer->finalize((int)$order, 'internal-credit-order:' . $order, true);
                    unset($_SESSION['coupon_code']);
                    DB::commit();
                    $finalizer->settlePlatformCreditPayoutsAfterCommit((int)$order);
                    $finalizer->communicate((int)$order);
                    H::redirect('/dashboard/order/' . (int)$order);
                }
                $session = (new CheckoutOrderService)->checkout($createdOrder, $createdItems);
                DB::exec('update orders set stripe_checkout_session_id=?,stripe_payment_status="pending" where id=?', [$session['id'] ?? null, $order]);
                unset($_SESSION['coupon_code']);
                DB::commit();
                header('Location: ' . $session['url'], true, 303);
                exit;

           }
            catch(Throwable $e)
           {
               if (DB::pdo()->inTransaction()) DB::rollBack();
                error_log('Grand Opening checkout pricing: '.get_class($e).': '.$e->getMessage());
                H::flash('error','Checkout failed. Please try again. If this persists, ask an admin to verify Stripe environment configuration.');
                H::redirect('/cart');

           }

        }
        $couponResult = $this->currentCoupon($items);
        $discount = ($couponResult && $couponResult['ok'])
            ? (float)$couponResult['discount']
            : 0.0;

        $coupon = ($couponResult && $couponResult['ok'])
            ? $couponResult['coupon']
            : null;

        $previewAllocations = $coupon
            ? CouponService::allocateDiscount(
                $coupon,
                $items,
                $discount
            )
            : [];

        $balances = (new CreditService())
            ->balances((int)H::user()['id']);

        $prizeBalances = (new PrizeCreditService())
            ->balances((int)H::user()['id']);

        $prizePreview = PrizeCreditService::allocateCart(
            $items,
            $previewAllocations,
            CreditService::parseCents(
                (string)$prizeBalances['available']
            ),
            true
        );

        H::view(
            'buyer/checkout',
            [
                'items' => $items,
                'pricing' => $this->pricing,
                'subtotal' => $total,
                'couponResult' => $couponResult,
                'discount' => $discount,
                'balances' => $balances,
                'prizeBalances' => $prizeBalances,
                'prizePreview' => $prizePreview,
                'finalTotal' => max(
                    0,
                    round($total - $discount, 2)
                ),
            ]
        );

    }

}
