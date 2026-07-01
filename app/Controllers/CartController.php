<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use Throwable;
class CartController
{
    private function owned(int $productId): bool
    {
        return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where o.user_id=? and oi.product_id=? and o.status in ("paid","completed") limit 1',[H::user()['id'],$productId]);

    }
    private function items(): array
    {
        return DB::rows('select ci.id cart_item_id,ci.license_type,p.*,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by sort_order,id limit 1) thumbnail from cart_items ci join products p on p.id=ci.product_id join designers d on d.id=p.designer_id where ci.user_id=? and p.status="approved" order by ci.created_at desc',[H::user()['id']]);

    }
    private function totals(array $items): array
    {
        $subtotal=0;
        foreach($items as &$p)
        {
            $license = LicenseService::purchasableLicense((int)$p['id'], $p['license_type']);
            $p['licenses'] = LicenseService::productLicenses($p);
            $p['license_invalid'] = !$license;
            $p['license_key'] = $license['license_key'] ?? (string)$p['license_type'];
            $p['license_name'] = $license['name'] ?? 'Unavailable license';
            $p['license_description'] = $license['description'] ?? 'This license is no longer available. Please choose an available license before checkout.';
            $p['license_price'] = $license['price'] ?? 0;
            $p['line_total'] = $license ? $license['price'] : 0;
            if ($license) $subtotal += $p['line_total'];

        }
        return [$items,$subtotal];

    }
    public function show()
    {
        H::requireLogin();
        [$items,$subtotal]=$this->totals($this->items());
        H::view('buyer/cart',['items'=>$items,'subtotal'=>$subtotal]);

    }
    public function add($id)
    {
        H::requireLogin();
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
        $license = LicenseService::purchasableLicense($id, $_POST['license_type'] ?? '');
        if (!$license) {
            H::flash('error','Please choose an available license for this product.');
            H::redirect('/product/'.$p['slug']);
        }
        DB::exec('insert ignore into cart_items (user_id,product_id,license_type) values (?,?,?)',[H::user()['id'],$id,$license['license_key']]);
        H::redirect('/cart');

    }
    public function remove($id)
    {
        H::requireLogin();
        DB::exec('delete from cart_items where id=? and user_id=?',[(int)$id,H::user()['id']]);
        H::redirect('/cart');

    }
    public function update()
    {
        H::requireLogin();
        foreach(($_POST['license_type']??[]) as $cartId=>$license)
        {
            $item=DB::row('select ci.*,p.slug from cart_items ci join products p on p.id=ci.product_id where ci.id=? and ci.user_id=?',[(int)$cartId,H::user()['id']]);
            if($item)
           {
               $selected = LicenseService::purchasableLicense((int)$item['product_id'], (string)$license);
               if($selected)
               {
                   $duplicate = DB::row('select id from cart_items where user_id=? and product_id=? and license_type=? and id<>? limit 1',[H::user()['id'],$item['product_id'],$selected['license_key'],(int)$cartId]);
                   if($duplicate) DB::exec('delete from cart_items where id=? and user_id=?',[(int)$cartId,H::user()['id']]);
                   else DB::exec('update cart_items set license_type=? where id=? and user_id=?',[$selected['license_key'],(int)$cartId,H::user()['id']]);
               }

           }

        }
        H::redirect('/cart');

    }
    public function checkout()
    {
        H::requireLogin();
        [$items,$total]=$this->totals($this->items());
        if($_POST && $items)
        {
            foreach($items as $p)
            {
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
                DB::exec('insert into orders (user_id,status,payment_processor,payment_mode,subtotal,credits_applied,total) values (?,?,?,?,?,?,?)',[H::user()['id'],'completed','mock','mock',$total,0,$total]);
                $order=DB::id();
                foreach($valid as $p)
               {
                   $comm=round($p['line_total']*.20,2);
                    DB::exec('insert into order_items (order_id,product_id,designer_id,license_type,license_name,license_price,license_description,license_snapshot,unit_price,commercial_license_price,total_price,commission_rate) values (?,?,?,?,?,?,?,?,?,?,?,?)',[$order,$p['id'],$p['designer_id'],$p['license_key'],$p['license_name'],$p['license_price'],$p['license_description'],json_encode(['key'=>$p['license_key'],'name'=>$p['license_name'],'price'=>$p['license_price'],'description'=>$p['license_description']]),$p['license_price'],0,$p['line_total'],.20]);
                    DB::exec('insert into seller_earnings (order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,?,?,?,?,?,?,?)',[$order,$p['id'],$p['designer_id'],H::user()['id'],$p['line_total'],$comm,$p['line_total']-$comm,'available']);
                    DB::exec('insert into platform_commissions (order_id,product_id,designer_id,gross_sale,commission_amount,referral_commission_placeholder) values (?,?,?,?,?,?)',[$order,$p['id'],$p['designer_id'],$p['line_total'],$comm,0]);
                    DB::exec('update products set sales_count=sales_count+1 where id=?',[$p['id']]);

               }
                DB::exec('delete from cart_items where user_id=?',[H::user()['id']]);
                DB::commit();
                H::redirect('/dashboard/order/'.$order);

           }
            catch(Throwable $e)
           {
               DB::rollBack();
                H::flash('error','Checkout failed. Please try again.');
                H::redirect('/cart');

           }

        }
        H::view('buyer/checkout',['items'=>$items,'subtotal'=>$total]);

    }

}
