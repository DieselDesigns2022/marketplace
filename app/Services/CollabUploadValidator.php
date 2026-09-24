<?php

namespace App\Services;

use DomainException;

final class CollabUploadValidator
{
    private const TERMS_EXTENSIONS = ['pdf','txt','rtf','doc','docx','odt'];
    private const EXECUTABLE_EXTENSIONS = ['php','php3','php4','php5','phtml','phar','cgi','pl','py','sh','exe','com','bat','cmd','js','html','htm'];

    public function validate(array $upload, string $kind): array
    {
        if (!in_array($kind, ['contribution','terms'], true)) throw new DomainException('Choose a valid file type.');
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) throw new DomainException($this->uploadError($error));
        $temporary = (string)($upload['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary) || !is_file($temporary)) throw new DomainException('The uploaded file could not be verified.');
        $size = filesize($temporary);
        if ($size === false || $size < 1) throw new DomainException('The uploaded file is empty.');
        $limit = self::serverUploadLimitBytes();
        if ($size > $limit) throw new DomainException('The uploaded file exceeds the marketplace server upload limit.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: 'application/octet-stream';
        return $this->validateMetadata((string)($upload['name'] ?? ''), (int)$size, $kind, $mime, $temporary);
    }

    public function validateMetadata(string $submittedName, int $size, string $kind, string $mime = 'application/octet-stream', ?string $temporary = null): array
    {
        if (!in_array($kind, ['contribution','terms'], true)) throw new DomainException('Choose a valid file type.');
        if ($size < 1) throw new DomainException('The uploaded file is empty.');
        if ($size > self::serverUploadLimitBytes()) throw new DomainException('The uploaded file exceeds the marketplace server upload limit.');
        $original = basename(str_replace('\\', '/', trim($submittedName)));
        if ($original === '' || strlen($original) > 255) throw new DomainException('The uploaded filename is invalid or too long.');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::EXECUTABLE_EXTENSIONS, true)) throw new DomainException('Executable or unsafe file types are not accepted.');
        if ($kind === 'terms' && !in_array($extension, self::TERMS_EXTENSIONS, true)) throw new DomainException('Terms/license files must be PDF, TXT, RTF, DOC, DOCX, or ODT.');
        return ['temporary'=>$temporary,'original'=>$original,'extension'=>$extension,'size'=>$size,'mime'=>$mime];
    }

    public static function replacementKind(string $existingKind, string $submittedKind): string
    {
        if (!in_array($existingKind, ['contribution','terms'], true)) throw new DomainException('The stored file type is invalid.');
        if ($submittedKind !== '' && $submittedKind !== $existingKind) throw new DomainException('A replacement must keep the original file type.');
        return $existingKind;
    }

    public static function serverUploadLimitBytes(): int
    {
        $limits = array_filter([self::iniBytes((string)ini_get('upload_max_filesize')), self::iniBytes((string)ini_get('post_max_size'))]);
        return $limits ? min($limits) : PHP_INT_MAX;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') return 0;
        $number = (int)$value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the marketplace server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            default => 'The file upload failed. Please try again.',
        };
    }
}
