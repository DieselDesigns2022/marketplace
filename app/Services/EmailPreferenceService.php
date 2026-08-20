<?php
namespace App\Services;

use App\Core\Database as DB;

final class EmailPreferenceService
{
    public const CATEGORIES=['weekly','monthly','favorite_shop'];
    private const COLUMNS=['weekly'=>'weekly_emails','monthly'=>'monthly_emails','favorite_shop'=>'favorite_shop_emails'];

    public static function column(string $category): ?string
    {
        return self::COLUMNS[$category]??null;
    }

    public static function ensure(int $userId): array
    {
        $row=DB::row('select * from email_preferences where user_id=?',[$userId]);
        if($row)return $row;
        DB::exec('insert ignore into email_preferences (user_id,marketing_opt_in,weekly_emails,monthly_emails,favorite_shop_emails,unsubscribe_nonce) values (?,0,0,0,0,?)',[$userId,bin2hex(random_bytes(32))]);
        return DB::row('select * from email_preferences where user_id=?',[$userId])?:[];
    }

    public static function enabled(array $preference,string $category): bool
    {
        $column=self::column($category);
        return $column!==null&&(int)($preference[$column]??0)===1;
    }

    public static function save(int $userId,array $selected): void
    {
        self::ensure($userId);
        $weekly=!empty($selected['weekly'])?1:0;
        $monthly=!empty($selected['monthly'])?1:0;
        $favorite=!empty($selected['favorite_shop'])?1:0;
        $any=($weekly||$monthly||$favorite)?1:0;
        DB::exec('update email_preferences set preference_changed_at=if(weekly_emails<>? or monthly_emails<>? or favorite_shop_emails<>?,now(),preference_changed_at),weekly_emails=?,monthly_emails=?,favorite_shop_emails=?,marketing_opt_in=?,marketing_opted_in_at=if(?=1,coalesce(marketing_opted_in_at,now()),marketing_opted_in_at),marketing_opted_out_at=if(?=0,coalesce(marketing_opted_out_at,now()),null) where user_id=?',[$weekly,$monthly,$favorite,$weekly,$monthly,$favorite,$any,$any,$any,$userId]);
    }
}
