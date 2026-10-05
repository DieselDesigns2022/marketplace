<?php
namespace App\Services;
use App\Core\Database as DB;
use App\Core\Helpers as H;

final class EmailCampaignService
{
    public const AUDIENCES=['waitlist_all','waitlist_buyer','waitlist_seller','waitlist_both','waitlist_tester','users_opted_in'];

    public const WAITLIST_COMPOSE_AUDIENCES=[
        'waitlist_all',
        'waitlist_seller',
        'waitlist_buyer',
        'waitlist_tester',
        'waitlist_both',
    ];

    public static function audienceLabel(string $audience):string
    {
        return [
            'waitlist_all'=>'All Waitlist',
            'waitlist_buyer'=>'Buyers',
            'waitlist_seller'=>'Sellers',
            'waitlist_both'=>'Seller & Buyer',
            'waitlist_tester'=>'Testers',
            'users_opted_in'=>'Opted-in registered users',
        ][$audience] ?? 'Unknown audience';
    }
    public static function safeCta(?string $url): ?string { $original=(string)$url;if(str_contains($original,'\\')||preg_match('/[\x00-\x1F\x7F]/',$original))return null;$url=trim($original);if($url==='')return null;if($url[0]==='/')return NotificationService::safeActionUrl($url);$p=parse_url($url);$app=parse_url(H::baseUrl());if($p===false||$app===false||($p['scheme']??'')!=='https'||($app['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass']))return null;$host=strtolower($p['host']??'');$appHost=strtolower($app['host']??'');$port=(int)($p['port']??443);$appPort=(int)($app['port']??443);return $host!==''&&hash_equals($appHost,$host)&&$port===$appPort?mb_substr($url,0,500):null; }
    public static function validate(array $in): array
    {
        $e=[];
        $subject=trim((string)($in['subject']??''));
        $body=(string)($in['body']??'');
        $aud=(string)($in['audience']??'');
        $format=(string)($in['body_format']??'plain');
        $label=trim((string)($in['cta_label']??''));
        $originalUrl=(string)($in['cta_url']??'');
        $urlIsEmpty=trim($originalUrl)==='';

        if(
            $subject==='' ||
            mb_strlen($subject)>190 ||
            preg_match('/[\r\n]/',$subject)
        ){
            $e[]='Enter a valid one-line subject (190 characters maximum).';
        }

        if(!in_array($format,['plain','rich_html'],true)){
            $e[]='Choose a valid email body format.';
        }

        if($format==='rich_html'){
            try {
                $safe=self::sanitizeRichBody($body);
                $plain=trim(
                    html_entity_decode(
                        strip_tags($safe),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )
                );

                if(
                    $plain==='' ||
                    mb_strlen($safe)>20000
                ){
                    $e[]='Enter email copy (20,000 characters maximum).';
                }
            } catch (\Throwable $error) {
                $e[]='The rich-text email could not be safely processed.';
            }
        } elseif(
            trim($body)==='' ||
            mb_strlen($body)>10000
        ){
            $e[]='Enter campaign copy (10,000 characters maximum).';
        }

        if(!in_array($aud,self::AUDIENCES,true)){
            $e[]='Select a valid audience.';
        }

        if(mb_strlen($label)>80){
            $e[]='CTA label is too long.';
        }

        if(
            ($label==='' xor $urlIsEmpty) ||
            (
                !$urlIsEmpty &&
                self::safeCta($originalUrl)===null
            )
        ){
            $e[]='CTA label and a safe Creative Moth URL are required together.';
        }

        return $e;
    }

