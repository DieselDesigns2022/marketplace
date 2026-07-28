<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\EmailCampaignService;
use App\Services\EmailQueueService;

final class AdminEmailCampaignController
{
    private function admin():void{H::requireRole('admin');}
    public function index():void{$this->admin();H::view('admin/email_campaigns/index',['campaigns'=>DB::rows('select c.*,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id) total,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id and r.status="sent") sent,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id and r.status="failed") failed from email_campaigns c order by c.created_at desc')]);}
    public function create():void
    {
        $this->admin();$errors=[];$campaign=$_POST;$preview=false;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=(string)($_POST['action']??'');
            if(!in_array($action,['preview','save','queue'],true))$errors[]='Choose Preview, Save draft, or Save and queue.';
            $errors=array_merge($errors,EmailCampaignService::validate($_POST));
            if($action==='preview'&&!$errors)$preview=true;
            elseif(in_array($action,['save','queue'],true)&&!$errors){$id=EmailCampaignService::create($_POST,(int)H::user()['id']);if($action==='queue'&&EmailCampaignService::queue($id)===0)H::flash('warning','No eligible consented recipients were found; the campaign completed without deliveries.');H::redirect('/admin/email-campaigns/'.$id);}
        }
        H::view('admin/email_campaigns/form',compact('errors','campaign','preview'));
    }
    public function show($id):void
    {
        $this->admin();if(!ctype_digit((string)$id))H::abort(404);$campaign=DB::row('select * from email_campaigns where id=?',[(int)$id])??H::abort(404);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=(string)($_POST['action']??'');
            if(!in_array($action,['queue','cancel','test'],true)){H::flash('warning','Unsupported campaign action. No changes were made.');H::redirect('/admin/email-campaigns/'.$id);}
            if($action==='queue'&&EmailCampaignService::queue((int)$id)===0)H::flash('warning','No eligible consented recipients were found; the campaign completed without deliveries.');
            elseif($action==='cancel')EmailCampaignService::cancel((int)$id);
            elseif($action==='test')$this->queueTest((int)$id,$campaign);
            H::redirect('/admin/email-campaigns/'.$id);
        }
        $recipients=DB::rows('select * from email_campaign_recipients where campaign_id=? order by id desc limit 100',[(int)$id]);$counts=DB::row('select count(*) total,sum(status="queued") queued,sum(status="sent") sent,sum(status="failed") failed,sum(status="suppressed") suppressed,sum(status="cancelled") cancelled,sum(status in ("pending","queued")) remaining from email_campaign_recipients where campaign_id=?',[(int)$id]);H::view('admin/email_campaigns/show',compact('campaign','recipients','counts'));
    }
    private function queueTest(int $id,array $campaign):void
    {
        $email=strtolower(trim($_POST['test_email']??''));$admin=filter_var($email,FILTER_VALIDATE_EMAIL)?DB::row('select id,email from users where role="admin" and status="active" and lower(email)=?',[$email]):null;
        if(!$admin){H::flash('warning','Test email must belong to an active administrator.');return;}
        $pref=DB::row('select user_id from email_preferences where user_id=?',[$admin['id']]);if(!$pref)DB::exec('insert into email_preferences(user_id,marketing_opt_in,unsubscribe_nonce) values (?,0,?)',[$admin['id'],bin2hex(random_bytes(32))]);
        $ok=EmailQueueService::queue('marketing',$email,'[TEST — NOT A LIVE CAMPAIGN] '.$campaign['subject'],'campaign',['name'=>'Admin','body'=>$campaign['body'],'cta_label'=>$campaign['cta_label'],'cta_url'=>$campaign['cta_url'],'user_id'=>(int)$admin['id']],"campaign:$id:test:".hash('sha256',$email.':'.microtime(true)),['admin_test'=>true]);
        H::flash($ok?'success':'warning',$ok?'Test message queued only for the selected administrator.':'The test message could not be queued.');
    }
}
