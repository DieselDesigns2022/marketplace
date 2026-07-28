<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\SellerReceiptService;
use App\Services\StripeService;
use App\Controllers\StripeController;
$failures=[];$check=function(bool $ok,string $message)use(&$failures){echo ($ok?'PASS':'FAIL').": $message\n";if(!$ok)$failures[]=$message;};
$source=fn(string $path)=>file_get_contents(dirname(__DIR__).'/'.$path);
$check(SellerReceiptService::normalizeNote(" hello\r\nworld ")==="hello\nworld",'receipt notes are normalized');
try{SellerReceiptService::normalizeNote('<script>alert(1)</script>');$plain=false;}catch(RuntimeException){$plain=true;}$check($plain,'seller HTML is rejected');
try{SellerReceiptService::normalizeNote(str_repeat('x',501));$length=false;}catch(RuntimeException){$length=true;}$check($length,'notes are limited to 500 characters');
$check(SellerReceiptService::MAX_IMAGE_BYTES===5*1024*1024&&SellerReceiptService::MAX_IMAGE_DIMENSION===1600,'image size and dimensions are bounded');
$check(SellerReceiptService::isSafePath('/uploads/receipt_images/'.str_repeat('a',32).'.webp')&&!SellerReceiptService::isSafePath('/uploads/receipt_images/x.svg')&&!SellerReceiptService::isSafePath('/etc/passwd'),'receipt image paths and types are allowlisted');
$check(!SellerReceiptService::canDeletePath('/uploads/receipt_images/'.str_repeat('b',32).'.png',true)&&SellerReceiptService::canDeletePath('/uploads/receipt_images/'.str_repeat('b',32).'.png',false),'referenced images are retained and unreferenced images may be removed');
$check(SellerReceiptService::supportsTransparency('image/png')&&SellerReceiptService::supportsTransparency('image/webp')&&!SellerReceiptService::supportsTransparency('image/jpeg'),'PNG and WEBP transparency behavior is testable');
$check(SellerReceiptService::encoderForMime('image/gif')===null&&in_array(SellerReceiptService::encoderForMime('image/png'),[null,'imagepng'],true),'encoder availability is checked without invoking a missing function');
foreach(['Customs/Personalized','CUSTOMS / PERSONALIZED','customs-personalized',' customs -- personalized '] as $variant)$check(SellerReceiptService::normalizeCategoryKey($variant)==='customspersonalized',"category punctuation normalization: $variant");
$categories=['Engagement Graphics'=>'engagement-graphics','Social Media Graphics'=>'social-media-graphics','Libby Wraps'=>'libby-wraps','Digital Papers'=>'digital-papers','Freebies'=>'freebies','Digital Services'=>'digital-services','Customs / Personalized'=>'customs-personalized'];$migration=$source('database/migrations/2026_07_28_phase_10_6_dashboard_cleanup_usability.sql');foreach($categories as $name=>$slug)$check(str_contains($migration,"'$name','$slug'"),"canonical category $slug is declared");$check(str_contains($migration,'coupon_restrictions')&&str_contains($migration,'REGEXP_REPLACE'),'category duplicates preserve coupon restrictions and normalize punctuation');
$cart=$source('app/Controllers/CartController.php');$check(str_contains($cart,'$total <= 0'),'Freebies retain zero-total blocking');$check(str_contains($cart,"select receipt_note,receipt_image_path from designers")&&str_contains($cart,'snapshotFromSeller'),'checkout snapshots fresh, safe current settings');
$product=$source('app/Views/seller/edit_product.php');$check(str_contains($product,'downloadable or Google Drive / manual-delivery'),'service/custom categories retain fulfillment choices');
$js=$source('assets/js/app.js');$auth=$source('app/Views/auth/login.php').$source('app/Views/auth/register.php');$check(str_contains($js,'aria-pressed')&&str_contains($js,'aria-label')&&str_contains($auth,'aria-controls'),'password toggle states and accessible relationships are explicit');
$nav=$source('app/Views/partials/dashboard_nav.php');$check(str_contains($nav,"'/seller/product/'")&&str_contains($nav,"'/seller/order-item/'")&&str_contains($nav,"'/admin/email-campaigns'")&&str_contains($nav,'aria-current="page"'),'dynamic dashboard navigation routes are matched');
$buyer=$source('app/Controllers/BuyerController.php');$downloads=$source('app/Views/buyer/downloads.php');$check(str_contains($source('app/Views/buyer/home.php'),"\$n['message']"),'buyer dashboard uses notification message field');$check(str_contains($buyer,'left join product_files pf')&&str_contains($buyer,'pf.id file_id'),'download history returns multiple protected product files');$check(strpos($downloads,"==='refunded'")<strpos($downloads,'elseif($available)'),'refunded status has precedence');
$sellerController=$source('app/Controllers/SellerController.php');$check(str_contains($sellerController,"['save','remove_image','remove_note','restore']")&&str_contains($sellerController,"\$action==='save'"),'receipt actions are explicitly allowlisted and uploads only apply to save');
$groups=SellerReceiptService::groupItemsBySeller([['designer_id'=>1,'seller_name'=>'A','seller_receipt_note_snapshot'=>'one'],['designer_id'=>2,'seller_name'=>'B','seller_receipt_note_snapshot'=>'two'],['designer_id'=>1,'seller_name'=>'A','seller_receipt_note_snapshot'=>'one']]);$check(count($groups)===2&&count($groups[0]['items'])===2,'multi-seller items remain grouped');
$email=$source('app/Views/emails/purchase_receipt.php');$check(str_contains($email,"H::e(\$group['receipt_note'])")&&str_contains($email,'Order number')&&str_contains($email,'Final paid total'),'seller receipt content is escaped and platform receipt data remains');
$admin=$source('app/Views/admin/home.php');$seller=$source('app/Views/seller/home.php');foreach(['Attention Required','Marketplace Statistics','Waitlist','Recent Activity','Recent Admin Notifications'] as $label)$check(str_contains($admin,$label),"admin dashboard section: $label");foreach(['Setup Checklist','Stripe &amp; Payout Status','Tax Status','Sales, Earnings &amp; Payouts','Recent Orders','Notifications','Quick Actions'] as $label)$check(str_contains($seller,$label),"seller dashboard section: $label");

