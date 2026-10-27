<?php
namespace App\Services;

use App\Core\Database as DB;
use DomainException;

final class GrandOpeningPricingService
{
    private static bool $homepagePreview = false;

    public static function isHomepagePreview(): bool
    {
        return self::$homepagePreview;
    }

    public static function withHomepagePreview(callable $render): void
    {
        \App\Core\Helpers::requireAdminPermission('homepage.view');
        $previous = self::$homepagePreview;
        self::$homepagePreview = true;
        try {
            $render();
        } finally {
            self::$homepagePreview = $previous;
        }
    }

    public static function pointsSql(string $alias='p'): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D',$alias)) throw new DomainException('Invalid product alias.');
        $eventCondition = self::$homepagePreview
            ? 'ge.id=1'
            : 'ge.id=1 and ge.is_enabled=1 and ge.starts_at<=utc_timestamp() and ge.ends_at>utc_timestamp()';
        return 'coalesce((select gs.discount_basis_points
            from grand_opening_events ge
            join grand_opening_sales gs on gs.event_id=ge.id
            join designers gd on gd.id=gs.designer_id
            where '.$eventCondition.'
              and gs.designer_id='.$alias.'.designer_id and gs.is_participating=1
              and gd.status="approved" and '.$alias.'.status="approved"
              and (gs.product_scope="shop" or exists (
                  select 1 from grand_opening_sale_products gsp
                  where gsp.event_id=ge.id and gsp.designer_id=gs.designer_id
                    and gsp.product_id='.$alias.'.id)) limit 1),0)';
    }

    public static function priceSql(string $alias='p'): string
    {
        $points=self::pointsSql($alias);
        return '(case when '.$alias.'.price>0 then greatest(0.01,'.$alias.'.price-round('.$alias.'.price*('.$points.')/10000,2)) else 0 end)';
    }

    public static function component(int $cents,int $points): int
    {
        if ($cents<0 || $points<0 || $points>9999) throw new DomainException('Invalid sale amount.');
        return $cents===0 ? 0 : max(1,$cents-intdiv($cents*$points+5000,10000));
    }

    public static function winner(int $saleCents,int $couponCents): string
    {
        if ($saleCents<0 || $couponCents<0) throw new DomainException('Invalid discount.');
        if ($couponCents>$saleCents) return 'coupon';
        return $saleCents>0 ? 'sale' : 'none';
    }

    public function decorate(array $products): array
    {
        $ids=[];
        foreach ($products as $p) if (($p['listing_type']??'product')==='product') $ids[]=(int)$p['id'];
        $quotes=$this->products($ids);
        foreach ($products as &$p) {
            if (($p['listing_type']??'product')!=='product') continue;
            $q=$quotes[(int)$p['id']]??null;
            if (!$q) continue;
            $p['go_regular_price']=$q['price'];
            $p['go_points']=(int)$q['go_points'];
            $p['price']=CreditService::formatCents(self::component(CreditService::parseCents((string)$q['price'],false),$p['go_points']));
        }
        unset($p);
        return $products;
    }

    private function products(array $ids): array
    {
        $ids=array_values(array_unique(array_map('intval',$ids)));
        $result=[];
        foreach (array_chunk($ids,250) as $chunk) {
            $marks=implode(',',array_fill(0,count($chunk),'?'));
            foreach (DB::rows('select p.*,d.display_name,d.store_slug,d.prize_credit_mode,
                d.receipt_note,d.receipt_image_path,'.self::pointsSql().' go_points
                from products p join designers d on d.id=p.designer_id
                where p.id in ('.$marks.') and p.status="approved" and d.status="approved"',$chunk) as $p) {
                $result[(int)$p['id']]=$p;
            }
        }
        return $result;
    }

    private function lockSources(array $ids,string $code): void
    {
        if (!DB::pdo()->inTransaction()) throw new DomainException('Checkout pricing needs a transaction.');
        if (!$ids) return;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $sellers=array_map('intval',array_column(DB::rows('select distinct designer_id from products where id in ('.$marks.') order by designer_id',$ids),'designer_id'));
        if ($sellers) {
            $sm=implode(',',array_fill(0,count($sellers),'?'));
            DB::rows('select id from designers where id in ('.$sm.') order by id for update',$sellers);
        }
        DB::row('select id from grand_opening_events where id=1 lock in share mode');
        DB::rows('select id from products where id in ('.$marks.') order by id for update',$ids);
        DB::rows('select id from product_license_types where product_id in ('.$marks.') order by product_id,id for update',$ids);
        DB::rows('select id from license_types order by id lock in share mode');
        if ($sellers) DB::rows('select id from seller_license_presets where designer_id in ('.$sm.') order by designer_id,id lock in share mode',$sellers);
        if ($code!=='') DB::row('select id from coupons where code=? for update',[CouponService::normalizeCode($code)]);
    }

    public function cart(array $input,string $code='',int $userId=0,bool $lock=false): array
    {
        if (self::$homepagePreview) throw new DomainException('Preview pricing cannot be used for checkout.');
        $ids=array_values(array_unique(array_map(static fn(array $p):int=>(int)$p['id'],$input)));
        sort($ids);
        if ($lock) $this->lockSources($ids,$code);
        $fresh=$this->products($ids);
        $items=[];$regular=0;$saleSavings=0;
        foreach ($input as $old) {
            $p=$fresh[(int)$old['id']]??null;
            if (!$p) {
                if ($lock) throw new DomainException('A product or seller is no longer available.');
                continue;
            }
            $p=array_merge($old,$p);
            $licenses=LicenseService::selectedLicenses($p,$old['license_type']);
            $p['licenses']=LicenseService::productLicenses($p);
            $p['selected_license_keys']=array_column($licenses,'license_key');
            $p['license_invalid']=!$licenses;
            if (!$licenses && $lock) throw new DomainException('A selected license is no longer available.');
            $p['license_key']=$licenses ? LicenseService::keyList($licenses) : (string)$old['license_type'];
            $p['license_name']=$licenses ? LicenseService::nameList($licenses) : 'Unavailable license';
            $p['license_description']=$licenses ? LicenseService::descriptionList($licenses) : 'Please choose available licenses.';
            $p['fulfillment_type']=$p['fulfillment_type']??'downloadable';
            $p['fulfillment_label']=$p['fulfillment_type']==='google_drive' ? 'Google Drive / Manual Delivery' : 'Downloadable Product';
            $p['go_regular_price']=$p['price'];
            $p['go_regular_licenses']=$licenses;
            $p['go_paid_licenses']=$licenses;
            $p['license_price']=$licenses ? LicenseService::priceTotal($licenses) : 0.0;
            $p['line_total']=$licenses ? CreditService::formatCents(CreditService::parseCents((string)$p['price'],false)+CreditService::parseCents((string)$p['license_price'],false)) : '0.00';
            $p['go_regular_line_total']=$p['line_total'];
            $p['go_sale_base']=self::component(CreditService::parseCents((string)$p['price'],false),(int)$p['go_points']);
            $p['go_sale_licenses']=$licenses;
            foreach ($p['go_sale_licenses'] as &$license) {
                $license['price']=CreditService::formatCents(self::component(CreditService::parseCents((string)$license['price'],false),(int)$p['go_points']));
            }
            unset($license);
            $p['go_sale_line']=$licenses ? $p['go_sale_base']+CreditService::parseCents((string)LicenseService::priceTotal($p['go_sale_licenses']),false) : 0;
            $p['go_candidate_savings']=CreditService::parseCents((string)$p['line_total'],false)-$p['go_sale_line'];
            $saleSavings+=$p['go_candidate_savings'];
            $regular+=CreditService::parseCents((string)$p['line_total'],false);
            $items[]=$p;
        }
        $candidate=($code!=='' && $userId>0) ? CouponService::validate($code,$items,$userId) : null;
        $couponCents=!empty($candidate['ok']) ? CreditService::parseCents((string)$candidate['discount'],false) : 0;
        $mode=self::winner($saleSavings,$couponCents);
        $appliedCoupon=$mode==='coupon' ? $candidate : null;
        $subtotal=0;
        foreach ($items as &$p) {
            $p['go_applied_points']=$mode==='sale' ? (int)$p['go_points'] : 0;
            if ($mode==='sale' && !empty($p['go_points']) && !$p['license_invalid']) {
                $p['price']=CreditService::formatCents($p['go_sale_base']);
                $p['go_paid_licenses']=$p['go_sale_licenses'];
                $p['license_price']=LicenseService::priceTotal($p['go_paid_licenses']);
                $p['line_total']=CreditService::formatCents($p['go_sale_line']);
            }
            $p['go_pricing_mode']=$mode;
            $p['go_priced_at']=gmdate('Y-m-d H:i:s');
            $subtotal+=CreditService::parseCents((string)$p['line_total'],false);
        }
        unset($p);
        $notice='';
        if ($candidate && empty($candidate['ok'])) $notice=$candidate['error'];
        elseif ($candidate && $mode==='sale') $notice='The Grand Opening Sale saves '.CreditService::formatCents($saleSavings).' USD, compared with '.CreditService::formatCents($couponCents).' USD using your coupon. The sale is applied; your coupon is not used.';
        elseif ($candidate && $mode==='coupon' && $saleSavings>0) $notice='Your coupon saves more than the Grand Opening Sale and replaces the sale discount for this cart.';
        return ['items'=>$items,'subtotal_cents'=>$subtotal,'regular_cents'=>$regular,
            'sale_savings_cents'=>$mode==='sale'?$saleSavings:0,'sale_candidate_cents'=>$saleSavings,
            'coupon_candidate'=>$candidate,'coupon_result'=>$appliedCoupon,'notice'=>$notice,'mode'=>$mode];
    }

    public function snapshot(int $itemId,array $p,int $couponCents=0): void
    {
        if (!DB::pdo()->inTransaction()) throw new DomainException('Pricing snapshots need an order transaction.');
        $value=['mode'=>$p['go_pricing_mode'],'priced_at'=>$p['go_priced_at'],
            'event_id'=>1,'candidate_basis_points'=>(int)$p['go_points'],
            'applied_basis_points'=>(int)$p['go_applied_points'],
            'regular_base'=>$p['go_regular_price'],'regular_licenses'=>$p['go_regular_licenses'],
            'regular_total'=>$p['go_regular_line_total'],'paid_base'=>$p['price'],
            'paid_licenses'=>$p['go_paid_licenses'],'before_coupon'=>$p['line_total'],
            'coupon_discount_cents'=>$couponCents,
            'merchandise_cents'=>CreditService::parseCents((string)$p['line_total'],false)-$couponCents];
        $json=json_encode($value,JSON_THROW_ON_ERROR);
        $existing=DB::row('select pricing_snapshot from grand_opening_pricing_snapshots where order_item_id=? for update',[$itemId]);
        if ($existing) {
            if (json_decode($existing['pricing_snapshot'],true)!==json_decode($json,true)) throw new DomainException('Pricing snapshot conflict.');
            return;
        }
        DB::exec('insert into grand_opening_pricing_snapshots(order_item_id,event_id,pricing_snapshot) values(?,1,?)',[$itemId,$json]);
    }

    public function saleProducts(int $limit=8): array
    {
        $limit=max(1,min(24,$limit));
        return DB::rows('select p.*,d.display_name,d.store_slug,d.average_rating,d.review_count,
            c.name category_name,c.slug category_slug,
            (select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image
            from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id
            where p.status="approved" and d.status="approved" and '.self::priceSql().'<p.price
            order by coalesce((select min(h.sort_order) from homepage_features h where h.feature_type="product" and h.feature_id=p.id and h.is_active=1),2147483647),p.created_at desc,p.id desc limit '.$limit);
    }

    public function saleSellers(): array
    {
        return DB::rows('select d.*,s.discount_basis_points,s.product_scope
            from designers d join grand_opening_sales s on s.designer_id=d.id and s.event_id=1
            where d.status="approved" and s.is_participating=1 and exists (
                select 1 from products p where p.designer_id=d.id and '.self::priceSql().'<p.price)
            order by coalesce((select min(h.sort_order) from homepage_features h where h.feature_type="designer" and h.feature_id=d.id and h.is_active=1),2147483647),d.display_name,d.id limit 12');
    }
}
