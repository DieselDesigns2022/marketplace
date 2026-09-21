<?php
if (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '') !== 'test' || getenv('RUN_DISPOSABLE_DB_TESTS') !== '1') exit(77);
require dirname(__DIR__,2).'/app/bootstrap.php';
use App\Controllers\AdminController;
use App\Controllers\SellerController;
use App\Services\SocialPublishingService;

foreach(['APP_ENV','SOCIAL_CREDENTIAL_ENCRYPTION_KEY','APP_URL'] as $key)if(getenv($key)!==false)$_ENV[$key]=getenv($key);

$_SERVER['REQUEST_METHOD']='POST';
$_SESSION['_csrf']='phase136-workflow';
$_SESSION['user']=['id'=>(int)getenv('PHASE136_USER_ID'),'name'=>'Phase 136','email'=>'phase136@example.test','role'=>getenv('PHASE136_ROLE')?:'designer'];
$_POST=['_csrf'=>'phase136-workflow'];
SocialPublishingService::setTestTransport(static function(string $method,string $url):array {
    if(str_ends_with($url,'/photos'))return['post_id'=>'workflow-fake-post'];
    throw new RuntimeException('Unexpected external request in workflow test: '.$method.' '.$url);
});
$mode=(string)getenv('PHASE136_WORKFLOW');$productId=(int)getenv('PHASE136_PRODUCT_ID');
if($mode==='admin'){$method=new ReflectionMethod(AdminController::class,'moderateProduct');$method->setAccessible(true);$method->invoke(new AdminController(),$productId,'approve','',false);exit(0);}
if($mode==='ip'){$_POST+=['ip_action'=>'published_flagged','admin_note'=>'Phase 13.6 workflow fixture'];(new AdminController())->productIpRiskReview($productId);}
if($mode==='single'){(new SellerController())->submitProduct($productId);}
if($mode==='batch'){$_POST+=['action'=>'submit_all'];(new SellerController())->mutateProductBatch((int)getenv('PHASE136_BATCH_ID'));}
if($mode==='seller_save'){
    $_POST+=['action'=>'review','title'=>(string)getenv('PHASE136_TITLE'),'short_description'=>'','description'=>'Phase 13.6 workflow description','price'=>'10.00','fulfillment_type'=>'downloadable','manual_delivery_instructions'=>'','category_id'=>(string)getenv('PHASE136_CATEGORY_ID'),'tags'=>'','ai_disclosure'=>'No AI Used','seo_title'=>'','seo_description'=>''];
    (new SellerController())->editProduct($productId);
}
exit(78);
