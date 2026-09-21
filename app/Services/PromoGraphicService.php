<?php
namespace App\Services;

use App\Core\Database as DB;

final class PromoGraphicService
{
    public const PLATFORMS = [
        'pinterest'=>'Pinterest', 'instagram_square'=>'Instagram Square',
        'instagram_story'=>'Instagram Story', 'facebook'=>'Facebook',
        'website_banners'=>'Website Banners', 'email_graphics'=>'Email Graphics',
        'profile_header'=>'Profile/Header Graphics',
    ];
    private const MAX_BYTES = 10485760;
    private const DIRECTORY = 'storage/protected_uploads/promo_graphics';

    public static function active(): array
    {
        return DB::rows('select * from promo_graphics where is_active=1 and archived_at is null order by field(platform,"pinterest","instagram_square","instagram_story","facebook","website_banners","email_graphics","profile_header"),size_label,sort_order,id');
    }

    public static function all(): array
    { return DB::rows('select * from promo_graphics where archived_at is null order by sort_order,id'); }

    public static function find(int $id, bool $publicOnly=false): ?array
    {
        return DB::row('select * from promo_graphics where id=? and archived_at is null'.($publicOnly?' and is_active=1':''),[$id]);
    }

    public static function validateFields(array $input): array
    {
        $platform=(string)($input['platform']??'');
        $size=trim((string)($input['size_label']??''));
        $category=trim((string)($input['category']??''));
        $alt=trim((string)($input['alt_text']??''));
        $caption=trim((string)($input['suggested_caption']??''));
        $order=filter_var($input['sort_order']??null,FILTER_VALIDATE_INT);
        if(!isset(self::PLATFORMS[$platform]))throw new \InvalidArgumentException('Choose a valid platform.');
        if($size===''||mb_strlen($size)>100)throw new \InvalidArgumentException('Size is required and must be 100 characters or fewer.');
        if($category===''||mb_strlen($category)>100)throw new \InvalidArgumentException('Category is required and must be 100 characters or fewer.');
        if($alt===''||mb_strlen($alt)>500)throw new \InvalidArgumentException('Useful alt text is required and must be 500 characters or fewer.');
        if(mb_strlen($caption)>2000)throw new \InvalidArgumentException('Suggested caption must be 2,000 characters or fewer.');
        if($order===false||$order<0||$order>1000000)throw new \InvalidArgumentException('Sort order must be a whole number from 0 to 1,000,000.');
        return compact('platform','size','category','alt','caption','order')+['active'=>($input['is_active']??'')==='1'];
    }

    public static function save(?int $id,array $input,?array $upload,int $adminId): int
    {
        $values=self::validateFields($input);$existing=$id?self::find($id):null;
        if($id&&!$existing)throw new \InvalidArgumentException('Promotional graphic not found.');
        $hasUpload=is_array($upload)&&(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
        if(!$existing&&!$hasUpload)throw new \InvalidArgumentException('Choose an image to upload.');
        $stored=$hasUpload?self::storeUpload($upload):null;
        try {
            if($existing){
                DB::exec('update promo_graphics set platform=?,size_label=?,category=?,alt_text=?,suggested_caption=?,is_active=?,sort_order=?,image_path=coalesce(?,image_path),original_name=coalesce(?,original_name),mime_type=coalesce(?,mime_type),updated_by=? where id=?',[$values['platform'],$values['size'],$values['category'],$values['alt'],$values['caption']?:null,$values['active']?1:0,$values['order'],$stored['path']??null,$stored['original']??null,$stored['mime']??null,$adminId,$id]);
            }else{
                DB::exec('insert into promo_graphics(platform,size_label,category,image_path,original_name,mime_type,alt_text,suggested_caption,is_active,sort_order,created_by,updated_by) values(?,?,?,?,?,?,?,?,?,?,?,?)',[$values['platform'],$values['size'],$values['category'],$stored['path'],$stored['original'],$stored['mime'],$values['alt'],$values['caption']?:null,$values['active']?1:0,$values['order'],$adminId,$adminId]);$id=(int)DB::id();
            }
        }catch(\Throwable $e){if($stored)self::unlink($stored['path']);throw $e;}
        if($stored&&$existing)self::unlink((string)$existing['image_path']);
        return (int)$id;
    }

    public static function archive(int $id,int $adminId): void
    { if(!self::find($id))throw new \InvalidArgumentException('Promotional graphic not found.');DB::exec('update promo_graphics set is_active=0,archived_at=now(),archived_by=?,updated_by=? where id=?',[$adminId,$adminId,$id]); }

    public static function absolutePath(array $graphic): string
    {
        $base=realpath(app_path(self::DIRECTORY));$real=realpath(app_path((string)$graphic['image_path']));
        if(!$base||!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($real)||!is_readable($real))throw new \RuntimeException('Graphic file is unavailable.');
        return $real;
    }

    private static function storeUpload(array $file): array
    {
        foreach(['name','tmp_name','error','size'] as $key)if(!array_key_exists($key,$file)||is_array($file[$key]))throw new \InvalidArgumentException('Malformed image upload.');
        if((int)$file['error']!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('The image upload failed.');
        $tmp=(string)$file['tmp_name'];$size=(int)$file['size'];$actual=@filesize($tmp);
        if(!is_uploaded_file($tmp)||$actual===false||$actual!==$size||$size<1||$size>self::MAX_BYTES)throw new \InvalidArgumentException('Upload a genuine image no larger than 10 MB.');
        $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        $bytes=@file_get_contents($tmp);$info=$bytes===false?false:@getimagesizefromstring($bytes);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if(!isset($allowed[$ext])||!$info||!is_string($mime)||$allowed[$ext]!==$mime||($info['mime']??'')!==$mime||!SellerReceiptService::hasExactImageContainer((string)$bytes,$mime))throw new \InvalidArgumentException('Images must be genuine JPG, PNG, or WEBP files.');
        if(!SellerReceiptService::sourceDimensionsAllowed((int)$info[0],(int)$info[1]))throw new \InvalidArgumentException('Image dimensions exceed the safe 25-megapixel limit.');
        if(!extension_loaded('gd')||!function_exists('imagecreatefromstring'))throw new \RuntimeException('Image decoding is unavailable.');
        $decoded=@imagecreatefromstring($bytes);if(!$decoded)throw new \InvalidArgumentException('The image could not be decoded.');imagedestroy($decoded);
        $dir=app_path(self::DIRECTORY);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Graphic storage is unavailable.');
        $suffix=$ext==='jpeg'?'jpg':$ext;$name=bin2hex(random_bytes(24)).'.'.$suffix;$absolute=$dir.'/'.$name;
        if(!move_uploaded_file($tmp,$absolute))throw new \RuntimeException('The image could not be stored.');chmod($absolute,0640);
        return ['path'=>self::DIRECTORY.'/'.$name,'original'=>mb_substr(basename((string)$file['name']),0,190),'mime'=>$mime];
    }

    private static function unlink(string $path): void
    {
        if(!preg_match('#^'.preg_quote(self::DIRECTORY,'#').'/[a-f0-9]{48}\.(?:jpg|png|webp)$#D',$path))return;
        $base=realpath(app_path(self::DIRECTORY));$real=realpath(app_path($path));
        if($base&&$real&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)&&is_file($real))@unlink($real);
    }
}
