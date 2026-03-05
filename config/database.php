<?php
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST']    ?? 'localhost';
            $dbname  = $_ENV['DB_NAME']    ?? '';
            $user    = $_ENV['DB_USER']    ?? '';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = 'utf8mb4';
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    public static function query(string $sql, array $p = []): PDOStatement {
        $s = self::getConnection()->prepare($sql);
        $s->execute($p);
        return $s;
    }

    public static function fetchAll(string $sql, array $p = []): array {
        return self::query($sql, $p)->fetchAll();
    }

    public static function fetchOne(string $sql, array $p = []): ?array {
        $r = self::query($sql, $p)->fetch();
        return $r ?: null;
    }

    public static function lastInsertId(): string {
        return self::getConnection()->lastInsertId();
    }
}
