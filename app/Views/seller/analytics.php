<?php use App\Core\Helpers as H; ?>

<style>
.dashboard-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem}
.dashboard-grid>.card{padding:.8rem 1rem;gap:.2rem}
.dashboard-grid>.card>strong{font-size:1.35rem}
section.card{padding:1rem;margin-bottom:.75rem}
section.card h2{margin-top:0;margin-bottom:.65rem}
.table-wrap th,.table-wrap td{padding:.5rem .65rem}
form.card{padding:.8rem 1rem;margin-bottom:.85rem}
</style>

<h1>Seller Analytics</h1><p><a class="btn" href="/seller/followers">💜 View Followers &amp; Favorites</a></p><p class="muted">Only sales, customers, promotions, referrals, and events connected to your seller account are included.</p>

<?php
$sellerTraffic=null;
$sellerTrafficError=false;

if (!$error) {
    try {
        $sellerTraffic=
            \App\Services\SellerTrafficReportService::forUser(
                (int)(H::user()['id']??0),
                (string)$report['filters']['from'],
                (string)$report['filters']['to']
            );
    } catch (\Throwable $trafficException) {
        error_log(
            'Seller traffic report failed: '.
            $trafficException->getMessage()
        );
        $sellerTrafficError=true;
    }
}
?>

<?php if($sellerTraffic!==null):?>
<section class="card seller-traffic-report">
    <h2>Shop &amp; Product Traffic</h2>
    <p class="muted">
        See how often shoppers view your storefront and products
        during your selected date range.
        Unique visitors are counted once per visitor per day.
        The same visitor may be counted on more than one day.
    </p>

    <div class="dashboard-grid">
        <div class="card">
            <strong><?=number_format($sellerTraffic['store_views'])?></strong>
            <span>Storefront views</span>
        </div>
        <div class="card">
            <strong><?=number_format($sellerTraffic['store_unique'])?></strong>
            <span>Daily unique storefront visitors</span>
        </div>
        <div class="card">
            <strong><?=number_format($sellerTraffic['product_views'])?></strong>
            <span>Product page views</span>
        </div>
        <div class="card">
            <strong><?=number_format($sellerTraffic['product_unique'])?></strong>
            <span>Daily unique product visitors</span>
        </div>
    </div>

    <h3>Most viewed products</h3>
    <?php if(!$sellerTraffic['products']):?>
        <p class="muted">No products to report yet.</p>
    <?php else:?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Views</th>
                        <th>Daily unique visitors</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($sellerTraffic['products'] as $trafficProduct):?>
                    <tr>
                        <td>
                            <a href="/product/<?=rawurlencode((string)$trafficProduct['slug'])?>">
                                <?=H::e($trafficProduct['title'])?>
                            </a>
                        </td>
                        <td><?=number_format((int)$trafficProduct['page_views'])?></td>
                        <td><?=number_format((int)$trafficProduct['daily_unique_visitors'])?></td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    <?php endif;?>

    <h3>Recent traffic by day</h3>
    <?php if(!$sellerTraffic['days']):?>
        <p class="muted">
            No storefront or product views recorded in this period.
        </p>
    <?php else:?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Views</th>
                        <th>Daily unique visitors</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($sellerTraffic['days'] as $trafficDay):?>
                    <tr>
                        <td><?=H::e($trafficDay['day'])?></td>
                        <td><?=number_format((int)$trafficDay['page_views'])?></td>
                        <td><?=number_format((int)$trafficDay['daily_unique_visitors'])?></td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    <?php endif;?>

    <p class="muted">
        Traffic figures are aggregated. Personal visitor
        details are not shown. Sales-related traffic
        sources remain available in Revenue by traffic source.
    </p>
</section>
<?php elseif($sellerTrafficError):?>
<p class="muted">
    Shop traffic is temporarily unavailable.
    Your other analytics are unaffected.
</p>
<?php endif;?>

