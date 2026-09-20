<?php
namespace App\Controllers;
use App\Core\Database as DB; use App\Core\Helpers as H; use App\Services\CustomDesignService; use App\Services\LicenseService; use App\Services\SellerReviewService;
final class CustomDesignController {
 public function index():void{$services=DB::rows('select s.*,d.display_name,(select image_path from custom_service_images where custom_service_id=s.id order by sort_order,id limit 1) preview_image from custom_design_services s join designers d on d.id=s.designer_id where s.is_active=1 and d.status="approved" order by s.created_at desc');H::view('public/custom_services',['services'=>$services]);}
 public function detail($slug):void{$service=$this->publicService($slug);$this->detailView($service,[]);}
 public function purchase($slug):void
 {
     H::requireLogin();
     H::verifyCsrf();

     $service=$this->publicService($slug);
     $api=new CustomDesignService;

     try{

         $token=$api->prepareCheckout(
             $service,
             (int)H::user()['id'],
             $_POST,
             $_FILES
         );

         H::redirect(
             '/custom-design/'.
             rawurlencode($service['slug']).
             '/checkout?token='.
             rawurlencode($token)
         );

     }catch(\InvalidArgumentException $e){

         $this->detailView(
             $service,
             [$e->getMessage()]
         );

     }catch(\Throwable $e){

         H::flash(
             'error',
             'Secure checkout could not be prepared.'
         );

         H::redirect(
             '/custom-design/'.$service['slug']
         );
     }
 }

 public function checkout($slug):void
 {
     H::requireLogin();

     $service=$this->publicService($slug);
     $api=new CustomDesignService;

     $token=trim(
         (string)(
             $_POST['checkout_token']
             ??$_GET['token']
             ??''
         )
     );

     $payload=$api->checkoutPayload(
         $token,
         (int)H::user()['id'],
         (int)$service['id']
     );

     $errors=[];

     if($_SERVER['REQUEST_METHOD']==='POST'){

         H::verifyCsrf();

         $input=$payload['input'];

         foreach([
             'billing_line1',
             'billing_line2',
             'billing_city',
             'billing_state',
             'billing_postal_code',
             'billing_country'
         ] as $field){
             $input[$field]=$_POST[$field]??'';
         }

         $input['use_credits']=
             ($_POST['use_credits']??'')==='1'
             ?'1'
             :'0';

         $input['checkout_token']=$token;

         try{

             $api->submitRequest(
                 $service,
                 (int)H::user()['id'],
                 $input,
                 $api->preparedCheckoutFiles($payload)
             );

         }catch(\InvalidArgumentException|\DomainException $e){

             $errors[]=$e->getMessage();

         }catch(\Throwable $e){

             $errors[]=
                 'Custom Design checkout could not be completed. Please try again.';
         }
     }

     $availableLicenses=$api->serviceLicenses(
         (int)$service['id']
     );

     $selectedKeys=array_map(
         'strval',
         (array)(
             $payload['input']['license_type']
             ??[]
         )
     );

     $selectedLicenses=array_values(
         array_filter(
             $availableLicenses,
             static fn(array $license):bool =>
                 in_array(
                     (string)$license['license_key'],
                     $selectedKeys,
                     true
                 )
         )
     );

     $licenseTotal=array_sum(
         array_map(
             static fn(array $license):float =>
                 (float)$license['price'],
             $selectedLicenses
         )
     );

     $balances=(new \App\Services\CreditService)
         ->balances((int)H::user()['id']);

     H::view(
         'buyer/custom_checkout',
         [
             'service'=>$service,
             'token'=>$token,
             'selectedLicenses'=>$selectedLicenses,
             'licenseTotal'=>$licenseTotal,
             'subtotal'=>
                 (float)$service['price']
                 +$licenseTotal,
             'balances'=>$balances,
             'errors'=>$errors
         ]
     );
 }

