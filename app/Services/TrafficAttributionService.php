<?php
namespace App\Services;

final class TrafficAttributionService
{
    public static function capture(array $query,?string $referer,?string $host):void
    {
        $explicit=trim((string)($query['utm_source']??''))!==''||!empty($query['ref']);
        $existing=$_SESSION['traffic_attribution']??null;
        if($existing&&!$explicit&&strtotime((string)($existing['at']??''))>=time()-2592000)return;
        $validReferral=false;$code=(string)($query['ref']??'');
        if($code!==''&&ReferralService::validFormat($code)){try{$validReferral=(bool)(new ReferralService())->referrer($code);}catch(\Throwable $error){NotificationService::reportFailure('traffic_attribution_referral_validation',$error);}}
        $source=self::classify((string)($query['utm_source']??''),$referer,$host,$validReferral);
        $_SESSION['traffic_attribution']=['source'=>$source,'at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s')];
    }
    public static function classify(string $utmSource,?string $referer,?string $host,bool $referralLink=false):string
    {
        if($referralLink)return 'Referral';
        $candidate=strtolower(trim($utmSource));
        $domain=strtolower((string)(parse_url((string)$referer,PHP_URL_HOST)??''));
        $value=$candidate!==''?$candidate:$domain;
        if(str_contains($value,'facebook')||$value==='fb')return 'Facebook';
        if(str_contains($value,'instagram')||$value==='ig')return 'Instagram';
        if(str_contains($value,'pinterest'))return 'Pinterest';
        if(str_contains($value,'google'))return 'Google';
        if($candidate!=='')return 'Other / Unknown';
        if($domain==='')return 'Direct';
        $local=strtolower(preg_replace('/:\d+$/','',(string)$host));
        if($local!==''&&($domain===$local||str_ends_with($domain,'.'.$local)))return 'Direct';
        return 'Other / Unknown';
    }
    public static function snapshot():array { $a=$_SESSION['traffic_attribution']??[];return ['source'=>$a['source']??null,'at'=>$a['at']??null]; }
}
