<?php
namespace App\Core;

class Helpers {
    public static function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
    public static function money($v): string { return '$' . number_format((float)$v, 2); }
    public static function slug(string $v): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($v)), '-'); }
    public static function csrf(): string { $_SESSION['_csrf'] ??= bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
    public static function verifyCsrf(): void {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')
    ) {
        self::abort(419);
    }
}
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function requireLogin(): void { if (!self::user()) self::redirect('/login'); }
    public static function requireRole(string $role): void { self::requireLogin(); if ((self::user()['role'] ?? '') !== $role) self::abort(403); }
    public static function requireSeller(): void { self::requireLogin(); if (in_array(self::user()['role'] ?? '', ['designer','admin'], true)) return; $designer = Database::row('select id from designers where user_id=? and status="approved" limit 1', [self::user()['id']]); if ($designer) { $_SESSION['user']['role'] = 'designer'; return; } self::flash('warning', 'You need an approved designer account before accessing the seller dashboard.'); self::redirect('/apply'); }
    public static function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type' => $type, 'message' => $message]; }
    public static function flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
    public static function redirect(string $to): never { header('Location: '.$to); exit; }
    public static function abort(int $code): never { http_response_code($code); echo "<h1>$code</h1><p>Request cannot be completed.</p>"; exit; }
    public static function view(string $view, array $data=[]): void { extract($data); require app_path('app/Views/layouts/app.php'); }
}