 private function publicService(string $slug):array{return DB::row('select s.*,d.display_name,d.user_id seller_user_id from custom_design_services s join designers d on d.id=s.designer_id where s.slug=? and s.is_active=1 and d.status="approved"',[$slug])??H::abort(404);}
 private function detailView(array $s,array $errors):void{$api=new CustomDesignService;$balances=H::user()?(new \App\Services\CreditService)->balances((int)H::user()['id']):['available'=>'0.00'];H::view('public/custom_service',['service'=>$s,'questions'=>DB::rows('select * from custom_service_questions where custom_service_id=? order by sort_order,id',[$s['id']]),'images'=>DB::rows('select * from custom_service_images where custom_service_id=? order by sort_order,id',[$s['id']]),'balances'=>$balances,'licenses'=>$api->serviceLicenses((int)$s['id']),'errors'=>$errors]);}
 public function sellerList():void{H::requireSeller();$d=(new CustomDesignService)->seller((int)H::user()['id']);H::view('seller/custom_services',['services'=>DB::rows('select * from custom_design_services where designer_id=? order by updated_at desc',[$d['id']])]);}
 public function sellerEdit($id='new'):void
 {
     H::requireSeller();

     $api=new CustomDesignService;
     $d=$api->seller((int)H::user()['id']);

     $s=$id==='new'
         ?null
         :(DB::row(
             'select *
              from custom_design_services
              where id=? and designer_id=?',
             [(int)$id,$d['id']]
         )??H::abort(404));

     $errors=[];

     if($_SERVER['REQUEST_METHOD']==='POST'){

         H::verifyCsrf();

         $action=(string)($_POST['form_action']??'save');

         if($action==='delete'){

             if(!$s){
                 H::abort(404);
             }

             try{
                 $api->deleteService(
                     (int)H::user()['id'],
                     (int)$s['id']
                 );

                 H::flash(
                     'success',
                     'Custom Design deleted.'
                 );

                 H::redirect('/seller/custom-designs');

             }catch(\DomainException $e){
                 $errors[]=$e->getMessage();
             }

         }else{

             if($action==='draft'){
                 $_POST['save_mode']='draft';
             }

             $errors=$api->saveService(
                 (int)H::user()['id'],
                 $s?(int)$s['id']:null,
                 $_POST,
                 $_FILES
             );

             if(!$errors){

                 H::flash(
                     'success',
                     $action==='draft'
                         ?'Custom Design saved as Draft.'
                         :'Custom Design saved.'
                 );

                 H::redirect('/seller/custom-designs');
             }
         }
     }

     H::view(
         'seller/custom_service_form',
         [
             'service'=>$s,
             'questions'=>$s
                 ?DB::rows(
                     'select *
                      from custom_service_questions
                      where custom_service_id=?
                      order by sort_order,id',
                     [$s['id']]
                 )
                 :[],
             'images'=>$s
                 ?DB::rows(
                     'select *
                      from custom_service_images
                      where custom_service_id=?
                      order by sort_order,id',
                     [$s['id']]
                 )
                 :[],
             'licenseTypes'=>LicenseService::platformTypes(),
             'serviceLicenses'=>$s
                 ?$api->serviceLicenses((int)$s['id'])
                 :LicenseService::presetLicensesForProductForm(
                     (int)$d['id']
                 ),
             'errors'=>$errors
         ]
     );
 }

