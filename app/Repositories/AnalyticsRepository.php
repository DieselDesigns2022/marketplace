<?php

namespace App\Repositories;

use App\Core\Database as DB;

/** Read-only reporting queries over the authoritative order and payout ledgers. */
final class AnalyticsRepository
{
    private function period(string $column, array &$params, string $from, string $to): string
    {
        $params[] = $from . ' 00:00:00';
        $params[] = $to . ' 23:59:59';
        return "$column between ? and ?";
    }

    public function financials(string $from, string $to, ?int $designerId = null): array
    {
        $params=[]; $where=$this->period('o.paid_at',$params,$from,$to).' and o.payment_status in ("paid","partially_refunded","refunded")';
        if ($designerId !== null) { $where.=' and sp.designer_id=?'; $params[]=$designerId; }
        $row=DB::row("select coalesce(sum(coalesce(sp.original_gross_amount,sp.gross_amount)),0) gross_sales,coalesce(sum(sp.platform_commission_amount),0) platform_commission,coalesce(sum(coalesce(sp.original_seller_payout_amount,sp.seller_payout_amount)),0) seller_earnings from seller_payouts sp join orders o on o.id=sp.order_id where $where",$params)??[];
        $refundParams=[]; $refundWhere=$this->period('o.paid_at',$refundParams,$from,$to).' and o.payment_status in ("partially_refunded","refunded")';
        if ($designerId !== null) { $refundWhere.=' and sp.designer_id=?'; $refundParams[]=$designerId; }
        $refund=DB::row("select coalesce(sum(greatest(0,coalesce(sp.original_gross_amount,sp.gross_amount)-sp.gross_amount)),0) refunds,coalesce(sum(greatest(0,coalesce(sp.original_seller_payout_amount,sp.seller_payout_amount)-sp.seller_payout_amount)),0) seller_refunds from seller_payouts sp join orders o on o.id=sp.order_id where $refundWhere",$refundParams)??[];
        $itemParams=[]; $itemWhere=$this->period('o.paid_at',$itemParams,$from,$to).' and o.payment_status in ("paid","partially_refunded","refunded")';
        if ($designerId !== null) { $itemWhere.=' and (oi.designer_id=? or exists(select 1 from collab_order_allocations ca where ca.order_item_id=oi.id and ca.designer_id=?))'; $itemParams[]=$designerId; $itemParams[]=$designerId; }
        $items=DB::row("select coalesce(sum(oi.coupon_discount),0) discounts,count(distinct o.id) orders from order_items oi join orders o on o.id=oi.order_id where $itemWhere",$itemParams)??[];
        $tax=0.0;
        if ($designerId === null) {
            $taxParams=[]; $taxWhere=$this->period('o.paid_at',$taxParams,$from,$to).' and o.payment_status in ("paid","partially_refunded","refunded")';
            $tax=(float)(DB::row("select coalesce(sum(o.tax_amount),0) tax from orders o where $taxWhere",$taxParams)['tax']??0);
            $taxRefundParams=[]; $taxRefundWhere=$this->period('o.paid_at',$taxRefundParams,$from,$to);
            $tax-=(float)(DB::row("select coalesce(sum(x.tax_refund_cents),0)/100 tax from (select m.order_id,max(m.cumulative_refund_cents) cumulative,max(m.tax_refund_cents) tax_refund_cents from marketplace_refund_observations m group by m.order_id) x join orders o on o.id=x.order_id where $taxRefundWhere",$taxRefundParams)['tax']??0);
        }
        $gross=(float)($row['gross_sales']??0); $refunds=(float)($refund['refunds']??0);
        return ['gross_sales'=>$gross,'net_sales'=>max(0,$gross-$refunds),'platform_commission'=>(float)($row['platform_commission']??0),'seller_earnings'=>max(0,(float)($row['seller_earnings']??0)-(float)($refund['seller_refunds']??0)),'discounts'=>(float)($items['discounts']??0),'refunds'=>$refunds,'tax_collected'=>max(0,$tax),'orders'=>(int)($items['orders']??0)];
    }