    public static function sanitizeRichBody(string $html): string
    {
        if(!class_exists(\DOMDocument::class)){
            throw new \RuntimeException(
                'PHP DOM extension is required.'
            );
        }

        $allowed=[
            'p','br',
            'strong','b',
            'em','i','u',
            'h2','h3',
            'ul','ol','li',
            'a',
            'blockquote',
        ];

        $document=new \DOMDocument(
            '1.0',
            'UTF-8'
        );

        $previous=libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8">'.
            '<div id="cm-email-root">'.
            $html.
            '</div>',
            LIBXML_HTML_NOIMPLIED |
            LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root=$document->getElementById(
            'cm-email-root'
        );

        if(!$root){
            return '';
        }

        $cleanNode=function($node) use (&$cleanNode,$allowed): void {
            for(
                $child=$node->firstChild;
                $child!==null;
            ){
                $next=$child->nextSibling;

                if(
                    $child->nodeType === XML_COMMENT_NODE
                ){
                    $node->removeChild($child);
                    $child=$next;
                    continue;
                }

                if(
                    $child->nodeType === XML_ELEMENT_NODE
                ){
                    $tag=strtolower($child->nodeName);

                    if(!in_array($tag,$allowed,true)){
                        while($child->firstChild){
                            $node->insertBefore(
                                $child->firstChild,
                                $child
                            );
                        }

                        $node->removeChild($child);
                        $child=$next;
                        continue;
                    }

                    $href='';
                    $title='';

                    if($tag==='a'){
                        $href=trim(
                            $child->getAttribute('href')
                        );

                        $title=trim(
                            $child->getAttribute('title')
                        );
                    }

                    while(
                        $child->attributes &&
                        $child->attributes->length
                    ){
                        $child->removeAttributeNode(
                            $child->attributes->item(0)
                        );
                    }

                    if(
                        $tag==='a' &&
                        self::safeRichLink($href)!==null
                    ){
                        $child->setAttribute(
                            'href',
                            self::safeRichLink($href)
                        );

                        if($title!==''){
                            $child->setAttribute(
                                'title',
                                mb_substr($title,0,190)
                            );
                        }
                    } elseif($tag==='a'){
                        $child->removeAttribute('href');
                    }

                    $cleanNode($child);
                }

                $child=$next;
            }
        };

        $cleanNode($root);

        $output='';

        foreach($root->childNodes as $child){
            $output.=$document->saveHTML($child);
        }

        return trim($output);
    }

    private static function safeRichLink(string $url): ?string
    {
        $url=trim($url);

        if($url===''){
            return null;
        }

        if(
            str_contains($url,'\\') ||
            preg_match('/[\x00-\x1F\x7F]/',$url)
        ){
            return null;
        }

        if(
            str_starts_with($url,'/') ||
            str_starts_with($url,'#')
        ){
            return mb_substr($url,0,1000);
        }

        $parts=parse_url($url);

        if($parts===false){
            return null;
        }

        $scheme=strtolower(
            (string)($parts['scheme']??'')
        );

        if(
            !in_array(
                $scheme,
                ['https','http','mailto'],
                true
            )
        ){
            return null;
        }

        return mb_substr($url,0,1000);
    }

    public static function waitlistAudienceCount(
        string $audience
    ): int {
        if(
            !in_array(
                $audience,
                self::WAITLIST_COMPOSE_AUDIENCES,
                true
            )
        ){
            return 0;
        }

        [$where,$params]=
            self::waitlistAudienceWhere($audience);

        return (int)(
            DB::row(
                'select count(*) c
                 from waitlist_entries
                 where '.$where,
                $params
            )['c'] ?? 0
        );
    }

    public static function waitlistAudienceCounts(): array
    {
        $counts=[];

        foreach(
            self::WAITLIST_COMPOSE_AUDIENCES
            as $audience
        ){
            $counts[$audience]=
                self::waitlistAudienceCount(
                    $audience
                );
        }

        return $counts;
    }

    private static function waitlistAudienceWhere(
        string $audience
    ): array {
        $where=
            'status in ("subscribed","invited")
             and unsubscribed_at is null
             and length(unsubscribe_nonce)=64';

        $params=[];

        if($audience==='waitlist_seller'){
            $where.=
                ' and find_in_set("seller",interest_type)>0';
        }

        if($audience==='waitlist_buyer'){
            $where.=
                ' and find_in_set("buyer",interest_type)>0';
        }

        if($audience==='waitlist_both'){
            $where.=
                ' and find_in_set("seller",interest_type)>0
                  and find_in_set("buyer",interest_type)>0';
        }

        if($audience==='waitlist_tester'){
            $where.=
                ' and find_in_set("tester",interest_type)>0';
        }

        return [$where,$params];
    }
    public static function create(array $in,int $adminId): int
    {
        $errors=self::validate($in);

        if($errors){
            throw new \InvalidArgumentException(
                implode(' ',$errors)
            );
        }

        $format=(string)($in['body_format']??'plain');

        $body=$format==='rich_html'
            ? self::sanitizeRichBody(
                (string)$in['body']
            )
            : trim((string)$in['body']);

        DB::exec(
            'insert into email_campaigns (
                campaign_type,
                audience,
                subject,
                body,
                body_format,
                cta_label,
                cta_url,
                created_by
             ) values (
                "promotional",
                ?,?,?,?,?,?,?
             )',
            [
                $in['audience'],
                trim((string)$in['subject']),
                $body,
                $format,
                trim(
                    (string)($in['cta_label']??'')
                ) ?: null,
                self::safeCta(
                    $in['cta_url']??null
                ),
                $adminId
            ]
        );