 public function sellerOrders():void{H::requireSeller();$d=(new CustomDesignService)->seller((int)H::user()['id']);H::view('seller/custom_orders',['orders'=>DB::rows('select co.*,o.payment_status,u.name buyer_name,JSON_UNQUOTE(JSON_EXTRACT(co.service_snapshot,"$.title")) title from custom_orders co join orders o on o.id=co.order_id join users u on u.id=co.buyer_user_id where co.designer_id=? and o.payment_status in ("paid","partially_refunded","refunded") order by co.created_at desc',[$d['id']])]);}
 public function buyerOrders():void{H::requireLogin();H::view('buyer/custom_orders',['orders'=>DB::rows('select co.*,o.payment_status,d.display_name,JSON_UNQUOTE(JSON_EXTRACT(co.service_snapshot,"$.title")) title from custom_orders co join orders o on o.id=co.order_id join designers d on d.id=co.designer_id where co.buyer_user_id=? order by co.created_at desc',[H::user()['id']])]);}
 public function buyerOrder($id):void{$this->orderForSide('buyer',(int)$id);}
 public function sellerOrder($id):void{$this->orderForSide('seller',(int)$id);}
 private function orderForSide(string $side,int $id):void{H::requireLogin();$api=new CustomDesignService;$o=$api->order($id,(int)H::user()['id'],$side);if($_SERVER['REQUEST_METHOD']==='POST'){H::verifyCsrf();try{$api->transition($o,(int)H::user()['id'],$_POST['action']??'',$_FILES);H::flash('success','Custom order updated.');H::redirect('/'.$side.'/custom-orders/'.$id);}catch(\Throwable $e){H::flash('error',$e->getMessage());}}$conversation=DB::row('select id from message_conversations where custom_order_id=?',[$id]);H::view($side.'/custom_order',['order'=>$o,'brief'=>json_decode($o['brief_snapshot'],true)?:[],'files'=>DB::rows('select * from custom_order_files where custom_order_id=? order by created_at,id',[$id]),'history'=>DB::rows('select h.*,u.name actor_name from custom_order_status_history h left join users u on u.id=h.actor_user_id where h.custom_order_id=? order by h.created_at desc,h.id desc',[$id]),'conversation'=>$conversation]);}
 public function file($id):void{H::requireLogin();$f=(new CustomDesignService)->file((int)$id,(int)H::user()['id']);$base=realpath(app_path('storage/protected_uploads/custom_designs'));$real=realpath(app_path('storage/protected_uploads/'.ltrim($f['storage_path'],'/')));if(!$base||!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($real)||!is_readable($real))H::abort(404);header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($real));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($f['original_name']));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');$delivered=readfile($real);if($delivered!==false&&(int)H::user()['id']===(int)$f['buyer_user_id']&&$f['file_kind']==='final'){try{(new SellerReviewService)->markCustomFinalDownloaded((int)$f['id'],(int)H::user()['id']);}catch(\Throwable $e){\App\Services\NotificationService::reportFailure('post-download custom review eligibility',$e);}}exit;}
 public function adminOrders():void{H::requireAdminPermission('custom_orders.view');H::view('admin/custom_orders',['orders'=>DB::rows('select co.*,o.payment_status,o.total,u.name buyer_name,d.display_name,JSON_UNQUOTE(JSON_EXTRACT(co.service_snapshot,"$.title")) title,(select count(*) from custom_order_files f where f.custom_order_id=co.id and f.file_kind="final") final_count from custom_orders co join orders o on o.id=co.order_id join users u on u.id=co.buyer_user_id join designers d on d.id=co.designer_id order by co.created_at desc')]);}
 public function adminOrder($id):void
 {
     if($_SERVER['REQUEST_METHOD']==='POST')H::verifyCsrf();
     H::requireAdminPermission($_SERVER['REQUEST_METHOD']==='POST'?'custom_orders.manage':'custom_orders.view');

     $o=DB::row(
         'select
             co.*,
             o.payment_status,
             o.total,
             o.manual_review_required,
             o.manual_review_reason,
             u.name buyer_name,
             d.display_name
          from custom_orders co
          join orders o
            on o.id=co.order_id
          join users u
            on u.id=co.buyer_user_id
          join designers d
            on d.id=co.designer_id
          where co.id=?',
         [$id]
     )??H::abort(404);

     $reports=DB::rows(
         'select
             r.id,
             r.status,
             r.reason,
             r.created_at
          from message_reports r
          join message_conversations c
            on c.id=r.conversation_id
          where c.custom_order_id=?
          order by r.created_at desc',
         [$id]
     );

     $licenseItem=DB::row(
         'select
             license_name,
             license_description,
             license_snapshot
          from order_items
          where id=?',
         [(int)$o['order_item_id']]
     )??[];

     $licenses=[];

     $snapshot=json_decode(
         (string)($licenseItem['license_snapshot']??''),
         true
     );

     if(is_array($snapshot)){

         if(array_is_list($snapshot)){

             foreach($snapshot as $license){
                 if(is_array($license)){
                     $licenses[]=$license;
                 }
             }

         }elseif(
             isset($snapshot['licenses']) &&
             is_array($snapshot['licenses'])
         ){

             foreach($snapshot['licenses'] as $license){
                 if(is_array($license)){
                     $licenses[]=$license;
                 }
             }

         }elseif(isset($snapshot['name'])){

             $licenses[]=$snapshot;
         }
     }

     /*
      * Older/custom orders without a list-style snapshot still
      * get a readable fallback from the stored order-item license.
      */
     if(
         !$licenses &&
         trim((string)($licenseItem['license_name']??''))!==''
     ){
         $licenses[]=[
             'name'=>(string)$licenseItem['license_name'],
             'description'=>
                 (string)($licenseItem['license_description']??''),
             'price'=>null,
             'included'=>null
         ];
     }

     H::view(
         'admin/custom_order',
         [
             'order'=>$o,
             'brief'=>json_decode(
                 $o['brief_snapshot'],
                 true
             )?:[],
             'licenses'=>$licenses,
             'files'=>DB::rows(
                 'select
                     id,
                     file_kind,
                     original_name,
                     file_size,
                     created_at
                  from custom_order_files
                  where custom_order_id=?
                  order by created_at,id',
                 [$id]
             ),
             'reports'=>$reports
         ]
     );
 }


}
