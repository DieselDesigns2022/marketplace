<?php
namespace App\Services;

use App\Core\Database as DB;

final class EmailDigestService
{
    public static function period(string $frequency,?string $ending=null): array
    {
        if(!in_array($frequency,['weekly','monthly'],true))throw new \InvalidArgumentException('Invalid digest frequency.');
        $end=$ending===null?new \DateTimeImmutable('today',new \DateTimeZone('UTC')):\DateTimeImmutable::createFromFormat('!Y-m-d',$ending,new \DateTimeZone('UTC'));
        if(!$end||($ending!==null&&$end->format('Y-m-d')!==$ending))throw new \InvalidArgumentException('The period end must use YYYY-MM-DD.');
        $start=$frequency==='weekly'?$end->modify('-7 days'):$end->modify('first day of this month')->modify('-1 month');
        if($frequency==='monthly')$end=$end->modify('first day of this month');
        return [$start->format('Y-m-d'),$end->format('Y-m-d')];
    }

    public static function queueDigest(string $frequency,?string $ending=null): int
    {
        [$start,$end]=self::period($frequency,$ending);
        $products=self::products($start,$end);
        if(!$products)return 0;
        $column=EmailPreferenceService::column($frequency);
        $users=DB::rows("select u.id,u.email,u.name from users u join email_preferences ep on ep.user_id=u.id where u.status=\"active\" and ep.$column=1");
        $count=0;
        foreach($users as $user){
            $data=['user_id'=>(int)$user['id'],'name'=>$user['name'],'frequency'=>$frequency,'period_start'=>$start,'period_end'=>$end,'marketing_preference'=>$frequency,'manage_preferences_url'=>self::manageUrl()];
            if(EmailDigestClaimService::queue($frequency,$user,$products,$start,$end,ucfirst($frequency).' Asset Moth marketplace digest','marketplace_digest',$data,"digest:$frequency:$start:{$user['id']}"))$count++;
        }
        return $count;
    }

    public static function queueFavoriteShops(?string $ending=null): int
    {
        [$start,$end]=self::period('weekly',$ending);
        $users=DB::rows('select distinct u.id,u.email,u.name from users u join email_preferences ep on ep.user_id=u.id join follows f on f.user_id=u.id join designers d on d.id=f.designer_id and d.status="approved" join products p on p.designer_id=d.id and p.status in ("approved","published") and p.created_at>=? and p.created_at<? where u.status="active" and ep.favorite_shop_emails=1',[$start,$end]);
        $count=0;
        foreach($users as $user){
            $products=DB::rows('select distinct p.id,p.title,p.slug,p.price,d.id designer_id,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from follows f join designers d on d.id=f.designer_id and d.status="approved" join products p on p.designer_id=d.id and p.status in ("approved","published") and p.created_at>=? and p.created_at<? where f.user_id=? order by p.created_at desc,p.id desc limit 24',[$start,$end,$user['id']]);
            if(!$products)continue;
            $data=['user_id'=>(int)$user['id'],'name'=>$user['name'],'period_start'=>$start,'period_end'=>$end,'marketing_preference'=>'favorite_shop','manage_preferences_url'=>self::manageUrl()];
            if(EmailDigestClaimService::queue('favorite_shop',$user,$products,$start,$end,'New from shops you follow on Asset Moth','favorite_shop_digest',$data,"favorite-shops:$start:{$user['id']}"))$count++;
        }
        return $count;
    }

    private static function products(string $start,string $end): array
    {
        return DB::rows('select p.id,p.designer_id,p.title,p.slug,p.price,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.status in ("approved","published") and d.status="approved" and p.created_at>=? and p.created_at<? order by p.created_at desc,p.id desc limit 24',[$start,$end]);
    }
    private static function manageUrl(): string{return \App\Core\Helpers::baseUrl().'/account#email-preferences';}
}
