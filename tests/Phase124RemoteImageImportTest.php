<?php
$root=dirname(__DIR__); require "$root/app/bootstrap.php";
use App\Services\RemoteProductImageService; use App\Services\WatermarkService;
$fail=0;$check=function(bool $ok,string $name)use(&$fail){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$fail++;};
$dir=sys_get_temp_dir().'/am-remote-'.bin2hex(random_bytes(4));mkdir($dir);
$make=function(string $type,string $path){$im=imagecreatetruecolor(20,20);$c=imagecolorallocate($im,20,100,180);imagefill($im,0,0,$c);match($type){'jpg'=>imagejpeg($im,$path),'png'=>imagepng($im,$path),'webp'=>function_exists('imagewebp')?imagewebp($im,$path):false};imagedestroy($im);};
$make('jpg',"$dir/good.jpg");$make('png',"$dir/good.png");if(function_exists('imagewebp'))$make('webp',"$dir/good.webp");file_put_contents("$dir/html",'<html>no</html>');file_put_contents("$dir/svg",'<svg xmlns="http://www.w3.org/2000/svg"></svg>');file_put_contents("$dir/bad",'not an image');
$resolver=fn($host)=>['93.184.216.34'];
$transport=function($url,$host,$port,$ips,$max)use($dir){$path=$dir.'/tmp-'.bin2hex(random_bytes(3));$name=basename(parse_url($url,PHP_URL_PATH));$map=['jpg'=>'good.jpg','png'=>'good.png','webp'=>'good.webp','html'=>'html','svg'=>'svg','fake.jpg'=>'bad','corrupt'=>'bad'];if(str_starts_with($name,'redirect-'))return ['status'=>302,'location'=>urldecode(substr($name,9)),'path'=>$path];if($name==='loop')return ['status'=>302,'location'=>'https://example.com/loop','path'=>$path];if($name==='timeout')throw new RuntimeException('Remote image server timed out or could not be reached.');if($name==='large')throw new RuntimeException('Remote image exceeded the 25MB preview-image limit.');copy($dir.'/'.($map[$name]??'bad'),$path);return ['status'=>200,'path'=>$path];};
$service=new RemoteProductImageService($resolver,$transport);
foreach(['jpg','png'] as $type){$got=$service->fetch("https://example.com/$type");$check($got['extension']===$type,"valid HTTPS $type bytes accepted");unlink($got['path']);}
if(function_exists('imagewebp')){$got=$service->fetch('https://example.com/webp');$check($got['extension']==='webp','valid HTTPS WEBP bytes accepted');unlink($got['path']);}else echo "SKIP: GD WEBP encoder is unavailable.\n";
$blocked=['http://example.com/jpg','https://localhost/jpg','https://127.0.0.1/jpg','https://[::1]/jpg','https://[fc00::1]/jpg','https://[fe80::1]/jpg','https://10.0.0.1/jpg','https://169.254.1.1/jpg','https://100.64.0.1/jpg','https://192.0.2.1/jpg'];foreach($blocked as $url){try{$service->fetch($url);$ok=false;}catch(RuntimeException $e){$ok=true;}$check($ok,"blocked destination rejected: $url");}
$privateDns=new RemoteProductImageService(fn($h)=>['192.168.1.2'],$transport);try{$privateDns->fetch('https://private.example/jpg');$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'blocked');}$check($ok,'DNS resolving to private address rejected');
foreach(['html','svg','fake.jpg','corrupt'] as $name){try{$service->fetch("https://example.com/$name");$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'supported')||str_contains($e->getMessage(),'corrupt');}$check($ok,"$name content rejected by downloaded bytes");}
foreach(['timeout'=>'timed out','large'=>'25MB'] as $name=>$message){try{$service->fetch("https://example.com/$name");$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),$message);}$check($ok,"$name failure is seller-safe");}
$redirectPrivate=new RemoteProductImageService(fn($h)=>$h==='private.example'?['10.0.0.2']:['93.184.216.34'],$transport);try{$redirectPrivate->fetch('https://example.com/redirect-'.urlencode('https://private.example/jpg'));$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'blocked');}$check($ok,'redirect destination is DNS validated and private redirect rejected');
try{$service->fetch('https://example.com/redirect-'.urlencode('http://example.com/jpg'));$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'not HTTPS');}$check($ok,'HTTPS to HTTP redirect downgrade rejected');
try{$service->fetch('https://example.com/loop');$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'redirect limit');}$check($ok,'redirect limit enforced');
$got=$service->fetch('https://example.com/png');
$errors=[];
$manual=WatermarkService::applyLocalPreview($got['path'],'product_previews',$errors);
unlink($got['path']);
$manualPrivate=$manual&&$manual['original_image_path']?app_path('storage/app/private/'.$manual['original_image_path']):'';
$manualPublic=$manual?public_path(ltrim($manual['image_path'],'/')):'';
$check($manual&&is_file($manualPrivate),'manual local preview retains private original');
$check($manual&&is_file($manualPublic),'manual local preview retains public watermarked preview');
if($manual){@unlink($manualPrivate);@unlink($manualPublic);}

