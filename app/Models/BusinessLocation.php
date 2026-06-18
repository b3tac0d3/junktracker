<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class BusinessLocation
{
    public const TYPE_STORE = 'store';
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_TERMINAL = 'terminal';
    public const TYPE_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_STORE => 'Store',
            self::TYPE_WAREHOUSE => 'Warehouse',
            self::TYPE_TERMINAL => 'Terminal',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('business_locations');
    }

    public static function labelForType(string $type): string
    {
        $type = strtolower(trim($type));

        return self::typeOptions()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function defaultColumnForType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            self::TYPE_STORE => 'default_store_location_id',
            self::TYPE_WAREHOUSE => 'default_warehouse_location_id',
            self::TYPE_TERMINAL => 'default_terminal_location_id',
            default => null,
        };
    }

    public static function indexCount(int $businessId, string $search = '', string $type = '', string $status = 'active'): int
    {
        if (!self::isAvailable() || $businessId <= 0) {
            return 0;
        }

        [$where, $params] = self::indexWhereParts($businessId, $search, $type, $status);
        $sql = 'SELECT COUNT(*)
                FROM business_locations bl
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function indexList(
        int $businessId,
        string $search = '',
        string $type = '',
        string $status = 'active',
        int $limit = 25,
        int $offset = 0
    ): array {
        if (!self::isAvailable() || $businessId <= 0) {
            return [];
        }

        [$where, $params] = self::indexWhereParts($businessId, $search, $type, $status);
        $sql = 'SELECT
                    bl.id,
                    bl.location_type,
                    bl.name,
                    bl.address_line1,
                    bl.address_line2,
                    bl.city,
                    bl.state,
                    bl.postal_code,
                    bl.country,
                    bl.phone,
                    bl.notes,
                    bl.is_active,
                    bl.sort_order
                FROM business_locations bl
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY bl.location_type ASC, bl.sort_order ASC, bl.name ASC, bl.id ASC
                LIMIT :row_limit OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function activeByType(int $businessId, string $type): array
    {
        if (!self::isAvailable() || $businessId <= 0) {
            return [];
        }

        $type = strtolower(trim($type));
        if (!array_key_exists($type, self::typeOptions())) {
            return [];
        }

        $sql = 'SELECT
                    bl.id,
                    bl.location_type,
                    bl.name,
                    bl.address_line1,
                    bl.address_line2,
                    bl.city,
                    bl.state,
                    bl.postal_code,
                    bl.country,
                    bl.phone,
                    bl.notes
                FROM business_locations bl
                WHERE bl.business_id = :business_id
                  AND bl.deleted_at IS NULL
                  AND bl.is_active = 1
                  AND LOWER(bl.location_type) = :location_type
                ORDER BY bl.sort_order ASC, bl.name ASC, bl.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'location_type' => $type,
        ]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function activeCountByType(int $businessId, string $type): int
    {
        return count(self::activeByType($businessId, $type));
    }

    public static function needsEmployeeDefaultPicker(int $businessId, string $type): bool
    {
        return self::activeCountByType($businessId, $type) >= 2;
    }

    public static function findForBusiness(int $businessId, int $locationId): ?array
    {
        if (!self::isAvailable() || $businessId <= 0 || $locationId <= 0) {
            return null;
        }

        $sql = 'SELECT
                    bl.id,
                    bl.business_id,
                    bl.location_type,
                    bl.name,
                    bl.address_line1,
                    bl.address_line2,
                    bl.city,
                    bl.state,
                    bl.postal_code,
                    bl.country,
                    bl.phone,
                    bl.notes,
                    bl.is_active,
                    bl.sort_order
                FROM business_locations bl
                WHERE bl.business_id = :business_id
                  AND bl.id = :id
                  AND bl.deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'id' => $locationId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable() || $businessId <= 0) {
            return 0;
        }

        $type = strtolower(trim((string) ($data['location_type'] ?? self::TYPE_OTHER)));
        if (!array_key_exists($type, self::typeOptions())) {
            $type = self::TYPE_OTHER;
        }

        $sql = 'INSERT INTO business_locations (
                    business_id,
                    location_type,
                    name,
                    address_line1,
                    address_line2,
                    city,
                    state,
                    postal_code,
                    country,
                    phone,
                    notes,
                    is_active,
                    sort_order,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :business_id,
                    :location_type,
                    :name,
                    :address_line1,
                    :address_line2,
                    :city,
                    :state,
                    :postal_code,
                    :country,
                    :phone,
                    :notes,
                    :is_active,
                    :sort_order,
                    :created_by,
                    :updated_by,
                    NOW(),
                    NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'location_type' => $type,
            'name' => trim((string) ($data['name'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')) ?: null,
            'address_line2' => trim((string) ($data['address_line2'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => trim((string) ($data['state'] ?? '')) ?: null,
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'country' => trim((string) ($data['country'] ?? 'US')) ?: 'US',
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'is_active' => ((int) ($data['is_active'] ?? 1)) === 1 ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $locationId, array $data, int $actorUserId): bool
    {
        if (!self::isAvailable() || $businessId <= 0 || $locationId <= 0) {
            return false;
        }

        $type = strtolower(trim((string) ($data['location_type'] ?? self::TYPE_OTHER)));
        if (!array_key_exists($type, self::typeOptions())) {
            $type = self::TYPE_OTHER;
        }

        $sql = 'UPDATE business_locations
                SET location_type = :location_type,
                    name = :name,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    country = :country,
                    phone = :phone,
                    notes = :notes,
                    is_active = :is_active,
                    sort_order = :sort_order,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute([
            'location_type' => $type,
            'name' => trim((string) ($data['name'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')) ?: null,
            'address_line2' => trim((string) ($data['address_line2'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => trim((string) ($data['state'] ?? '')) ?: null,
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'country' => trim((string) ($data['country'] ?? 'US')) ?: 'US',
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'is_active' => ((int) ($data['is_active'] ?? 1)) === 1 ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $locationId,
        ]);
    }

    public static function softDelete(int $businessId, int $locationId, int $actorUserId): bool
    {
        if (!self::isAvailable() || $businessId <= 0 || $locationId <= 0) {
            return false;
        }

        $sql = 'UPDATE business_locations
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $deleted = $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $locationId,
        ]);

        if ($deleted && $stmt->rowCount() > 0) {
            self::clearEmployeeDefaultsForLocation($businessId, $locationId);
        }

        return $deleted && $stmt->rowCount() > 0;
    }

    /**
     * Resolve the effective location for an employee and type.
     * Falls back to base of operations when no location applies.
     *
     * @param array<string, mixed>|null $employee
     * @return array<string, mixed>
     */
    public static function resolveForEmployee(int $businessId, ?array $employee, string $type): array
    {
        $type = strtolower(trim($type));
        $locations = self::activeByType($businessId, $type);

        if (count($locations) === 1) {
            return self::normalizeLocation($locations[0], 'location');
        }

        if (count($locations) >= 2 && is_array($employee)) {
            $column = self::defaultColumnForType($type);
            if ($column !== null && SchemaInspector::hasColumn('employees', $column)) {
                $locationId = (int) ($employee[$column] ?? 0);
                if ($locationId > 0) {
                    $selected = self::findForBusiness($businessId, $locationId);
                    if (
                        $selected !== null
                        && strtolower(trim((string) ($selected['location_type'] ?? ''))) === $type
                        && (int) ($selected['is_active'] ?? 0) === 1
                    ) {
                        return self::normalizeLocation($selected, 'location');
                    }
                }
            }
        }

        return self::baseOfOperations($businessId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function baseOfOperations(int $businessId): array
    {
        $business = Business::findById($businessId);
        if ($business === null) {
            return self::emptyLocation('Base of operations');
        }

        $name = trim((string) ($business['name'] ?? ''));
        if ($name === '') {
            $name = 'Base of operations';
        }

        return [
            'source' => 'base',
            'location_id' => null,
            'location_type' => 'base',
            'name' => $name,
            'address_line1' => trim((string) ($business['address_line1'] ?? '')),
            'address_line2' => trim((string) ($business['address_line2'] ?? '')),
            'city' => trim((string) ($business['city'] ?? '')),
            'state' => trim((string) ($business['state'] ?? '')),
            'postal_code' => trim((string) ($business['postal_code'] ?? '')),
            'country' => trim((string) ($business['country'] ?? 'US')),
            'phone' => trim((string) ($business['phone'] ?? '')),
            'notes' => '',
            'formatted_address' => self::formatAddress(
                trim((string) ($business['address_line1'] ?? '')),
                trim((string) ($business['address_line2'] ?? '')),
                trim((string) ($business['city'] ?? '')),
                trim((string) ($business['state'] ?? '')),
                trim((string) ($business['postal_code'] ?? ''))
            ),
        ];
    }

    public static function formatAddress(
        string $line1,
        string $line2,
        string $city,
        string $state,
        string $postal
    ): string {
        $parts = [];
        if ($line1 !== '') {
            $parts[] = $line1;
        }
        if ($line2 !== '') {
            $parts[] = $line2;
        }

        $cityLine = trim($city . ($state !== '' ? ', ' . $state : '') . ($postal !== '' ? ' ' . $postal : ''));
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private static function indexWhereParts(int $businessId, string $search, string $type, string $status): array
    {
        $query = trim($search);
        $type = strtolower(trim($type));
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $where = [
            'bl.business_id = :business_id',
            'bl.deleted_at IS NULL',
        ];
        $params = ['business_id' => $businessId];

        if ($status === 'active') {
            $where[] = 'bl.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'bl.is_active = 0';
        }

        if ($type !== '' && array_key_exists($type, self::typeOptions())) {
            $where[] = 'LOWER(bl.location_type) = :location_type';
            $params['location_type'] = $type;
        }

        $where[] = '(
            :query = \'\'
            OR bl.name LIKE :query_like_1
            OR COALESCE(bl.address_line1, \'\') LIKE :query_like_2
            OR COALESCE(bl.city, \'\') LIKE :query_like_3
            OR CAST(bl.id AS CHAR) LIKE :query_like_4
        )';
        $like = '%' . $query . '%';
        $params['query'] = $query;
        $params['query_like_1'] = $like;
        $params['query_like_2'] = $like;
        $params['query_like_3'] = $like;
        $params['query_like_4'] = $like;

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeLocation(array $row, string $source): array
    {
        return [
            'source' => $source,
            'location_id' => (int) ($row['id'] ?? 0),
            'location_type' => strtolower(trim((string) ($row['location_type'] ?? ''))),
            'name' => trim((string) ($row['name'] ?? '')),
            'address_line1' => trim((string) ($row['address_line1'] ?? '')),
            'address_line2' => trim((string) ($row['address_line2'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'state' => trim((string) ($row['state'] ?? '')),
            'postal_code' => trim((string) ($row['postal_code'] ?? '')),
            'country' => trim((string) ($row['country'] ?? 'US')),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'notes' => trim((string) ($row['notes'] ?? '')),
            'formatted_address' => self::formatAddress(
                trim((string) ($row['address_line1'] ?? '')),
                trim((string) ($row['address_line2'] ?? '')),
                trim((string) ($row['city'] ?? '')),
                trim((string) ($row['state'] ?? '')),
                trim((string) ($row['postal_code'] ?? ''))
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyLocation(string $name): array
    {
        return [
            'source' => 'base',
            'location_id' => null,
            'location_type' => 'base',
            'name' => $name,
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'US',
            'phone' => '',
            'notes' => '',
            'formatted_address' => '',
        ];
    }

    private static function clearEmployeeDefaultsForLocation(int $businessId, int $locationId): void
    {
        if (!SchemaInspector::hasTable('employees') || $locationId <= 0) {
            return;
        }

        $columns = array_filter([
            SchemaInspector::hasColumn('employees', 'default_store_location_id') ? 'default_store_location_id' : null,
            SchemaInspector::hasColumn('employees', 'default_warehouse_location_id') ? 'default_warehouse_location_id' : null,
            SchemaInspector::hasColumn('employees', 'default_terminal_location_id') ? 'default_terminal_location_id' : null,
        ]);

        foreach ($columns as $column) {
            $sql = "UPDATE employees
                    SET {$column} = NULL, updated_at = NOW()
                    WHERE business_id = :business_id
                      AND {$column} = :location_id
                      AND deleted_at IS NULL";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'business_id' => $businessId,
                'location_id' => $locationId,
            ]);
        }
    }
}
