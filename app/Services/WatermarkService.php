<?php

namespace App\Services;

use App\Core\Helpers as H;
use Throwable;

class WatermarkService
{
    public const STATUS_WATERMARKED = 'watermarked';
    public const STATUS_ORIGINAL_FALLBACK = 'original_fallback';
    public const STATUS_FAILED = 'failed';

    public static function sourcePath(): string
    {
        $configured = trim($_ENV['WATERMARK_SOURCE_PATH'] ?? '');
        return $configured !== '' ? $configured : app_path('storage/app/private/branding/watermark.png');
    }

    public static function extraProtectionSourcePath(): string
    {
        return app_path(
            'storage/app/private/branding/extra-protection-watermark.png'
        );
    }

    public static function applyUploadedPreview(array $file, string $folder, array &$errors, bool $extraProtection = false): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Preview image upload failed.';
            return null;
        }
        if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
            $errors[] = 'Preview images must be 25MB or smaller.';
            return null;
        }
        $tmp = $file['tmp_name'] ?? '';
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !self::isSupportedImage($tmp, $ext)) {
            $errors[] = 'Preview images must be valid JPG, PNG, or WEBP files.';
            return null;
        }

        return self::storeValidatedPreview($tmp, $ext, $folder, $errors, true, $extraProtection);
    }

    /** Store a validated server-side image through the normal preview pipeline. */
    public static function applyLocalPreview(string $path, string $folder, array &$errors): ?array
    {
        if (!is_file($path) || filesize($path) > 25 * 1024 * 1024) {
            $errors[] = 'Remote image exceeded the 25MB preview-image limit.';
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? '';
        if ($ext === '' || !self::isSupportedImage($path, $ext)) {
            $errors[] = 'Remote file was not a supported JPG, PNG, or WEBP image.';
            return null;
        }
        return self::storeValidatedPreview($path, $ext, $folder, $errors, false);
    }

    /**
     * Imported remote previews only need the final public watermarked image.
     * They are resized for storefront use and do not retain a private source copy.
     */
    public static function applyImportedRemotePreview(
        string $path,
        string $folder,
        array &$errors,
        int $maxDimension = 1200,
        bool $extraProtection = false
    ): ?array {
        if (!is_file($path) || filesize($path) > 25 * 1024 * 1024) {
            $errors[] = 'Remote image exceeded the 25MB preview-image limit.';
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $sourceTypes = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        ];
        $sourceExts = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($sourceTypes[$mime]) || !self::isSupportedImage($path, $sourceExts[$mime])) {
            $errors[] = 'Remote file was not a supported JPG, PNG, or WEBP image.';
            return null;
        }

        if (!extension_loaded('gd')) {
            $errors[] = 'Preview watermarking is unavailable because PHP GD is not installed.';
            return null;
        }

        $publicDir = public_path('uploads/' . trim($folder, '/'));
        if (!is_dir($publicDir) && !mkdir($publicDir, 0755, true)) {
            $errors[] = 'Public preview directory is unavailable.';
            return null;
        }

        $sourceType = $sourceTypes[$mime];
        $outputType = function_exists('imagewebp') ? IMAGETYPE_WEBP : $sourceType;
        $outputExt = match ($outputType) {
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_PNG => 'png',
            default => 'jpg',
        };

        $name = bin2hex(random_bytes(12)) . '-wm.' . $outputExt;
        $publicAbs = $publicDir . '/' . $name;

        $result = self::watermarkFile(
            $path,
            $publicAbs,
            max(1, $maxDimension),
            $outputType,
            $extraProtection
        );

        if (!$result['ok']) {
            @unlink($publicAbs);
            $errors[] = $result['message'];
            return null;
        }

        return [
            'image_path' => '/uploads/' . trim($folder, '/') . '/' . $name,
            'original_image_path' => null,
            'watermark_status' => self::STATUS_WATERMARKED,
            'watermark_error' => null,
        ];
    }

    private static function storeValidatedPreview(string $tmp, string $ext, string $folder, array &$errors, bool $uploaded, bool $extraProtection = false): ?array
    {
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $privateDir = app_path('storage/app/private/product_previews');
        $publicDir = public_path('uploads/' . trim($folder, '/'));
        if (!is_dir($privateDir)) mkdir($privateDir, 0750, true);
        if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);

        $originalAbs = $privateDir . '/' . $name;
        $stored = $uploaded ? move_uploaded_file($tmp, $originalAbs) : copy($tmp, $originalAbs);
        if (!$stored) {
            $errors[] = 'Preview image could not be saved.';
            return null;
        }

        $watermarkedName = pathinfo($name, PATHINFO_FILENAME) . '-wm.' . $ext;
        $publicAbs = $publicDir . '/' . $watermarkedName;
        $result = self::watermarkFile(
            $originalAbs,
            $publicAbs,
            null,
            null,
            $extraProtection
        );
        if (!$result['ok']) {
            $fallbackAbs = $publicDir . '/' . $name;
            if (!copy($originalAbs, $fallbackAbs)) {
                @unlink($publicAbs);
                @unlink($originalAbs);
                $errors[] = 'Preview image was saved privately, but the public preview could not be created.';
                return null;
            }
            error_log('Creative Moth watermark fallback: ' . $result['message']);
            return [
                'image_path' => '/uploads/' . trim($folder, '/') . '/' . $name,
                'original_image_path' => 'product_previews/' . $name,
                'watermark_status' => self::STATUS_ORIGINAL_FALLBACK,
                'watermark_error' => $result['message'],
            ];
        }

        return [
            'image_path' => '/uploads/' . trim($folder, '/') . '/' . $watermarkedName,
            'original_image_path' => 'product_previews/' . $name,
            'watermark_status' => self::STATUS_WATERMARKED,
            'watermark_error' => null,
        ];
    }

    public static function regenerate(string $originalRelative, string $currentPublicPath, bool $extraProtection = false): array
    {
        $originalRelative = ltrim(str_replace(['..', '\\'], '', $originalRelative), '/');
        $originalAbs = app_path('storage/app/private/' . $originalRelative);
        $originalBase = realpath(app_path('storage/app/private/product_previews'));
        $originalReal = realpath($originalAbs);
        if (!$originalBase || !$originalReal || !str_starts_with($originalReal, $originalBase)) {
            return ['ok' => false, 'message' => 'Private preview source path is invalid.'];
        }
        $publicRelative = ltrim(str_replace('\\', '/', $currentPublicPath), '/');
        if ($publicRelative === '' || str_contains($publicRelative, "\0") || str_contains($publicRelative, '..') || !str_starts_with($publicRelative, 'uploads/product_previews/')) {
            return ['ok' => false, 'message' => 'Public preview path is invalid.'];
        }
        $publicBase = public_path('uploads/product_previews');
        if (!is_dir($publicBase) && !mkdir($publicBase, 0755, true)) {
            return ['ok' => false, 'message' => 'Public preview directory is unavailable.'];
        }
        $publicBaseReal = realpath($publicBase);
        $publicDir = public_path(dirname($publicRelative));
        if (!is_dir($publicDir) && !mkdir($publicDir, 0755, true)) {
            return ['ok' => false, 'message' => 'Public preview destination directory is unavailable.'];
        }
        $publicDirReal = realpath($publicDir);
        if (!$publicBaseReal || !$publicDirReal || !str_starts_with($publicDirReal, $publicBaseReal)) {
            return ['ok' => false, 'message' => 'Public preview destination is outside the preview upload folder.'];
        }
        $publicAbs = $publicDirReal . '/' . basename($publicRelative);
        if (!is_file($originalReal)) {
            return ['ok' => false, 'message' => 'Original private preview image is unavailable.'];
        }
        return self::watermarkFile(
            $originalReal,
            $publicAbs,
            null,
            null,
            $extraProtection
        );
    }

    public static function regenerateImportedRemotePreview(
        string $sourceAbs,
        string $currentPublicPath,
        bool $extraProtection = false
    ): array {
        if (!is_file($sourceAbs)) {
            return [
                'ok' => false,
                'message' => 'Imported preview source is unavailable.',
            ];
        }

        $info = @getimagesize($sourceAbs);

        if (
            !$info ||
            !in_array(
                (int)$info[2],
                [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP],
                true
            )
        ) {
            return [
                'ok' => false,
                'message' => 'Imported preview source is invalid.',
            ];
        }

        $relative = ltrim(
            str_replace('\\', '/', $currentPublicPath),
            '/'
        );

        if (
            $relative === '' ||
            str_contains($relative, '..') ||
            !str_starts_with(
                $relative,
                'uploads/product_previews/'
            )
        ) {
            return [
                'ok' => false,
                'message' => 'Public preview path is invalid.',
            ];
        }

        $ext = strtolower(
            pathinfo($relative, PATHINFO_EXTENSION)
        );

        $outputType = match ($ext) {
            'webp' => IMAGETYPE_WEBP,
            'png' => IMAGETYPE_PNG,
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            default => 0,
        };

        if (
            !$outputType ||
            (
                $outputType === IMAGETYPE_WEBP &&
                !function_exists('imagewebp')
            )
        ) {
            return [
                'ok' => false,
                'message' => 'Existing preview format is unsupported.',
            ];
        }

        $publicAbs = public_path($relative);

        return self::watermarkFile(
            $sourceAbs,
            $publicAbs,
            1200,
            $outputType,
            $extraProtection
        );
    }

    public static function storeCustomDesignPreview(array $file, array &$errors, bool $extraProtection = false): ?array
    {
        $ext = strtolower((string)($file['ext'] ?? ''));
        $type = [
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => IMAGETYPE_WEBP,
        ][$ext] ?? null;

        $tmp = (string)($file['tmp'] ?? '');

        if (!$type || !is_uploaded_file($tmp)) {
            $errors[] = 'Custom Design preview could not be stored safely.';
            return null;
        }

        $name = bin2hex(random_bytes(24)) . '.' . $ext;
        $relative = 'custom_designs/examples/' . $name;

        $originalAbs = app_path(
            'storage/app/private/custom_design_previews/' . $relative
        );

        $publicAbs = public_path(
            'uploads/' . $relative
        );

        if (
            !is_dir(dirname($originalAbs)) &&
            !mkdir(dirname($originalAbs), 0750, true) &&
            !is_dir(dirname($originalAbs))
        ) {
            $errors[] = 'Private Custom Design preview storage is unavailable.';
            return null;
        }

        if (
            !is_dir(dirname($publicAbs)) &&
            !mkdir(dirname($publicAbs), 0755, true) &&
            !is_dir(dirname($publicAbs))
        ) {
            $errors[] = 'Public Custom Design preview storage is unavailable.';
            return null;
        }

        if (!move_uploaded_file($tmp, $originalAbs)) {
            $errors[] = 'Custom Design preview could not be saved.';
            return null;
        }

        $result = self::watermarkFile(
            $originalAbs,
            $publicAbs,
            null,
            $type,
            $extraProtection
        );

        if (!$result['ok']) {
            @unlink($originalAbs);
            @unlink($publicAbs);
            $errors[] = $result['message'];
            return null;
        }

        return [
            'image_path' => '/uploads/' . $relative,
            'original_abs' => $originalAbs,
            'public_abs' => $publicAbs,
        ];
    }

    public static function regenerateCustomDesignPreview(string $publicPath, bool $extraProtection = false): array
    {
        $publicRelative = ltrim(str_replace('\\', '/', $publicPath), '/');

        if (
            $publicRelative === '' ||
            str_contains($publicRelative, "\0") ||
            str_contains($publicRelative, '..') ||
            !str_starts_with(
                $publicRelative,
                'uploads/custom_designs/examples/'
            )
        ) {
            return [
                'ok' => false,
                'message' => 'Custom Design preview path is invalid.',
            ];
        }

        $publicAbs = public_path($publicRelative);

        if (!is_file($publicAbs)) {
            return [
                'ok' => false,
                'message' => 'Custom Design preview file is missing.',
            ];
        }

        $relative = substr(
            $publicRelative,
            strlen('uploads/')
        );

        $originalAbs = app_path(
            'storage/app/private/custom_design_previews/' . $relative
        );

        if (!is_dir(dirname($originalAbs))) {
            mkdir(dirname($originalAbs), 0750, true);
        }

        if (!is_file($originalAbs)) {
            if (!copy($publicAbs, $originalAbs)) {
                return [
                    'ok' => false,
                    'message' => 'Custom Design preview original could not be preserved.',
                ];
            }
        }

        return self::watermarkFile(
            $originalAbs,
            $publicAbs,
            null,
            null,
            $extraProtection
        );
    }

    public static function deleteCustomDesignPreview(string $publicPath): void
    {
        $publicRelative = ltrim(str_replace('\\', '/', $publicPath), '/');

        if (
            str_contains($publicRelative, '..') ||
            !str_starts_with(
                $publicRelative,
                'uploads/custom_designs/examples/'
            )
        ) {
            return;
        }

        $publicAbs = public_path($publicRelative);
        $relative = substr(
            $publicRelative,
            strlen('uploads/')
        );

        $originalAbs = app_path(
            'storage/app/private/custom_design_previews/' . $relative
        );

        @unlink($publicAbs);
        @unlink($originalAbs);
    }

    public static function storeCustomProof(array $file, array &$errors): ?array
    {
        $ext = strtolower((string)($file['ext'] ?? ''));
        $type = [
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => IMAGETYPE_WEBP,
        ][$ext] ?? null;

        $tmp = (string)($file['tmp'] ?? '');

        if (!$type || !is_uploaded_file($tmp)) {
            $errors[] = 'Proof must be a valid JPG, PNG, or WEBP image.';
            return null;
        }

        $relative =
            'custom_designs/proofs/' .
            bin2hex(random_bytes(24)) .
            '.' .
            $ext;

        $originalAbs = app_path(
            'storage/app/private/custom_design_proof_originals/' .
            $relative
        );

        $protectedAbs = app_path(
            'storage/protected_uploads/' . $relative
        );

        if (
            !is_dir(dirname($originalAbs)) &&
            !mkdir(dirname($originalAbs), 0750, true) &&
            !is_dir(dirname($originalAbs))
        ) {
            $errors[] = 'Private proof storage is unavailable.';
            return null;
        }

        if (
            !is_dir(dirname($protectedAbs)) &&
            !mkdir(dirname($protectedAbs), 0750, true) &&
            !is_dir(dirname($protectedAbs))
        ) {
            $errors[] = 'Protected proof storage is unavailable.';
            return null;
        }

        if (!move_uploaded_file($tmp, $originalAbs)) {
            $errors[] = 'Proof could not be saved safely.';
            return null;
        }

        $result = self::watermarkFile(
            $originalAbs,
            $protectedAbs,
            null,
            $type
        );

        if (!$result['ok']) {
            @unlink($originalAbs);
            @unlink($protectedAbs);
            $errors[] = $result['message'];
            return null;
        }

        @chmod($protectedAbs, 0640);

        return [
            'storage_path' => $relative,
            'original_abs' => $originalAbs,
            'protected_abs' => $protectedAbs,
            'file_size' => filesize($protectedAbs),
        ];
    }

    public static function regenerateCustomProof(string $protectedRelative): array
    {
        $protectedRelative = ltrim(
            str_replace('\\', '/', $protectedRelative),
            '/'
        );

        if (
            $protectedRelative === '' ||
            str_contains($protectedRelative, "\0") ||
            str_contains($protectedRelative, '..') ||
            !str_starts_with(
                $protectedRelative,
                'custom_designs/proofs/'
            )
        ) {
            return [
                'ok' => false,
                'message' => 'Protected proof path is invalid.',
            ];
        }

        $protectedAbs = app_path(
            'storage/protected_uploads/' . $protectedRelative
        );

        if (!is_file($protectedAbs)) {
            return [
                'ok' => false,
                'message' => 'Protected proof file is missing.',
            ];
        }

        $originalAbs = app_path(
            'storage/app/private/custom_design_proof_originals/' .
            $protectedRelative
        );

        if (!is_dir(dirname($originalAbs))) {
            mkdir(dirname($originalAbs), 0750, true);
        }

        if (!is_file($originalAbs)) {
            if (!copy($protectedAbs, $originalAbs)) {
                return [
                    'ok' => false,
                    'message' => 'Proof original could not be preserved.',
                ];
            }
        }

        $result = self::watermarkFile(
            $originalAbs,
            $protectedAbs
        );

        if ($result['ok']) {
            @chmod($protectedAbs, 0640);
        }

        return $result;
    }

    private static function isSupportedImage(string $path, string $ext): bool
    {
        if (!is_file($path) || !@getimagesize($path)) return false;
        $mime = mime_content_type($path) ?: '';
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        return ($allowed[$ext] ?? '') === $mime;
    }

    private static function watermarkFile(
        string $sourceAbs,
        string $destinationAbs,
        ?int $maxDimension = null,
        ?int $outputType = null,
        bool $extraProtection = false
    ): array
    {
        if (!extension_loaded('gd')) return ['ok' => false, 'message' => 'PHP GD extension is not available.'];
        $info = @getimagesize($sourceAbs);
        if (!$info) return ['ok' => false, 'message' => 'Source preview image is invalid.'];
        try {
            $base = self::imageFrom($sourceAbs, (int)$info[2]);
            if (!$base) return ['ok' => false, 'message' => 'Source image type is not supported by GD.'];
            imagealphablending($base, true);
            imagesavealpha($base, true);
            $bw = imagesx($base); $bh = imagesy($base);

            if ($maxDimension !== null && max($bw, $bh) > $maxDimension) {
                $scale = $maxDimension / max($bw, $bh);
                $newW = max(1, (int)round($bw * $scale));
                $newH = max(1, (int)round($bh * $scale));
                $resized = imagecreatetruecolor($newW, $newH);
                if (!$resized) {
                    imagedestroy($base);
                    return ['ok' => false, 'message' => 'Preview image could not be resized.'];
                }

                if (in_array((int)$info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $newW - 1, $newH - 1, $transparent);
                }

                if (!imagecopyresampled(
                    $resized,
                    $base,
                    0,
                    0,
                    0,
                    0,
                    $newW,
                    $newH,
                    $bw,
                    $bh
                )) {
                    imagedestroy($resized);
                    imagedestroy($base);
                    return ['ok' => false, 'message' => 'Preview image could not be resized.'];
                }

                imagedestroy($base);
                $base = $resized;
                imagealphablending($base, true);
                imagesavealpha($base, true);
                $bw = $newW;
                $bh = $newH;
            }

            /*
             * Optional seller-selected extra protection:
             * design -> stretched full-image protection watermark ->
             * centered Creative Moth watermark.
             */
            if ($extraProtection) {
                $protection = self::extraProtectionImage($bw, $bh);

                if (!$protection) {
                    imagedestroy($base);

                    return [
                        'ok' => false,
                        'message' => 'Extra-protection watermark image is unavailable.',
                    ];
                }

                $protection = self::applyOpacity($protection, 15);

                imagecopy(
                    $base,
                    $protection,
                    0,
                    0,
                    0,
                    0,
                    $bw,
                    $bh
                );

                imagedestroy($protection);
            }

            $mark = self::watermarkImage(max(1, (int)round($bw * 0.30)), max(1, (int)round($bh * 0.20)));
            $mark = self::applyOpacity($mark, 40);
            $mw = imagesx($mark);
            $mh = imagesy($mark);

            $watermarkX = max(0, (int)round(($bw - $mw) / 2));
            $watermarkY = max(0, (int)round(($bh - $mh) / 2));

            imagecopy(
                $base,
                $mark,
                $watermarkX,
                $watermarkY,
                0,
                0,
                $mw,
                $mh
            );
            if (!is_dir(dirname($destinationAbs))) mkdir(dirname($destinationAbs), 0755, true);
            $saved = self::saveImage($base, $destinationAbs, $outputType ?? (int)$info[2]);
            imagedestroy($base); imagedestroy($mark);
            return $saved ? ['ok' => true, 'message' => 'Watermark created.'] : ['ok' => false, 'message' => 'Watermarked preview could not be written.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Watermark creation failed.'];
        }
    }

    private static function imageFrom(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private static function extraProtectionImage(int $width, int $height)
    {
        $source = self::extraProtectionSourcePath();

        if (!is_file($source)) {
            return false;
        }

        $info = @getimagesize($source);

        if (!$info || (int)$info[2] !== IMAGETYPE_PNG) {
            return false;
        }

        $original = @imagecreatefrompng($source);

        if (!$original) {
            return false;
        }

        $out = imagecreatetruecolor(
            max(1, $width),
            max(1, $height)
        );

        if (!$out) {
            imagedestroy($original);
            return false;
        }

        imagealphablending($out, false);
        imagesavealpha($out, true);

        $transparent = imagecolorallocatealpha(
            $out,
            255,
            255,
            255,
            127
        );

        imagefill($out, 0, 0, $transparent);

        $ok = imagecopyresampled(
            $out,
            $original,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($original),
            imagesy($original)
        );

        imagedestroy($original);

        if (!$ok) {
            imagedestroy($out);
            return false;
        }

        return $out;
    }

    private static function applyOpacity($image, int $opacityPercent)
    {
        $opacityPercent = max(0, min(100, $opacityPercent));
        $w = imagesx($image);
        $h = imagesy($image);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 255, 255, 255, 127);
        imagefill($out, 0, 0, $transparent);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $visible = 127 - $a;
                $newAlpha = 127 - (int)round($visible * ($opacityPercent / 100));
                $color = imagecolorallocatealpha($out, $r, $g, $b, max(0, min(127, $newAlpha)));
                imagesetpixel($out, $x, $y, $color);
            }
        }

        imagedestroy($image);
        return $out;
    }

    private static function saveImage($image, string $path, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 90),
            IMAGETYPE_PNG => imagepng($image, $path, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($image, $path, 88) : false,
            default => false,
        };
    }

    private static function watermarkImage(int $maxW, int $maxH)
    {
        $source = self::sourcePath();
        if (is_file($source) && ($info = @getimagesize($source))) {
            $img = self::imageFrom($source, (int)$info[2]);
            if ($img) {
                $scale = min($maxW / imagesx($img), $maxH / imagesy($img), 1);
                $w = max(1, (int)round(imagesx($img) * $scale));
                $h = max(1, (int)round(imagesy($img) * $scale));
                $resized = imagecreatetruecolor($w, $h);
                imagealphablending($resized, false); imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
                imagedestroy($img);
                return $resized;
            }
        }
        $w = max(120, min($maxW, 260)); $h = max(44, min($maxH, 90));
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false); imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $transparent);
        $white = imagecolorallocatealpha($img, 255, 255, 255, 0);
        imagestring($img, 5, 12, max(8, (int)($h / 2) - 8), 'Creative Moth', $white);
        return $img;
    }
}
