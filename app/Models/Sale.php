<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Sale
{
    private const TYPES = ['shop', 'scrap', 'ebay', 'other'];

    public static function filter(array $filters): array
    {
        $hasDisposalLocationId = Schema::hasColumn('sales', 'disposal_location_id');
        $hasCreatedBy = Schema::hasColumn('sales', 'created_by');

        $disposalLocationSelect = $hasDisposalLocationId ? 's.disposal_location_id' : 'NULL';
        $disposalLocationNameSelect = $hasDisposalLocationId ? 'dl.name' : 'NULL';
        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uc.first_name, uc.last_name)), \'\'), CONCAT(\'User #\', s.created_by))'
            : '\'—\'';

        $disposalJoin = $hasDisposalLocationId ? ' LEFT JOIN disposal_locations dl ON dl.id = s.disposal_location_id' : '';
        $createdByJoin = $hasCreatedBy ? ' LEFT JOIN users uc ON uc.id = s.created_by' : '';

        $sql = 'SELECT
                    s.id,
                    s.job_id,
                    s.type,
                    s.name,
                    s.note,
                    s.start_date,
                    s.end_date,
                    s.gross_amount,
                    s.net_amount,
                    s.active,
                    s.created_at,
                    s.updated_at,
                    s.deleted_at,
                    ' . $disposalLocationSelect . ' AS disposal_location_id,
                    COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS job_name,
                    ' . $disposalLocationNameSelect . ' AS disposal_location_name,
                    ' . $createdByNameSelect . ' AS created_by_name
                FROM sales s
                LEFT JOIN jobs j ON j.id = s.job_id'
                . $disposalJoin
                . $createdByJoin;

        $where = [];
        $params = [];

        if (Schema::hasColumn('sales', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                $where[] = '1 = 0';
            } else {
                $where[] = 's.business_id = :business_id';
                $params['business_id'] = $businessId;
            }
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $searchClauses = [
                's.name LIKE :q',
                's.note LIKE :q',
                's.type LIKE :q',
                'CAST(s.id AS CHAR) LIKE :q',
                'CAST(s.job_id AS CHAR) LIKE :q',
                'j.name LIKE :q',
            ];
            if ($hasDisposalLocationId) {
                $searchClauses[] = 'dl.name LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $searchClauses) . ')';
            $params['q'] = '%' . $query . '%';
        }

        $type = (string) ($filters['type'] ?? 'all');
        if (in_array($type, ['shop', 'scrap', 'ebay', 'other'], true)) {
            $where[] = 's.type = :type';
            $params['type'] = $type;
        }

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = '(s.deleted_at IS NULL AND COALESCE(s.active, 1) = 1)';
        } elseif ($recordStatus === 'deleted') {
            $where[] = '(s.deleted_at IS NOT NULL OR COALESCE(s.active, 1) = 0)';
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $where[] = 'DATE(COALESCE(s.start_date, s.created_at)) >= :start_date';
            $params['start_date'] = $startDate;
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $where[] = 'DATE(COALESCE(s.end_date, s.start_date, s.created_at)) <= :end_date';
            $params['end_date'] = $endDate;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY COALESCE(s.end_date, s.start_date, DATE(s.created_at)) DESC, s.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $hasDisposalLocationId = Schema::hasColumn('sales', 'disposal_location_id');
        $hasCreatedBy = Schema::hasColumn('sales', 'created_by');
        $hasDeletedBy = Schema::hasColumn('sales', 'deleted_by');
        $hasUpdatedBy = Schema::hasColumn('sales', 'updated_by');

        $disposalLocationSelect = $hasDisposalLocationId ? 's.disposal_location_id' : 'NULL';
        $disposalLocationNameSelect = $hasDisposalLocationId ? 'dl.name' : 'NULL';
        $createdBySelect = $hasCreatedBy ? 's.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 's.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 's.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', s.created_by))'
            : '\'—\'';
        $updatedByNameSelect = $hasUpdatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', s.updated_by))'
            : '\'—\'';
        $deletedByNameSelect = $hasDeletedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', s.deleted_by))'
            : '\'—\'';

        $joins = ' LEFT JOIN jobs j ON j.id = s.job_id
                   LEFT JOIN clients c ON c.id = j.client_id';
        if ($hasDisposalLocationId) {
            $joins .= ' LEFT JOIN disposal_locations dl ON dl.id = s.disposal_location_id';
        }
        if ($hasCreatedBy) {
            $joins .= ' LEFT JOIN users u_created ON u_created.id = s.created_by';
        }
        if ($hasUpdatedBy) {
            $joins .= ' LEFT JOIN users u_updated ON u_updated.id = s.updated_by';
        }
        if ($hasDeletedBy) {
            $joins .= ' LEFT JOIN users u_deleted ON u_deleted.id = s.deleted_by';
        }

        $sql = 'SELECT
                    s.id,
                    s.job_id,
                    s.type,
                    s.name,
                    s.note,
                    s.start_date,
                    s.end_date,
                    s.gross_amount,
                    s.net_amount,
                    s.active,
                    s.created_at,
                    s.updated_at,
                    s.deleted_at,
                    ' . $disposalLocationSelect . ' AS disposal_location_id,
                    ' . $disposalLocationNameSelect . ' AS disposal_location_name,
                    COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS job_name,
                    COALESCE(
                        NULLIF(c.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                        CONCAT(\'Client #\', c.id)
                    ) AS client_name,
                    ' . $createdBySelect . ' AS created_by,
                    ' . $createdByNameSelect . ' AS created_by_name,
                    ' . $updatedBySelect . ' AS updated_by,
                    ' . $updatedByNameSelect . ' AS updated_by_name,
                    ' . $deletedBySelect . ' AS deleted_by,
                    ' . $deletedByNameSelect . ' AS deleted_by_name
                FROM sales s'
                . $joins . '
                WHERE s.id = :id' . (Schema::hasColumn('sales', 'business_id') ? '
                  AND s.business_id = :business_id' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('sales', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                return null;
            }
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);
        $sale = $stmt->fetch();

        return $sale ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $hasDisposalLocationId = Schema::hasColumn('sales', 'disposal_location_id');
        $hasActive = Schema::hasColumn('sales', 'active');
        $hasCreatedBy = Schema::hasColumn('sales', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('sales', 'updated_by');

        $columns = [
            'job_id',
            'type',
            'name',
            'note',
            'start_date',
            'end_date',
            'gross_amount',
            'net_amount',
        ];
        $values = [
            ':job_id',
            ':type',
            ':name',
            ':note',
            ':start_date',
            ':end_date',
            ':gross_amount',
            ':net_amount',
        ];
        $params = [
            'job_id' => $data['job_id'],
            'type' => $data['type'],
            'name' => $data['name'],
            'note' => $data['note'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'gross_amount' => $data['gross_amount'],
            'net_amount' => $data['net_amount'],
        ];

        if ($hasDisposalLocationId) {
            $columns[] = 'disposal_location_id';
            $values[] = ':disposal_location_id';
            $params['disposal_location_id'] = $data['disposal_location_id'];
        }
        if (Schema::hasColumn('sales', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                throw new \RuntimeException('No active business workspace selected.');
            }
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = $businessId;
        }
        if ($hasActive) {
            $columns[] = 'active';
            $values[] = '1';
        }
        if ($actorId !== null && $hasCreatedBy) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && $hasUpdatedBy) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO sales (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        $hasActive = Schema::hasColumn('sales', 'active');
        $hasDeletedAt = Schema::hasColumn('sales', 'deleted_at');
        $hasDeletedBy = Schema::hasColumn('sales', 'deleted_by');
        $hasUpdatedBy = Schema::hasColumn('sales', 'updated_by');

        $sets = [];
        $params = ['id' => $id];

        if ($hasActive) {
            $sets[] = 'active = 0';
        }
        if ($hasDeletedAt) {
            $sets[] = 'deleted_at = COALESCE(deleted_at, NOW())';
        }
        if ($actorId !== null && $hasDeletedBy) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }
        if ($actorId !== null && $hasUpdatedBy) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        if (empty($sets)) {
            return;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';
        if (Schema::hasColumn('sales', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                return;
            }
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = $businessId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $hasDisposalLocationId = Schema::hasColumn('sales', 'disposal_location_id');
        $hasUpdatedBy = Schema::hasColumn('sales', 'updated_by');

        $sets = [
            'job_id = :job_id',
            'type = :type',
            'name = :name',
            'note = :note',
            'start_date = :start_date',
            'end_date = :end_date',
            'gross_amount = :gross_amount',
            'net_amount = :net_amount',
        ];
        $params = [
            'id' => $id,
            'job_id' => $data['job_id'],
            'type' => $data['type'],
            'name' => $data['name'],
            'note' => $data['note'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'gross_amount' => $data['gross_amount'],
            'net_amount' => $data['net_amount'],
        ];

        if ($hasDisposalLocationId) {
            $sets[] = 'disposal_location_id = :disposal_location_id';
            $params['disposal_location_id'] = $data['disposal_location_id'];
        }
        if ($actorId !== null && $hasUpdatedBy) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';
        if (Schema::hasColumn('sales', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                return;
            }
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = $businessId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function disposalLocations(): array
    {
        if (!Schema::hasColumn('sales', 'disposal_location_id')) {
            return [];
        }

        $sql = 'SELECT id, name
                FROM disposal_locations
                WHERE deleted_at IS NULL
                  AND active = 1';
        $params = [];
        if (Schema::hasColumn('disposal_locations', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                return [];
            }
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        $sql .= '
                ORDER BY name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function validTypes(): array
    {
        return self::TYPES;
    }

    public static function summarize(array $rows): array
    {
        $summary = [
            'record_count' => 0,
            'gross_total' => 0.0,
            'net_total' => 0.0,
            'scrap_gross_total' => 0.0,
            'scrap_net_total' => 0.0,
            'shop_gross_total' => 0.0,
            'shop_net_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $summary['record_count']++;
            $gross = (float) ($row['gross_amount'] ?? 0);
            $net = ($row['net_amount'] ?? null) === null ? $gross : (float) $row['net_amount'];
            $type = (string) ($row['type'] ?? '');

            $summary['gross_total'] += $gross;
            $summary['net_total'] += $net;

            if ($type === 'scrap') {
                $summary['scrap_gross_total'] += $gross;
                $summary['scrap_net_total'] += $net;
            } elseif ($type === 'shop') {
                $summary['shop_gross_total'] += $gross;
                $summary['shop_net_total'] += $net;
            }
        }

        return $summary;
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return (int) current_business_id();
        }

        return (int) config('app.default_business_id', 1);
    }
}
