<?php
namespace App\Core;
use PDO;

class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (!self::$pdo) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1'; $db = $_ENV['DB_NAME'] ?? 'marketplace';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
            self::$pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
    public static function rows(string $sql, array $params=[]): array { $s=self::pdo()->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public static function row(string $sql, array $params=[]): ?array { $s=self::pdo()->prepare($sql); $s->execute($params); return $s->fetch() ?: null; }
    public static function exec(string $sql, array $params=[]): bool { $s=self::pdo()->prepare($sql); return $s->execute($params); }
    public static function id(): string { return self::pdo()->lastInsertId(); }
}
