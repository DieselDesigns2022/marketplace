<?php
namespace App\Controllers;

use App\Core\Helpers as H;
use App\Services\PromoGraphicService;

final class PromoLibraryController
{
    public function index(): void
    {
        $groups=[];foreach(PromoGraphicService::active() as $graphic)$groups[$graphic['platform']][]=$graphic;
        H::view('public/promo_library',['groups'=>$groups,'platforms'=>PromoGraphicService::PLATFORMS,'meta'=>['title'=>'Free Promotional Graphics | Creative Moth','description'=>'Download and share free Creative Moth promotional graphics for social media, websites, and email.']]);
    }
    public function image($id): void{$this->serve((int)$id,false);}
    public function download($id): void{$this->serve((int)$id,true);}
    private function serve(int $id,bool $download): never
    {
        $graphic=PromoGraphicService::find($id,true);if(!$graphic)H::abort(404);
        try{$path=PromoGraphicService::absolutePath($graphic);}catch(\RuntimeException $e){H::abort(404);}
        header('Content-Type: '.$graphic['mime_type']);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=3600');
        if($download)header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($graphic['original_name']));
        readfile($path);exit;
    }
}
