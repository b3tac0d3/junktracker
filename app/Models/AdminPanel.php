<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class AdminPanel
{
    public static function healthSummary(): array
    {
        return [
            'pending_invites' => self::pendingInvites(),
            'expired_invites' => self::expiredInvites(),
            'failed_mail_24h' => self::failedMailCountLastDay(),
            'active_sessions' => self::activeSessionCount(),
            'overdue_tasks' => self::overdueTaskCount(),
        ];
    }

    private static function pendingInvites(): int
    {
        return self::safeCount(static function (): int {
            User::ensureAuthColumns();
            $sql = 'SELECT COUNT(*)
                    FROM users
                    WHERE is_active = 1
                      AND password_setup_sent_at IS NOT NULL
                      AND password_setup_used_at IS NULL';
            return (int) Database::connection()->query($sql)->fetchColumn();
        });
    }

    private static function expiredInvites(): int
    {
        return self::safeCount(static function (): int {
            User::ensureAuthColumns();
            $sql = 'SELECT COUNT(*)
                    FROM users
                    WHERE is_active = 1
                      AND password_setup_sent_at IS NOT NULL
                      AND password_setup_used_at IS NULL
                      AND password_setup_expires_at IS NOT NULL
                      AND password_setup_expires_at < NOW()';
            return (int) Database::connection()->query($sql)->fetchColumn();
        });
    }

    private static function overdueTaskCount(): int
    {
        return self::safeCount(static function (): int {
            $sql = 'SELECT COUNT(*)
                    FROM todos
                    WHERE deleted_at IS NULL
                      AND status IN (\'open\', \'in_progress\')
                      AND due_at IS NOT NULL
                      AND due_at < NOW()';
            return (int) Database::connection()->query($sql)->fetchColumn();
        });
    }

    private static function activeSessionCount(): int
    {
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            return 0;
        }

        $files = @scandir($dir);
        if (!is_array($files)) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if (str_starts_with($file, 'sess_')) {
                $count++;
            }
        }

        return $count;
    }

    private static function failedMailCountLastDay(): int
    {
        $file = BASE_PATH . '/storage/logs/mail.log';
        if (!is_file($file) || !is_readable($file)) {
            return 0;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return 0;
        }

        $cutoff = time() - 86400;
        $count = 0;

        foreach ($lines as $line) {
            if (strpos($line, 'FAILED') === false) {
                continue;
            }

            if (preg_match('/^\[(.*?)\]/', $line, $matches) !== 1) {
                continue;
            }

            $timestamp = strtotime((string) ($matches[1] ?? ''));
            if ($timestamp !== false && $timestamp >= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    private static function safeCount(callable $callback): int
    {
        try {
            return max(0, (int) $callback());
        } catch (Throwable) {
            return 0;
        }
    }
}

