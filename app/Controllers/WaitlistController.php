<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\EmailQueueService;
use App\Services\NotificationService;
use App\Services\UnsubscribeService;

final class WaitlistController
{
    public const INTERESTS=['seller','buyer','tester'];
    public const SOURCES=['direct','homepage','seller','social','referral','campaign'];

    public static function normalizeFields(array $input): array
    {
        $raw=$input['interest_type']??[];
        if(!is_array($raw))$raw=[$raw];
        $interests=[];
        foreach($raw as $interest){
            $interest=trim((string)$interest);
            if($interest!==''&&!in_array($interest,$interests,true))$interests[]=$interest;
        }
        return [
            'name'=>trim((string)($input['name']??'')),
            'email'=>strtolower(trim((string)($input['email']??''))),
            'interest_type'=>$interests,
            'business_name'=>trim((string)($input['business_name']??'')),
            'source'=>trim((string)($input['source']??'direct')),
        ];
    }

    public static function validateFields(array $values): array
    {
        $errors=[];
        if($values['name']===''||mb_strlen($values['name'])>120)$errors[]='Enter your name (120 characters maximum).';
        if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||mb_strlen($values['email'])>190)$errors[]='Enter a valid email address.';
        if(!$values['interest_type']||array_diff($values['interest_type'],self::INTERESTS))$errors[]='Choose at least one valid interest.';
        if(mb_strlen($values['business_name'])>190)$errors[]='Business name is too long.';
        if(!in_array($values['source'],self::SOURCES,true))$errors[]='Invalid signup source.';
        return $errors;
    }

    public static function interestValue(array $selected): string
    {
        $ordered=[];
        foreach(self::INTERESTS as $interest)if(in_array($interest,$selected,true))$ordered[]=$interest;
        return implode(',',$ordered);
    }

    public static function interestLabel(string $value): string
    {
        $labels=[];
        foreach(explode(',',$value) as $interest){
            $interest=trim($interest);
            if(in_array($interest,self::INTERESTS,true))$labels[]=ucfirst($interest);
        }
        return $labels?implode(', ',$labels):'Community member';
    }

    public function waitlist(): void
    {
        $values=self::normalizeFields(['source'=>in_array($_GET['source']??'',self::SOURCES,true)?$_GET['source']:'direct']);
        $errors=[];$success=false;$already=false;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $values=self::normalizeFields($_POST);
            if(($_POST['website']??'')!==''){$success=true;}
            else {
                $errors=self::validateFields($values);
                if(!$errors){
                    $result=$this->saveSignup($values);
                    if($result!==false){$success=true;$already=$result==='existing';}
                    else $errors[]='We could not save your waitlist request right now. Please try again shortly.';
                }
            }
        }
        H::minimalView('public/waitlist',compact('errors','success','already','values'));
    }

    private function saveSignup(array $values): string|false
    {
        $interestType=self::interestValue($values['interest_type']);
        $existing=DB::row('select * from waitlist_entries where email=?',[$values['email']]);

        if($existing&&$existing['status']==='suppressed')return 'suppressed';

        if($existing&&in_array($existing['status'],['subscribed','invited'],true)){
            return 'existing';
        }

        try {
            DB::begin();
            $nonce=bin2hex(random_bytes(32));
            $event=$existing?'resubscription':'signup';

            if($existing){
                DB::exec(
                    'update waitlist_entries set name=?,interest_type=?,business_name=?,source=?,status="subscribed",consent_at=now(),unsubscribed_at=null,unsubscribe_nonce=?,confirmation_sent_at=null where id=?',
                    [$values['name'],$interestType,$values['business_name']?:null,$values['source'],$nonce,$existing['id']]
                );
                $id=(int)$existing['id'];
            } else {
                DB::exec(
                    'insert into waitlist_entries (name,email,interest_type,business_name,source,status,consent_at,unsubscribe_nonce) values (?,?,?,?,?,"subscribed",now(),?)',
                    [$values['name'],$values['email'],$interestType,$values['business_name']?:null,$values['source'],$nonce]
                );
                $id=(int)DB::id();
            }

            $eventKey="waitlist:$id:$event:$nonce";
            $url=UnsubscribeService::url('w',$id,$nonce);

            if(!EmailQueueService::queue(
                'transactional',
                $values['email'],
                'Welcome to the Asset Moth waitlist',
                'waitlist_confirmation',
                [
                    'name'=>$values['name'],
                    'interest_type'=>$interestType,
                    'unsubscribe_url'=>$url
                ],
                $eventKey.':confirmation',
                ['waitlist_entry_id'=>$id]
            ))throw new \RuntimeException('Confirmation queue rejected the request.');

            DB::commit();
        } catch(\Throwable $e) {
            if(DB::pdo()->inTransaction())DB::rollBack();
            NotificationService::reportFailure('waitlist_signup_transaction',$e);
            return false;
        }

        try{
            NotificationService::admins(
                'waitlist_'.$event,
                'Waitlist '.($event==='signup'?'signup':'resubscription'),
                'A visitor updated launch waitlist consent.',
                $eventKey.':admin',
                '/admin/waitlist'
            );
        }catch(\Throwable $e){
            NotificationService::reportFailure('waitlist_admin_notification',$e);
        }

        return $event;
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
            H::verifyCsrf();
            if($identity['kind']==='w')DB::exec('update waitlist_entries set status=if(status="suppressed",status,"unsubscribed"),unsubscribed_at=if(status="suppressed",unsubscribed_at,coalesce(unsubscribed_at,now())) where id=? and unsubscribe_nonce=?',[$identity['id'],$identity['nonce']]);
            else {
                $column=['uw'=>'weekly_emails','um'=>'monthly_emails','uf'=>'favorite_shop_emails'][$identity['kind']]??null;
                if($column)DB::exec("update email_preferences set preference_changed_at=if($column=1,now(),preference_changed_at),$column=0,marketing_opt_in=(weekly_emails or monthly_emails or favorite_shop_emails),marketing_opted_out_at=if((weekly_emails or monthly_emails or favorite_shop_emails)=0,coalesce(marketing_opted_out_at,now()),null) where user_id=? and unsubscribe_nonce=?",[$identity['id'],$identity['nonce']]);
                else DB::exec('update email_preferences set preference_changed_at=if(weekly_emails=1 or monthly_emails=1 or favorite_shop_emails=1,now(),preference_changed_at),marketing_opt_in=0,weekly_emails=0,monthly_emails=0,favorite_shop_emails=0,marketing_opted_out_at=coalesce(marketing_opted_out_at,now()) where user_id=? and unsubscribe_nonce=?',[$identity['id'],$identity['nonce']]);
            }
            $complete=true;
        }
        $category=$identity?(['uw'=>'weekly emails','um'=>'monthly emails','uf'=>'favorite-shop emails','u'=>'optional marketing emails','w'=>'waitlist emails'][$identity['kind']]??'optional emails'):'';
        H::view('public/email_unsubscribe',['token'=>$valid?$token:'','valid'=>$valid,'complete'=>$complete,'category'=>$category]);
    }
}
