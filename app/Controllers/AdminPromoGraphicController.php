<?php
namespace App\Controllers;

use App\Core\Helpers as H;
use App\Services\PromoGraphicService;

final class AdminPromoGraphicController
{
    private function gate(string $permission):void{H::requireAdminPermission($permission);}
    public function index():void{$this->gate('promotions.view');H::view('admin/promo_graphics',['graphics'=>PromoGraphicService::all()]);}
    public function create():void{$this->edit(null);}
    public function update($id):void{$this->edit((int)$id);}
    private function edit(?int $id):void
    {
        $this->gate($id?'promotions.manage':'promotions.manage');$graphic=$id?PromoGraphicService::find($id):null;if($id&&!$graphic)H::abort(404);
        if($_SERVER['REQUEST_METHOD']==='POST'){H::verifyCsrf();try{$saved=PromoGraphicService::save($id,$_POST,$_FILES['image']??null,(int)H::user()['id']);H::flash('success',$id?'Promotional graphic updated.':'Promotional graphic uploaded.');H::redirect('/admin/promo-library/'.$saved);}catch(\InvalidArgumentException|\RuntimeException $e){H::flash('error',$e->getMessage());$graphic=array_merge($graphic??[],['platform'=>$_POST['platform']??'','size_label'=>$_POST['size_label']??'','category'=>$_POST['category']??'','alt_text'=>$_POST['alt_text']??'','suggested_caption'=>$_POST['suggested_caption']??'','sort_order'=>$_POST['sort_order']??0,'is_active'=>($_POST['is_active']??'')==='1']);}}
        H::view('admin/promo_graphic_form',['graphic'=>$graphic,'platforms'=>PromoGraphicService::PLATFORMS]);
    }
    public function archive($id):void{$this->gate('promotions.manage');H::verifyCsrf();try{PromoGraphicService::archive((int)$id,(int)H::user()['id']);H::flash('success','Promotional graphic archived.');}catch(\InvalidArgumentException $e){H::flash('error',$e->getMessage());}H::redirect('/admin/promo-library');}
    public function image($id):void{$this->gate('promotions.view');$g=PromoGraphicService::find((int)$id);if(!$g)H::abort(404);try{$path=PromoGraphicService::absolutePath($g);}catch(\RuntimeException $e){H::abort(404);}header('Content-Type: '.$g['mime_type']);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($path);exit;}
}
