<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\EmailQueueService;
use App\Services\NotificationService;

final class AdminWaitlistController
{
    private const ALLOWED=['interest_type'=>['seller','buyer','both','tester'],'source'=>['direct','homepage','seller','social','referral','campaign'],'status'=>['subscribed','invited','unsubscribed','suppressed']];
    private const TRANSITIONS=['subscribed'=>['subscribed','unsubscribed','suppressed'],'invited'=>['invited','unsubscribed','suppressed'],'unsubscribed'=>['unsubscribed','suppressed'],'suppressed'=>['suppressed']];
    private function admin():void{H::requireRole('admin');}
    public static function allowedStatusTransition(string $from,string $to):bool{return in_array($to,self::TRANSITIONS[$from]??[],true);}
    public static function statusOptions(string $status):array{return self::TRANSITIONS[$status]??[];}
    public static function invitationDecision(array $input,array $filters):array
    {
        $mode=(string)($input['mode']??'');$id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $hasFilters=(bool)array_filter($filters,fn($v)=>$v!=='');
        if(!in_array($mode,['individual','filtered'],true))return ['valid'=>false,'message'=>'Choose a valid invitation action.'];
        if($mode==='individual')return $id?['valid'=>true,'mode'=>$mode,'id'=>(int)$id]:['valid'=>false,'message'=>'Choose one valid waitlist entry.'];
        if($id)return ['valid'=>false,'message'=>'Individual and filtered invitation targets cannot be combined.'];
        if(($input['confirm']??'')!=='1')return ['valid'=>false,'message'=>'Bulk invitations require explicit confirmation.'];
        if(!$hasFilters&&($input['all_eligible']??'')!=='1')return ['valid'=>false,'message'=>'Choose at least one filter or explicitly confirm all eligible subscribers.'];
        return ['valid'=>true,'mode'=>$mode];
    }
    private function state(array $input):array{$out=['q'=>trim((string)($input['q']??'')),'interest_type'=>'','source'=>'','status'=>''];foreach(self::ALLOWED as $k=>$allowed)if(in_array($input[$k]??'',$allowed,true))$out[$k]=$input[$k];return $out;}
    private function where(array $f,bool $eligible=false):array{$w=[];$p=[];if($f['q']!==''){$w[]='(name like ? or email like ? or business_name like ?)';$p=array_merge($p,array_fill(0,3,'%'.$f['q'].'%'));}foreach(self::ALLOWED as $k=>$a)if($f[$k]!==''){$w[]="$k=?";$p[]=$f[$k];}if($eligible){$w[]='status in ("subscribed","invited")';$w[]='unsubscribed_at is null';$w[]='invited_at is null';}return [$w?implode(' and ',$w):'1=1',$p];}
    private static function query(array $f):string{return http_build_query(array_filter($f,fn($v)=>$v!==''));}
    public function index():void
    {
        $this->admin();$f=$this->state($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $id=(int)($_POST['id']??0);$to=(string)($_POST['status']??'');$entry=$id>0?DB::row('select status from waitlist_entries where id=?',[$id]):null;
            if(!$entry||!self::allowedStatusTransition($entry['status'],$to))H::flash('warning','That waitlist status transition is not permitted. No changes were made.');
            else {DB::exec('update waitlist_entries set status=?,unsubscribed_at=if(?="unsubscribed",coalesce(unsubscribed_at,now()),unsubscribed_at) where id=?',[$to,$to,$id]);H::flash('success','Waitlist status updated.');}
            $this->back($f);
        }
        [$w,$p]=$this->where($f);$page=max(1,(int)($_GET['page']??1));$total=(int)(DB::row('select count(*) c from waitlist_entries where '.$w,$p)['c']??0);$pages=max(1,(int)ceil($total/25));$page=min($page,$pages);$rows=DB::rows('select * from waitlist_entries where '.$w.' order by created_at desc,id desc limit 25 offset '.(($page-1)*25),$p);$counts=DB::rows('select status,count(*) count from waitlist_entries group by status');[$ew,$ep]=$this->where($f,true);$eligible=(int)(DB::row('select count(*) c from waitlist_entries where '.$ew,$ep)['c']??0);H::view('admin/waitlist/index',['entries'=>$rows,'counts'=>$counts,'page'=>$page,'pages'=>$pages,'total'=>$total,'filters'=>$f,'query'=>self::query($f),'eligible'=>$eligible]);
    }
    public function export():void{$this->admin();$f=$this->state($_GET);[$w,$p]=$this->where($f);header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="asset-moth-waitlist.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Name','Email','Interest','Business','Source','Status','Confirmation sent','Invited','Created']);foreach(DB::rows('select name,email,interest_type,business_name,source,status,confirmation_sent_at,invited_at,created_at from waitlist_entries where '.$w.' order by created_at desc',$p) as $r)fputcsv($out,array_map([self::class,'csvCell'],array_values($r)));fclose($out);}
    public static function csvCell($v):string{$v=(string)$v;return preg_match('/^\s*[=+\-@]/u',$v)?"'".$v:$v;}
    public function invite():void
    {
        $this->admin();$f=$this->state($_POST);$decision=self::invitationDecision($_POST,$f);if(!$decision['valid']){H::flash('warning',$decision['message']);$this->back($f);}
        if($decision['mode']==='individual'){$rows=DB::rows('select * from waitlist_entries where id=? and status in ("subscribed","invited") and unsubscribed_at is null and invited_at is null',[$decision['id']]);$already=$rows?0:(int)(DB::row('select count(*) c from waitlist_entries where id=? and invited_at is not null',[$decision['id']])['c']??0);}
        else{[$w,$p]=$this->where($f,true);$rows=DB::rows('select * from waitlist_entries where '.$w,$p);[$aw,$ap]=$this->where($f);$already=(int)(DB::row('select count(*) c from waitlist_entries where '.$aw.' and invited_at is not null',$ap)['c']??0);}
        $queued=0;$skipped=0;foreach($rows as $r){try{$ok=EmailQueueService::queue('marketing',$r['email'],'Asset Moth is ready for you','launch_invite',['name'=>$r['name'],'cta_url'=>H::baseUrl().'/register'],"waitlist:{$r['id']}:launch-invite",['waitlist_entry_id'=>$r['id']]);$ok?$queued++:$skipped++;}catch(\Throwable $e){$skipped++;NotificationService::reportFailure('admin_waitlist_invite',$e);}}
        H::flash('success',"Invitations queued: $queued; skipped: $skipped; already invited: $already.");$this->back($f);
    }
    private function back(array $f):never{H::redirect('/admin/waitlist'.(($q=self::query($f))?'?'.$q:''));}
}
