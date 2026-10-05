<?php
namespace App\Services;
use App\Repositories\AnalyticsRepository;
use DateTimeImmutable;
use DomainException;

final class AnalyticsService
{
    public function __construct(private readonly ?AnalyticsRepository $repository=null) {}
    public function filters(array $input):array
    { $to=$this->date($input['to']??date('Y-m-d'));$from=$this->date($input['from']??date('Y-m-d',strtotime('-29 days')));if($from>$to)throw new DomainException('From date must not be after the to date.');$start=new DateTimeImmutable($from);$end=new DateTimeImmutable($to);$days=$start->diff($end)->days+1;if($days>367)throw new DomainException('Reports are limited to 367 days.');$previousTo=$start->modify('-1 day');return ['from'=>$from,'to'=>$to,'previous_from'=>$previousTo->modify('-'.($days-1).' days')->format('Y-m-d'),'previous_to'=>$previousTo->format('Y-m-d')]; }
    private function date(string $date):string { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new DomainException('Dates must use YYYY-MM-DD.');return $date; }
    public function report(array $input,?int $designerId=null):array
    {
        $f=$this->filters($input);$r=$this->repository??new AnalyticsRepository();
        $financials=$r->financials($f['from'],$f['to'],$designerId);$previous=$r->financials($f['previous_from'],$f['previous_to'],$designerId);
        $data=['filters'=>$f,'financials'=>$financials,'previous_financials'=>$previous,'comparisons'=>self::comparisons($financials,$previous),'products'=>$r->topProducts($f['from'],$f['to'],$designerId),'customers'=>$r->customers($f['from'],$f['to'],$designerId),'coupons'=>$r->coupons($f['from'],$f['to'],$designerId),'promos'=>$r->promos($f['from'],$f['to'],$designerId),'referrals'=>$r->referrals($f['from'],$f['to'],$designerId),'bundles'=>$r->bundles($f['from'],$f['to'],$designerId),'traffic_sources'=>$r->trafficSources($f['from'],$f['to'],$designerId)];
        if($designerId===null){$growth=$r->growth($f['from'],$f['to']);$previousGrowth=$r->growth($f['previous_from'],$f['previous_to']);$data+=['sellers'=>$r->topSellers($f['from'],$f['to']),'growth'=>$growth,'previous_growth'=>$previousGrowth,'searches'=>$r->searchAnalytics($f['from'],$f['to']),'search_trends'=>$r->searchTrends($f['from'],$f['to']),'failures'=>$r->failures($f['from'],$f['to']),'health'=>$r->health()];foreach(['buyers','sellers'] as $key){$currentTotal=array_sum(array_column($growth,$key));$previousTotal=array_sum(array_column($previousGrowth,$key));$data['comparisons'][$key.'_growth']=self::comparison($currentTotal,$previousTotal);}}else{$data['scheduled']=$r->scheduledDrops($designerId);}
        $data['insights']=self::insights($data,$designerId===null);return $data;
    }
    public static function comparisons(array $current,array $previous):array
    { $out=[];foreach($current as $key=>$value)$out[$key]=self::comparison((float)$value,(float)($previous[$key]??0));return $out; }
    private static function comparison(float $now,float $prior):array{return ['current'=>$now,'previous'=>$prior,'difference'=>$now-$prior,'percent'=>$prior!=0?round((($now-$prior)/abs($prior))*100,1):null];}
    public static function insights(array $report,bool $admin):array
    {
        $out=[];$gross=$report['comparisons']['gross_sales']??null;
        if(($report['financials']['orders']??0)>0&&$gross&&$gross['previous']>0){$direction=$gross['difference']>=0?'increased':'decreased';$out[]=sprintf('Gross sales %s by %.1f%% versus the equivalent previous period (%s to %s).',$direction,abs($gross['percent']),self::money($gross['previous']),self::money($gross['current']));}
        $orders=(int)($report['financials']['orders']??0);$repeat=array_sum(array_map(fn($row)=>(int)$row['order_count']>1?1:0,$report['customers']??[]));if($orders>0&&$repeat>0)$out[]=$repeat.' customer'.($repeat===1?' has':'s have').' placed more than one qualifying order in this period. Consider nurturing these returning customers.';
        $grossValue=(float)($report['financials']['gross_sales']??0);$refunds=(float)($report['financials']['refunds']??0);if($grossValue>0&&$refunds>0)$out[]=sprintf('Refunds were %.1f%% of gross sales (%s refunded from %s). Review refund patterns if this remains elevated.',($refunds/$grossValue)*100,self::money($refunds),self::money($grossValue));
        if(!empty($report['products'][0])&&(float)$report['products'][0]['gross_sales']>0)$out[]=$report['products'][0]['title'].' was the highest-grossing product in this period at '.self::money((float)$report['products'][0]['gross_sales']).'.';
        if($admin){$zero=array_values(array_filter($report['searches']??[],fn($row)=>(int)$row['zero_result_searches']>0));if($zero)$out[]='“'.$zero[0]['normalized_query'].'” produced zero results '.(int)$zero[0]['zero_result_searches'].' time(s). Consider reviewing catalog coverage or terminology.';}
        return $out?:['There is not enough recorded activity in this period to produce a reliable insight.'];
    }
    private static function money(float $value):string{return '$'.number_format($value,2);}
    public static function csv(array $report):string
    { $out=fopen('php://temp','r+');fputcsv($out,['Metric','Current period','Previous period','Difference','Percent change']);foreach($report['comparisons'] as $key=>$row)fputcsv($out,[ucwords(str_replace('_',' ',$key)),$row['current'],$row['previous'],$row['difference'],$row['percent']===null?'Not available (prior value is zero)':$row['percent'].'%']);self::csvSection($out,['Product','Seller','Units','Gross sales'],$report['products'],['title','display_name','units','gross_sales']);self::csvSection($out,['Customer','Qualifying completed orders','Last order'],$report['customers'],['name','order_count','last_order_at']);self::csvSection($out,['Traffic source','Orders','Net revenue'],$report['traffic_sources'],['traffic_source','orders','net_revenue']);if(isset($report['searches'])){self::csvSection($out,['Search query','Searches','Successful searches','Zero-result searches'],$report['searches'],['normalized_query','searches','successful_searches','zero_result_searches']);self::csvSection($out,['Search date','Searches','Successful searches','Zero-result searches'],$report['search_trends']??[],['search_date','searches','successful_searches','zero_result_searches']);}rewind($out);return stream_get_contents($out); }
    private static function csvSection($out,array $headings,array $rows,array $keys):void { fputcsv($out,[]);fputcsv($out,$headings);foreach($rows as $row)fputcsv($out,array_map(fn($key)=>$row[$key]??'',$keys)); }
}
