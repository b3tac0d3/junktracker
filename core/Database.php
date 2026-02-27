<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = config('database');
        if (!is_array($config)) {
            throw new PDOException('Database configuration is missing.');
        }

        $driver = (string) ($config['driver'] ?? 'mysql');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);

        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}
