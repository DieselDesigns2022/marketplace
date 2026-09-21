<?php
namespace App\Controllers;

use App\Core\Helpers as H;
use App\Services\PromoGraphicService;

final class AdminPromoGraphicController
{
    private function gate(string $permission):void
    {
        H::requireAdminPermission($permission);
    }

    public function index():void
    {
        $this->gate('promotions.view');
        H::view('admin/promo_graphics',[
            'graphics'=>PromoGraphicService::all()
        ]);
    }

    public function create():void
    {
        $this->edit(null);
    }

    public function update($id):void
    {
        $this->edit((int)$id);
    }

    private function edit(?int $id):void
    {
        $this->gate('promotions.manage');

        $graphic=$id?PromoGraphicService::find($id):null;

        if($id&&!$graphic){
            H::abort(404);
        }

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::verifyCsrf();

            try{
                if($id){
                    $saved=PromoGraphicService::save(
                        $id,
                        $_POST,
                        $_FILES['image']??null,
                        (int)H::user()['id']
                    );

                    H::flash('success','Promotional graphic updated.');
                    H::redirect('/admin/promo-library/'.$saved);
                }

                $ids=PromoGraphicService::createDraftBatch(
                    $_POST,
                    $_FILES['images']??null,
                    (int)H::user()['id']
                );

                $token=bin2hex(random_bytes(16));

                $_SESSION['promo_graphic_batches'][$token]=$ids;

                H::redirect('/admin/promo-library/batch/'.$token);
            }catch(\InvalidArgumentException|\RuntimeException $e){
                H::flash('error',$e->getMessage());

                $graphic=array_merge($graphic??[],[
                    'platform'=>$_POST['platform']??'',
                    'category'=>$_POST['category']??'',
                    'alt_text'=>$_POST['alt_text']??'',
                    'suggested_caption'=>$_POST['suggested_caption']??'',
                    'is_active'=>($_POST['is_active']??'')==='1'
                ]);
            }
        }

        H::view('admin/promo_graphic_form',[
            'graphic'=>$graphic,
            'platforms'=>PromoGraphicService::PLATFORMS
        ]);
    }

    public function batch($token):void
    {
        $this->gate('promotions.manage');

        $token=(string)$token;
        $ids=$_SESSION['promo_graphic_batches'][$token]??null;

        if(!is_array($ids)||!$ids){
            H::abort(404);
        }

        $graphics=PromoGraphicService::drafts($ids);

        if(count($graphics)!==count($ids)){
            H::abort(404);
        }

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::verifyCsrf();

            try{
                PromoGraphicService::finalizeDraftBatch(
                    $ids,
                    $_POST,
                    (int)H::user()['id']
                );

                unset($_SESSION['promo_graphic_batches'][$token]);

                H::flash(
                    'success',
                    count($ids).' promotional graphic'.(count($ids)===1?'':'s').' saved.'
                );

                H::redirect('/admin/promo-library');
            }catch(\InvalidArgumentException|\RuntimeException $e){
                H::flash('error',$e->getMessage());

                $postedCategories=$_POST['category']??[];
                $postedAlts=$_POST['alt_text']??[];
                $postedCaptions=$_POST['suggested_caption']??[];

                foreach($graphics as &$graphic){
                    $graphicId=(int)$graphic['id'];

                    if(array_key_exists($graphicId,$postedCategories)){
                        $graphic['category']=(string)$postedCategories[$graphicId];
                    }

                    if(array_key_exists($graphicId,$postedAlts)){
                        $graphic['alt_text']=(string)$postedAlts[$graphicId];
                    }

                    if(array_key_exists($graphicId,$postedCaptions)){
                        $graphic['suggested_caption']=(string)$postedCaptions[$graphicId];
                    }
                }

                unset($graphic);
            }
        }

        H::view('admin/promo_graphic_batch',[
            'graphics'=>$graphics,
            'token'=>$token
        ]);
    }

    public function archive($id):void
    {
        $this->gate('promotions.manage');
        H::verifyCsrf();

        try{
            PromoGraphicService::archive(
                (int)$id,
                (int)H::user()['id']
            );

            H::flash('success','Promotional graphic archived.');
        }catch(\InvalidArgumentException $e){
            H::flash('error',$e->getMessage());
        }

        H::redirect('/admin/promo-library');
    }

    public function image($id):void
    {
        $this->gate('promotions.view');

        $g=PromoGraphicService::find((int)$id);

        if(!$g){
            H::abort(404);
        }

        try{
            $path=PromoGraphicService::absolutePath($g);
        }catch(\RuntimeException $e){
            H::abort(404);
        }

        header('Content-Type: '.$g['mime_type']);
        header('Content-Length: '.filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
        exit;
    }
}
