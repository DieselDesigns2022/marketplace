<?php
namespace App\Controllers;
use App\Core\Helpers as H;
use App\Services\SocialContentService;
use DomainException;
use Throwable;
final class SocialContentController
{
    public function index(): void
    {
        H::requireAdminPermission('social_content.view');
        header('Cache-Control: private, no-store, max-age=0');
        $service=new SocialContentService();$actor=(int)H::user()['id'];
        $error='';$form=[];
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            H::requireAdminPermission('social_content.manage');
            if (empty($_SESSION['_csrf']) || !is_string($_POST['_csrf']??null)) H::abort(419);
            H::verifyCsrf();
            try {
                if (($_POST['action']??'')==='create') $service->create($actor,$_POST);
                elseif (($_POST['action']??'')==='mark') {
                    $version=filter_var($_POST['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                    if ($version===false) throw new DomainException('Invalid queue version.');
                    $service->mark($actor,(string)($_POST['key']??''),(string)($_POST['status']??''),$version);
                } else throw new DomainException('Choose a valid action.');
                H::flash('success','Social Content queue updated.');
                H::redirect('/admin/social-content');return;
            } catch(DomainException $e) {$error=$e->getMessage();$form=$_POST;}
            catch(Throwable $e) {error_log('Social Content: '.$e->getMessage());$error='The update could not be saved. Please try again.';$form=$_POST;}
        }
        $type=(string)($_GET['type']??'');if(!isset(SocialContentService::TYPES[$type]))$type='';
        $status=(string)($_GET['status']??'pending');if(!in_array($status,['pending','done','all'],true))$status='pending';
        $q=mb_substr(trim((string)($_GET['q']??'')),0,150);
        $items=array_values(array_filter($service->items($actor),static fn(array $i):bool=>($type===''||$i['type']===$type)&&($status==='all'||$i['status']===$status)&&($q===''||mb_stripos($i['title'].' '.$i['detail'],$q)!==false)));
        $total=count($items);$pages=max(1,(int)ceil($total/24));$page=min($pages,max(1,(int)($_GET['page']??1)));
        H::view('admin/social_content',['items'=>array_slice($items,($page-1)*24,24),'types'=>SocialContentService::TYPES,'type'=>$type,'status'=>$status,'q'=>$q,'total'=>$total,'pages'=>$pages,'page'=>$page,'error'=>$error,'form'=>$form,'canManage'=>H::canAdmin('social_content.manage')]);
    }
}
