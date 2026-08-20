<?php
namespace App\Services;

use App\Core\Database as DB;

final class EmailDigestClaimService
{
    private const PRIORITY=['favorite_shop'=>1,'weekly'=>2,'monthly'=>3];

    public static function queue(string $category,array $user,array $products,string $start,string $end,string $subject,string $template,array $data,string $dedupe): bool
    {
        if(!isset(self::PRIORITY[$category])||!$products)return false;
        DB::begin();
        try{
            DB::row('select id from users where id=? for update',[(int)$user['id']]);
            $assigned=[];
            foreach($products as $product)if(self::claimable((int)$user['id'],(int)$product['id'],$category,$start,$end))$assigned[]=$product;
            if(!$assigned){DB::commit();return false;}
            $data['products']=$assigned;
            $queued=EmailQueueService::queue('marketing',$user['email'],$subject,$template,$data,$dedupe);
            $message=DB::row('select id from email_messages where deduplication_key=?',[$dedupe]);
            if(!$queued||!$message){DB::rollBack();return false;}
            foreach($assigned as $product)DB::exec('insert ignore into email_digest_content_claims (user_id,product_id,preference_category,period_start,period_end,email_message_id) values (?,?,?,?,?,?)',[(int)$user['id'],(int)$product['id'],$category,$start,$end,(int)$message['id']]);
            DB::commit();return true;
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    private static function claimable(int $userId,int $productId,string $category,string $start,string $end): bool
    {
        $claims=DB::rows('select c.id,c.preference_category,c.email_message_id,m.status from email_digest_content_claims c join email_messages m on m.id=c.email_message_id where c.user_id=? and c.product_id=? and c.period_start<? and c.period_end>? and m.status in ("pending","processing","sent") for update',[$userId,$productId,$end,$start]);
        foreach($claims as $claim){
            if(self::PRIORITY[$claim['preference_category']]<=self::PRIORITY[$category])return false;
            if($claim['status']!=='pending')return false;
        }
        foreach($claims as $claim){self::removeProduct((int)$claim['email_message_id'],$productId);DB::exec('delete from email_digest_content_claims where id=?',[(int)$claim['id']]);}
        return true;
    }

    private static function removeProduct(int $messageId,int $productId): void
    {
        $message=DB::row('select template_data from email_messages where id=? and status="pending" for update',[$messageId]);
        if(!$message)return;
        $data=json_decode($message['template_data'],true,512,JSON_THROW_ON_ERROR);
        $data['products']=array_values(array_filter(is_array($data['products']??null)?$data['products']:[],static fn($product):bool=>(int)($product['id']??0)!==$productId));
        if(!$data['products'])DB::exec('update email_messages set status="cancelled",last_error="Content reassigned by digest preference precedence" where id=? and status="pending"',[$messageId]);
        else DB::exec('update email_messages set template_data=? where id=? and status="pending"',[json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),$messageId]);
    }
}
