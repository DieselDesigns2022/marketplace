<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\PromoGraphicService;
$failures=[];$check=function(bool $ok,string $message)use(&$failures){echo($ok?'PASS: ':'FAIL: ').$message."\n";if(!$ok)$failures[]=$message;};
$root=dirname(__DIR__);$read=fn(string $p)=>file_get_contents($root.'/'.$p);
$routes=$read('public/index.php');$service=$read('app/Services/PromoGraphicService.php');$public=$read('app/Views/public/promo_library.php');$admin=$read('app/Controllers/AdminPromoGraphicController.php');$css=$read('assets/css/app.css');$migration=$read('database/migrations/2026_09_20_phase_13_5_public_promo_library.sql');
$check(count(PromoGraphicService::PLATFORMS)===7&&isset(PromoGraphicService::PLATFORMS['profile_header']),'all seven required platform groups are defined');
$check(str_contains($service,'is_active=1 and archived_at is null')&&str_contains($service,'size_label,sort_order,id'),'public records are active/non-archived and deterministically ordered by platform, size, configured order, and ID');
$groups=['pinterest'=>[
    ['id'=>3,'size_label'=>'1000 x 1500','category'=>'Launch','alt_text'=>'Third graphic','suggested_caption'=>null],
    ['id'=>7,'size_label'=>'1080 x 1080','category'=>'Seller','alt_text'=>'First square graphic','suggested_caption'=>null],
    ['id'=>8,'size_label'=>'1080 x 1080','category'=>'Supporter','alt_text'=>'Second square graphic','suggested_caption'=>null],
]];$platforms=PromoGraphicService::PLATFORMS;ob_start();require app_path('app/Views/public/promo_library.php');$rendered=ob_get_clean();
$firstSize=strpos($rendered,'>1000 x 1500</h3>');$secondSize=strpos($rendered,'>1080 x 1080</h3>');$firstSquare=strpos($rendered,'First square graphic');$secondSquare=strpos($rendered,'Second square graphic');
$check(substr_count($rendered,'class="promo-size-group"')===2&&$firstSize!==false&&$secondSize!==false&&$firstSize<$secondSize,'public platform sections contain distinct accessible size groups in service order');
$check($firstSquare!==false&&$secondSquare!==false&&$firstSquare<$secondSquare,'graphics preserve configured service order within a size group');
$check(str_contains($routes,"'/promo-library'")&&str_contains($routes,"'/promo-library/download/{id}'")&&str_contains($routes,"'/admin/promo-library/{id}/archive'"),'public, download, admin, and archive routes are registered');
$check(str_contains($admin,"'promotions.view'")&&str_contains($admin,"'promotions.manage'")&&str_contains($admin,'verifyCsrf'),'admin access uses existing granular permissions and CSRF');
$check(str_contains($service,'is_uploaded_file')&&str_contains($service,'FILEINFO_MIME_TYPE')&&str_contains($service,'getimagesizefromstring')&&str_contains($service,'hasExactImageContainer')&&str_contains($service,'sourceDimensionsAllowed'),'upload validates provenance, MIME, decoding/container, and dimensions');
$check(str_contains($service,'storage/protected_uploads/promo_graphics')&&str_contains($service,'realpath')&&str_contains($service,'str_starts_with'),'files use contained protected storage');
$check(str_contains($service,"if(\$stored&&\$existing)self::unlink")&&str_contains($service,"preg_match('#^'.preg_quote(self::DIRECTORY"),'replacement deletes only a validated prior promo file after persistence');
$check(str_contains($service,'archived_at=now()')&&str_contains($service,'is_active=0'),'archive atomically removes a graphic from public eligibility');
$check(str_contains($public,'H::e($graphic[\'alt_text\'])')&&str_contains($public,'H::e($graphic[\'suggested_caption\'])'),'public alt text and captions are escaped');
$check(str_contains($public,'data-copy-url')&&str_contains($public,'navigator.clipboard')&&str_contains($public,'navigator.share')&&str_contains($public,'Download image'),'copy, share, and download actions are present');
$check(str_contains($public,'aria-label=')&&str_contains($public,'loading="lazy"')&&str_contains($css,'@media(max-width:640px)'),'accessible image action markup and mobile styling are present');
$check(str_contains($migration,'promo_graphics_public_idx')&&str_contains($migration,'archived_at')&&str_contains($migration,'suggested_caption'),'migration contains public index and required management fields');
exit($failures?1:0);
