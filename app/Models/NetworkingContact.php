<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class NetworkingContact
{
    /**
     * @return array<int, string>
     */
    public static function typeOptions(int $businessId): array
    {
        $options = FormSelectValue::optionsForSection($businessId, 'networking_type');
        if ($options === []) {
            return ['realtor', 'property_manager', 'hoa', 'contractor', 'vendor', 'other'];
        }

        $normalized = [];
        foreach ($options as $optionRaw) {
            $value = strtolower(trim((string) $optionRaw));
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized !== [] ? $normalized : ['realtor', 'property_manager', 'hoa', 'contractor', 'vendor', 'other'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(int $businessId, string $search = '', string $type = '', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('networking_contacts')) {
            return [];
        }

        $query = trim($search);
        $type = strtolower(trim($type));
        $nameExpr = self::displayNameSql();

        $where = [
            'n.business_id = :business_id',
            'n.deleted_at IS NULL',
        ];
        if ($type !== '') {
            $where[] = 'LOWER(COALESCE(n.contact_type, "")) = :contact_type';
        }
        $where[] = '(
            :query = ""
            OR ' . $nameExpr . ' LIKE :query_like_1
            OR COALESCE(n.company, "") LIKE :query_like_2
            OR COALESCE(n.email, "") LIKE :query_like_3
            OR COALESCE(n.phone, "") LIKE :query_like_4
            OR COALESCE(n.notes, "") LIKE :query_like_5
            OR CAST(n.id AS CHAR) LIKE :query_like_6
        )';

        $sql = 'SELECT n.*,
                       ' . $nameExpr . ' AS contact_name
                FROM networking_contacts n
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY n.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($type !== '') {
            $stmt->bindValue(':contact_type', $type);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $type = ''): int
    {
        if (!SchemaInspector::hasTable('networking_contacts')) {
            return 0;
        }

        $query = trim($search);
        $type = strtolower(trim($type));
        $nameExpr = self::displayNameSql();

        $where = [
            'n.business_id = :business_id',
            'n.deleted_at IS NULL',
        ];
        if ($type !== '') {
            $where[] = 'LOWER(COALESCE(n.contact_type, "")) = :contact_type';
        }
        $where[] = '(
            :query = ""
            OR ' . $nameExpr . ' LIKE :query_like_1
            OR COALESCE(n.company, "") LIKE :query_like_2
            OR COALESCE(n.email, "") LIKE :query_like_3
            OR COALESCE(n.phone, "") LIKE :query_like_4
            OR COALESCE(n.notes, "") LIKE :query_like_5
            OR CAST(n.id AS CHAR) LIKE :query_like_6
        )';

        $sql = 'SELECT COUNT(*) AS row_count
                FROM networking_contacts n
                WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($type !== '') {
            $stmt->bindValue(':contact_type', $type);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForBusiness(int $businessId, int $contactId): ?array
    {
        if (!SchemaInspector::hasTable('networking_contacts') || $contactId <= 0) {
            return null;
        }

        $nameExpr = self::displayNameSql();
        $stmt = Database::connection()->prepare(
            'SELECT *,
                    ' . $nameExpr . ' AS contact_name
             FROM networking_contacts
             WHERE business_id = :business_id
               AND id = :contact_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('networking_contacts')) {
            return 0;
        }

        $columns = ['business_id'];
        $values = [':business_id'];
        if (SchemaInspector::hasColumn('networking_contacts', 'first_name')) {
            $columns[] = 'first_name';
            $values[] = ':first_name';
        }
        if (SchemaInspector::hasColumn('networking_contacts', 'last_name')) {
            $columns[] = 'last_name';
            $values[] = ':last_name';
        }
        if (SchemaInspector::hasColumn('networking_contacts', 'name')) {
            $columns[] = 'name';
            $values[] = ':name';
        }
        $columns = array_merge($columns, ['company', 'contact_type', 'phone', 'email', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at']);
        $values = array_merge($values, [':company', ':contact_type', ':phone', ':email', ':notes', ':created_by', ':updated_by', 'NOW()', 'NOW()']);

        $stmt = Database::connection()->prepare(
            'INSERT INTO networking_contacts (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')'
        );
        $payload = self::payload($businessId, $data, $actorUserId, true);
        $stmt->execute($payload);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $contactId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('networking_contacts') || $contactId <= 0) {
            return false;
        }

        $setParts = [];
        if (SchemaInspector::hasColumn('networking_contacts', 'first_name')) {
            $setParts[] = 'first_name = :first_name';
        }
        if (SchemaInspector::hasColumn('networking_contacts', 'last_name')) {
            $setParts[] = 'last_name = :last_name';
        }
        if (SchemaInspector::hasColumn('networking_contacts', 'name')) {
            $setParts[] = 'name = :name';
        }
        $setParts = array_merge($setParts, [
            'company = :company',
            'contact_type = :contact_type',
            'phone = :phone',
            'email = :email',
            'notes = :notes',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ]);

        $stmt = Database::connection()->prepare(
            'UPDATE networking_contacts
             SET ' . implode(', ', $setParts) . '
             WHERE business_id = :business_id
               AND id = :contact_id
               AND deleted_at IS NULL'
        );
        $payload = self::payload($businessId, $data, $actorUserId, false);
        $payload['contact_id'] = $contactId;
        $stmt->execute($payload);
        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $contactId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('networking_contacts') || $contactId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE networking_contacts
             SET deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :contact_id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $data, int $businessId): array
    {
        $errors = [];

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $type = strtolower(trim((string) ($data['contact_type'] ?? '')));
        $email = trim((string) ($data['email'] ?? ''));
        $allowedTypes = self::typeOptions($businessId);

        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        }
        if ($type !== '' && !in_array($type, $allowedTypes, true)) {
            $errors['contact_type'] = 'Choose a valid type.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(int $businessId, array $data, int $actorUserId, bool $includeCreate): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $payload = [
            'business_id' => $businessId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'company' => trim((string) ($data['company'] ?? '')),
            'contact_type' => strtolower(trim((string) ($data['contact_type'] ?? ''))),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        if ($includeCreate) {
            $payload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        return $payload;
    }

    private static function displayNameSql(): string
    {
        $hasFirstName = SchemaInspector::hasColumn('networking_contacts', 'first_name');
        $hasLastName = SchemaInspector::hasColumn('networking_contacts', 'last_name');
        $hasLegacyName = SchemaInspector::hasColumn('networking_contacts', 'name');

        if ($hasFirstName && $hasLastName && $hasLegacyName) {
            return 'COALESCE(NULLIF(TRIM(CONCAT_WS(" ", n.first_name, n.last_name)), ""), NULLIF(TRIM(n.name), ""), CONCAT("Contact #", n.id))';
        }
        if ($hasFirstName && $hasLastName) {
            return 'COALESCE(NULLIF(TRIM(CONCAT_WS(" ", n.first_name, n.last_name)), ""), CONCAT("Contact #", n.id))';
        }
        if ($hasLegacyName) {
            return 'COALESCE(NULLIF(TRIM(n.name), ""), CONCAT("Contact #", n.id))';
        }

        return 'CONCAT("Contact #", n.id)';
    }
}
