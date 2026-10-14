<?php

namespace App\Services;

use App\Core\Helpers as H;

final class EmailImageService
{
    public static function compatibleUrl(?string $path): string
    {
        $path=trim((string)$path);

        if($path===''){
            return '';
        }

        $url=H::assetUrl($path);

        $urlPath=parse_url(
            $url,
            PHP_URL_PATH
        );

        if(
            !is_string($urlPath) ||
            strtolower(pathinfo($urlPath,PATHINFO_EXTENSION))!=='webp'
        ){
            return $url;
        }

        if(
            !extension_loaded('gd') ||
            !function_exists('imagecreatefromwebp') ||
            !function_exists('imagejpeg')
        ){
            return $url;
        }

        $relative=ltrim($urlPath,'/');
        $publicRoot=realpath(public_path());

        if($publicRoot===false){
            return $url;
        }

        $source=realpath(
            public_path($relative)
        );

        if(
            $source===false ||
            !is_file($source) ||
            !str_starts_with(
                $source,
                $publicRoot.DIRECTORY_SEPARATOR
            )
        ){
            return $url;
        }

        $cacheDir=public_path(
            'uploads/email_previews'
        );

        if(
            !is_dir($cacheDir) &&
            !mkdir($cacheDir,0755,true) &&
            !is_dir($cacheDir)
        ){
            return $url;
        }

        $key=hash(
            'sha256',
            $relative.'|'.
            (string)filemtime($source).'|'.
            (string)filesize($source)
        );

        $filename=$key.'.jpg';
        $target=$cacheDir.'/'.$filename;

        if(!is_file($target)){
            $sourceImage=@imagecreatefromwebp(
                $source
            );

            if($sourceImage===false){
                return $url;
            }

            $width=imagesx($sourceImage);
            $height=imagesy($sourceImage);

            $jpeg=imagecreatetruecolor(
                $width,
                $height
            );

            if($jpeg===false){
                imagedestroy($sourceImage);
                return $url;
            }

            $white=imagecolorallocate(
                $jpeg,
                255,
                255,
                255
            );

            imagefill(
                $jpeg,
                0,
                0,
                $white
            );

            imagecopy(
                $jpeg,
                $sourceImage,
                0,
                0,
                0,
                0,
                $width,
                $height
            );

            $saved=@imagejpeg(
                $jpeg,
                $target,
                88
            );

            imagedestroy($jpeg);
            imagedestroy($sourceImage);

            if(!$saved){
                @unlink($target);
                return $url;
            }

            @chmod($target,0644);
        }

        return H::baseUrl().
            '/uploads/email_previews/'.
            rawurlencode($filename);
    }
}