$buyerController=$source('app/Controllers/BuyerController.php');$buyerDownloads=$source('app/Views/buyer/downloads.php');
$check(str_contains($buyerController,'protectedFileAvailable')&&str_contains($buyerController,'is_file($real)')&&str_contains($buyerController,'is_readable($real)'),'buyer availability requires a contained readable protected file');
$check(str_contains($buyerController,"['file_available']")&&str_contains($buyerDownloads,"['file_available']"),'download rows and buttons require file_available');
$serviceSource=$source('app/Services/SellerReceiptService.php');
$check(SellerReceiptService::MAX_SOURCE_PIXELS===25000000&&SellerReceiptService::sourceDimensionsAllowed(5000,5000)&&!SellerReceiptService::sourceDimensionsAllowed(5001,5000)&&!SellerReceiptService::sourceDimensionsAllowed(0,100),'source-pixel limit rejects invalid or oversized dimensions');
$check(strpos($serviceSource,'sourceDimensionsAllowed($width,$height)')<strpos($serviceSource,'imagecreatefromstring($bytes)'),'source-pixel rejection occurs before GD decode');
$fallback=SellerReceiptService::snapshotFromSeller(['receipt_note'=>'<b>invalid</b>','receipt_image_path'=>'/etc/passwd']);$check($fallback===['note'=>null,'image_path'=>null],'invalid optional receipt settings safely fall back to null snapshots');
$sellerControllerSource=$source('app/Controllers/SellerController.php');$check(!str_contains($sellerControllerSource,'stripe_onboarding_started_at'),'seller dashboard does not reference undocumented Stripe onboarding timestamp');
$check(substr_count($nav,"['/account','Account'")===3,'buyer, seller, and admin navigation include Account');
$check(str_contains($source('app/Controllers/AdminController.php'),'payment_status in ("failed","manual_review")'),'admin payment warnings include manual-review status');
$sellerHome=$source('app/Views/seller/home.php');$check(str_contains($sellerHome,'Resolve payout issues')&&str_contains($sellerHome,'/seller/stripe'),'seller payout issues provide a Stripe action');
$receiptViews=$source('app/Views/buyer/order.php').$source('app/Views/seller/receipt_settings.php');$check(substr_count($receiptViews,'H::assetUrl(')>=2&&substr_count($receiptViews,'safePublicPath')+substr_count($receiptViews,"['receipt_image_path']")>=2,'validated receipt images render through assetUrl');

