<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientContact
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('client_contacts');
    }

    public static function create(int $businessId, int $clientId, array $payload, int $actorUserId): int
    {
        if ($businessId <= 0 || $clientId <= 0 || !self::isAvailable()) {
            return 0;
        }

        $columns = [];
        $values = [];
        $params = [];

        if (SchemaInspector::hasColumn('client_contacts', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('client_contacts', 'client_id')) {
            $columns[] = 'client_id';
            $values[] = ':client_id';
            $params['client_id'] = $clientId;
        }
        if (SchemaInspector::hasColumn('client_contacts', 'contacted_at')) {
            $columns[] = 'contacted_at';
            $values[] = ':contacted_at';
            $params['contacted_at'] = trim((string) ($payload['contacted_at'] ?? '')) !== ''
                ? trim((string) $payload['contacted_at'])
                : date('Y-m-d H:i:s');
        }
        if (SchemaInspector::hasColumn('client_contacts', 'contact_type')) {
            $columns[] = 'contact_type';
            $values[] = ':contact_type';
            $params['contact_type'] = trim((string) ($payload['contact_type'] ?? ''));
        }
        if (SchemaInspector::hasColumn('client_contacts', 'note')) {
            $columns[] = 'note';
            $values[] = ':note';
            $params['note'] = trim((string) ($payload['note'] ?? ''));
        }
        if (SchemaInspector::hasColumn('client_contacts', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('client_contacts', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }
        if (SchemaInspector::hasColumn('client_contacts', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        if ($columns === [] || $values === []) {
            return 0;
        }

        $sql = 'INSERT INTO client_contacts (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forClient(int $businessId, int $clientId, int $limit = 50): array
    {
        if ($businessId <= 0 || $clientId <= 0 || !self::isAvailable()) {
            return [];
        }

        $contactedAtSql = SchemaInspector::hasColumn('client_contacts', 'contacted_at')
            ? 'cc.contacted_at'
            : (SchemaInspector::hasColumn('client_contacts', 'created_at') ? 'cc.created_at' : 'NULL');
        $typeSql = SchemaInspector::hasColumn('client_contacts', 'contact_type') ? 'cc.contact_type' : "''";
        $noteSql = SchemaInspector::hasColumn('client_contacts', 'note') ? 'cc.note' : "''";

        $createdByIdSql = SchemaInspector::hasColumn('client_contacts', 'created_by') ? 'cc.created_by' : 'NULL';
        $createdByNameSql = "''";
        $joins = [];

        if (SchemaInspector::hasColumn('client_contacts', 'created_by') && SchemaInspector::hasTable('users')) {
            $join = 'LEFT JOIN users u ON u.id = cc.created_by';
            if (
                SchemaInspector::hasColumn('users', 'business_id')
                && SchemaInspector::hasColumn('client_contacts', 'business_id')
            ) {
                $join .= ' AND u.business_id = cc.business_id';
            }
            if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                $join .= ' AND u.deleted_at IS NULL';
            }
            $joins[] = $join;
            $createdByNameSql = "COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''),
                NULLIF(u.email, ''),
                CONCAT('User #', u.id)
            )";
        }

        $where = ['cc.client_id = :client_id'];
        if (SchemaInspector::hasColumn('client_contacts', 'business_id')) {
            $where[] = 'cc.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('client_contacts', 'deleted_at')) {
            $where[] = 'cc.deleted_at IS NULL';
        }

        $sql = "SELECT
                    cc.id,
                    {$contactedAtSql} AS contacted_at,
                    {$typeSql} AS contact_type,
                    {$noteSql} AS note,
                    {$createdByIdSql} AS created_by,
                    {$createdByNameSql} AS created_by_name
                FROM client_contacts cc
                " . implode("\n", $joins) . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$contactedAtSql} DESC, cc.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('client_contacts', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

