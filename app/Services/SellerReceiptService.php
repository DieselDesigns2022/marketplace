<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use RuntimeException;

/** Security boundary for the optional, seller-authored portion of receipts. */
final class SellerReceiptService
{
    public const MAX_NOTE_LENGTH = 500;
    public const MAX_IMAGE_BYTES = 10485760;
    public const MAX_IMAGE_DIMENSION = 1600;
    public const MAX_SOURCE_PIXELS = 25000000;
    public const PUBLIC_PREFIX = '/uploads/receipt_images/';
    private const MIME_EXTENSIONS = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];

    public static function normalizeNote(?string $note): ?string
    {
        $note = str_replace(["\r\n", "\r"], "\n", trim((string)$note));
        if ($note === '') return null;
        if (mb_strlen($note) > self::MAX_NOTE_LENGTH) throw new RuntimeException('Receipt note must be 500 characters or fewer.');
        if ($note !== strip_tags($note)) throw new RuntimeException('Receipt note must be plain text; HTML is not allowed.');
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $note)) throw new RuntimeException('Receipt note contains unsupported characters.');
        return $note;
    }

    public static function escapedNote(?string $note): string { return H::e(self::normalizeNote($note)); }

    public static function isSafePath(?string $path): bool
    {
        return is_string($path) && preg_match('#^/uploads/receipt_images/[a-f0-9]{24,64}\.(?:jpg|png|webp)$#D', $path) === 1;
    }

    public static function safePublicPath(?string $path): ?string { return self::isSafePath($path) ? $path : null; }

    public static function encoderForMime(string $mime): ?string
    {
        $encoder=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime]??null;
        return $encoder && function_exists($encoder) ? $encoder : null;
    }

    public static function supportsTransparency(string $mime): bool { return in_array($mime,['image/png','image/webp'],true); }

    public static function sourceDimensionsAllowed(int $width,int $height): bool
    {
        return $width>0 && $height>0 && $width<=intdiv(self::MAX_SOURCE_PIXELS,$height);
    }

    public static function snapshotFromSeller(array $seller): array
    {
        try { $note=self::normalizeNote($seller['receipt_note']??null); }
        catch (RuntimeException) { $note=null; }
        return ['note'=>$note,'image_path'=>self::safePublicPath($seller['receipt_image_path']??null)];
    }

    public static function hasExactImageContainer(string $bytes,string $mime): bool
    {
        $length=strlen($bytes);
        if($mime==='image/jpeg')return self::jpegHasExactEoi($bytes);
        if($mime==='image/webp')return $length>=12&&substr($bytes,0,4)==='RIFF'&&substr($bytes,8,4)==='WEBP'&&(unpack('V',substr($bytes,4,4))[1]+8)===$length;
        if($mime!=='image/png'||$length<20||substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n")return false;
        $offset=8;$sawIend=false;
        while($offset+12<=$length){$chunkLength=unpack('N',substr($bytes,$offset,4))[1];$offset+=4;if($chunkLength>$length-$offset-8)return false;$type=substr($bytes,$offset,4);$offset+=4;$data=substr($bytes,$offset,$chunkLength);$offset+=$chunkLength;$crc=substr($bytes,$offset,4);$offset+=4;if(!hash_equals(hash('crc32b',$type.$data,true),$crc))return false;if($type==='IEND'){if($chunkLength!==0)return false;$sawIend=true;break;}}
        return $sawIend&&$offset===$length;
    }

    private static function jpegHasExactEoi(string $bytes): bool
    {
        $length=strlen($bytes);if($length<4||substr($bytes,0,2)!=="\xFF\xD8")return false;$offset=2;$inScan=false;
        while($offset<$length){if(ord($bytes[$offset])!==0xFF){if($inScan){$offset++;continue;}return false;}$markerStart=$offset;while($offset<$length&&ord($bytes[$offset])===0xFF)$offset++;if($offset>=$length)return false;$marker=ord($bytes[$offset++]);if($inScan&&$marker===0x00)continue;if($marker===0xD9)return $offset===$length;if($marker===0xD8||$marker===0x01||($marker>=0xD0&&$marker<=0xD7))continue;if($inScan){$inScan=false;$offset=$markerStart;continue;}if($offset+2>$length)return false;$segmentLength=unpack('n',substr($bytes,$offset,2))[1];if($segmentLength<2||$offset+$segmentLength>$length)return false;$offset+=$segmentLength;if($marker===0xDA)$inScan=true;}
        return false;
    }

    public static function normalizeCategoryKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/','',strtolower(trim($value))) ?? '';
    }

    public function storeUpload(array $upload): string
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Receipt image upload failed.');
        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_IMAGE_BYTES) throw new RuntimeException('Receipt image must be 10 MB or smaller.');
        $tmp = (string)($upload['tmp_name'] ?? '');
        $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg','jpeg','png','webp'], true) || !is_file($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('Receipt image must be a valid uploaded JPG, PNG, or WEBP file.');
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) throw new RuntimeException('Receipt images cannot be processed because GD image support is unavailable.');
        $bytes = @file_get_contents($tmp);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';
        if (!$info || !isset(self::MIME_EXTENSIONS[$mime]) || !hash_equals($mime, (new \finfo(FILEINFO_MIME_TYPE))->file($tmp))) throw new RuntimeException('Receipt image is malformed or is not a supported image.');
        if(!self::hasExactImageContainer($bytes,$mime))throw new RuntimeException('Receipt image contains invalid or trailing data.');
        $encoder=self::encoderForMime($mime);
        if (!$encoder) throw new RuntimeException('Receipt images cannot be processed because the required GD encoder is unavailable.');
        $width=(int)$info[0]; $height=(int)$info[1];
        if (!self::sourceDimensionsAllowed($width,$height)) throw new RuntimeException('Receipt image dimensions are invalid or exceed the 25-megapixel source limit.');
        $source = @imagecreatefromstring($bytes);
        if (!$source) throw new RuntimeException('Receipt image could not be decoded.');
        $scale=min(1, self::MAX_IMAGE_DIMENSION/max($width,$height));
        $newWidth=max(1,(int)round($width*$scale)); $newHeight=max(1,(int)round($height*$scale));
        $target=imagecreatetruecolor($newWidth,$newHeight);
        if (!$target) { imagedestroy($source); throw new RuntimeException('Receipt image could not be processed.'); }
        if (self::supportsTransparency($mime)) {
            imagealphablending($target,false);
            imagesavealpha($target,true);
            $transparent=imagecolorallocatealpha($target,0,0,0,127);
            imagefilledrectangle($target,0,0,$newWidth,$newHeight,$transparent);
        }
        imagecopyresampled($target,$source,0,0,0,0,$newWidth,$newHeight,$width,$height);
        $ext=self::MIME_EXTENSIONS[$mime]; $dir=public_path('uploads/receipt_images');
        if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) { imagedestroy($source); imagedestroy($target); throw new RuntimeException('Receipt image directory is unavailable.'); }
        $name=bin2hex(random_bytes(16)).'.'.$ext; $destination=$dir.'/'.$name;
        $saved = $ext==='jpg' ? $encoder($target,$destination,88) : ($ext==='png' ? $encoder($target,$destination,6) : $encoder($target,$destination,88));
        imagedestroy($source); imagedestroy($target);
        if (!$saved) { @unlink($destination); throw new RuntimeException('Receipt image could not be saved.'); }
        return self::PUBLIC_PREFIX.$name;
    }

    public static function canDeletePath(?string $path, bool $referenced): bool { return self::isSafePath($path) && !$referenced; }

    public function removeStoredFile(?string $path): bool
    {
        if (!self::isSafePath($path)) return false;
        $root=realpath(public_path('uploads/receipt_images')); $file=realpath(public_path(ltrim($path,'/')));
        return (bool)($root && $file && str_starts_with($file,$root.DIRECTORY_SEPARATOR) && is_file($file) && unlink($file));
    }

    public function removeIfUnreferenced(?string $path): bool
    {
        if (!self::isSafePath($path)) return false;
        $referenced=(bool)DB::row('select id from order_items where seller_receipt_image_path_snapshot=? limit 1',[$path]);
        if ($referenced) return false;
        return $this->removeStoredFile($path);
    }

    public static function groupItemsBySeller(array $items): array
    {
        $groups=[];
        foreach ($items as $item) {
            $key=(string)($item['designer_id'] ?? 'unknown');
            if (!isset($groups[$key])) $groups[$key]=['designer_id'=>$item['designer_id']??null,'seller_name'=>$item['seller_name']??'Seller','receipt_note'=>$item['seller_receipt_note_snapshot']??null,'receipt_image_path'=>self::safePublicPath($item['seller_receipt_image_path_snapshot']??null),'items'=>[]];
            $groups[$key]['items'][]=$item;
        }
        return array_values($groups);
    }
}
