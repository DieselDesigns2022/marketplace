<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\EmailCampaignService;
use App\Services\EmailQueueService;

final class AdminEmailCampaignController
{
    private function admin(bool $manage=false):void{if($_SERVER['REQUEST_METHOD']==='POST')H::verifyCsrf();H::requireAdminPermission($manage?'email_campaigns.manage':'email_campaigns.view');}
    public function index():void{$this->admin();H::view('admin/email_campaigns/index',['campaigns'=>DB::rows('select c.*,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id) total,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id and r.status="sent") sent,(select count(*) from email_campaign_recipients r where r.campaign_id=c.id and r.status="failed") failed from email_campaigns c order by c.created_at desc')]);}
    public function create():void
    {
        $this->admin(true);$errors=[];$campaign=$_POST;$preview=false;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=(string)($_POST['action']??'');
            if(!in_array($action,['preview','save','queue'],true))$errors[]='Choose Preview, Save draft, or Save and queue.';
            $errors=array_merge($errors,EmailCampaignService::validate($_POST));
            if($action==='preview'&&!$errors)$preview=true;
            elseif(in_array($action,['save','queue'],true)&&!$errors){$id=EmailCampaignService::create($_POST,(int)H::user()['id']);if($action==='queue'&&EmailCampaignService::queue($id)===0)H::flash('warning','No eligible consented recipients were found; the campaign completed without deliveries.');H::redirect('/admin/email-campaigns/'.$id);}
        }
        H::view('admin/email_campaigns/form',compact('errors','campaign','preview'));
    }
    public function waitlistCompose(): void
    {
        $this->admin(true);

        $errors=[];
        $campaign=$_POST;
        $preview=false;

        $campaign['audience']=
            $campaign['audience'] ?? 'waitlist_all';

        $campaign['body_format']='rich_html';
        $campaign['cta_label']='';
        $campaign['cta_url']='';

        $audienceCounts=
            EmailCampaignService::waitlistAudienceCounts();

        $safeBody='';

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=(string)($_POST['action']??'');

            if(
                !in_array(
                    $action,
                    ['preview','test','send'],
                    true
                )
            ){
                $errors[]='Choose Preview, Send Test Email, or Send Email.';
            }

            if(
                !in_array(
                    (string)$campaign['audience'],
                    EmailCampaignService::WAITLIST_COMPOSE_AUDIENCES,
                    true
                )
            ){
                $errors[]='Choose a valid waitlist audience.';
            }

            $errors=array_merge(
                $errors,
                EmailCampaignService::validate(
                    $campaign
                )
            );

            try {
                $safeBody=
                    EmailCampaignService::sanitizeRichBody(
                        (string)($campaign['body']??'')
                    );
            } catch (\Throwable $error) {
                if(!$errors){
                    $errors[]=
                        'The rich-text email could not be safely processed.';
                }
            }

            $recipientCount=
                EmailCampaignService::waitlistAudienceCount(
                    (string)$campaign['audience']
                );

            if($action==='preview'&&!$errors){
                $preview=true;
            }

            if($action==='test'&&!$errors){
                $email=strtolower(
                    trim(
                        (string)($_POST['test_email']??'')
                    )
                );

                $admin=
                    filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
                    ? DB::row(
                        'select id,email,name
                         from users
                         where role="admin"
                           and status="active"
                           and lower(email)=?',
                        [$email]
                    )
                    : null;

                if(!$admin){
                    $errors[]=
                        'Test email must belong to an active administrator.';
                } else {
                    $pref=DB::row(
                        'select user_id
                         from email_preferences
                         where user_id=?',
                        [(int)$admin['id']]
                    );

                    if(!$pref){
                        DB::exec(
                            'insert into email_preferences(
                                user_id,
                                marketing_opt_in,
                                unsubscribe_nonce
                             ) values(?,0,?)',
                            [
                                (int)$admin['id'],
                                bin2hex(random_bytes(32))
                            ]
                        );
                    }

                    $ok=EmailQueueService::queue(
                        'marketing',
                        $email,
                        '[TEST — NOT A LIVE SEND] '.
                            trim(
                                (string)$campaign['subject']
                            ),
                        'campaign',
                        [
                            'name'=>
                                $admin['name'] ?: 'Admin',

                            'body'=>$safeBody,

                            'body_format'=>
                                'rich_html',

                            'cta_label'=>null,
                            'cta_url'=>null,

                            'user_id'=>
                                (int)$admin['id']
                        ],
                        'waitlist-email-test:'.
                            hash(
                                'sha256',
                                $email.':'.
                                microtime(true)
                            ),
                        [
                            'admin_test'=>true
                        ]
                    );

                    H::flash(
                        $ok?'success':'warning',
                        $ok
                            ? 'Test email queued only for '.$email.'.'
                            : 'The test email could not be queued.'
                    );

                    $preview=true;
                }
            }

            if($action==='send'&&!$errors){
                $confirmed=
                    ($_POST['confirm_send']??'')==='1';

                $confirmedCount=
                    filter_var(
                        $_POST['confirmed_count']??null,
                        FILTER_VALIDATE_INT,
                        [
                            'options'=>[
                                'min_range'=>0
                            ]
                        ]
                    );

                if(!$confirmed){
                    $errors[]=
                        'Confirm the recipient count before sending.';
                }

                if(
                    $confirmedCount===false ||
                    (int)$confirmedCount !==
                    $recipientCount
                ){
                    $errors[]=
                        'The eligible recipient count changed. Review the current count and confirm again before sending.';
                }

                if($recipientCount<1){
                    $errors[]=
                        'There are no eligible recipients in this audience.';
                }

                if(!$errors){
                    $campaign['body']=$safeBody;

                    $id=EmailCampaignService::create(
                        $campaign,
                        (int)H::user()['id']
                    );

                    $queued=
                        EmailCampaignService::queue($id);

                    if($queued===0){
                        H::flash(
                            'warning',
                            'No eligible consented recipients were available when the campaign was queued.'
                        );
                    } else {
                        H::flash(
                            'success',
                            'Waitlist email queued for '.
                            number_format($queued).
                            ' recipient(s).'
                        );
                    }

                    H::redirect(
                        '/admin/email-campaigns/'.$id
                    );
                }
            }
        }

        if($safeBody===''){
            try {
                $safeBody=
                    EmailCampaignService::sanitizeRichBody(
                        (string)($campaign['body']??'')
                    );
            } catch (\Throwable $ignored) {
                $safeBody='';
            }
        }

        $recentCampaigns=DB::rows(
            'select
                c.*,
                (
                    select count(*)
                    from email_campaign_recipients r
                    where r.campaign_id=c.id
                ) total,
                (
                    select count(*)
                    from email_campaign_recipients r
                    where r.campaign_id=c.id
                      and r.status="sent"
                ) sent
             from email_campaigns c
             where c.audience like "waitlist_%"
             order by c.created_at desc
             limit 20'
        );

        H::view(
            'admin/waitlist/email',
            compact(
                'errors',
                'campaign',
                'preview',
                'safeBody',
                'audienceCounts',
                'recentCampaigns'
            )
        );
    }

    public function show($id):void
    {
        $this->admin($_SERVER['REQUEST_METHOD']==='POST');if(!ctype_digit((string)$id))H::abort(404);$campaign=DB::row('select * from email_campaigns where id=?',[(int)$id])??H::abort(404);
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
        $ok=EmailQueueService::queue('marketing',$email,'[TEST — NOT A LIVE CAMPAIGN] '.$campaign['subject'],'campaign',['name'=>'Admin','body'=>$campaign['body'],'body_format'=>$campaign['body_format']??'plain','cta_label'=>$campaign['cta_label'],'cta_url'=>$campaign['cta_url'],'user_id'=>(int)$admin['id']],"campaign:$id:test:".hash('sha256',$email.':'.microtime(true)),['admin_test'=>true]);
        H::flash($ok?'success':'warning',$ok?'Test message queued only for the selected administrator.':'The test message could not be queued.');
    }
}
