<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Subcontractor
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('subcontractors');
    }

    public static function hasAddressFields(): bool
    {
        return self::isAvailable() && SchemaInspector::hasColumn('subcontractors', 'address_line1');
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function formattedAddress(array $row): string
    {
        $street = implode(', ', array_filter([
            trim((string) ($row['address_line1'] ?? '')),
            trim((string) ($row['address_line2'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $region = implode(', ', array_filter([
            trim((string) ($row['city'] ?? '')),
            trim((string) ($row['state'] ?? '')),
            trim((string) ($row['postal_code'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));

        return implode(', ', array_filter([$street, $region], static fn (string $value): bool => $value !== ''));
    }

    public static function displayNameSql(string $alias = 's'): string
    {
        $first = $alias . '.first_name';
        $last = $alias . '.last_name';
        $company = $alias . '.company';

        return "TRIM(CONCAT(
            COALESCE({$first}, ''),
            CASE WHEN COALESCE({$last}, '') <> '' THEN CONCAT(' ', {$last}) ELSE '' END,
            CASE WHEN COALESCE({$company}, '') <> '' THEN CONCAT(' (', {$company}, ')') ELSE '' END
        ))";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(int $businessId, string $search = '', string $status = '', int $limit = 25, int $offset = 0): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $nameExpr = self::displayNameSql('s');

        $where = [
            's.business_id = :business_id',
            's.deleted_at IS NULL',
        ];
        if ($status !== '') {
            $where[] = 'LOWER(COALESCE(s.status, "active")) = :status';
        }
        $addressSearch = self::addressSearchSql('s');
        $where[] = '(
            :query = ""
            OR ' . $nameExpr . ' LIKE :query_like_1
            OR COALESCE(s.company, "") LIKE :query_like_2
            OR COALESCE(s.email, "") LIKE :query_like_3
            OR COALESCE(s.phone, "") LIKE :query_like_4
            OR COALESCE(s.notes, "") LIKE :query_like_5
            OR CAST(s.id AS CHAR) LIKE :query_like_6
            ' . $addressSearch . '
        )';

        $sql = 'SELECT s.*,
                       ' . $nameExpr . ' AS display_name
                FROM subcontractors s
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY s.first_name ASC, s.last_name ASC, s.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        if (self::hasAddressFields()) {
            $stmt->bindValue(':query_like_7', $queryLike);
            $stmt->bindValue(':query_like_8', $queryLike);
            $stmt->bindValue(':query_like_9', $queryLike);
            $stmt->bindValue(':query_like_10', $queryLike);
            $stmt->bindValue(':query_like_11', $queryLike);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $nameExpr = self::displayNameSql('s');

        $where = [
            's.business_id = :business_id',
            's.deleted_at IS NULL',
        ];
        if ($status !== '') {
            $where[] = 'LOWER(COALESCE(s.status, "active")) = :status';
        }
        $addressSearch = self::addressSearchSql('s');
        $where[] = '(
            :query = ""
            OR ' . $nameExpr . ' LIKE :query_like_1
            OR COALESCE(s.company, "") LIKE :query_like_2
            OR COALESCE(s.email, "") LIKE :query_like_3
            OR COALESCE(s.phone, "") LIKE :query_like_4
            OR COALESCE(s.notes, "") LIKE :query_like_5
            OR CAST(s.id AS CHAR) LIKE :query_like_6
            ' . $addressSearch . '
        )';

        $sql = 'SELECT COUNT(*) AS row_count
                FROM subcontractors s
                WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        if (self::hasAddressFields()) {
            $stmt->bindValue(':query_like_7', $queryLike);
            $stmt->bindValue(':query_like_8', $queryLike);
            $stmt->bindValue(':query_like_9', $queryLike);
            $stmt->bindValue(':query_like_10', $queryLike);
            $stmt->bindValue(':query_like_11', $queryLike);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeOptions(int $businessId, string $search = '', int $limit = 50): array
    {
        return self::indexList($businessId, $search, 'active', $limit, 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForBusiness(int $businessId, int $subcontractorId): ?array
    {
        if (!self::isAvailable() || $subcontractorId <= 0) {
            return null;
        }

        $nameExpr = self::displayNameSql('s');
        $stmt = Database::connection()->prepare(
            'SELECT s.*,
                    ' . $nameExpr . ' AS display_name
             FROM subcontractors s
             WHERE s.business_id = :business_id
               AND s.id = :subcontractor_id
               AND s.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'subcontractor_id' => $subcontractorId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $columns = ['business_id', 'first_name', 'last_name', 'company'];
        $placeholders = [':business_id', ':first_name', ':last_name', ':company'];
        foreach (self::addressColumns() as $column) {
            $columns[] = $column;
            $placeholders[] = ':' . $column;
        }
        $columns = array_merge($columns, ['phone', 'email', 'notes', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at']);
        $placeholders = array_merge($placeholders, [':phone', ':email', ':notes', ':status', ':created_by', ':updated_by', 'NOW()', 'NOW()']);

        $stmt = Database::connection()->prepare(
            'INSERT INTO subcontractors (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $payload = self::payload($data, $actorUserId, true);
        $payload['business_id'] = $businessId;
        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $subcontractorId, array $data, int $actorUserId): bool
    {
        if (!self::isAvailable() || $subcontractorId <= 0) {
            return false;
        }

        $assignments = [
            'first_name = :first_name',
            'last_name = :last_name',
            'company = :company',
        ];
        foreach (self::addressColumns() as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        $assignments = array_merge($assignments, [
            'phone = :phone',
            'email = :email',
            'notes = :notes',
            'status = :status',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ]);

        $stmt = Database::connection()->prepare(
            'UPDATE subcontractors
             SET ' . implode(', ', $assignments) . '
             WHERE business_id = :business_id
               AND id = :subcontractor_id
               AND deleted_at IS NULL'
        );
        $payload = self::payload($data, $actorUserId, false);
        $payload['business_id'] = $businessId;
        $payload['subcontractor_id'] = $subcontractorId;
        $stmt->execute($payload);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $subcontractorId, int $actorUserId): bool
    {
        if (!self::isAvailable() || $subcontractorId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE subcontractors
             SET deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :subcontractor_id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'subcontractor_id' => $subcontractorId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $data): array
    {
        $errors = [];
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? 'active')));

        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(array $data, int $actorUserId, bool $includeCreate): array
    {
        $payload = [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')) ?: null,
            'company' => trim((string) ($data['company'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => strtolower(trim((string) ($data['status'] ?? 'active'))) ?: 'active',
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        foreach (self::addressColumns() as $column) {
            $payload[$column] = trim((string) ($data[$column] ?? '')) ?: null;
        }
        if ($includeCreate) {
            $payload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private static function addressColumns(): array
    {
        if (!self::hasAddressFields()) {
            return [];
        }

        return ['address_line1', 'address_line2', 'city', 'state', 'postal_code'];
    }

    private static function addressSearchSql(string $alias): string
    {
        if (!self::hasAddressFields()) {
            return '';
        }

        return 'OR COALESCE(' . $alias . '.address_line1, "") LIKE :query_like_7
                OR COALESCE(' . $alias . '.address_line2, "") LIKE :query_like_8
                OR COALESCE(' . $alias . '.city, "") LIKE :query_like_9
                OR COALESCE(' . $alias . '.state, "") LIKE :query_like_10
                OR COALESCE(' . $alias . '.postal_code, "") LIKE :query_like_11';
    }
}
