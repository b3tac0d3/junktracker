<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Event
{
    public static function ensureTable(): void
    {
        if (SchemaInspector::hasTable('events')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS events (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    business_id INT UNSIGNED NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    type VARCHAR(40) NOT NULL DEFAULT 'appointment',
                    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
                    start_at DATETIME NOT NULL,
                    end_at DATETIME NULL,
                    all_day TINYINT(1) NOT NULL DEFAULT 0,
                    notes TEXT NULL,
                    link_type VARCHAR(40) NULL,
                    link_id INT UNSIGNED NULL,
                    created_by INT UNSIGNED NULL,
                    updated_by INT UNSIGNED NULL,
                    cancelled_at DATETIME NULL,
                    cancelled_by INT UNSIGNED NULL,
                    deleted_at DATETIME NULL,
                    deleted_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_events_business_start (business_id, start_at),
                    INDEX idx_events_business_type (business_id, type),
                    INDEX idx_events_business_status (business_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        Database::connection()->exec($sql);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function range(int $businessId, string $start, string $end, array $filters = []): array
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return [];
        }

        $start = self::normalizeIsoDateTime($start) ?? date('Y-m-01 00:00:00');
        $end = self::normalizeIsoDateTime($end) ?? date('Y-m-t 23:59:59');
        $q = trim((string) ($filters['q'] ?? ''));
        $types = is_array($filters['types'] ?? null) ? $filters['types'] : [];
        $statuses = is_array($filters['statuses'] ?? null) ? $filters['statuses'] : [];

        $where = [
            'business_id = :business_id',
            'deleted_at IS NULL',
            'start_at >= :start_at',
            'start_at < :end_at',
        ];

        $params = [
            'business_id' => $businessId,
            'start_at' => $start,
            'end_at' => $end,
        ];

        if ($q !== '') {
            $where[] = '(title LIKE :q OR COALESCE(notes, \'\') LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($types !== []) {
            $in = [];
            foreach (array_values($types) as $i => $type) {
                $key = 'type_' . $i;
                $in[] = ':' . $key;
                $params[$key] = strtolower(trim((string) $type));
            }
            if ($in !== []) {
                $where[] = 'LOWER(type) IN (' . implode(', ', $in) . ')';
            }
        }

        if ($statuses !== []) {
            $in = [];
            foreach (array_values($statuses) as $i => $status) {
                $key = 'status_' . $i;
                $in[] = ':' . $key;
                $params[$key] = strtolower(trim((string) $status));
            }
            if ($in !== []) {
                $where[] = 'LOWER(status) IN (' . implode(', ', $in) . ')';
            }
        }

        $sql = 'SELECT id, title, type, status, start_at, end_at, all_day, notes, link_type, link_id, cancelled_at
                FROM events
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY start_at ASC, id ASC
                LIMIT 3000';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $id): ?array
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM events
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'id' => $id,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO events (
                business_id, title, type, status, start_at, end_at, all_day, notes, link_type, link_id,
                created_by, updated_by, created_at, updated_at
            ) VALUES (
                :business_id, :title, :type, :status, :start_at, :end_at, :all_day, :notes, :link_type, :link_id,
                :created_by, :updated_by, NOW(), NOW()
            )'
        );

        $payload = self::sanitizePayload($data);
        $stmt->execute([
            'business_id' => $businessId,
            'title' => $payload['title'],
            'type' => $payload['type'],
            'status' => $payload['status'],
            'start_at' => $payload['start_at'],
            'end_at' => $payload['end_at'],
            'all_day' => $payload['all_day'] ? 1 : 0,
            'notes' => $payload['notes'],
            'link_type' => $payload['link_type'],
            'link_id' => $payload['link_id'],
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $id, array $data, int $actorUserId): bool
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return false;
        }

        $payload = self::sanitizePayload($data);
        $stmt = Database::connection()->prepare(
            'UPDATE events
             SET title = :title,
                 type = :type,
                 status = :status,
                 start_at = :start_at,
                 end_at = :end_at,
                 all_day = :all_day,
                 notes = :notes,
                 link_type = :link_type,
                 link_id = :link_id,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'title' => $payload['title'],
            'type' => $payload['type'],
            'status' => $payload['status'],
            'start_at' => $payload['start_at'],
            'end_at' => $payload['end_at'],
            'all_day' => $payload['all_day'] ? 1 : 0,
            'notes' => $payload['notes'],
            'link_type' => $payload['link_type'],
            'link_id' => $payload['link_id'],
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function setCancelled(int $businessId, int $id, bool $cancelled, int $actorUserId): bool
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE events
             SET status = :status,
                 cancelled_at = :cancelled_at,
                 cancelled_by = :cancelled_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'status' => $cancelled ? 'cancelled' : 'scheduled',
            'cancelled_at' => $cancelled ? date('Y-m-d H:i:s') : null,
            'cancelled_by' => $cancelled && $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function move(int $businessId, int $id, string $startAt, ?string $endAt, bool $allDay, int $actorUserId): bool
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return false;
        }

        $start = self::normalizeIsoDateTime($startAt);
        if ($start === null) {
            return false;
        }

        $end = $endAt !== null ? self::normalizeIsoDateTime($endAt) : null;

        $stmt = Database::connection()->prepare(
            'UPDATE events
             SET start_at = :start_at,
                 end_at = :end_at,
                 all_day = :all_day,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'start_at' => $start,
            'end_at' => $end,
            'all_day' => $allDay ? 1 : 0,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $id, int $actorUserId): bool
    {
        self::ensureTable();
        if (!SchemaInspector::hasTable('events')) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE events
             SET deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    private static function sanitizePayload(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Untitled';
        }

        $type = strtolower(trim((string) ($data['type'] ?? 'appointment')));
        if (!in_array($type, ['appointment', 'cancellation', 'task', 'note', 'reminder', 'other'], true)) {
            $type = 'appointment';
        }

        $status = strtolower(trim((string) ($data['status'] ?? 'scheduled')));
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            $status = 'scheduled';
        }

        $start = self::normalizeIsoDateTime((string) ($data['start_at'] ?? '')) ?? date('Y-m-d H:i:s');
        $end = trim((string) ($data['end_at'] ?? ''));
        $endNorm = $end !== '' ? self::normalizeIsoDateTime($end) : null;

        $allDay = ((string) ($data['all_day'] ?? '0')) === '1' || !empty($data['all_day']);
        $notes = trim((string) ($data['notes'] ?? ''));

        $linkType = strtolower(trim((string) ($data['link_type'] ?? '')));
        if ($linkType === '') {
            $linkType = null;
        }
        $linkId = isset($data['link_id']) && (int) $data['link_id'] > 0 ? (int) $data['link_id'] : null;

        return [
            'title' => $title,
            'type' => $type,
            'status' => $status,
            'start_at' => $start,
            'end_at' => $endNorm,
            'all_day' => $allDay,
            'notes' => $notes,
            'link_type' => $linkType,
            'link_id' => $linkId,
        ];
    }

    private static function normalizeIsoDateTime(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