$largePath="$dir/large-source.png";
$large=imagecreatetruecolor(1600,900);
$largeColor=imagecolorallocate($large,40,80,120);
imagefill($large,0,0,$largeColor);
imagepng($large,$largePath);
imagedestroy($large);

$importErrors=[];
$imported=WatermarkService::applyImportedRemotePreview($largePath,'product_previews',$importErrors);
$importedPublic=$imported?public_path(ltrim($imported['image_path'],'/')):'';
$importedInfo=$importedPublic&&is_file($importedPublic)?getimagesize($importedPublic):false;

$check(
    $imported
    && $imported['original_image_path']===null
    && is_file($importedPublic),
    'imported remote preview is public-only with no private original'
);
$check(
    $imported
    && $imported['watermark_status']===WatermarkService::STATUS_WATERMARKED,
    'imported remote preview is watermarked'
);
$check(
    $importedInfo
    && max((int)$importedInfo[0],(int)$importedInfo[1])<=1200,
    'imported remote preview longest dimension is capped at 1200px'
);

if(function_exists('imagewebp')){
    $check(
        $imported
        && strtolower(pathinfo($imported['image_path'],PATHINFO_EXTENSION))==='webp',
        'imported remote preview uses WEBP when encoder is available'
    );
}else{
    echo "SKIP: GD WEBP encoder is unavailable for imported-preview encoding.\n";
}

@unlink($largePath);
if($importedPublic)@unlink($importedPublic);
$order=[];$tempPaths=[];$mixed=$service->processUrls(['https://example.com/png','https://example.com/html','https://example.com/jpg'],function($download,$index)use(&$order,&$tempPaths){$order[]=$index;$tempPaths[]=$download['path'];});$check($mixed['succeeded']===2&&$order===[0,2]&&count($mixed['warnings'])===1&&str_starts_with($mixed['warnings'][0],'Image 2:'),'multiple images preserve order and mixed failure remains partial');$check(!array_filter($tempPaths,'is_file'),'successful and partial processing removes temporary downloads');
$all=$service->processUrls(['http://example.com/jpg','https://example.com/svg'],fn()=>null);$check($all['succeeded']===0&&count($all['warnings'])===2,'all image failures return warnings without throwing product-fatal error');
$created=[];
$attach=$service->processUrls(
    ['https://example.com/png'],
    function($download)use(&$created){
        $errors=[];
        $saved=WatermarkService::applyImportedRemotePreview(
            $download['path'],
            'product_previews',
            $errors
        );
        $created=$saved;
        if($saved&&!empty($saved['image_path'])){
            @unlink(public_path(ltrim($saved['image_path'],'/')));
        }
        throw new RuntimeException('Image could not be attached safely.');
    }
);
$check(
    $attach['succeeded']===0
    && str_contains($attach['warnings'][0],'attached safely'),
    'attachment failure is partial and imported public preview can be cleaned'
);
$watermark=file_get_contents("$root/app/Services/WatermarkService.php");
$controller=file_get_contents("$root/app/Controllers/SellerController.php");

$check(
    str_contains($watermark,'applyUploadedPreview')
    && str_contains($watermark,'applyImportedRemotePreview')
    && str_contains($watermark,"'original_image_path' => null"),
    'manual and imported remote preview storage paths are intentionally distinct'
);

$check(
    str_contains($controller,'applyImportedRemotePreview'),
    'CSV import controller uses imported remote preview pipeline'
);
foreach(glob("$dir/*") as $f)@unlink($f);@rmdir($dir);exit($fail?1:0);
