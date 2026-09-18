<?php

namespace App\Core;
class Helpers
{
    public const SITE_NAME = 'Creative Moth';
    public const SITE_SHORT_NAME = 'Creative Moth';
    public const DEFAULT_DESCRIPTION = 'Shop digital designs, templates, graphics, and creative files from independent designers on Creative Moth.';

    public static function baseUrl(): string
    {
        $url = trim($_ENV['APP_URL'] ?? '');
        return rtrim($url !== '' ? $url : 'https://marketplace.dieseldesigns.co', '/');

    }

    public static function canonical(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return self::baseUrl() . $path;

    }

    public static function assetUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return self::baseUrl() . '/' . ltrim($path, '/');

    }

     public static function e(?string $v): string
    {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

    }
     public static function money($v): string
    {
        return '$' . number_format((float)$v, 2);

    }
     public static function slug(string $v): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($v)), '-');

    }
     public static function csrf(): string
    {
        $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];

    }
     public static function verifyCsrf(): void
    {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '') )
        {
            self::abort(419);

        }

    }
     public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;

    }
     public static function requireLogin(): void
    {
        $sessionUser = self::user();
        if (!$sessionUser || empty($sessionUser['id'])) self::redirect('/login');
        $authoritative = Database::row(
            'select id,name,email,role,status,referral_code from users where id=? limit 1',
            [(int) $sessionUser['id']]
        );
        if (!$authoritative || $authoritative['status'] !== 'active') {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            self::flash('warning', 'Your session is no longer active. Please log in again.');
            self::redirect('/login');
        }
        unset($authoritative['status']);
        $_SESSION['user'] = $authoritative;

    }
     public static function requireRole(string $role): void
    {
        self::requireLogin();
        if ((self::user()['role'] ?? '') !== $role) self::abort(403);

    }
     public static function canAdmin(string $permission): bool
    {
        $user = self::user();
        return $user && (new \App\Services\AdminPermissionService())->can((int) $user['id'], $permission);

    }
     public static function requireAdminPermission(string $permission): void
    {
        self::requireLogin();
        (new \App\Services\AdminPermissionService())->require((int) self::user()['id'], $permission);

    }
     public static function requireSeller(): void
    {
        self::requireLogin();
        if (self::hasApprovedDesigner((int) self::user()['id'])) return;
        self::flash('warning', 'You need an approved designer account before accessing the seller dashboard.');
        self::redirect('/apply');

    }
     public static function hasApprovedDesigner(?int $userId = null): bool
    {
        $userId ??= (int) (self::user()['id'] ?? 0);
        return $userId > 0 && (bool) Database::row(
            'select id from designers where user_id=? and status="approved" limit 1',
            [$userId]
        );

    }
     public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];

    }
     public static function flashes(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;

    }
     public static function redirect(string $to): never
    {
        header('Location: '.$to);
        exit;

    }
     public static function abort(int $code): never
    {
        http_response_code($code);
        echo "<h1>$code</h1><p>Request cannot be completed.</p>";
        exit;

    }
     public static function view(string $view, array $data=[]): void
    {
        extract($data);
        require app_path('app/Views/layouts/app.php');

    }

     public static function minimalView(string $view, array $data=[]): void
    {
        extract($data);
        require app_path('app/Views/layouts/minimal.php');

    }

}
