<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\StripeService;
use Throwable;

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
            return DB::rows('select ci.id cart_item_id,ci.license_type,ci.price_snapshot,ci.license_price_snapshot,ci.total_snapshot,p.*,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by sort_order,id limit 1) thumbnail from cart_items ci join products p on p.id=ci.product_id join designers d on d.id=p.designer_id where ci.user_id=? and p.status="approved" order by ci.created_at desc',[H::user()['id']]);
        }

        $items = [];
        foreach ($this->guestCart() as $cartId => $cartItem) {
            $p = DB::row('select p.*,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by sort_order,id limit 1) thumbnail from products p join designers d on d.id=p.designer_id where p.id=? and p.status="approved"',[(int)($cartItem['product_id'] ?? 0)]);
            if (!$p) continue;
            $p['cart_item_id'] = (int)$cartId;
            $p['license_type'] = (string)($cartItem['license_type'] ?? 'personal');
            $items[] = $p;
        }
        return $items;

    }

    private function totals(array $items): array
    {
        $subtotal=0;
        foreach($items as &$p)
        {
            $licenses = LicenseService::purchasableLicenses((int)$p['id'], $p['license_type']);
            $p['licenses'] = LicenseService::productLicenses($p);
            $p['selected_license_keys'] = array_column($licenses, 'license_key');
            $p['license_invalid'] = !$licenses;
            $p['license_key'] = $licenses ? LicenseService::keyList($licenses) : (string)$p['license_type'];
            $p['license_name'] = $licenses ? LicenseService::nameList($licenses) : 'Unavailable license';
            $p['license_description'] = $licenses ? LicenseService::descriptionList($licenses) : 'One or more selected licenses are no longer available. Please choose available licenses before checkout.';
            $p['license_price'] = $licenses ? LicenseService::priceTotal($licenses) : 0.00;
            $p['fulfillment_type'] = $p['fulfillment_type'] ?? 'downloadable';
            $p['fulfillment_label'] = $p['fulfillment_type'] === 'google_drive' ? 'Google Drive / Manual Delivery' : 'Downloadable Product';
            $p['line_total'] = $licenses ? (float)$p['price'] + $p['license_price'] : 0;
            if (!empty($p['cart_item_id']) && H::user() && $licenses) DB::exec('update cart_items set quantity=1, price_snapshot=?, license_price_snapshot=?, total_snapshot=?, fulfillment_type_snapshot=? where id=? and user_id=?', [$p['price'], $p['license_price'], $p['line_total'], $p['fulfillment_type'], $p['cart_item_id'], H::user()['id']]);
            if ($licenses) $subtotal += $p['line_total'];

        }
        return [$items,$subtotal];

    }

    public function show()
    {
        [$items,$subtotal]=$this->totals($this->items());
        H::view('buyer/cart',['items'=>$items,'subtotal'=>$subtotal]);

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
                $total=array_sum(array_column($valid,'line_total'));
                $commissionRate = StripeService::commissionRate();
                DB::exec('insert into orders (user_id,status,payment_processor,payment_mode,payment_provider,payment_status,subtotal,tax_amount,credits_applied,coupon_discount,total,fulfillment_status,phase9_foundation_order,stripe_currency,stripe_amount_total,platform_commission_total) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[H::user()['id'],'pending','stripe','checkout','stripe','pending',$total,0,0,0,$total,'pending',1,StripeService::currency(),StripeService::cents($total),round($total * $commissionRate, 2)]);
                $order=DB::id();
                foreach($valid as $p)
               {
                   $comm=round($p['line_total'] * $commissionRate, 2);
                    $manualEmail = trim($_POST['google_drive_email'] ?? '');
                    $isManualDelivery = ($p['fulfillment_type'] ?? 'downloadable') === 'google_drive';
                    $itemGoogleDriveEmail = $isManualDelivery ? ($manualEmail ?: null) : null;
                    $manualStatus = $isManualDelivery ? 'pending_delivery' : 'not_applicable';
                    DB::exec('insert into order_items (order_id,product_id,product_title,product_slug,product_image,designer_id,seller_name,license_type,license_name,license_price,license_description,license_snapshot,fulfillment_type,delivery_instructions_snapshot,buyer_google_drive_email,manual_delivery_status,unit_price,commercial_license_price,total_price,commission_rate,purchased_file_version) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$order,$p['id'],$p['title'],$p['slug'],$p['thumbnail'] ?? null,$p['designer_id'],$p['display_name'] ?? null,$p['license_key'],$p['license_name'],$p['license_price'],$p['license_description'],LicenseService::snapshot(LicenseService::selectedLicenses($p, $p['license_key'])),$p['fulfillment_type'] ?? 'downloadable',$isManualDelivery ? ($p['manual_delivery_instructions'] ?? null) : null,$itemGoogleDriveEmail,$manualStatus,$p['price'],$p['license_price'],$p['line_total'],$commissionRate,null]);
                    DB::exec('insert into seller_earnings (order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,?,?,?,?,?,?,?)',[$order,$p['id'],$p['designer_id'],H::user()['id'],$p['line_total'],$comm,$p['line_total']-$comm,'pending_payment']);

               }
                $createdOrder = DB::row('select * from orders where id=?', [$order]);
                $createdItems = DB::rows('select * from order_items where order_id=?', [$order]);
                $session = StripeService::createCheckoutSession($createdOrder, $createdItems);
                DB::exec('update orders set stripe_checkout_session_id=?,stripe_payment_status="pending" where id=?', [$session['id'] ?? null, $order]);
                DB::exec('delete from cart_items where user_id=?',[H::user()['id']]);
                DB::commit();
                header('Location: ' . $session['url'], true, 303);
                exit;

           }
            catch(Throwable $e)
           {
               DB::rollBack();
                H::flash('error','Checkout failed. Please try again. If this persists, ask an admin to verify Stripe environment configuration.');
                H::redirect('/cart');

           }

        }
        H::view('buyer/checkout',['items'=>$items,'subtotal'=>$total]);

    }

}
