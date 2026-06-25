<?php spl_autoload_register(function ($class)
{
     $prefix = 'App\\';
     if (str_starts_with($class, $prefix))
    {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($path)) require $path;

    }

}
);
 $envFile = dirname(__DIR__) . '/.env';
 if (file_exists($envFile)) foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
{
     if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
     [$k,$v] = explode('=', $line, 2);
     $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"");

}
 ini_set('session.cookie_httponly', '1');
 ini_set('session.cookie_samesite', 'Lax');
 if (!empty($_SERVER['HTTPS'])) ini_set('session.cookie_secure', '1');
 session_name('design_marketplace');
 session_start();
 function app_path(string $path = ''): string
{
     return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : '');

}
 function public_path(string $path = ''): string
{
     return app_path('public' . ($path ? '/' . ltrim($path, '/') : ''));

}
 class_alias('App\\Core\\Helpers', 'H');