    public function topProducts(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded") and oi.product_id is not null';if($designerId!==null){$w.=' and oi.designer_id=?';$p[]=$designerId;}return DB::rows("select p.id,p.title,d.display_name,count(*) units,coalesce(sum(oi.total_price),0) gross_sales from order_items oi join orders o on o.id=oi.order_id join products p on p.id=oi.product_id join designers d on d.id=p.designer_id where $w group by p.id,p.title,d.display_name order by gross_sales desc,units desc,p.id limit 25",$p); }
    public function topSellers(string $from,string $to):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded")';return DB::rows("select d.id,d.display_name,count(distinct sp.order_id) orders,coalesce(sum(sp.gross_amount),0) net_sales,coalesce(sum(sp.seller_payout_amount),0) earnings from seller_payouts sp join orders o on o.id=sp.order_id join designers d on d.id=sp.designer_id where $w group by d.id,d.display_name order by net_sales desc,orders desc,d.id limit 25",$p); }
    public function customers(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded")';if($designerId!==null){$w.=' and exists(select 1 from seller_payouts sp where sp.order_id=o.id and sp.designer_id=? and sp.gross_amount>0)';$p[]=$designerId;}return DB::rows("select u.id,u.name,count(distinct o.id) order_count,max(o.paid_at) last_order_at from orders o join users u on u.id=o.user_id where $w group by u.id,u.name order by order_count desc,last_order_at desc,u.id limit 100",$p); }
    public function coupons(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('cu.created_at',$p,$from,$to);if($designerId!==null){$w.=' and c.scope="seller" and c.seller_id=?';$p[]=$designerId;}return DB::rows("select c.id,c.code,c.scope,count(cu.id) uses,coalesce(sum(cu.discount_amount),0) discount_amount from coupon_usages cu join coupons c on c.id=cu.coupon_id join orders o on o.id=cu.order_id where $w and o.payment_status in ('paid','partially_refunded') group by c.id,c.code,c.scope order by uses desc,discount_amount desc,c.id",$p); }
    public function promos(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('a.created_at',$p,$from,$to);if($designerId!==null){$w.=' and a.designer_id=?';$p[]=$designerId;}return DB::rows("select a.placement,a.status,count(*) campaigns,coalesce(sum(a.impressions),0) impressions,coalesce(sum(a.clicks),0) clicks,coalesce(sum(case when a.payment_status='paid' then a.price_cents else 0 end),0) spend_cents from ads a where $w group by a.placement,a.status order by campaigns desc,a.placement",$p); }
    public function referrals(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('r.created_at',$p,$from,$to);if($designerId!==null){$w.=' and (r.referred_designer_id=? or exists(select 1 from designers d where d.id=? and d.user_id=r.referrer_user_id))';$p[]=$designerId;$p[]=$designerId;}return DB::rows("select coalesce(r.referral_type,'unclassified') referral_type,count(*) referrals,sum(r.buyer_rewarded_at is not null or r.seller_rewarded_at is not null) rewarded from referrals r where $w group by r.referral_type order by referrals desc",$p); }
    public function bundles(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded")';if($designerId!==null){$w.=' and ca.designer_id=?';$p[]=$designerId;}return DB::rows("select c.id,c.title,count(distinct oi.order_id) orders,coalesce(sum(ca.allocation_cents-ca.refunded_allocation_cents),0) earnings_cents from collab_order_allocations ca join collab_events c on c.id=ca.collab_id join order_items oi on oi.id=ca.order_item_id join orders o on o.id=oi.order_id where $w group by c.id,c.title order by orders desc,earnings_cents desc,c.id",$p); }
    public function scheduledDrops(int $designerId):array { return DB::rows('select date(c.sale_starts_at) drop_date,count(*) listing_count from collab_events c join collab_participants cp on cp.collab_id=c.id where cp.designer_id=? and cp.membership_status in ("host","accepted") and c.sale_starts_at>now() and c.status in ("collecting","processing","ready") group by date(c.sale_starts_at) order by drop_date',[$designerId]); }
    public function growth(string $from,string $to):array { return DB::rows('select date(created_at) day,sum(role="buyer") buyers,sum(role="designer") sellers from users where created_at between ? and ? group by date(created_at) order by day',[$from.' 00:00:00',$to.' 23:59:59']); }
    public function trafficSources(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded")';if($designerId!==null){$w.=' and sp.designer_id=?';$p[]=$designerId;}return DB::rows("select coalesce(nullif(o.traffic_source,''),'Unknown / Unattributed') traffic_source,count(distinct o.id) orders,coalesce(sum(sp.gross_amount),0) net_revenue from seller_payouts sp join orders o on o.id=sp.order_id where $w group by coalesce(nullif(o.traffic_source,''),'Unknown / Unattributed') order by net_revenue desc,traffic_source",$p); }
    public function searchAnalytics(string $from,string $to):array
    { return DB::rows('select normalized_query,count(*) searches,sum(result_count>0) successful_searches,sum(result_count=0) zero_result_searches,min(searched_at) first_searched_at,max(searched_at) last_searched_at from search_events where searched_at between ? and ? group by normalized_query order by searches desc,normalized_query limit 100',[$from.' 00:00:00',$to.' 23:59:59']); }
    public function searchTrends(string $from,string $to):array
    { return DB::rows('select date(searched_at) search_date,count(*) searches,sum(result_count>0) successful_searches,sum(result_count=0) zero_result_searches from search_events where searched_at between ? and ? group by date(searched_at) order by search_date',[$from.' 00:00:00',$to.' 23:59:59']); }
    public function salesByWeekday(string $from,string $to,?int $designerId=null):array
    { $p=[];$w=$this->period('o.paid_at',$p,$from,$to).' and o.payment_status in ("paid","partially_refunded")';if($designerId!==null){$w.=' and exists(select 1 from seller_payouts sp where sp.order_id=o.id and sp.designer_id=? and sp.gross_amount>0)';$p[]=$designerId;}return DB::rows("select weekday(o.paid_at) weekday_number,dayname(o.paid_at) weekday_name,count(distinct o.id) orders from orders o where $w group by weekday(o.paid_at),dayname(o.paid_at) order by weekday_number",$p); }
    public function failures(string $from,string $to):array { return ['payments'=>DB::rows('select id,payment_status,manual_review_required,created_at from orders where created_at between ? and ? and (payment_status in ("failed","manual_review") or manual_review_required=1) order by created_at desc limit 100',[$from.' 00:00:00',$to.' 23:59:59']),'webhooks'=>DB::rows('select stripe_event_id,event_type,processing_status,processing_error,created_at from stripe_events where created_at between ? and ? and (processing_status="failed" or processing_error is not null) order by created_at desc limit 100',[$from.' 00:00:00',$to.' 23:59:59'])]; }
    public function health():array { return DB::row('select (select count(*) from orders where payment_status in ("failed","manual_review") or manual_review_required=1) payment_issues,(select count(*) from stripe_events where (processing_status="failed" or processing_error is not null) and admin_resolved_at is null) webhook_issues,(select count(*) from seller_payouts where (payout_status="transfer_failed" or stripe_transfer_error is not null) and admin_resolved_at is null) payout_issues,(select count(*) from marketplace_refund_observations where allocation_status="needs_allocation") refunds_needing_allocation,(select count(*) from products where status="pending_review") products_pending_review')??[]; }

    public function visitorOverview(string $from,string $to):array
    {
        $daily=DB::row(
            'select
                count(*) visitor_days,
                count(distinct visitor_hash) distinct_visitors,
                sum(visitor_type="guest") guests,
                sum(visitor_type="buyer") buyers,
                sum(visitor_type="seller") sellers
             from analytics_daily_visitors
             where visit_date between ? and ?
               and confirmed_at is not null',
            [$from,$to]
        )??[];

        $sessions=DB::row(
            'select
                count(*) sessions,
                coalesce(sum(page_views),0) recorded_page_views
             from analytics_sessions
             where started_at between ? and ?
               and confirmed_at is not null',
            [$from.' 00:00:00',$to.' 23:59:59']
        )??[];

        $engagement=DB::row(
            'select
                coalesce(round(avg(session_seconds),1),0) avg_session_seconds,
                coalesce(round(avg(page_count),2),0) avg_pages_per_session
             from (
                select
                    session_hash,
                    sum(duration_seconds) session_seconds,
                    count(*) page_count
                from analytics_page_views
                where viewed_at between ? and ?
                  and confirmed_at is not null
                group by session_hash
             ) x',
            [$from.' 00:00:00',$to.' 23:59:59']
        )??[];

        $returning=DB::row(
            'select
                coalesce(sum(first_date between ? and ?),0) new_visitors,
                coalesce(sum(first_date < ?),0) returning_visitors
             from (
                select selected.visitor_hash,min(all_visits.visit_date) first_date
                from (
                    select distinct visitor_hash
                    from analytics_daily_visitors
                    where visit_date between ? and ?
                      and confirmed_at is not null
                ) selected
                join analytics_daily_visitors all_visits
                  on all_visits.visitor_hash=selected.visitor_hash
                 and all_visits.confirmed_at is not null
                group by selected.visitor_hash
             ) x',
            [$from,$to,$from,$from,$to]
        )??[];

        $cutoff=(new \DateTimeImmutable(
            'now',
            new \DateTimeZone($_ENV['APP_TIMEZONE']??'America/Detroit')
        ))->modify('-15 minutes')->format('Y-m-d H:i:s');

        $active=(int)(
            DB::row(
                'select count(*) c
                 from analytics_sessions
                 where last_seen_at>=?
                   and confirmed_at is not null',
                [$cutoff]
            )['c']??0
        );

        return [
            'visitor_days'=>(int)($daily['visitor_days']??0),
            'distinct_visitors'=>(int)($daily['distinct_visitors']??0),
            'guests'=>(int)($daily['guests']??0),
            'buyers'=>(int)($daily['buyers']??0),
            'sellers'=>(int)($daily['sellers']??0),
            'sessions'=>(int)($sessions['sessions']??0),
            'page_views'=>(int)($sessions['recorded_page_views']??0),
            'avg_session_seconds'=>(float)($engagement['avg_session_seconds']??0),
            'avg_pages_per_session'=>(float)($engagement['avg_pages_per_session']??0),
            'new_visitors'=>(int)($returning['new_visitors']??0),
            'returning_visitors'=>(int)($returning['returning_visitors']??0),
            'active_15m'=>$active,
        ];
    }

    public function visitorDaily(string $from,string $to):array
    {
        return DB::rows(
            'select
                d.visit_date day,
                count(*) unique_visitors,
                sum(d.visitor_type="guest") guests,
                sum(d.visitor_type="buyer") buyers,
                sum(d.visitor_type="seller") sellers,
                coalesce(p.page_views,0) page_views
             from analytics_daily_visitors d
             left join (
                select visit_date,count(*) page_views
                from analytics_page_views
                where visit_date between ? and ?
                  and confirmed_at is not null
                group by visit_date
             ) p on p.visit_date=d.visit_date
             where d.visit_date between ? and ?
               and d.confirmed_at is not null
             group by d.visit_date,p.page_views
             order by d.visit_date',
            [$from,$to,$from,$to]
        );
    }

    public function pageEngagement(string $from,string $to):array
    {
        return DB::rows(
            'select
                path,
                count(*) page_views,
                count(distinct concat(visit_date,":",visitor_hash)) unique_visitors,
                sum(is_entry=1) landing_visits,
                sum(is_exit=1) exit_visits,
                coalesce(round(avg(case when duration_seconds>0 then duration_seconds end),1),0) avg_seconds,
                coalesce(sum(duration_seconds),0) total_seconds
             from analytics_page_views
             where viewed_at between ? and ?
               and confirmed_at is not null
             group by path
             order by page_views desc,avg_seconds desc,path
             limit 100',
            [$from.' 00:00:00',$to.' 23:59:59']
        );
    }


}
