<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\EmailQueueService;
use App\Services\NotificationService;
use App\Services\UnsubscribeService;

final class WaitlistController
{
    public const INTERESTS=['seller','buyer','both','tester'];
    public const SOURCES=['direct','homepage','seller','social','referral','campaign'];

    public static function normalizeFields(array $input): array
    {
        return [
            'name'=>trim((string)($input['name']??'')),
            'email'=>strtolower(trim((string)($input['email']??''))),
            'interest_type'=>trim((string)($input['interest_type']??'both')),
            'business_name'=>trim((string)($input['business_name']??'')),
            'source'=>trim((string)($input['source']??'direct')),
        ];
    }

    public static function validateFields(array $values): array
    {
        $errors=[];
        if($values['name']===''||mb_strlen($values['name'])>120)$errors[]='Enter your name (120 characters maximum).';
        if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||mb_strlen($values['email'])>190)$errors[]='Enter a valid email address.';
        if(!in_array($values['interest_type'],self::INTERESTS,true))$errors[]='Select a valid interest.';
        if(mb_strlen($values['business_name'])>190)$errors[]='Business name is too long.';
        if(!in_array($values['source'],self::SOURCES,true))$errors[]='Invalid signup source.';
        return $errors;
    }

    public function waitlist(): void
    {
        $values=self::normalizeFields(['source'=>in_array($_GET['source']??'',self::SOURCES,true)?$_GET['source']:'direct']);
        $errors=[];$success=false;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $values=self::normalizeFields($_POST);
            if(($_POST['website']??'')!==''){$success=true;}
            else {
                $errors=self::validateFields($values);
                if(!$errors){
                    if($this->saveSignup($values))$success=true;
                    else $errors[]='We could not save your waitlist request right now. Please try again shortly.';
                }
            }
        }
        H::view('public/waitlist',compact('errors','success','values'));
    }

    private function saveSignup(array $values): bool
    {
        $existing=DB::row('select * from waitlist_entries where email=?',[$values['email']]);
        if($existing&&$existing['status']==='suppressed')return true;
        if($existing&&in_array($existing['status'],['subscribed','invited'],true)){
            try { DB::exec('update waitlist_entries set name=?,interest_type=?,business_name=?,source=? where id=?',[$values['name'],$values['interest_type'],$values['business_name']?:null,$values['source'],$existing['id']]);return true; }
            catch(\Throwable $e){NotificationService::reportFailure('waitlist_repeat_update',$e);return false;}
        }
        try {
            DB::begin();$nonce=bin2hex(random_bytes(32));$event=$existing?'resubscription':'signup';
            if($existing){DB::exec('update waitlist_entries set name=?,interest_type=?,business_name=?,source=?,status="subscribed",consent_at=now(),unsubscribed_at=null,unsubscribe_nonce=?,confirmation_sent_at=null where id=?',[$values['name'],$values['interest_type'],$values['business_name']?:null,$values['source'],$nonce,$existing['id']]);$id=(int)$existing['id'];}
            else{DB::exec('insert into waitlist_entries (name,email,interest_type,business_name,source,status,consent_at,unsubscribe_nonce) values (?,?,?,?,?,"subscribed",now(),?)',[$values['name'],$values['email'],$values['interest_type'],$values['business_name']?:null,$values['source'],$nonce]);$id=(int)DB::id();}
            $eventKey="waitlist:$id:$event:$nonce";
            $url=UnsubscribeService::url('w',$id,$nonce);
            if(!EmailQueueService::queue('transactional',$values['email'],'Welcome to the Asset Moth waitlist','waitlist_confirmation',['name'=>$values['name'],'interest_type'=>$values['interest_type'],'unsubscribe_url'=>$url],$eventKey.':confirmation',['waitlist_entry_id'=>$id]))throw new \RuntimeException('Confirmation queue rejected the request.');
            DB::commit();
        } catch(\Throwable $e) { if(DB::pdo()->inTransaction())DB::rollBack();NotificationService::reportFailure('waitlist_signup_transaction',$e);return false; }
        try{NotificationService::admins('waitlist_'.$event,'Waitlist '.($event==='signup'?'signup':'resubscription'),'A visitor updated launch waitlist consent.',$eventKey.':admin','/admin/waitlist');}catch(\Throwable $e){NotificationService::reportFailure('waitlist_admin_notification',$e);}
        return true;
    }

    public function unsubscribe(): void
    {
        $token=(string)($_GET['token']??$_POST['token']??'');
        $identity=UnsubscribeService::verify($token);$valid=false;$complete=false;
        if($identity){
            $table=$identity['kind']==='w'?'waitlist_entries':'email_preferences';
            $column=$identity['kind']==='w'?'id':'user_id';
            $valid=(bool)DB::row("select $column from $table where $column=? and unsubscribe_nonce=?",[$identity['id'],$identity['nonce']]);
        }
        if($_SERVER['REQUEST_METHOD']==='POST'&&$valid){
            if($identity['kind']==='w')DB::exec('update waitlist_entries set status=if(status="suppressed",status,"unsubscribed"),unsubscribed_at=if(status="suppressed",unsubscribed_at,coalesce(unsubscribed_at,now())) where id=? and unsubscribe_nonce=?',[$identity['id'],$identity['nonce']]);
            else DB::exec('update email_preferences set marketing_opt_in=0,marketing_opted_out_at=coalesce(marketing_opted_out_at,now()) where user_id=? and unsubscribe_nonce=?',[$identity['id'],$identity['nonce']]);
            $complete=true;
        }
        H::view('public/email_unsubscribe',['token'=>$valid?$token:'','valid'=>$valid,'complete'=>$complete]);
    }
}