<?php if($error):?><div class="alert error"><?=H::e($error)?></div><?php else:?><form class="card" method="get"><label>From <input type="date" name="from" value="<?=H::e($report['filters']['from'])?>"></label><label>To <input type="date" name="to" value="<?=H::e($report['filters']['to'])?>"></label><label>Compare with <select name="comparison"><option value="previous" <?=$report['filters']['comparison']==='previous'?'selected':''?>>Previous equivalent period</option><option value="year_over_year" <?=$report['filters']['comparison']==='year_over_year'?'selected':''?>>Same dates last year</option></select></label><button>Apply</button> <a class="btn" href="/seller/analytics.csv?<?=http_build_query(['from'=>$report['filters']['from'],'to'=>$report['filters']['to'],'comparison'=>$report['filters']['comparison']])?>">Export CSV</a></form>
<div class="dashboard-grid"><?php foreach(['gross_sales'=>'Gross sales','net_sales'=>'Net sales','seller_earnings'=>'Earnings','discounts'=>'Discounts','refunds'=>'Refunds','orders'=>'Orders'] as $key=>$label):?><div class="card"><strong><?=$key==='orders'?number_format($report['financials'][$key]):H::money($report['financials'][$key])?></strong><span><?=H::e($label)?></span></div><?php endforeach;?></div>
<section class="card"><h2>What this means</h2><ul><?php foreach($report['insights'] as $insight):?><li><?=H::e($insight)?></li><?php endforeach;?></ul></section>
<section class="card"><h2>Period comparison</h2><p class="muted"><strong><?=$report['filters']['comparison']==='year_over_year'?'Same dates last year':'Previous equivalent period'?>:</strong> <?=H::e($report['filters']['from'])?>–<?=H::e($report['filters']['to'])?> compared with <?=H::e($report['filters']['previous_from'])?>–<?=H::e($report['filters']['previous_to'])?>.</p><div class="table-wrap"><table><tr><th>Metric</th><th>Current</th><th>Previous</th><th>Difference</th><th>Change</th></tr><?php foreach(['gross_sales'=>'Gross sales','net_sales'=>'Net sales','seller_earnings'=>'Earnings','discounts'=>'Discounts','refunds'=>'Refunds','orders'=>'Orders'] as $key=>$label):$c=$report['comparisons'][$key];?><tr><th><?=H::e($label)?></th><td><?=H::e((string)$c['current'])?></td><td><?=H::e((string)$c['previous'])?></td><td><?=H::e((string)$c['difference'])?></td><td><?=$c['percent']===null?'Not available — prior value is zero':H::e((string)$c['percent']).'%'?></td></tr><?php endforeach;?></table></div></section>
<?php $tables=[['Sales by day of week',$report['sales_by_weekday'],['weekday_name'=>'Day','orders'=>'Qualifying orders']],['Revenue by traffic source',$report['traffic_sources'],['traffic_source'=>'Source','orders'=>'Orders','net_revenue'=>'Net revenue']],['Product performance',$report['products'],['title'=>'Product','units'=>'Units','gross_sales'=>'Gross sales']],['Customer ranking',$report['customers'],['name'=>'Customer','order_count'=>'Qualifying orders','last_order_at'=>'Last order']],['Returning customers',$report['returning_customers'],['name'=>'Customer','order_count'=>'Qualifying orders','last_order_at'=>'Last order']],['Coupon performance',$report['coupons'],['code'=>'Code','uses'=>'Uses','discount_amount'=>'Discount']],['Promotion performance',$report['promos'],['placement'=>'Placement','status'=>'Status','campaigns'=>'Campaigns','impressions'=>'Impressions','clicks'=>'Clicks']],['Referral activity',$report['referrals'],['referral_type'=>'Type','referrals'=>'Referrals','rewarded'=>'Rewarded']],['Bundle-event performance',$report['bundles'],['title'=>'Event','orders'=>'Orders','earnings_cents'=>'Earnings']],['Upcoming collaborative-event drops',$report['scheduled'],['drop_date'=>'Drop date','listing_count'=>'Scheduled events']]];foreach($tables as [$title,$rows,$columns]):?><section class="card"><h2><?=H::e($title)?></h2><?php if(!$rows):?><p class="muted">No data for this period.</p><?php else:?><div class="table-wrap"><table><thead><tr><?php foreach($columns as $heading):?><th><?=H::e($heading)?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($columns as $key=>$heading):?><td><?=$key==='earnings_cents'?H::money(((float)$row[$key])/100):H::e((string)$row[$key])?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><?php endforeach;?><?php endif;?>
