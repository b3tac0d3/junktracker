<?php

declare(strict_types=1);

namespace Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = config('database');

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed: ' . $exception->getMessage());
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $offset = self::timezoneOffset($timezone);
        if ($offset !== null) {
            self::$connection->exec('SET time_zone = ' . self::$connection->quote($offset));
        }

        return self::$connection;
    }

    private static function timezoneOffset(string $timezone): ?string
    {
        try {
            $zone = new DateTimeZone($timezone);
            return (new DateTimeImmutable('now', $zone))->format('P');
        } catch (Throwable) {
            return null;
        }
    }
}
