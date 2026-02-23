<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class UserAction
{
    private static ?bool $tableAvailable = null;

    public static function isAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$tableAvailable = false;
            return false;
        }

        $sql = 'SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = :table
                LIMIT 1';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'schema' => $schema,
                'table' => 'user_actions',
            ]);
            self::$tableAvailable = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }

    public static function create(array $data): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $columns = ['user_id', 'action_key', 'entity_table', 'entity_id', 'summary', 'details', 'ip_address', 'created_at'];
        $values = [':user_id', ':action_key', ':entity_table', ':entity_id', ':summary', ':details', ':ip_address', 'NOW()'];
        $params = [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'action_key' => (string) ($data['action_key'] ?? 'event'),
            'entity_table' => $data['entity_table'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'summary' => (string) ($data['summary'] ?? ''),
            'details' => $data['details'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ];
        if (self::hasBusinessColumn()) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql = 'INSERT INTO user_actions (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function forUser(int $userId, array $filters = []): array
    {
        if ($userId <= 0 || !self::isAvailable()) {
            return [];
        }

        $search = trim((string) ($filters['q'] ?? ''));
        $actionKey = trim((string) ($filters['action_key'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $sql = 'SELECT ua.id,
                       ua.user_id,
                       ua.action_key,
                       ua.entity_table,
                       ua.entity_id,
                       ua.summary,
                       ua.details,
                       ua.ip_address,
                       ua.created_at,
                       TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS actor_name,
                       u.email AS actor_email
                FROM user_actions ua
                LEFT JOIN users u
                    ON u.id = ua.user_id
                WHERE ua.user_id = :user_id';
        $params = ['user_id' => $userId];
        if (self::hasBusinessColumn()) {
            $sql .= ' AND ua.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        if ($actionKey !== '') {
            $sql .= ' AND ua.action_key = :action_key';
            $params['action_key'] = $actionKey;
        }
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(ua.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND DATE(ua.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if ($search !== '') {
            $searchParts = [
                'ua.action_key LIKE :search',
                'ua.entity_table LIKE :search',
                'ua.summary LIKE :search',
                'ua.details LIKE :search',
            ];
            $params['search'] = '%' . $search . '%';

            if (ctype_digit($search)) {
                $searchParts[] = 'ua.entity_id = :query_entity_id';
                $searchParts[] = 'ua.id = :query_action_id';
                $params['query_entity_id'] = (int) $search;
                $params['query_action_id'] = (int) $search;
            }

            $sql .= ' AND (' . implode(' OR ', $searchParts) . ')';
        }

        $sql .= ' ORDER BY ua.created_at DESC, ua.id DESC
                  LIMIT 1000';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function search(array $filters): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $sql = 'SELECT ua.id,
                       ua.user_id,
                       ua.action_key,
                       ua.entity_table,
                       ua.entity_id,
                       ua.summary,
                       ua.details,
                       ua.ip_address,
                       ua.created_at,
                       TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS actor_name,
                       u.email AS actor_email
                FROM user_actions ua
                LEFT JOIN users u
                    ON u.id = ua.user_id
                WHERE 1=1';
        $params = [];
        if (self::hasBusinessColumn()) {
            $sql .= ' AND ua.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $sql .= ' AND (
                        ua.action_key LIKE :q
                        OR ua.entity_table LIKE :q
                        OR ua.summary LIKE :q
                        OR ua.details LIKE :q
                        OR CAST(ua.entity_id AS CHAR) LIKE :q
                        OR CAST(ua.id AS CHAR) LIKE :q
                      )';
            $params['q'] = '%' . $query . '%';
        }

        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : 0;
        if ($userId > 0) {
            $sql .= ' AND ua.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $entityTable = trim((string) ($filters['entity_table'] ?? ''));
        if ($entityTable !== '') {
            $sql .= ' AND ua.entity_table = :entity_table';
            $params['entity_table'] = $entityTable;
        }

        $actionKey = trim((string) ($filters['action_key'] ?? ''));
        if ($actionKey !== '') {
            $sql .= ' AND ua.action_key = :action_key';
            $params['action_key'] = $actionKey;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(ua.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(ua.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY ua.created_at DESC, ua.id DESC
                  LIMIT 2000';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function entityOptions(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        try {
            $sql = 'SELECT DISTINCT entity_table
                    FROM user_actions
                    WHERE entity_table IS NOT NULL
                      AND entity_table <> \'\'';
            $params = [];
            if (self::hasBusinessColumn()) {
                $sql .= ' AND business_id = :business_id';
                $params['business_id'] = self::currentBusinessId();
            }
            $sql .= ' ORDER BY entity_table ASC';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            return array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['entity_table'] ?? ''),
                $rows
            )));
        } catch (Throwable) {
            return [];
        }
    }

    public static function actionOptions(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        try {
            $sql = 'SELECT DISTINCT action_key
                    FROM user_actions
                    WHERE action_key <> \'\'';
            $params = [];
            if (self::hasBusinessColumn()) {
                $sql .= ' AND business_id = :business_id';
                $params['business_id'] = self::currentBusinessId();
            }
            $sql .= ' ORDER BY action_key ASC';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            return array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['action_key'] ?? ''),
                $rows
            )));
        } catch (Throwable) {
            return [];
        }
    }

    private static function hasBusinessColumn(): bool
    {
        return Schema::hasColumn('user_actions', 'business_id');
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(1, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
