<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\AdminPermissionService;
use App\Services\NotificationService;

final class FeedbackController
{
    private static function safeContext(?string $value): ?string
    {
        $value=trim((string)$value);

        if(
            $value==='' ||
            $value[0]!=='/' ||
            str_starts_with($value,'//') ||
            str_contains($value,'\\') ||
            preg_match('/[\x00-\x1F\x7F]/',$value)
        ){
            return null;
        }

        return mb_substr($value,0,500);
    }

    private static function referringContext(): ?string
    {
        $referer=trim((string)($_SERVER['HTTP_REFERER']??''));

        if($referer===''){
            return null;
        }

        $host=parse_url($referer,PHP_URL_HOST);
        $current=preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''));

        if(
            !is_string($host) ||
            strcasecmp($host,$current)!==0
        ){
            return null;
        }

        $path=(string)(parse_url($referer,PHP_URL_PATH)??'/');
        $query=parse_url($referer,PHP_URL_QUERY);

        return self::safeContext(
            $path.($query!==null&&$query!==''?'?'.$query:'')
        );
    }

    public function form(): void
    {
        H::requireLogin();

        $user=H::user();
        $errors=[];
        $type=(string)($_POST['submission_type']??'feedback');
        $message=trim((string)($_POST['message']??''));

        $context=self::safeContext(
            $_POST['context_url'] ??
            $_GET['from'] ??
            self::referringContext()
        );

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::verifyCsrf();

            if(!in_array($type,['feedback','suggestion'],true)){
                $errors[]='Choose Feedback or Suggestion.';
            }

            if(mb_strlen($message)<5){
                $errors[]='Please enter at least 5 characters.';
            }

            if(mb_strlen($message)>5000){
                $errors[]='Please keep your message under 5,000 characters.';
            }

            if(!$errors){
                DB::exec(
                    'insert into feedback_submissions
                    (user_id,user_name_snapshot,user_email_snapshot,submission_type,message,context_url)
                    values (?,?,?,?,?,?)',
                    [
                        (int)$user['id'],
                        (string)$user['name'],
                        (string)$user['email'],
                        $type,
                        $message,
                        $context
                    ]
                );

                $id=(int)DB::id();
                $permissions=new AdminPermissionService();

                foreach(DB::rows(
                    'select id from users where role="admin" and status="active"'
                ) as $admin){
                    $adminId=(int)$admin['id'];

                    if(!$permissions->can($adminId,'feedback.view')){
                        continue;
                    }

                    NotificationService::create(
                        $adminId,
                        'user_feedback',
                        'admin',
                        'New '.ucfirst($type).' received',
                        (string)$user['name'].' submitted '.($type==='suggestion'?'a suggestion.':'feedback.'),
                        'feedback:'.$id.':admin:'.$adminId,
                        '/admin/feedback/'.$id
                    );
                }

                H::flash(
                    'success',
                    $type==='suggestion'
                        ? 'Thanks! Your suggestion was received.'
                        : 'Thanks! Your feedback was received.'
                );

                H::redirect('/feedback');
            }
        }

        H::view(
            'feedback/form',
            compact('errors','type','message','context')
        );
    }

    public function adminIndex(): void
    {
        H::requireAdminPermission('feedback.view');

        $status=(string)($_GET['status']??'open');
        $type=(string)($_GET['type']??'all');
        $q=trim((string)($_GET['q']??''));

        if(!in_array($status,['open','resolved','all'],true)){
            $status='open';
        }

        if(!in_array($type,['feedback','suggestion','all'],true)){
            $type='all';
        }

        if(mb_strlen($q)>120){
            $q=mb_substr($q,0,120);
        }

        $where=['1=1'];
        $params=[];

        if($status!=='all'){
            $where[]='f.status=?';
            $params[]=$status;
        }

        if($type!=='all'){
            $where[]='f.submission_type=?';
            $params[]=$type;
        }

        if($q!==''){
            $like='%'.$q.'%';
            $where[]='(
                f.message like ?
                or f.user_name_snapshot like ?
                or f.user_email_snapshot like ?
                or coalesce(f.context_url,"") like ?
            )';

            array_push($params,$like,$like,$like,$like);
        }

        $submissions=DB::rows(
            'select f.*
             from feedback_submissions f
             where '.implode(' and ',$where).'
             order by
                field(f.status,"open","resolved"),
                f.created_at desc,
                f.id desc',
            $params
        );

        H::view(
            'admin/feedback',
            compact('submissions','status','type','q')
        );
    }

    public function adminShow($id): void
    {
        H::requireAdminPermission(
            $_SERVER['REQUEST_METHOD']==='POST'
                ? 'feedback.manage'
                : 'feedback.view'
        );

        $submission=DB::row(
            'select
                f.*,
                resolver.name resolver_name
             from feedback_submissions f
             left join users resolver
               on resolver.id=f.resolved_by
             where f.id=?',
            [(int)$id]
        )??H::abort(404);

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::verifyCsrf();

            $status=(string)($_POST['status']??'');

            if(!in_array($status,['open','resolved'],true)){
                H::abort(422);
            }

            if($status==='resolved'){
                DB::exec(
                    'update feedback_submissions
                     set status="resolved",
                         resolved_by=?,
                         resolved_at=now()
                     where id=?',
                    [(int)H::user()['id'],(int)$id]
                );
            }else{
                DB::exec(
                    'update feedback_submissions
                     set status="open",
                         resolved_by=null,
                         resolved_at=null
                     where id=?',
                    [(int)$id]
                );
            }

            H::flash('success','Feedback status updated.');
            H::redirect('/admin/feedback/'.(int)$id);
        }

        H::view('admin/feedback_detail',compact('submission'));
    }
}
