<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class AuditLog
{
    public static function write(
        string $action,
        string $entity,
        ?int $entityId,
        ?int $businessId,
        ?int $userId,
        array $meta = []
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_logs (
                business_id, user_id, action, entity, entity_id, metadata_json, created_at
             ) VALUES (
                :business_id, :user_id, :action, :entity, :entity_id, :metadata_json, NOW()
             )'
        );

        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata_json' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array{q?: string, user_id?: int, date?: string, entity?: string} $filters
     */
    public static function indexCount(int $businessId, array $filters = []): int
    {
        [$whereSql, $params] = self::filterClause($businessId, $filters);
        $sql = 'SELECT COUNT(*)
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array{q?: string, user_id?: int, date?: string, entity?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function indexList(int $businessId, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        [$whereSql, $params] = self::filterClause($businessId, $filters);
        $sql = 'SELECT
                    al.id,
                    al.business_id,
                    al.user_id,
                    al.action,
                    al.entity,
                    al.entity_id,
                    al.metadata_json,
                    al.created_at,
                    u.email AS user_email,
                    u.first_name AS user_first_name,
                    u.last_name AS user_last_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE ' . $whereSql . '
                ORDER BY al.created_at DESC, al.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $metaRaw = $row['metadata_json'] ?? null;
            if (is_string($metaRaw) && $metaRaw !== '') {
                $decoded = json_decode($metaRaw, true);
                $row['metadata'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['metadata'] = [];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function distinctEntities(int $businessId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT entity
             FROM activity_logs
             WHERE business_id = :business_id
             ORDER BY entity ASC'
        );
        $stmt->execute(['business_id' => $businessId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $entities = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entity = trim((string) ($row['entity'] ?? ''));
            if ($entity !== '') {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @param array{q?: string, user_id?: int, date?: string, entity?: string} $filters
     * @return array{0: string, 1: array<string, int|string>}
     */
    private static function filterClause(int $businessId, array $filters): array
    {
        $where = ['al.business_id = :business_id'];
        $params = ['business_id' => $businessId];

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $entity = trim((string) ($filters['entity'] ?? ''));
        if ($entity !== '') {
            $where[] = 'al.entity = :entity';
            $params['entity'] = $entity;
        }

        $date = trim((string) ($filters['date'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $start = $date . ' 00:00:00';
            $endTs = strtotime($date . ' +1 day');
            $end = $endTs !== false ? date('Y-m-d H:i:s', $endTs) : ($date . ' 23:59:59');
            $where[] = 'al.created_at >= :date_start AND al.created_at < :date_end';
            $params['date_start'] = $start;
            $params['date_end'] = $end;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(
                al.action LIKE :query_like
                OR al.entity LIKE :query_like
                OR CAST(al.entity_id AS CHAR) LIKE :query_like
                OR COALESCE(al.metadata_json, \'\') LIKE :query_like
                OR COALESCE(u.first_name, \'\') LIKE :query_like
                OR COALESCE(u.last_name, \'\') LIKE :query_like
                OR COALESCE(u.email, \'\') LIKE :query_like
            )';
            $params['query_like'] = '%' . $query . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
