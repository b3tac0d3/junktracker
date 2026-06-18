<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DevTrackerLog
{
    /** @return array<int, string> */
    public static function entryTypeOptions(): array
    {
        return ['created', 'comment', 'status_change', 'accepted', 'rejected', 'updated'];
    }

    public static function entryTypeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'created' => 'Reported',
            'comment' => 'Update',
            'status_change' => 'Status Change',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'updated' => 'Details Updated',
            default => ucwords(str_replace('_', ' ', trim($type))),
        };
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('dev_tracker_log_entries');
    }

    /**
     * @param array{
     *     body?: string|null,
     *     status_from?: string|null,
     *     status_to?: string|null,
     *     screenshot_path?: string|null
     * } $data
     */
    public static function append(int $itemId, string $entryType, array $data, int $actorUserId): int
    {
        if ($itemId <= 0 || !self::isAvailable()) {
            return 0;
        }

        $entryType = strtolower(trim($entryType));
        if (!in_array($entryType, self::entryTypeOptions(), true)) {
            $entryType = 'comment';
        }

        $body = trim((string) ($data['body'] ?? ''));
        $statusFrom = trim((string) ($data['status_from'] ?? ''));
        $statusTo = trim((string) ($data['status_to'] ?? ''));
        $screenshotPath = trim((string) ($data['screenshot_path'] ?? ''));

        $stmt = Database::connection()->prepare(
            'INSERT INTO dev_tracker_log_entries (
                dev_tracker_item_id,
                entry_type,
                body,
                status_from,
                status_to,
                screenshot_path,
                created_by,
                created_at
             ) VALUES (
                :dev_tracker_item_id,
                :entry_type,
                :body,
                :status_from,
                :status_to,
                :screenshot_path,
                :created_by,
                NOW()
             )'
        );
        $stmt->execute([
            'dev_tracker_item_id' => $itemId,
            'entry_type' => $entryType,
            'body' => $body !== '' ? $body : null,
            'status_from' => $statusFrom !== '' ? $statusFrom : null,
            'status_to' => $statusTo !== '' ? $statusTo : null,
            'screenshot_path' => $screenshotPath !== '' ? $screenshotPath : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forItem(int $itemId): array
    {
        if ($itemId <= 0 || !self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                l.id,
                l.dev_tracker_item_id,
                l.entry_type,
                l.body,
                l.status_from,
                l.status_to,
                l.screenshot_path,
                l.created_by,
                l.created_at,
                u.first_name,
                u.last_name,
                u.email
             FROM dev_tracker_log_entries l
             LEFT JOIN users u ON u.id = l.created_by
             WHERE l.dev_tracker_item_id = :item_id
             ORDER BY l.created_at ASC, l.id ASC'
        );
        $stmt->execute(['item_id' => $itemId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function authorLabel(array $entry): string
    {
        $full = trim(((string) ($entry['first_name'] ?? '')) . ' ' . ((string) ($entry['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        $email = trim((string) ($entry['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'System';
    }
}
