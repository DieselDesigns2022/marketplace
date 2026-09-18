<?php
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Helpers as H;
use App\Controllers\StripeController;
use App\Services\StripeService;

$failures=[];
$check=function(bool $ok,string $message)use(&$failures):void{echo($ok?'PASS: ':'FAIL: ').$message."\n";if(!$ok)$failures[]=$message;};

$_ENV['STRIPE_SECRET_KEY']='sk_test_phase13';
$_ENV['STRIPE_CURRENCY']='usd';
$_ENV['APP_URL']='https://creative-moth.example';
$request=null;
StripeService::setTestTransport(function(string $method,string $path,array $params,?string $key)use(&$request):array{$request=compact('method','path','params','key');return['id'=>'cs_promo','url'=>'https://checkout.stripe.test/promo'];});
$campaign=['id'=>42,'designer_id'=>7,'price_cents'=>1500,'currency'=>'usd'];
$session=StripeService::createPromoCheckoutSession($campaign);
$metadata=['payment_kind'=>'promo','promo_campaign_id'=>'42','designer_id'=>'7'];
$check($session['id']==='cs_promo'&&$request['method']==='POST'&&$request['path']==='/v1/checkout/sessions','promo checkout uses Stripe Checkout');
$check($request['params']['mode']==='payment'&&$request['params']['line_items'][0]['price_data']['unit_amount']===1500&&$request['params']['line_items'][0]['price_data']['currency']==='usd','promo checkout charges the exact snapshot');
$check($request['params']['metadata']===$metadata&&$request['params']['payment_intent_data']['metadata']===$metadata,'promo metadata is copied to Checkout and PaymentIntent');
$check($request['key']==='creative_moth_promo_campaign_42','promo checkout uses a campaign-specific idempotency key');
$encoded=json_encode($request['params']);
$check(!str_contains($encoded,'order_id')&&!str_contains($encoded,'seller_payout')&&!str_contains($encoded,'transfer_data')&&!str_contains($encoded,'application_fee'),'promo checkout has no order, payout, transfer, or marketplace-fee metadata');
StripeService::setTestTransport(null);

$stripeController=new StripeController();$dispatch=new ReflectionMethod($stripeController,'processEvent');$dispatch->setAccessible(true);
$malformed=[null,'','0','-1','1.5','1abc',' 1','1 ','+1',[],new stdClass()];
foreach($malformed as $rawId){$metadata=['payment_kind'=>'promo'];if($rawId!==null)$metadata['promo_campaign_id']=$rawId;try{$dispatch->invoke($stripeController,['data'=>['object'=>['id'=>'obj_phase13_metadata','metadata'=>$metadata]]],'evt_phase13_metadata','payment_intent.succeeded');$rejected=false;}catch(RuntimeException $e){$rejected=$e->getMessage()==='Promotion metadata is incomplete.';}$check($rejected,'promo campaign metadata rejects '.var_export($rawId,true).' before database access');}
try{$dispatch->invoke($stripeController,['data'=>['object'=>['id'=>'ch_phase13_bad','metadata'=>['payment_kind'=>'promo','promo_campaign_id'=>'1abc']]]],'evt_phase13_bad_unsupported','charge.succeeded');$unsupportedMalformed=false;}catch(RuntimeException $e){$unsupportedMalformed=$e->getMessage()==='Promotion metadata is incomplete.';}$check($unsupportedMalformed,'unsupported promo event still rejects malformed campaign metadata');
try{$dispatch->invoke($stripeController,['data'=>['object'=>['id'=>'ch_phase13_valid','metadata'=>['payment_kind'=>'promo','promo_campaign_id'=>'42']]]],'evt_phase13_valid_unsupported','charge.succeeded');$validUnsupported=true;}catch(Throwable $e){$validUnsupported=false;}$check($validUnsupported,'canonical positive promo ID reaches unsupported-event no-op without database access');

