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

    public static function previewContent(string $frequency): array
    {
        [$start,$end]=self::period($frequency);

        $paidPromos=
            $frequency==='weekly'
                ? PromoService::weekly($start,$end)
                : [];

        $products=
            $frequency==='weekly'
                ? self::weeklyProducts($paidPromos)
                : self::products($start,$end);

        return [
            'frequency'=>$frequency,
            'period_start'=>$start,
            'period_end'=>$end,
            'marketing_preference'=>$frequency,
            'paid_promos'=>$paidPromos,
            'products'=>$products
        ];
    }

    public static function queueDigest(string $frequency,?string $ending=null): int
    {
        [$start,$end]=self::period($frequency,$ending);
        $paidPromos=$frequency==='weekly'?PromoService::weekly($start,$end):[];
        $products=$frequency==='weekly'?self::weeklyProducts($paidPromos):self::products($start,$end);
        if(!$products&&!$paidPromos)return 0;
        $column=EmailPreferenceService::column($frequency);
        $users=DB::rows("select u.id,u.email,u.name from users u join email_preferences ep on ep.user_id=u.id where u.status=\"active\" and ep.$column=1");
        $count=0;$endingChecked=false;
        foreach($users as $user){
            $data=['user_id'=>(int)$user['id'],'name'=>$user['name'],'frequency'=>$frequency,'period_start'=>$start,'period_end'=>$end,'marketing_preference'=>$frequency,'manage_preferences_url'=>self::manageUrl(),'paid_promos'=>$paidPromos];
            if(EmailDigestClaimService::queue($frequency,$user,$products,$start,$end,ucfirst($frequency).' Creative Moth marketplace digest','marketplace_digest',$data,"digest:$frequency:$start:{$user['id']}")){$count++;if($frequency==='weekly'&&!$endingChecked&&$paidPromos){PromoService::notifyWeeklyEndingForQueuedIssue($paidPromos,$start,$end);$endingChecked=true;}}
        }
        return $count;
    }

    public static function queueCampaign(int $campaignId): int
    {
        $campaign=DB::row(
            'select *
             from email_campaigns
             where id=?
               and recurring_definition_id is not null
               and newsletter_type in ("weekly","monthly")
               and status="scheduled"
               and scheduled_for<=now()',
            [$campaignId]
        );

        if(!$campaign){
            return 0;
        }

        $frequency=(string)$campaign['newsletter_type'];

        $timezone=
            trim((string)($campaign['schedule_timezone']??''))
            ?: 'America/Detroit';

        $ending=
            (new \DateTimeImmutable(
                (string)$campaign['scheduled_for'],
                new \DateTimeZone('UTC')
            ))
            ->setTimezone(
                new \DateTimeZone($timezone)
            )
            ->format('Y-m-d');

        [$start,$end]=self::period(
            $frequency,
            $ending
        );

        $paidPromos=
            $frequency==='weekly'
                ? PromoService::weekly($start,$end)
                : [];

        $products=
            $frequency==='weekly'
                ? self::weeklyProducts($paidPromos)
                : self::products($start,$end);

        if(!$products && !$paidPromos){
            DB::exec(
                'update email_campaigns
                 set status="completed",
                     queued_at=coalesce(queued_at,now()),
                     completed_at=coalesce(completed_at,now())
                 where id=?
                   and status="scheduled"',
                [$campaignId]
            );

            return 0;
        }

        $column=
            EmailPreferenceService::column(
                $frequency
            );

        $users=DB::rows(
            "select
                u.id,
                u.email,
                u.name
             from users u
             join email_preferences ep
               on ep.user_id=u.id
             where u.status=\"active\"
               and ep.$column=1"
        );

        $queuedCount=0;
        $endingChecked=false;

        foreach($users as $user){
            DB::exec(
                'insert ignore into email_campaign_recipients
                 (
                    campaign_id,
                    user_id,
                    email,
                    name,
                    status
                 )
                 values (?, ?, ?, ?, "pending")',
                [
                    $campaignId,
                    (int)$user['id'],
                    $user['email'],
                    $user['name']
                ]
            );

            $recipient=DB::row(
                'select *
                 from email_campaign_recipients
                 where campaign_id=?
                   and email=?',
                [
                    $campaignId,
                    $user['email']
                ]
            );

            if(
                !$recipient ||
                $recipient['status']!=='pending'
            ){
                continue;
            }

            $data=[
                'user_id'=>(int)$user['id'],
                'name'=>$user['name'],
                'frequency'=>$frequency,
                'period_start'=>$start,
                'period_end'=>$end,
                'marketing_preference'=>$frequency,
                'manage_preferences_url'=>self::manageUrl(),
                'paid_promos'=>$paidPromos,
                'newsletter_subject'=>$campaign['subject'],
                'preheader'=>$campaign['preheader'],
                'newsletter_body'=>$campaign['body'],
                'newsletter_body_format'=>$campaign['body_format'],
                'cta_label'=>$campaign['cta_label'],
                'cta_url'=>$campaign['cta_url']
            ];

            $queued=
                EmailDigestClaimService::queue(
                    $frequency,
                    $user,
                    $products,
                    $start,
                    $end,
                    (string)$campaign['subject'],
                    'marketplace_digest',
                    $data,
                    'recurring-campaign:'.
                        $campaignId.
                        ':user:'.
                        (int)$user['id'],
                    [
                        'campaign_id'=>$campaignId,
                        'campaign_recipient_id'=>
                            (int)$recipient['id']
                    ]
                );

            if($queued){
                $queuedCount++;

                if(
                    $frequency==='weekly' &&
                    !$endingChecked &&
                    $paidPromos
                ){
                    PromoService::
                        notifyWeeklyEndingForQueuedIssue(
                            $paidPromos,
                            $start,
                            $end
                        );

                    $endingChecked=true;
                }

                continue;
            }

            DB::exec(
                'update email_campaign_recipients
                 set status="suppressed",
                     last_error=?
                 where id=?
                   and status="pending"',
                [
                    'No eligible digest content or email preference unavailable',
                    (int)$recipient['id']
                ]
            );
        }

        DB::exec(
            'update email_campaigns
             set status="queued",
                 queued_at=coalesce(queued_at,now())
             where id=?
               and status="scheduled"',
            [$campaignId]
        );

        if($queuedCount===0){
            EmailCampaignService::recalculate(
                $campaignId
            );
        }

        return $queuedCount;
    }

    public static function queueFavoriteShops(?string $ending=null): int
    {
        [$start,$end]=self::period('weekly',$ending);
        $users=DB::rows('select distinct u.id,u.email,u.name from users u join email_preferences ep on ep.user_id=u.id join follows f on f.user_id=u.id join designers d on d.id=f.designer_id and d.status="approved" join products p on p.designer_id=d.id and p.status in ("approved","published") and p.created_at>=? and p.created_at<? where u.status="active" and ep.favorite_shop_emails=1',[$start,$end]);
        $count=0;
        foreach($users as $user){
            $products=DB::rows('select distinct p.id,p.title,p.slug,p.price,p.created_at,d.id designer_id,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from follows f join designers d on d.id=f.designer_id and d.status="approved" join products p on p.designer_id=d.id and p.status in ("approved","published") and p.created_at>=? and p.created_at<? where f.user_id=? order by p.created_at desc,p.id desc',[$start,$end,$user['id']]);
            $products=self::balanceByDesigner($products);
            if(!$products)continue;
            $data=['user_id'=>(int)$user['id'],'name'=>$user['name'],'period_start'=>$start,'period_end'=>$end,'marketing_preference'=>'favorite_shop','manage_preferences_url'=>self::manageUrl()];
            if(EmailDigestClaimService::queue('favorite_shop',$user,$products,$start,$end,'New from shops you follow on Creative Moth','favorite_shop_digest',$data,"favorite-shops:$start:{$user['id']}"))$count++;
        }
        return $count;
    }

    private static function products(string $start,string $end): array
    {
        $products=DB::rows('select p.id,p.designer_id,p.title,p.slug,p.price,p.created_at,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.status in ("approved","published") and d.status="approved" and p.created_at>=? and p.created_at<? order by p.created_at desc,p.id desc',[$start,$end]);
        return self::balanceByDesigner($products);
    }
    private static function weeklyProducts(array $promos): array
    { $exclude=[];foreach($promos as $promo)if($promo['type']==='product')$exclude[]=(int)$promo['product_id'];$limit=max(0,24-count($promos));if(!$limit)return [];$sql='select p.id,p.designer_id,p.title,p.slug,p.price,p.created_at,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order asc,pi.id asc limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.status in ("approved","published") and d.status="approved"';$params=[];if($exclude){$sql.=' and p.id not in ('.implode(',',array_fill(0,count($exclude),'?')).')';$params=$exclude;}$sql.=' order by rand() limit '.(int)$limit;return DB::rows($sql,$params); }
    private static function balanceByDesigner(array $products,int $limit=24): array
    {
        $groups=[];
        foreach($products as $product)$groups[(int)$product['designer_id']][]=$product;
        $result=[];
        while(count($result)<$limit){
            $added=false;
            foreach($groups as &$group){
                if(!$group)continue;
                $result[]=array_shift($group);
                $added=true;
                if(count($result)>=$limit)break;
            }
            unset($group);
            if(!$added)break;
        }
        return $result;
    }

    private static function manageUrl(): string{return \App\Core\Helpers::baseUrl().'/account#email-preferences';}
}
