<?php
namespace App\Services;
use App\Core\Database as DB;

final class CustomCheckoutDraftService
{
    public static function save(string $token,array $payload):void
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',$token)
            || (int)($payload['buyer_id']??0)<=0
            || (int)($payload['service_id']??0)<=0){
            throw new \DomainException('Invalid custom request.');
        }
        $json=json_encode($payload,JSON_THROW_ON_ERROR);
        DB::exec(
            'insert ignore into custom_checkout_drafts
             (token,buyer_user_id,custom_service_id,payload) values (?,?,?,?)',
            [$token,$payload['buyer_id'],$payload['service_id'],$json]
        );
        $stored=DB::row('select * from custom_checkout_drafts where token=?',[$token]);
        if(!$stored || (int)$stored['buyer_user_id']!==(int)$payload['buyer_id']
            || (int)$stored['custom_service_id']!==(int)$payload['service_id']
            || json_decode($stored['payload'],true,512,JSON_THROW_ON_ERROR)!==$payload){
            throw new \DomainException('Custom request storage conflict.');
        }
    }

    public static function load(string $token,int $buyerId,int $serviceId=0):?array
    {
        if($buyerId<=0 || !preg_match('/^[a-f0-9]{48}$/D',$token)) return null;
        $row=DB::row(
            'select * from custom_checkout_drafts where token=? and buyer_user_id=?',
            [$token,$buyerId]
        );
        if($row){
            $payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        }else{
            // Preserve existing requests when their original session is available.
            $payload=$_SESSION['custom_design_checkout'][$token]??null;
        }
        if(!is_array($payload) || (int)($payload['buyer_id']??0)!==$buyerId
            || ($serviceId>0 && (int)($payload['service_id']??0)!==$serviceId)){
            return null;
        }
        if(!$row) self::save($token,$payload);
        return $payload;
    }

    public static function forget(string $token,int $buyerId):void
    {
        DB::exec('delete from custom_checkout_drafts where token=? and buyer_user_id=?',
            [$token,$buyerId]);
    }
}
