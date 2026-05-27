<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class GoogleCalendarEventLink
{
    public static function ensureTable(): void
    {
        if (SchemaInspector::hasTable('google_calendar_event_links')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS google_calendar_event_links (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    source_type VARCHAR(40) NOT NULL,
                    source_id BIGINT UNSIGNED NOT NULL,
                    google_calendar_id VARCHAR(190) NOT NULL,
                    google_event_id VARCHAR(190) NOT NULL,
                    last_synced_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_google_calendar_event_link (user_id, source_type, source_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::connection()->exec($sql);
    }

    public static function find(int $userId, string $sourceType, int $sourceId): ?array
    {
        self::ensureTable();
        if ($userId <= 0 || $sourceId <= 0 || !SchemaInspector::hasTable('google_calendar_event_links')) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM google_calendar_event_links
             WHERE user_id = :user_id
               AND source_type = :source_type
               AND source_id = :source_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'source_type' => strtolower(trim($sourceType)),
            'source_id' => $sourceId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function upsert(int $userId, string $sourceType, int $sourceId, string $calendarId, string $googleEventId): void
    {
        self::ensureTable();
        if ($userId <= 0 || $sourceId <= 0 || !SchemaInspector::hasTable('google_calendar_event_links')) {
            return;
        }

        $sourceType = strtolower(trim($sourceType));
        $calendarId = trim($calendarId);
        $googleEventId = trim($googleEventId);
        if ($sourceType === '' || $calendarId === '' || $googleEventId === '') {
            return;
        }

        $existing = self::find($userId, $sourceType, $sourceId);
        if ($existing === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO google_calendar_event_links (
                    user_id, source_type, source_id, google_calendar_id, google_event_id, last_synced_at, created_at, updated_at
                 ) VALUES (
                    :user_id, :source_type, :source_id, :google_calendar_id, :google_event_id, NOW(), NOW(), NOW()
                 )'
            );
            $stmt->execute([
                'user_id' => $userId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'google_calendar_id' => $calendarId,
                'google_event_id' => $googleEventId,
            ]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE google_calendar_event_links
             SET google_calendar_id = :google_calendar_id,
                 google_event_id = :google_event_id,
                 last_synced_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => (int) ($existing['id'] ?? 0),
            'google_calendar_id' => $calendarId,
            'google_event_id' => $googleEventId,
        ]);
    }

    public static function delete(int $userId, string $sourceType, int $sourceId): void
    {
        self::ensureTable();
        if ($userId <= 0 || $sourceId <= 0 || !SchemaInspector::hasTable('google_calendar_event_links')) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM google_calendar_event_links
             WHERE user_id = :user_id
               AND source_type = :source_type
               AND source_id = :source_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'source_type' => strtolower(trim($sourceType)),
            'source_id' => $sourceId,
        ]);
    }

    public static function deleteAllForUser(int $userId): void
    {
        self::ensureTable();
        if ($userId <= 0 || !SchemaInspector::hasTable('google_calendar_event_links')) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM google_calendar_event_links WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
    }
}
