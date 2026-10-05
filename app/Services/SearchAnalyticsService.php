<?php
namespace App\Services;
use App\Core\Database as DB;

final class SearchAnalyticsService
{
    public static function record(string $query,int $resultCount,?callable $writer=null,?callable $reporter=null):bool
    {
        $query=mb_strtolower(trim(preg_replace('/\s+/u',' ',$query)));
        if($query===''||mb_strlen($query)<2)return false;
        try {
            ($writer??fn(string $term,int $count)=>DB::exec('insert into search_events(normalized_query,result_count) values(?,?)',[$term,$count]))(mb_substr($query,0,190),max(0,$resultCount));
            return true;
        } catch (\Throwable $error) {
            try { ($reporter??fn(\Throwable $failure)=>NotificationService::reportFailure('search_analytics_tracking',$failure))($error); }
            catch (\Throwable) { /* Analytics and its failure reporting must never break public search. */ }
            return false;
        }
    }
}