$promo=['id'=>42,'type'=>'product','title'=>'<Promo & Product>','image'=>'/safe.webp','store'=>'Shop & Studio','price'=>'12.50','click_token'=>str_repeat('a',64)];
$sponsoredPromo=$promo;ob_start();require app_path('app/Views/public/sponsored_card.php');$card=ob_get_clean();
$check(str_contains($card,'Sponsored')&&str_contains($card,'/promo/click/'.str_repeat('a',64)),'website card is sponsored and uses the tracked URL');
$check(str_contains($card,'&lt;Promo &amp; Product&gt;')&&!str_contains($card,'<Promo & Product>'),'website card escapes campaign content');

$data=['paid_promos'=>[$promo]];ob_start();require app_path('app/Views/emails/paid_promos.php');$email=ob_get_clean();
$check(str_contains($email,'Sponsored')&&str_contains($email,H::baseUrl().'/promo/click/'.str_repeat('a',64)),'weekly email promo is sponsored and uses the tracked URL');
$check(str_contains($email,'&lt;Promo &amp; Product&gt;')&&!str_contains($email,'<Promo & Product>'),'weekly email promo escapes campaign content');

$data=['name'=>'Buyer','frequency'=>'monthly','products'=>[],'manage_preferences_url'=>'/account','unsubscribe_url'=>'/unsubscribe'];ob_start();require app_path('app/Views/emails/marketplace_digest.php');$monthly=ob_get_clean();
$data['frequency']='weekly';ob_start();require app_path('app/Views/emails/marketplace_digest.php');$weekly=ob_get_clean();
$check(str_contains($monthly,'newest digital goods')&&!str_contains($weekly,'newest digital goods'),'monthly keeps newest wording while weekly uses marketplace-wide wording');

$products=[];$categories=[];$packages=[['id'=>1,'placement'=>'marketplace','duration_value'=>1,'duration_unit'=>'day','price_cents'=>300]];$campaigns=[];ob_start();require app_path('app/Views/seller/promos.php');$sellerPage=ob_get_clean();
$check(str_contains($sellerPage,'Impressions, clicks, and sales are not guaranteed')&&str_contains($sellerPage,'next 1, 2, 4, or 6 eligible')&&str_contains($sellerPage,'does not have to be listed in that category')&&str_contains($sellerPage,'No promotions yet'),'seller page explains guarantees, category choice, weekly appearances, and empty history');
$check(str_contains($sellerPage,'no approved products')&&!str_contains($sellerPage,'no approved or published products')&&str_contains($sellerPage,'still promote your shop'),'seller page explains approved-product-only empty state and shop promotion');
$campaigns=[['id'=>1,'target_type'=>'shop','display_name'=>'Shop','product_title'=>null,'placement'=>'category','category_name'=>'Graphics','duration_value'=>1,'duration_unit'=>'day','price_cents'=>400,'payment_status'=>'paid','status'=>'active','starts_at'=>'2026-09-17','ends_at'=>'2026-09-18','impressions'=>9,'email_recipient_sends'=>0,'clicks'=>2],['id'=>2,'target_type'=>'shop','display_name'=>'Shop','product_title'=>null,'placement'=>'weekly_email','category_name'=>null,'duration_value'=>4,'duration_unit'=>'week','price_cents'=>2500,'payment_status'=>'paid','status'=>'active','email_appearances_used'=>1,'email_total_appearances'=>4,'email_recipient_sends'=>12,'impressions'=>0,'clicks'=>3]];ob_start();require app_path('app/Views/seller/promos.php');$history=ob_get_clean();
$check(str_contains($history,'Category: Graphics')&&str_contains($history,'Appearances: 1 of 4')&&str_contains($history,'Remaining: 3')&&str_contains($history,'Sends: 12')&&str_contains($history,'Clicks: 3')&&!str_contains($history,'0 impressions'),'seller weekly history distinguishes appearances, sends, and clicks from website impressions');
$packages=[];ob_start();require app_path('app/Views/admin/ads.php');$admin=ob_get_clean();$check(str_contains($admin,'Appearances: 1 / 4')&&str_contains($admin,'Recipient sends: 12')&&str_contains($admin,'Clicks: 3')&&str_contains($admin,'value="remove"')&&!str_contains($admin,'Impressions: 0'),'admin weekly stats and Remove action distinguish email campaigns from website impressions');

exit($failures?1:0);
