<?php
namespace App\Controllers;
use App\Core\Helpers as H;
use App\Services\PromoService;
use App\Services\StripeService;
use App\Services\NotificationService;

final class PromoController
{
    private function seller(): array { H::requireSeller();return PromoService::seller((int)H::user()['id']); }
    public function index(): void { $s=$this->seller();H::view('seller/promos',['seller'=>$s,'products'=>PromoService::products((int)$s['id']),'categories'=>PromoService::categories(),'packages'=>PromoService::packages(),'campaigns'=>PromoService::campaigns((int)$s['id'])]); }
    public function checkout(): void
    {
        $s=$this->seller();H::verifyCsrf();$ad=null;
        try {
            $ad=PromoService::createPending($s,(string)($_POST['target_type']??''),($_POST['product_id']??'')!==''?(int)$_POST['product_id']:null,(int)($_POST['package_id']??0),($_POST['category_id']??'')!==''?(int)$_POST['category_id']:null);
            $session=StripeService::createPromoCheckoutSession($ad);$sessionId=trim((string)($session['id']??''));$url=trim((string)($session['url']??''));
            if($sessionId===''||$url===''||!filter_var($url,FILTER_VALIDATE_URL))throw new \RuntimeException('Stripe Checkout returned an incomplete promotion session.');
            PromoService::storeCheckoutIds((int)$ad['id'],$sessionId,$session['payment_intent']??null);H::redirect($url);
        } catch(\Throwable $e) {
            if(!$ad&&$e instanceof \InvalidArgumentException){H::flash('error',$e->getMessage());H::redirect('/seller/promos');}
            if($ad)try{PromoService::checkoutSetupFailed((int)$ad['id']);}catch(\Throwable $cleanup){try{NotificationService::reportFailure('promo_checkout_cleanup',$cleanup);}catch(\Throwable $ignored){}}
            try{NotificationService::reportFailure('promo_checkout_setup',$e);}catch(\Throwable $ignored){}
            H::flash('error','Promotion checkout could not be started. No charge was confirmed. Please try again.');H::redirect('/seller/promos');
        }
    }
    public function success(): void
    {
        $seller=$this->seller();$id=(int)($_GET['campaign']??0);$campaign=$id?PromoService::sellerCampaign($id,(int)$seller['id']):null;
        if($id&&!$campaign)H::abort(404);
        if(!$campaign)H::flash('warning','Checkout returned. Open your promotion history to review its verified payment status.');
        elseif($campaign['payment_status']==='paid'&&$campaign['status']==='active')H::flash('success','Payment is confirmed and your promotion is active.');
        elseif($campaign['payment_status']==='paid')H::flash('success','Payment is confirmed. Your promotion is '.str_replace('_',' ',(string)$campaign['status']).'.');
        elseif($campaign['payment_status']==='failed')H::flash('error','Promotion payment failed. No promotion was activated.');
        elseif($campaign['payment_status']==='cancelled')H::flash('warning','Promotion payment was cancelled. No promotion was activated.');
        else H::flash('warning','Checkout returned and payment is still being confirmed by Stripe.');
        H::redirect('/seller/promos');
    }
    public function cancel(): void
    { $seller=$this->seller();$id=(int)($_GET['campaign']??0);if($id&&!PromoService::sellerCampaign($id,(int)$seller['id']))H::abort(404);H::flash('warning','Checkout was cancelled. No promotion will activate unless Stripe later confirms payment.');H::redirect('/seller/promos'); }
    public function click($token): void { H::redirect(PromoService::click((string)$token)); }
}
