<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\AdminPermissionService;
use App\Services\NotificationService;
use App\Services\FeedbackAttachmentService;

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
                try {
                    $id=FeedbackAttachmentService::createSubmission(
                        $user,$type,$message,$context,
                        $_FILES['attachments']??[]
                    );
                }catch(\InvalidArgumentException $e){
                    $errors[]=$e->getMessage();
                }catch(\Throwable $e){
                    error_log('Feedback attachment submission: '.$e->getMessage());
                    $errors[]='Submission could not be saved. Please try again.';
                }
            }

            if(!$errors){
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


    private function sendSupportReply(array $submission,string $body,string $token):bool
    {
        $body=trim($body);
        if($body===''||mb_strlen($body)>10000)
            throw new \InvalidArgumentException('Enter a response of 10,000 characters or fewer.');
        if(!preg_match('/^[a-f0-9]{32}$/D',$token))
            throw new \InvalidArgumentException('Invalid reply request. Refresh the page.');

        $adminId=(int)H::user()['id'];
        $buyerId=(int)($submission['user_id']??0);
        $buyer=$buyerId>0?DB::row('select id,status from users where id=?',[$buyerId]):null;

        if(!$buyer||$buyer['status']!=='active')
            throw new \InvalidArgumentException('The submitter no longer has an active account.');
        if($buyerId===$adminId)
            throw new \InvalidArgumentException('You cannot respond to your own account.');

        DB::begin();
        try{
            $stmt=DB::pdo()->prepare(
                'insert ignore into feedback_reply_requests(reply_token,feedback_submission_id) values (?,?)'
            );
            $stmt->execute([$token,(int)$submission['id']]);
            if($stmt->rowCount()===0){
                DB::commit();
                return false;
            }

            $key='feedback-support:'.(int)$submission['id'];
            DB::exec(
                'insert ignore into message_conversations
                 (buyer_user_id,seller_user_id,designer_id,context_label,context_key,conversation_type,feedback_submission_id)
                 values (?,?,null,?,?,"support",?)',
                [$buyerId,$adminId,'Creative Moth Support - Feedback #'.(int)$submission['id'],$key,(int)$submission['id']]
            );

            $c=DB::row(
                'select id,buyer_user_id,conversation_type from message_conversations
                 where context_key=? for update',
                [$key]
            );
            if(!$c||(int)$c['buyer_user_id']!==$buyerId||$c['conversation_type']!=='support')
                throw new \RuntimeException('Support conversation could not be verified.');

            DB::exec(
                'insert into conversation_messages(conversation_id,sender_user_id,body) values (?,?,?)',
                [(int)$c['id'],$adminId,$body]
            );
            $messageId=(int)DB::id();

            DB::exec(
                'update feedback_reply_requests set message_id=? where reply_token=?',
                [$messageId,$token]
            );
            DB::exec(
                'update message_conversations
                 set last_message_at=now(),buyer_archived_at=null,seller_archived_at=null,
                 seller_last_read_message_id=? where id=?',
                [$messageId,(int)$c['id']]
            );

            \App\Services\NotificationService::create(
                $buyerId,'internal_message','buyer',
                'Creative Moth Support replied',
                'We responded to your feedback #'.(int)$submission['id'].'.',
                'internal-message:'.$messageId.':recipient:'.$buyerId,
                '/buyer/messages/'.(int)$c['id']
            );

            DB::commit();
            return true;
        }catch(\Throwable $e){
            if(DB::pdo()->inTransaction())DB::rollBack();
            throw $e;
        }
    }


    public function downloadReplyAttachment($id,$attachmentId): void
    {
        H::requireAdminPermission('feedback.view');

        $file = DB::row(
            'select a.*
             from message_attachments a
             join conversation_messages m on m.id=a.message_id
             join message_conversations c on c.id=m.conversation_id
             where a.id=?
               and c.feedback_submission_id=?
               and c.conversation_type="support"
             limit 1',
            [(int)$attachmentId,(int)$id]
        );

        if (!$file) H::abort(404);

        $base = realpath(app_path('storage/protected_uploads/messages'));
        $path = realpath(app_path(
            'storage/protected_uploads/messages/'.$file['stored_name']
        ));

        if (!$base || !$path ||
            !str_starts_with($path,$base.DIRECTORY_SEPARATOR) ||
            !is_file($path) || !is_readable($path)) {
            H::abort(404);
        }

        $preview = ($_GET['preview'] ?? '') === '1';
        $allowedImages = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];
        $inline = $preview &&
            in_array($file['mime_type'], $allowedImages, true);

        header('Content-Type: '.
            ($inline ? $file['mime_type'] : 'application/octet-stream'));
        header('Content-Length: '.filesize($path));
        header("Content-Disposition: ".
            ($inline ? 'inline' : 'attachment').
            "; filename*=UTF-8''".
            rawurlencode($file['original_name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function downloadAttachment($id,$attachmentId): void
    {
        H::requireAdminPermission('feedback.view');

        $file=DB::row(
            'select * from feedback_attachments
             where id=? and feedback_submission_id=?',
            [(int)$attachmentId,(int)$id]
        );
        if(!$file)H::abort(404);

        $base=realpath(app_path('storage/protected_uploads/feedback'));
        $path=realpath(app_path(
            'storage/protected_uploads/feedback/'.$file['stored_name']
        ));

        if(!$base||!$path||
           !str_starts_with($path,$base.DIRECTORY_SEPARATOR)||
           !is_file($path)||!is_readable($path)){
            H::abort(404);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Length: '.filesize($path));
        header("Content-Disposition: attachment; filename*=UTF-8''".
            rawurlencode($file['original_name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
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

            if(($_POST['action']??'')==='reply'){
                try{
                    $sent=$this->sendSupportReply(
                        $submission,
                        (string)($_POST['body']??''),
                        (string)($_POST['reply_token']??'')
                    );
                    H::flash('success',$sent?'Support response sent.':'Reply already submitted.');
                }catch(\InvalidArgumentException $e){
                    H::flash('warning',$e->getMessage());
                }
                H::redirect('/admin/feedback/'.(int)$id);
            }


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

        $conversation=DB::row(
            'select id from message_conversations
             where feedback_submission_id=? and conversation_type="support"',
            [(int)$id]
        );
        $replyMessages=$conversation
            ? DB::rows(
                'select m.id,m.sender_user_id,m.body,m.created_at
                 from conversation_messages m where m.conversation_id=? order by m.id',
                [(int)$conversation['id']]
              )
            : [];
        $attachments=DB::rows(
            'select id,original_name,mime_type,byte_size
             from feedback_attachments where feedback_submission_id=? order by id',
            [(int)$id]
        );
        $replyAttachments = [];
        if ($conversation) {
            $rows = DB::rows(
                'select a.id,a.message_id,a.original_name,a.byte_size
                 from message_attachments a
                 join conversation_messages m on m.id=a.message_id
                 where m.conversation_id=?
                 order by a.id',
                [(int)$conversation['id']]
            );
            foreach ($rows as $row) {
                $replyAttachments[(int)$row['message_id']][] = $row;
            }
        }

        H::view('admin/feedback_detail',compact('submission','conversation','replyMessages','attachments','replyAttachments'));
    }
}
