<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database as DB;
use App\Services\ProductScheduleService;
use App\Services\ProductSubmissionService;
if(getenv('RUN_DISPOSABLE_DB_TESTS')!=='1'){echo "SKIP: RUN_DISPOSABLE_DB_TESTS=1 and disposable MariaDB credentials are required.\n";exit(0);}
$pdo=DB::pdo();$original=$pdo->query('select database()')->fetchColumn();$fixture='phase152_fixture_'.bin2hex(random_bytes(4));$root=dirname(__DIR__);$fail=0;$server=null;
$check=function(bool $ok,string $name)use(&$fail){echo ($ok?'PASS':'FAIL').": $name\n";if(!$ok)$fail++;};
$tmp=sys_get_temp_dir().'/'.$fixture;mkdir($tmp);
try {
    $pdo->exec("create database `$fixture` character set utf8mb4");$pdo->exec("use `$fixture`");
    $schema=file_get_contents($root.'/database/schema.sql');
    // Exercise the migration against the schema before Phase 15.2, then test the result.
    $schema=str_replace(["    timezone VARCHAR(64) NULL,\n","    scheduled_publish_at DATETIME NULL COMMENT 'UTC publication instant',\n","    publication_timezone VARCHAR(64) NULL,\n","    schedule_error VARCHAR(1000) NULL,\n","    schedule_checked_at DATETIME NULL COMMENT 'UTC last eligibility check',\n","    KEY products_schedule_due_idx(status,scheduled_publish_at,id),\n","'pending_review','scheduled','approved'"],['','','','','','',"'pending_review','approved'"],$schema);
    $split=strpos($schema,'ALTER TABLE orders ADD COLUMN marketplace_fee_model');
    $pdo->exec(substr($schema,0,$split));
    foreach(['2026_07_01_phase_9_cart_orders_downloads_delivery.sql','2026_07_02_phase_10_stripe_payment_integration.sql'] as $file)$pdo->exec(file_get_contents($root.'/database/migrations/'.$file));
    $pdo->exec(substr($schema,$split));
    $pdo->exec(file_get_contents($root.'/database/migrations/2026_10_07_phase_15_2_product_schedules.sql'));
    $check((bool)DB::row("select column_name from information_schema.columns where table_schema=? and table_name='products' and column_name='scheduled_publish_at'",[$fixture]),'Phase 15.2 migration applies to the previous schema');
    DB::exec('insert into users(name,email,password_hash,role,status) values("Seller","phase152@example.test","x","designer","active")');$user=(int)DB::id();
    DB::exec('insert into designers(user_id,display_name,store_slug,bio,status,stripe_connect_account_id,stripe_details_submitted,stripe_payouts_enabled) values(?,"Schedule Shop","phase152-shop","Complete biography","approved","acct_test",1,1)',[$user]);$designer=(int)DB::id();
    DB::exec('insert into categories(name,slug,is_active) values("Phase152 Art","phase152-art",1)');$category=(int)DB::id();
    // Real HTTP requests preserve uploaded-file checks and mapping sessions.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
    $port=substr($address,strrpos($address,':')+1);
    $env=array_merge(getenv(),$_ENV,['RUN_DISPOSABLE_DB_TESTS'=>'1','PHASE152_FIXTURE_DB'=>$fixture,'PHASE152_SELLER_USER'=>(string)$user]);
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,$root.'/tests/helpers/Phase152HttpProbe.php'],[0=>['pipe','r'],1=>['file',$tmp.'/http.log','a'],2=>['file',$tmp.'/http.log','a']],$pipes,$root,$env);
    for($attempt=0;$attempt<50;$attempt++){ $connection=@fsockopen('127.0.0.1',(int)$port);if($connection){fclose($connection);break;}usleep(20000); }
    $request=function(string $action,array $post=[],array $query=[],bool $send=false)use($port,$tmp):array {
        $ch=curl_init('http://127.0.0.1:'.$port.'/?'.http_build_query(['action'=>$action]+$query));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$tmp.'/cookies',CURLOPT_COOKIEJAR=>$tmp.'/cookies',CURLOPT_TIMEOUT=>10]);
        if($post||$send)curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
        $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($body===false||$code>=500)throw new RuntimeException("HTTP $action failed ($code): $error $body");
        return [$code,$body];
    };
    [$code]=$request('upload',['source_platform'=>'etsy','csv_file'=>new CURLFile(__DIR__.'/fixtures/phase152/etsy-listings.csv','text/csv','etsy.csv')]);
    $run=(int)DB::row('select max(id) id from product_import_runs')['id'];
    $check($code===302&&$run>0&&DB::row('select total_detected from product_import_runs where id=?',[$run])['total_detected']===2,'ordinary Etsy CSV uploads through the real controller into preview');
    [, $preview]=$request('preview',[],['id'=>$run]);$check(str_contains($preview,'Botanical')&&str_contains($preview,'Minimal Fern'),'import review displays both Etsy products');
    $request('confirm',['select_all'=>'1'],['id'=>$run]);
    for($i=0;$i<10&&DB::row('select status from product_import_runs where id=?',[$run])['status']==='processing';$i++)$request('process',[],['id'=>$run],true);
    $runRow=DB::row('select * from product_import_runs where id=?',[$run]);
    $check($runRow['status']==='completed'&&(int)$runRow['imported_count']===2,'Etsy confirmation and processing complete successfully');
    $product=DB::row('select p.* from products p join product_import_items i on i.product_id=p.id where i.import_run_id=? order by i.id limit 1',[$run]);$id=(int)$product['id'];
    $check($product['status']==='draft'&&$product['ai_disclosure']===null&&$product['price']==='4.50'&&str_contains($product['description'],"Second line"),'import creates a populated draft with explicit seller review');
    $check(DB::row('select source_sku from product_import_items where product_id=?',[$id])['source_sku']==='MOON-1'&&count(DB::rows('select * from product_tags where product_id=?',[$id]))===3,'SKU metadata and tags persist through draft creation');
    [, $summary]=$request('summary',[],['id'=>$run]);$check(str_contains($summary,'Botanical'),'completed import renders its seller summary');
    $request('upload',['source_platform'=>'etsy','csv_file'=>new CURLFile(__DIR__.'/fixtures/phase152/etsy-listings.csv','text/csv','etsy.csv')]);
    $repeat=(int)DB::row('select max(id) id from product_import_runs')['id'];
    $check((int)DB::row('select count(*) c from product_import_items where import_run_id=? and result_status="duplicate"',[$repeat])['c']===2,'re-import recognizes SKU and fallback-title duplicates');
    [, $mapping]=$request('upload',['source_platform'=>'etsy','csv_file'=>new CURLFile(__DIR__.'/fixtures/phase152/etsy-manual.csv','text/csv','manual.csv')]);
    $check(str_contains($mapping,'mapping[title]'),'unknown Etsy headers show the manual mapping form');
    $request('mapping',['mapping[source_id]'=>'External Key','mapping[title]'=>'Artwork Name','mapping[description]'=>'Copy','mapping[price]'=>'Amount','mapping[sku]'=>'Reference','mapping[tags]'=>'Keywords']);
    $manual=(int)DB::row('select max(id) id from product_import_runs')['id'];$request('confirm',['select_all'=>'1'],['id'=>$manual]);
    for($i=0;$i<6&&DB::row('select status from product_import_runs where id=?',[$manual])['status']==='processing';$i++)$request('process',[],['id'=>$manual],true);
    $check(DB::row('select status from product_import_runs where id=?',[$manual])['status']==='completed','manual mapping persists and completes its import');
    $blocked=(new ProductSubmissionService())->submit($id,$designer,$user);$check(!$blocked['ok']&&str_contains($blocked['error'],'file'),'imported drafts cannot publish without required source files and review');
    // Attach actual seller uploads and finish import review with the normal editor.
    $image=imagecreatetruecolor(80,80);imagepng($image,$tmp.'/preview.png');imagedestroy($image);file_put_contents($tmp.'/download.txt','Protected seller source file.');
    $future=(new DateTimeImmutable('now',new DateTimeZone('America/Detroit')))->modify('+2 days')->format('Y-m-d\TH:i');
    $post=['title'=>$product['title'],'description'=>$product['description'],'price'=>'4.50','category_id'=>(string)$category,'ai_disclosure'=>'No AI Used','fulfillment_type'=>'downloadable','confirm_import_licenses'=>'1','confirm_import_fulfillment'=>'1','tags'=>'botanical,moon,printable','action'=>'review','publish_mode'=>'scheduled','publication_timezone'=>'America/Detroit','scheduled_local'=>$future];
    [$code,$body]=$request('edit',$post+['preview_images[0]'=>new CURLFile($tmp.'/preview.png','image/png','preview.png'),'product_files[0]'=>new CURLFile($tmp.'/download.txt','text/plain','download.txt')],['id'=>$id]);
    $p=DB::row('select * from products where id=?',[$id]);
    $check($code===302&&$p['status']==='scheduled'&&$p['scheduled_publish_at']===ProductScheduleService::toUtc($future,'America/Detroit'),'seller uploads, final review and schedule submission complete end-to-end');
    $check(DB::row('select timezone from designers where id=?',[$designer])['timezone']==='America/Detroit','seller timezone preference is saved');
    [, $newForm]=$request('edit');$check(str_contains($newForm,'value="America/Detroit" selected'),'new product form reuses the saved seller timezone preference');
    foreach(['home','browse','category','store','sitemap'] as $action){[, $body]=$request($action,[],['slug'=>$action==='category'?'phase152-art':'phase152-shop']);$check(!str_contains($body,$p['slug']),"scheduled product is absent from public $action");}
    [, $body]=$request('browse',[],['q'=>'Botanical']);$check(!str_contains($body,$p['slug']),'scheduled product is absent from search');
    [$code]=$request('product',[],['slug'=>$p['slug']]);$check($code===404,'direct public scheduled product URL returns 404');
    $worker=new ProductScheduleService();$before=(new DateTimeImmutable($p['scheduled_publish_at'],new DateTimeZone('UTC')))->modify('-1 second');
    $check($worker->processDue($before)['published']===0,'worker does not publish before the UTC instant');
    $later=(new DateTimeImmutable($future,new DateTimeZone('America/Detroit')))->modify('+1 day')->format('Y-m-d\TH:i');
    $request('edit',array_replace($post,['scheduled_local'=>$later,'action'=>'draft']),['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);
    $check($p['status']==='scheduled'&&$p['scheduled_publish_at']===ProductScheduleService::toUtc($later,'America/Detroit'),'seller can reschedule an approved private listing');
    $request('edit',array_replace($post,['publish_mode'=>'immediate','action'=>'draft']),['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);
    [$code]=$request('product',[],['slug'=>$p['slug']]);
    $check($p['status']==='draft'&&$p['scheduled_publish_at']===null&&$code===404,'scheduled listing saved as draft with immediate selected clears its schedule and stays private');
    $request('edit',$post,['id'=>$id]);
    $request('edit',array_replace($post,['publish_mode'=>'cancel','action'=>'draft']),['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);
    $check($p['status']==='draft'&&$p['scheduled_publish_at']===null,'cancelling scheduling keeps the listing as a private draft');
    $request('edit',$post,['id'=>$id]);
    $request('edit',array_replace($post,['publish_mode'=>'immediate','action'=>'review']),['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);
    $check($p['status']==='approved'&&$p['scheduled_publish_at']===null,'switching from scheduled to immediate honors the publication workflow');
    $request('edit',array_replace($post,['publish_mode'=>'immediate']),['id'=>$id]);$check(DB::row('select status from products where id=?',[$id])['status']==='approved','ordinary unscheduled publishing remains unchanged');
    $request('edit',$post,['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);$due=new DateTimeImmutable($p['scheduled_publish_at'],new DateTimeZone('UTC'));
    $result=$worker->processDue($due);$check($result['published']===1&&DB::row('select status from products where id=?',[$id])['status']==='approved','eligible ordinary listing publishes at its exact scheduled instant');
    [$code,$body]=$request('product',[],['slug'=>$p['slug']]);$check($code===200&&str_contains($body,'Botanical'),'published listing becomes accessible through the public product route');
    $check($worker->processDue($due)['published']===0,'repeated scheduled worker runs do not republish');
    $request('edit',$post,['id'=>$id]);$p=DB::row('select * from products where id=?',[$id]);
    [$code,$body]=$request('edit',array_replace($post,['scheduled_local'=>'2000-01-01T12:00']),['id'=>$id]);
    $check(str_contains($body,'must be in the future')&&DB::row('select scheduled_publish_at from products where id=?',[$id])['scheduled_publish_at']===$p['scheduled_publish_at'],'past scheduling is rejected without changing the saved schedule');
    $deleteFile=new ReflectionMethod(\App\Controllers\SellerController::class,'deleteProductFile');foreach(DB::rows('select id from product_files where product_id=?',[$id]) as $file)$deleteFile->invoke(new \App\Controllers\SellerController(),(int)$file['id'],$id);$result=$worker->processDue($due);
    $check($result['published']===0&&$result['blocked']===1&&DB::row('select status,schedule_error from products where id=?',[$id])['status']==='scheduled','a listing losing required files stays unavailable at its due time');
    DB::exec('insert into product_files(product_id,storage_path,original_name,file_size,mime_type) values(?,"test.txt","test.txt",1,"text/plain")',[$id]);
    DB::exec('insert into ip_risk_terms(term,normalized_term,category,is_enabled) values("Botanical","botanical","brand",1)');
    $result=$worker->processDue($due);$check($result['published']===0&&str_contains(DB::row('select schedule_error from products where id=?',[$id])['schedule_error'],'IP review'),'new IP risks cannot bypass review at scheduled publication');
    $request('edit',array_replace($post,['publish_mode'=>'immediate','ip_rights_confirmation'=>'1']),['id'=>$id]);
    $check(DB::row('select status from products where id=?',[$id])['status']==='pending_review','switching to immediate cannot bypass a newly required IP review');
    // Restore the future request while preserving its pending review for moderation checks.
    $request('edit',array_replace($post,['ip_rights_confirmation'=>'1']),['id'=>$id]);
    DB::exec('update products set status="pending_review" where id=?',[$id]);$check($worker->processDue($due)['published']===0,'pending approval never publishes through the scheduled worker');
    DB::exec('insert into users(name,email,password_hash,role,status) values("Admin","phase152-admin@example.test","x","admin","active")');$admin=(int)DB::id();
    $_SESSION['user']=['id'=>$admin,'role'=>'admin'];
    $ipRepo=new \App\Repositories\IpRiskRepository();
    $transition=$ipRepo->applyAdminReviewTransition($id,'published_flagged','Reviewed fixture',$admin);
    $check(DB::row('select status from products where id=?',[$id])['status']==='scheduled','IP moderation approval preserves the future schedule');
    $ipRepo->applyAdminReviewTransition($id,'approve','Approved fixture',$admin);
    DB::exec('update products set status="pending_review" where id=?',[$id]);
    $moderate=new ReflectionMethod(\App\Controllers\AdminController::class,'moderateProduct');
    $moderate->invoke(new \App\Controllers\AdminController(),$id,'approve','',false);
    $check(DB::row('select status from products where id=?',[$id])['status']==='scheduled','normal admin approval preserves the schedule');
    $check($worker->processDue($due)['published']===1,'reviewed eligible scheduled listing publishes after admin approval');
    for($i=0;$i<101;$i++)DB::exec('insert into products(designer_id,title,slug,description,price,ai_disclosure,status,scheduled_publish_at) values(?,?,?,"Blocked fixture",1,"No AI Used","scheduled",?)',[$designer,'Blocked '.$i,'blocked-'.$i,$due->format('Y-m-d H:i:s')]);
    $worker->processDue($due);$worker->processDue($due->modify('+1 minute'));
    $check((int)DB::row('select count(*) c from products where slug like "blocked-%" and schedule_checked_at is not null')['c']===101,'blocked listings do not starve later due listings across bounded worker runs');
    $check((int)DB::row('select count(*) c from collab_events')['c']===0,'ordinary publication processing leaves collaboration events unchanged');
} catch(Throwable $e){$check(false,'integration exception: '.$e->getMessage());if(is_file($tmp.'/http.log'))echo substr(file_get_contents($tmp.'/http.log'),-4000);}
finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($pdo->inTransaction())$pdo->rollBack();
    try {
        foreach(DB::rows('select stored_path from product_import_runs where stored_path is not null') as $run)(new \App\Services\CsvProductImportService())->removeTemporaryFile($run['stored_path']);
        $seller=new \App\Controllers\SellerController();
        $cleanup=new ReflectionMethod($seller,'cleanupProductUploadRowsAndFiles');
        foreach(DB::rows('select id from products where designer_id=?',[$designer??0]) as $row)$cleanup->invoke($seller,(int)$row['id']);
    } catch(Throwable) {}
    $pdo->exec("use `$original`");$pdo->exec("drop database if exists `$fixture`");
    foreach(glob($tmp.'/*')?:[] as $file)unlink($file);rmdir($tmp);
}
exit($fail?1:0);
