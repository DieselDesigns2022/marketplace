<?php
require __DIR__.'/helpers/GrandOpeningPricingBootstrap.php';
use App\Core\Database as DB;
use App\Services\GrandOpeningPricingService as Pricing;
use App\Services\GrandOpeningSaleService as Sale;
use App\Services\CreditService;
use App\Services\PrizeCreditService;
use App\Services\MarketplaceFeeService;
use App\Services\MarketplaceRefundService;
use App\Controllers\PublicController;

$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$fixture=static function(string $table,array $fields): void {
    if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Invalid fixture table.');
    foreach (DB::rows('show columns from `'.$table.'`') as $column) {
        $name=$column['Field'];
        if (array_key_exists($name,$fields) || $column['Null']==='YES' || $column['Default']!==null || str_contains($column['Extra'],'auto_increment') || str_contains($column['Extra'],'GENERATED')) continue;
        $type=$column['Type'];
        if (str_starts_with($type,'enum(')) {
            preg_match("/^enum\\('((?:[^'\\\\]|\\\\.)*)'/",$type,$m);
            $fields[$name]=stripslashes($m[1]);
        } elseif (preg_match('/int|decimal|float|double|bit/',$type)) $fields[$name]=0;
        elseif (preg_match('/date|time|year/',$type)) $fields[$name]='2026-01-01 00:00:00';
        elseif ($type==='json') $fields[$name]='{}';
        else $fields[$name]='fixture';
    }
    $columns=implode(',',array_map(static fn(string $s):string=>'`'.$s.'`',array_keys($fields)));
    DB::exec('insert into `'.$table.'`('.$columns.') values('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
};

$fixture('users',['id'=>1,'name'=>'Fixture Buyer','email'=>'buyer@example.invalid','role'=>'buyer','status'=>'active']);
$fixture('users',['id'=>2,'name'=>'Fixture Seller A','email'=>'seller-a@example.invalid','role'=>'designer','status'=>'active']);
$fixture('users',['id'=>3,'name'=>'Fixture Seller B','email'=>'seller-b@example.invalid','role'=>'designer','status'=>'active']);
$fixture('designers',['id'=>1,'user_id'=>2,'display_name'=>'Fixture Shop A','store_slug'=>'fixture-a','status'=>'approved','prize_credit_mode'=>'fixed_5']);
$fixture('designers',['id'=>2,'user_id'=>3,'display_name'=>'Fixture Shop B','store_slug'=>'fixture-b','status'=>'approved','prize_credit_mode'=>'percent_25']);
foreach ([1=>[1,'10.00','5.00'],2=>[2,'20.00','10.00'],3=>[2,'30.00','0.00'],4=>[1,'40.00','0.00'],5=>[1,'0.00','0.00'],6=>[1,'0.01','0.00']] as $id=>[$seller,$base,$license]) {
    $fixture('products',['id'=>$id,'designer_id'=>$seller,'title'=>'Fixture Product '.$id,'slug'=>'fixture-product-'.$id,'price'=>$base,'status'=>'approved','ai_disclosure'=>'No AI Used','commercial_license_enabled'=>$license!=='0.00'?1:0,'commercial_license_price'=>$license]);
}
$fixture('grand_opening_events',['id'=>1,'campaign_key'=>'grand-opening-2026','starts_at'=>gmdate('Y-m-d H:i:s',time()-86400),'ends_at'=>gmdate('Y-m-d H:i:s',time()+864000),'is_enabled'=>1]);
$fixture('grand_opening_sales',['event_id'=>1,'designer_id'=>1,'is_participating'=>1,'discount_basis_points'=>2000,'product_scope'=>'shop']);
$fixture('grand_opening_sales',['event_id'=>1,'designer_id'=>2,'is_participating'=>1,'discount_basis_points'=>2500,'product_scope'=>'selected']);
$fixture('grand_opening_sale_products',['event_id'=>1,'designer_id'=>2,'product_id'=>2]);
foreach ([1=>['LOW','percent','10.00'],2=>['HIGH','percent','30.00'],3=>['TIE','fixed','10.50'],4=>['SELLER','percent','50.00']] as $id=>[$code,$type,$discount]) {
    $fixture('coupons',['id'=>$id,'code'=>$code,'scope'=>$code==='SELLER'?'seller':'platform','seller_id'=>$code==='SELLER'?1:null,'discount_type'=>$type,'discount_value'=>$discount,'is_active'=>1]);
}
$input=[['id'=>1,'license_type'=>'personal,commercial'],['id'=>2,'license_type'=>'personal,commercial']];
$engine=new Pricing();
$sale=$engine->cart($input);
$check($sale['regular_cents']===4500,'Original product and license total');
$check($sale['subtotal_cents']===3450,'Sale covers products and paid license add-ons');
$check($sale['sale_savings_cents']===1050,'Seller-specific percentages add correctly');
$check((string)$sale['items'][0]['price']==='8.00','Discounted base price');
$check((float)$sale['items'][0]['license_price']===4.0,'Discounted paid license');
$low=$engine->cart($input,'LOW',1);
$check($low['mode']==='sale' && $low['coupon_result']===null,'Smaller coupon cannot stack or be consumed');
$check($low['subtotal_cents']===3450,'Smaller coupon leaves sale total intact');
$high=$engine->cart($input,'HIGH',1);
$check($high['mode']==='coupon' && $high['sale_savings_cents']===0,'Larger coupon replaces all sale discounts');
$check($high['subtotal_cents']===4500 && (float)$high['coupon_result']['discount']===13.5,'Coupon uses regular prices');
$check((float)$high['items'][0]['price']===10.0 && (float)$high['items'][0]['license_price']===5.0,'Coupon path restores base and licenses');
$tie=$engine->cart($input,'TIE',1);
$check($tie['mode']==='sale' && $tie['coupon_result']===null,'Equal savings retain sale without consuming coupon');
$scoped=$engine->cart($input,'SELLER',1);
$check($scoped['mode']==='sale','Seller coupon compares actual scoped savings with full sale');
$invalid=$engine->cart($input,'MISSING',1);
$check($invalid['mode']==='sale' && $invalid['notice']!=='','Invalid coupon leaves sale and reports reason');
$check((int)DB::row('select count(*) n from coupon_usages')['n']===0,'Quote comparisons never redeem coupons');
$check($engine->cart([['id'=>3,'license_type'=>'personal']])['subtotal_cents']===3000,'Unselected product excluded');
$check($engine->cart([['id'=>4,'license_type'=>'personal']])['subtotal_cents']===3200,'Shop-wide includes other approved products');
$check($engine->cart([['id'=>5,'license_type'=>'personal']])['subtotal_cents']===0,'Originally free product remains free');
$check(Pricing::component(1,9999)===1,'Discount rounding cannot create free paid products');
$tampered=$input;$tampered[0]['price']='0.01';$tampered[0]['go_points']=9999;
$check($engine->cart($tampered)['subtotal_cents']===3450,'Database prices override tampered input');
DB::exec('update products set commercial_license_price=6 where id=1');
$check($engine->cart([['id'=>1,'license_type'=>'personal,commercial']])['subtotal_cents']===1280,'Current license prices are authoritative');
DB::exec('update products set commercial_license_price=5 where id=1');
DB::exec('update grand_opening_sales set is_participating=0 where designer_id=1');
$check($engine->cart([['id'=>1,'license_type'=>'personal,commercial']])['subtotal_cents']===1500,'Opt-out removes sale');
DB::exec('update grand_opening_sales set is_participating=1 where designer_id=1');
DB::exec('update designers set status="disabled" where id=1');
$check($engine->cart([['id'=>1,'license_type'=>'personal']])['items']===[],'Disabled seller is not purchasable');
DB::exec('update designers set status="approved" where id=1');
DB::exec('update products set status="pending_review" where id=1');
$check($engine->cart([['id'=>1,'license_type'=>'personal']])['items']===[],'Unapproved product is not purchasable');
DB::exec('update products set status="approved" where id=1');
DB::exec('update grand_opening_events set starts_at=date_add(utc_timestamp(),interval 1 day) where id=1');
$check($engine->cart($input)['subtotal_cents']===4500,'Upcoming sale has regular prices');
$check($engine->saleProducts()===[],'Upcoming sale has no sale homepage products');
DB::exec('update grand_opening_events set starts_at=date_sub(utc_timestamp(),interval 8 day),ends_at=utc_timestamp() where id=1');
$check($engine->cart($input)['subtotal_cents']===4500,'Ended sale reverts prices automatically');
$check($engine->saleProducts()===[] && $engine->saleSellers()===[],'Ended sale removes event features');
DB::exec('update grand_opening_events set starts_at=utc_timestamp(),ends_at=date_add(utc_timestamp(),interval 8 day) where id=1');
$check($engine->cart($input)['subtotal_cents']===3450,'Start boundary activates prices');
$featured=$engine->saleProducts();
$featuredIds=array_map('intval',array_column($featured,'id'));
$check(in_array(1,$featuredIds,true) && in_array(2,$featuredIds,true) && !in_array(3,$featuredIds,true),'Homepage includes only eligible sale products');
$check(count($engine->saleSellers())===2,'Sale seller list requires eligible products');
$fixture('homepage_features',['feature_type'=>'product','feature_id'=>1,'sort_order'=>0,'is_active'=>1]);
$check((int)$engine->saleProducts()[0]['id']===1,'Existing homepage curation prioritizes sale products');
$quoted=$engine->decorate([['id'=>1,'price'=>'999.00'],['id'=>1,'listing_type'=>'custom','price'=>'77.00']]);
$check($quoted[0]['price']==='8.00' && $quoted[1]['price']==='77.00','Display uses same price and leaves custom services alone');
$sql=DB::row('select '.Pricing::priceSql().' paid from products p where p.id=1');
$check((float)$sql['paid']===8.0,'Browse SQL price agrees with pricing engine');
$filters=['q'=>'','category'=>'','ai'=>'','pod'=>'','creator'=>'','min_price'=>'','max_price'=>'','featured'=>'','new'=>'','file_type'=>'','commercial'=>'','grand_opening'=>'1'];
$browse=new ReflectionMethod(PublicController::class,'browseQuery');$browse->setAccessible(true);
$result=$browse->invoke(new PublicController(),$filters,[],'price_asc',1);
$check($result['total']===count($featured),'Sale browse count uses actual eligibility');
$check((int)$result['products'][0]['id']===1,'Sale browse sorts by effective price');
$filters['min_price']='9';
$filtered=$browse->invoke(new PublicController(),$filters,[],'price_asc',1);
$check(!in_array(1,array_map('intval',array_column($filtered['products'],'id')),true),'Minimum-price filter uses discounted price');
$prize=PrizeCreditService::allocateCart($sale['items'],[],99999,true);
$check($prize['seller_cents'][1]===500,'Prize Credit seller cap follows sale');
$check($prize['seller_cents'][2]===562,'Percentage Prize Credit uses discounted merchandise');
$credits=CreditService::checkoutBreakdown('34.50','0.00','2.07','100.00',true);
$check($credits['credit_cents']===3657,'Marketplace Credit follows sale and includes tax');

$renderHome=static function(array $event) use ($engine): string {
    $openingEvent=$event;
    $openingProducts=$engine->saleProducts();$openingSellers=$engine->saleSellers();
    $cats=[];$products=[];$recentProducts=[];$designers=[];$collabs=[];$partySellers=[];$homepagePromo=null;
    ob_start();
    try {include app_path('app/Views/public/home.php');return ob_get_clean();}
    catch (Throwable $e) {ob_end_clean();throw $e;}
};
$event=(new Sale())->event();
$liveHtml=$renderHome($event);
$check(str_contains($liveHtml,'id="grand-opening-sale"') && str_contains($liveHtml,'fixture-product-1'),'Live homepage shows actual sale products');
$upcoming=array_merge($event,['starts_at'=>gmdate('Y-m-d H:i:s',time()+86400)]);
$upcomingHtml=$renderHome($upcoming);
$check(str_contains($upcomingHtml,'Something creative is about to take flight.') && !str_contains($upcomingHtml,'id="grand-opening-sale"'),'Upcoming homepage announces dates without selling early');
$ended=array_merge($event,['ends_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
$endedHtml=$renderHome($ended);
$check(str_contains($endedHtml,'Shop downloadable designs from independent creators.') && !str_contains($endedHtml,'id="grand-opening-sale"'),'Ended homepage automatically restores normal hero');

DB::pdo()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
DB::begin();
$locked=$engine->cart($input,'LOW',1,true);
$check($locked['subtotal_cents']===3450,'Locked checkout quote matches display');
$other=new PDO(getenv('CM_SALE_TEST_DSN'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$other->exec('set innodb_lock_wait_timeout=1');
$blocked=false;
try {$other->exec('update grand_opening_events set is_enabled=0 where id=1');}
catch (PDOException $e) {$blocked=($e->errorInfo[1]??0)===1205;}
$check($blocked,'Checkout locks prevent concurrent event changes');
$engine->snapshot(999,$locked['items'][0]);
$engine->snapshot(999,$locked['items'][0]);
$check((int)DB::row('select count(*) n from grand_opening_pricing_snapshots where order_item_id=999')['n']===1,'Pricing snapshot replay is idempotent');
$conflict=$locked['items'][0];$conflict['price']='0.01';$rejected=false;
try {$engine->snapshot(999,$conflict);}catch (DomainException $e) {$rejected=true;}
$check($rejected,'Conflicting replay cannot rewrite price history');
DB::commit();

$fixture('cart_items',['id'=>1,'user_id'=>1,'product_id'=>1,'license_type'=>'personal,commercial','quantity'=>1]);
$probe=__DIR__.'/helpers/GrandOpeningCheckoutProbe.php';
$runCheckout=static function(string $coupon) use ($probe,$check): array {
    $trace=tempnam(sys_get_temp_dir(),'cm-sale-trace-');
    try {
        $process=proc_open([PHP_BINARY,$probe,$trace,$coupon],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start checkout probe.');
        fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
        $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        $exit=proc_close($process);
        $check($exit===0,'Checkout probe completes: '.$stdout.$stderr);
        $requests=json_decode(file_get_contents($trace),true,512,JSON_THROW_ON_ERROR);
        $check(count($requests)===2,'Checkout uses only mocked tax and payment calls');
        $order=DB::row('select * from orders order by id desc limit 1');
        $check(!empty($order['stripe_checkout_session_id']),'Checkout persisted its payment session: '.$stderr);
        return [$order,$requests];
    } finally {unlink($trace);}
};
[$saleOrder,$saleCalls]=$runCheckout('');
$saleItem=DB::row('select * from order_items where order_id=?',[$saleOrder['id']]);
$check((float)$saleOrder['subtotal']===12.0 && (float)$saleOrder['coupon_discount']===0.0,'Real cart checkout stores sale without coupon stacking');
$check((float)$saleItem['unit_price']===8.0 && (float)$saleItem['license_price']===4.0 && (float)$saleItem['total_price']===12.0,'Order item snapshots paid product and license amounts');
$check($saleCalls[0]['params']['line_items'][0]['amount']===1200,'Stripe Tax receives discounted merchandise');
$check($saleCalls[1]['params']['line_items'][0]['price_data']['unit_amount']===1272,'Stripe charge equals discounted merchandise plus tax');
$check((float)$saleItem['platform_commission_amount']===1.38 && (float)$saleItem['seller_payout_amount']===10.62,'Fee stays 9 percent plus 30 cents on sale value');
$licenseSnapshot=json_decode($saleItem['license_snapshot'],true,512,JSON_THROW_ON_ERROR);
$commercial=array_values(array_filter($licenseSnapshot,static fn(array $l):bool=>$l['key']==='commercial'));
$check((float)$commercial[0]['price']===4.0,'Purchased license snapshot preserves discounted price');
[$couponOrder,$couponCalls]=$runCheckout('HIGH');
$couponItem=DB::row('select * from order_items where order_id=?',[$couponOrder['id']]);
$check((float)$couponOrder['subtotal']===15.0 && (float)$couponOrder['coupon_discount']===4.5 && (float)$couponItem['total_price']===10.5,'Real checkout applies larger coupon alone');
$check($couponCalls[0]['params']['line_items'][0]['amount']===1050 && $couponCalls[1]['params']['line_items'][0]['price_data']['unit_amount']===1113,'Coupon-only tax and charge match final merchandise');
DB::exec('update grand_opening_events set ends_at=utc_timestamp() where id=1');
$check((float)DB::row('select total_price from order_items where id=?',[$saleItem['id']])['total_price']===12.0,'Event expiry preserves past order prices');
$refunds=new MarketplaceRefundService();
$ob=$refunds->observe((int)$saleOrder['id'],'fixture-refund-partial',500);
$refunds->completeAllocation((int)$ob['id'],[(int)$saleItem['id']=>500],0);
$check((int)$refunds->allocations((int)$saleOrder['id'])[0]['refunded_cents']===500,'Partial refund uses stored paid merchandise');
$over=$refunds->observe((int)$saleOrder['id'],'fixture-refund-over',1300);
$rejected=false;
try {$refunds->completeAllocation((int)$over['id'],[(int)$saleItem['id']=>800],0);}
catch (DomainException $e) {$rejected=true;}
$check($rejected,'Refund cannot exceed remaining discounted merchandise');
$history=DB::row('select pricing_snapshot from grand_opening_pricing_snapshots where order_item_id=?',[$saleItem['id']]);
$history=json_decode($history['pricing_snapshot'],true,512,JSON_THROW_ON_ERROR);
$check((float)$history['regular_total']===15.0 && $history['merchandise_cents']===1200,'Historical record retains regular and paid totals after expiry');
echo "PASS: $checks Grand Opening pricing/checkout/refund checks\n";
