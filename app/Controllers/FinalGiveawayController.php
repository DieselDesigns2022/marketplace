<?php
namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\FinalGiveawayService as Wheel;
use DomainException;
use Throwable;
final class FinalGiveawayController
{
    public function remove():void
    {
        H::requireAdminPermission('prizes.view');H::requireAdminPermission('prizes.manage');
        if(empty($_SESSION['_csrf'])||empty($_POST['_csrf']))H::abort(403);H::verifyCsrf();
        header('Cache-Control: no-store');header('Content-Type: application/json');
        try{$s=new Wheel();$s->removeEntry((int)H::user()['id'],(int)($_POST['draw_id']??0)); echo json_encode(['ok'=>true]);}
        catch(Throwable $e){http_response_code(422);echo json_encode(['error'=>$e instanceof DomainException?$e->getMessage():'Entry removal failed. Please retry.']);}
    }

    public function admin():void
    {
        H::requireAdminPermission('prizes.view');header('Cache-Control: private, no-store');
        $s=new Wheel();
        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::requireAdminPermission('prizes.manage');
            if(empty($_SESSION['_csrf'])||empty($_POST['_csrf']))H::abort(403);
            H::verifyCsrf();
            try{
                $actor=(int)H::user()['id'];$action=(string)($_POST['action']??'');
                if($action==='live'||$action==='done')$s->setLive($actor,$action==='live');
                elseif($action==='draw')$s->draw($actor,(string)($_POST['pool']??''),(int)($_POST['prize_id']??0),(string)($_POST['request_key']??''));
                else throw new DomainException('Choose a valid action.');
                H::flash('success',$action==='draw'?'Winner selected. Prize message saved and email queued.':($action==='done'?'Giveaway done. The viewer and homepage live link are now off.':'The drawing is now live on the homepage.'));
            }catch(Throwable $e){H::flash('error',$e instanceof DomainException?$e->getMessage():'Could not complete this action. No new prize was awarded.');}
            H::redirect('/admin/final-giveaway');
        }
        if(isset($_GET['audit'])){
            $d=DB::row('select id,pool_snapshot,pool_hash,pool_count,selected_index,winner_entry_key,drawn_by,created_at from final_giveaway_draws where id=?',[(int)$_GET['audit']]);
            if(!$d)H::abort(404);
            header('Content-Type: application/json');header('Content-Disposition: attachment; filename="giveaway-draw-'.(int)$d['id'].'.json"');echo json_encode($d,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);return;
        }
        $poolCounts=[];$poolError=null;
        try{foreach(Wheel::POOLS as $key=>$label)$poolCounts[$key]=count($s->pool($key,false));}catch(Throwable $e){$poolError=$e instanceof DomainException?$e->getMessage():'Entry counts could not be loaded.';}
        H::view('admin/final_giveaway',['live'=>$s->live(),'prizes'=>$s->prizes(),'draws'=>$s->history(),'poolCounts'=>$poolCounts,'poolError'=>$poolError,'canManage'=>H::canAdmin('prizes.manage')]);
    }
    public function operator():void
    {
        H::requireAdminPermission('prizes.view');H::requireAdminPermission('prizes.manage');
        header('Cache-Control: private, no-store');H::view('public/final_giveaway',['wheelOperator'=>true]);
    }
    public function show():void
    {
        header('Cache-Control: no-store');H::view('public/final_giveaway',[]);
    }
    public function state():void
    {
        header('Content-Type: application/json');header('Cache-Control: no-store');
        echo json_encode((new Wheel())->publicState(),JSON_THROW_ON_ERROR);
    }
    public function prize(string $id):void
    {
        H::requireLogin();header('Cache-Control: private, no-store');
        $s=new Wheel();$prize=$s->prizeForUser((int)$id,(int)H::user()['id']);if(!$prize)H::abort(404);
        if(isset($_GET['download'])){
            if(!$prize['delivery_file_path'])H::abort(404);
            try{$file=$s->safeFile($prize['delivery_file_path']);}catch(DomainException $e){H::abort(404);}
            header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($file));
            header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($prize['delivery_file_name']?:'creative-moth-prize'));
            header('X-Content-Type-Options: nosniff');readfile($file);return;
        }
        H::view('buyer/final_giveaway_prize',['prize'=>$prize]);
    }
}
