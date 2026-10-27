<?php
namespace App\Controllers;
use App\Core\Helpers as H;
use App\Core\Database as DB;
use App\Services\BuyerReferralGiveawayService as Giveaway;
use DomainException;
use InvalidArgumentException;
use Throwable;
final class BuyerReferralGiveawayController
{
    public function index():void
    {
        H::requireAdminPermission('referrals.view');
        header('Cache-Control: private, no-store, max-age=0');
        $service=new Giveaway();$actor=(int)H::user()['id'];$error='';
        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::requireAdminPermission('prizes.manage');
            if(empty($_SESSION['_csrf'])||!is_string($_POST['_csrf']??null))H::abort(419);
            H::verifyCsrf();
            try {
                $action=(string)($_POST['action']??'');$id=(int)($_POST['id']??0);
                switch($action){
                    case 'enable':$service->enable($actor,true);break;
                    case 'pause':$service->enable($actor,false);break;
                    case 'refresh':$service->refresh();break;
                    case 'exclude':$service->exclude($actor,$id,(string)($_POST['reason']??''));break;
                    case 'restore':$service->exclude($actor,$id,(string)($_POST['reason']??''),true);break;
                    case 'draw':$service->draw($actor);break;
                    case 'disqualify':$service->disqualify($actor,$id,(string)($_POST['reason']??''));break;
                    case 'award':$service->award($actor,$id,(int)($_POST['donation_id']??0));break;
                    default:throw new DomainException('Choose a valid action.');
                }
                H::flash('success','Referral Giveaway updated.');H::redirect('/admin/referral-giveaway');return;
            }catch(DomainException|InvalidArgumentException $e){$error=$e->getMessage();}
            catch(Throwable $e){error_log('Referral Giveaway: '.$e->getMessage());$error='The update could not be saved. No partial action was kept.';}
        }
        if(($_GET['export']??'')==='draw'){
            $saved=DB::row('select id,pool_snapshot,pool_hash,selected_index,drawn_at from buyer_referral_giveaway_draws where id=?',[(int)($_GET['id']??0)]);
            if(!$saved)H::abort(404);
            header('Content-Type: application/json');header('Content-Disposition: attachment; filename="referral-giveaway-draw-'.(int)$saved['id'].'.json"');
            echo json_encode(['draw_id'=>(int)$saved['id'],'pool_hash'=>$saved['pool_hash'],'selected_index'=>(int)$saved['selected_index'],'drawn_at'=>$saved['drawn_at'],'pool'=>json_decode($saved['pool_snapshot'],true)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);return;
        }
        $service->refresh();$entries=$service->entries();
        if(($_GET['export']??'')==='entries'){
            header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="buyer-referral-giveaway.csv"');
            $out=fopen('php://output','w');fputcsv($out,['Entry','Referral','Referrer user','Referred user','Status','Paid cents','Order evidence','Reason'],',','"','');
            foreach($entries as $e){$row=[$e['id'],$e['referral_id'],$e['referrer_user_id'],$e['referred_user_id'],$e['status'],$e['qualifying_cents'],$e['qualifying_orders'],$e['reason']];foreach($row as &$v){$v=(string)$v;if(preg_match('/^[\s]*[=+@-]/',$v))$v="'".$v;}unset($v);fputcsv($out,$row,',','"','');}fclose($out);return;
        }
        $filter=(string)($_GET['status']??'');if(!in_array($filter,['pending','eligible','ineligible','review','excluded'],true))$filter='';
        $counts=array_count_values(array_column($entries,'status'));$total=count($entries);
        if($filter!=='')$entries=array_values(array_filter($entries,static fn($e)=>$e['status']===$filter));
        $pages=max(1,(int)ceil(count($entries)/30));$page=min($pages,max(1,(int)($_GET['page']??1)));
        $entries=array_slice($entries,($page-1)*30,30);
        H::view('admin/referral_giveaway',[
            'settings'=>$service->settings(),'entries'=>$entries,'counts'=>$counts,'total'=>$total,'filter'=>$filter,'page'=>$page,'pages'=>$pages,'error'=>$error,
            'canManage'=>H::canAdmin('prizes.manage'),
            'draws'=>DB::rows('select d.id,d.winner_entry_id,d.winner_user_id,d.pool_hash,d.pool_count,d.selected_index,d.status,d.reason,d.assignment_id,d.drawn_at,u.name winner_name from buyer_referral_giveaway_draws d left join users u on u.id=d.winner_user_id order by d.id desc'),
            'prizes'=>DB::rows('select id,title,quantity_remaining from prize_donations where status="available" and quantity_remaining>0 and find_in_set("giveaway",usage_designations)>0 order by title,id'),
            'audit'=>DB::rows('select a.action,a.subject_id,a.actor_id,a.details,a.created_at from buyer_referral_giveaway_audits a order by a.id desc limit 40'),
        ]);
    }
}
