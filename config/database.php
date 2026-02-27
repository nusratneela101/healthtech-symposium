<?php
class Database {
    private static ?PDO $instance = null;
    private static array $config = [
        'host'    => 'localhost',
        'dbname'  => 'YOUR_DB_NAME',
        'user'    => 'YOUR_DB_USER',
        'pass'    => 'YOUR_DB_PASS',
        'charset' => 'utf8mb4',
    ];

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $c = self::$config;
            $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
            self::$instance = new PDO($dsn, $c['user'], $c['pass'], [
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
