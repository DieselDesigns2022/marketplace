<?php
namespace App\Services;

final class CustomDesignPublicationTransitionService
{
    public static function shouldDispatch(bool $before,bool $after):bool
    {
        return !$before&&$after;
    }

    public static function dispatchAfterCommit(int $serviceId,bool $before,bool $after,?callable $publisher=null):bool
    {
        if(!self::shouldDispatch($before,$after))return false;
        try{
            ($publisher??static fn(int $id)=>(new SocialPublishingService())->autoPostCustomDesign($id))($serviceId);
        }catch(\Throwable $e){
            NotificationService::reportFailure('custom_design_social_auto_post_dispatch',$e);
        }
        return true;
    }

    public static function dispatchAfterPreviewProcessing(int $serviceId,bool $before,bool $after,int $regenerationFailures,?callable $publisher=null):bool
    {
        if($regenerationFailures>0)return false;
        return self::dispatchAfterCommit($serviceId,$before,$after,$publisher);
    }
}