$buyerOrderView=$source('app/Views/buyer/order.php');
$check(str_contains($buyerController,'file_storage_path')&&str_contains($buyerController,"\$item['file_available']=\$this->protectedFileAvailable"),'buyer order supplies protected file availability');
$check(str_contains($buyerOrderView,"!empty(\$i['file_available'])")&&str_contains($buyerOrderView,'File unavailable.'),'buyer order download button requires file_available');
$slugPriority=strpos($migration,"TRIM(duplicate.slug)");$nameFallback=strpos($migration,"TRIM(duplicate.name)");
$check(str_contains($migration,'COALESCE(')&&str_contains($migration,'LIMIT 1')&&$slugPriority!==false&&$nameFallback!==false&&$slugPriority<$nameFallback,'category duplicate mapping selects one deterministic target with canonical-slug priority');
$check(str_contains($migration,'selected.canonical_id<>selected.duplicate_id'),'category duplicate mapping excludes each canonical category own ID');

$refundItems=[['id'=>1,'total_price'=>'10.00','commission_rate'=>.20],['id'=>2,'total_price'=>'20.00','commission_rate'=>.20]];
$partial=StripeController::allocateSellerRefund($refundItems,1650,300);$full=StripeController::allocateSellerRefund($refundItems,3300,300);
$check(array_sum(array_column($partial,'gross_refund_cents'))===1500&&array_sum(array_column($partial,'seller_refund_cents'))===1200,'partial cumulative refund is cents-safe and excludes proportional tax');
$check(array_sum(array_column($full,'gross_refund_cents'))===3000&&array_sum(array_column($full,'seller_refund_cents'))===2400,'full cumulative refund reconciles merchandise and excludes tax');
$check($partial===StripeController::allocateSellerRefund($refundItems,1650,300)&&str_contains($sellerControllerSource,'max(amount) cumulative_refund'),'refund replay is deterministic and dashboard uses highest authoritative cumulative amount');
$stripeControllerSource=$source('app/Controllers/StripeController.php');$check(str_contains($stripeControllerSource,'max(amount) amount')&&str_contains($stripeControllerSource,'payout_status<>"transferred"'),'refund reconciliation applies the highest cumulative amount only to non-transferred ledgers');
$pngChunk=static function(string $type,string $data):string{return pack('N',strlen($data)).$type.$data.hash('crc32b',$type.$data,true);};
$png="\x89PNG\r\n\x1a\n".$pngChunk('IHDR',pack('NNCCCCC',1,1,8,6,0,0,0)).$pngChunk('IEND','');
$webp='RIFF'.pack('V',4).'WEBP';$jpeg="\xFF\xD8\xFF\xD9";
$check(SellerReceiptService::hasExactImageContainer($png,'image/png')&&!SellerReceiptService::hasExactImageContainer($png.'payload','image/png'),'PNG requires IEND at exact file end');
$check(SellerReceiptService::hasExactImageContainer($webp,'image/webp')&&!SellerReceiptService::hasExactImageContainer($webp.'payload','image/webp'),'WEBP RIFF length must exactly match file length');
$check(SellerReceiptService::hasExactImageContainer($jpeg,'image/jpeg')&&!SellerReceiptService::hasExactImageContainer($jpeg.'payload','image/jpeg'),'JPEG requires its valid EOI marker at exact file end');
$check(strpos($serviceSource,'hasExactImageContainer($bytes,$mime)')<strpos($serviceSource,'imagecreatefromstring($bytes)'),'trailing-data validation runs before GD decode');
$check(StripeService::connectedAccountStatus(['id'=>'acct','requirements'=>['currently_due'=>['business_profile.url']]])==='information_required','Stripe unresolved requirements map to information_required');
$check(StripeService::connectedAccountStatus(['id'=>'acct','details_submitted'=>true,'payouts_enabled'=>true])==='payout_ready','Stripe completed account maps to payout_ready');
$check(in_array(StripeService::connectedAccountStatus(['id'=>'acct','requirements'=>['disabled_reason'=>'requirements.past_due']]),['restricted','disabled'],true),'Stripe disabled requirements map to a payout-issue status');

$adminDestinations=['/admin','/admin/users','/admin/applications','/admin/designers','/admin/products','/admin/ip-risk-terms','/admin/categories','/admin/coupons','/admin/orders','/admin/referrals','/admin/homepage','/admin/ads','/admin/payment-logs','/admin/waitlist','/admin/email-campaigns','/account','/notifications'];
$adminHomeSource=$source('app/Views/admin/home.php');
foreach($adminDestinations as $destination){$check(str_contains($nav,"['".$destination."'")&&str_contains($adminHomeSource,'href="'.$destination.'"'),"admin navigation and Quick Actions include $destination");}
exit($failures?1:0);