        return (int)DB::id();
    }
    public static function queue(int $id): int { $c=DB::row('select * from email_campaigns where id=? and status="draft"',[$id]);if(!$c)return 0; DB::begin(); try { $rows=self::audience($c['audience']); if(!$rows){DB::exec('update email_campaigns set status="completed",completed_at=now() where id=?',[$id]);DB::commit();return 0;} $queuedCount=0; foreach($rows as $r) DB::exec('insert ignore into email_campaign_recipients (campaign_id,waitlist_entry_id,user_id,email,name,status) values (?,?,?,?,?,"pending")',[$id,$r['waitlist_entry_id']??null,$r['user_id']??null,$r['email'],$r['name']??null]); foreach(DB::rows('select * from email_campaign_recipients where campaign_id=? and status="pending"',[$id]) as $r){ $data=['name'=>$r['name'],'body'=>$c['body'],'body_format'=>$c['body_format']??'plain','cta_label'=>$c['cta_label'],'cta_url'=>$c['cta_url'],'user_id'=>$r['user_id']]; $queued=EmailQueueService::queue('marketing',$r['email'],$c['subject'],'campaign',$data,"campaign:$id:recipient:".$r['id'],['campaign_id'=>$id,'campaign_recipient_id'=>$r['id'],'waitlist_entry_id'=>$r['waitlist_entry_id']]);if($queued)$queuedCount++; DB::exec('update email_campaign_recipients set status=?,last_error=? where id=?',[$queued?'queued':'suppressed',$queued?null:'Consent or unsubscribe authorization unavailable',$r['id']]); } DB::exec('update email_campaigns set status="queued",queued_at=now() where id=?',[$id]);DB::commit();if($queuedCount===0)self::recalculate($id); }catch(\Throwable $e){DB::rollBack();throw $e;} return count($rows); }
    private static function audience(string $aud): array
    {
        if($aud==='users_opted_in'){
            return DB::rows(
                'select
                    u.id user_id,
                    null waitlist_entry_id,
                    u.email,
                    u.name
                 from users u
                 join email_preferences ep
                   on ep.user_id=u.id
                 where u.status="active"
                   and ep.marketing_opt_in=1
                   and ep.marketing_opted_out_at is null
                   and length(ep.unsubscribe_nonce)=64'
            );
        }

        [$where,$params]=
            self::waitlistAudienceWhere($aud);

        return DB::rows(
            'select
                id waitlist_entry_id,
                null user_id,
                email,
                name
             from waitlist_entries
             where '.$where,
            $params
        );
    }
    public static function cancel(int $id): void { DB::exec('update email_campaigns set status="cancelled",cancelled_at=now() where id=? and status in ("draft","queued","sending")',[$id]); DB::exec('update email_messages set status="cancelled" where campaign_id=? and status="pending"',[$id]); DB::exec('update email_campaign_recipients set status="cancelled" where campaign_id=? and status in ("pending","queued")',[$id]); }
    public static function statusForCounts(array $n): string { $total=(int)($n['total']??0);$sent=(int)($n['sent']??0);$failed=(int)($n['failed']??0);$active=(int)($n['active']??0);if($active>0)return 'sending';if($failed>0&&$sent>0)return 'partially_failed';if($failed>0)return 'failed';if($sent>0)return 'sent';return 'completed'; }
    public static function recalculate(int $id): void { $c=DB::row('select status from email_campaigns where id=?',[$id]);if(!$c||$c['status']==='cancelled')return;$n=DB::row('select count(*) total,sum(status="sent") sent,sum(status="failed") failed,sum(status in ("pending","queued")) active from email_campaign_recipients where campaign_id=?',[$id]);$status=self::statusForCounts($n?:[]);DB::exec('update email_campaigns set status=?,sent_at=if(? in ("sent","partially_failed"),coalesce(sent_at,now()),sent_at),completed_at=if(? in ("sent","completed","partially_failed","failed"),coalesce(completed_at,now()),completed_at) where id=?',[$status,$status,$status,$id]); }
}
