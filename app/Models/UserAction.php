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

        $sql = 'INSERT INTO user_actions
                    (user_id, action_key, entity_table, entity_id, summary, details, ip_address, created_at)
                VALUES
                    (:user_id, :action_key, :entity_table, :entity_id, :summary, :details, :ip_address, NOW())';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => (int) ($data['user_id'] ?? 0),
            'action_key' => (string) ($data['action_key'] ?? 'event'),
            'entity_table' => $data['entity_table'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'summary' => (string) ($data['summary'] ?? ''),
            'details' => $data['details'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);
    }

    public static function forUser(int $userId, string $query = ''): array
    {
        if ($userId <= 0 || !self::isAvailable()) {
            return [];
        }

        $search = trim($query);
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
}
