<?php
namespace App\Services;

use App\Core\Database as DB;

final class NotificationService
{
    public static function reportFailure(string $context, \Throwable $error): void
    {
        $safe = OperationalErrorSanitizer::sanitize($error->getMessage(), 240);
        $context = OperationalErrorSanitizer::context($context);
        error_log('Asset Moth communication hook '.$context.' failed: '.$safe);
    }
    public static function safeActionUrl(?string $url): ?string
    {
        if ($url === null || $url === '') return null;
        if ($url[0] !== '/' || str_starts_with($url, '//') || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) return null;
        return mb_substr($url, 0, 500);
    }

    public static function create(int $userId, string $type, string $audience, string $title, string $message, string $eventKey, ?string $actionUrl = null): bool
    {
        if ($userId < 1 || !preg_match('/^[a-z0-9_.:-]{1,190}$/i', $eventKey)) return false;
        return DB::exec('insert ignore into notifications (user_id,notification_type,audience,title,message,action_url,event_key) values (?,?,?,?,?,?,?)',
            [$userId, mb_substr($type,0,80), in_array($audience,['buyer','designer','admin','system'],true)?$audience:'system', mb_substr(strip_tags($title),0,190), strip_tags($message), self::safeActionUrl($actionUrl), $eventKey]);
    }

    public static function admins(string $type, string $title, string $message, string $eventKey, ?string $url=null): void
    { foreach (DB::rows('select id from users where role="admin" and status="active"') as $u) self::create((int)$u['id'],$type,'admin',$title,$message,$eventKey,$url); }
    public static function creditReferralUpdate(int $userId,string $eventKey,string $message): bool { return self::create($userId,'credit_referral','buyer','Credit or referral update',$message,$eventKey,'/dashboard/referrals'); }
    public static function sellerTaxReminder(int $userId,string $eventKey,string $message): bool { return self::create($userId,'tax_reminder','designer','Tax information reminder',$message,$eventKey,'/seller/stripe'); }
    public static function promoStatus(int $userId,string $eventKey,string $message): bool { return self::create($userId,'promo_status','designer','Promotion status',$message,$eventKey); }
    public static function bundleInvitation(int $userId,string $eventKey,string $message): bool { return self::create($userId,'bundle_invitation','designer','Bundle invitation',$message,$eventKey); }
    public static function rankBadge(int $userId,string $eventKey,string $message): bool { return self::create($userId,'rank_badge','designer','Rank or badge earned',$message,$eventKey,'/seller/rank'); }
    /** Foundation only: call when a future compliant seller-tax transition exists. */
    public static function sellerTaxEnabled(string $eventKey,string $message,?string $url=null): void { self::admins('seller_tax_enabled','Seller tax status enabled',$message,$eventKey,$url); }
    /** Foundation only: call when a future promotional-submission record is created. */
    public static function newPromotionalSubmission(string $eventKey,string $message,?string $url=null): void { self::admins('promotional_submission','New promotional submission',$message,$eventKey,$url); }
    /** Foundation only: call when a future bundle-submission record is created. */
    public static function newBundleSubmission(string $eventKey,string $message,?string $url=null): void { self::admins('bundle_submission','New bundle submission',$message,$eventKey,$url); }
}
