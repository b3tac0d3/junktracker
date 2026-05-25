<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientFamilyMember
{
    /**
     * @return array<string, string>
     */
    public static function relationshipOptions(): array
    {
        return [
            'mother' => 'Mother',
            'father' => 'Father',
            'step_mother' => 'Step Mother',
            'step_father' => 'Step Father',
            'brother' => 'Brother',
            'sister' => 'Sister',
            'step_brother' => 'Step Brother',
            'step_sister' => 'Step Sister',
            'aunt' => 'Aunt',
            'uncle' => 'Uncle',
            'grandmother' => 'Grandmother',
            'grandfather' => 'Grandfather',
            'son' => 'Son',
            'daughter' => 'Daughter',
            'step_son' => 'Step Son',
            'step_daughter' => 'Step Daughter',
            'cousin' => 'Cousin',
            'other' => 'Other',
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('client_family_members');
    }

    public static function normalizeRelationship(mixed $value): ?string
    {
        $key = strtolower(trim((string) $value));
        if ($key === '') {
            return null;
        }

        return array_key_exists($key, self::relationshipOptions()) ? $key : null;
    }

    public static function relationshipLabel(?string $relationship): string
    {
        $normalized = self::normalizeRelationship($relationship ?? '');
        if ($normalized === null) {
            return '';
        }

        return self::relationshipOptions()[$normalized];
    }

    public static function displayName(array $row): string
    {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $combined = trim($first . ' ' . $last);
        if ($combined !== '') {
            return $combined;
        }

        $linked = trim((string) ($row['linked_client_name'] ?? ''));
        if ($linked !== '') {
            return $linked;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? ('Family #' . (string) $id) : 'Family member';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forClient(int $businessId, int $clientId, int $limit = 100): array
    {
        if ($businessId <= 0 || $clientId <= 0 || !self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $linkedNameSql = "''";
        $joinSql = '';
        if (SchemaInspector::hasColumn('client_family_members', 'linked_client_id') && SchemaInspector::hasTable('clients')) {
            $joinSql = ' LEFT JOIN clients lc ON lc.id = cfm.linked_client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id')) {
                $joinSql .= ' AND lc.business_id = cfm.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND lc.deleted_at IS NULL';
            }
            $linkedNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', lc.first_name, lc.last_name)), ''), NULLIF(lc.company_name, ''), '')";
        }

        $sql = "SELECT
                    cfm.id,
                    cfm.client_id,
                    cfm.linked_client_id,
                    cfm.first_name,
                    cfm.last_name,
                    cfm.relationship,
                    cfm.phone,
                    cfm.created_at,
                    {$linkedNameSql} AS linked_client_name
                FROM client_family_members cfm
                {$joinSql}
                WHERE cfm.business_id = :business_id
                  AND cfm.client_id = :client_id
                  AND cfm.deleted_at IS NULL
                ORDER BY cfm.last_name ASC, cfm.first_name ASC, cfm.id ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForClient(int $businessId, int $clientId, int $memberId): ?array
    {
        if ($businessId <= 0 || $clientId <= 0 || $memberId <= 0 || !self::isAvailable()) {
            return null;
        }

        $rows = self::forClient($businessId, $clientId, 500);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $memberId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(int $businessId, int $clientId, array $data, int $actorUserId): int
    {
        if ($businessId <= 0 || $clientId <= 0 || !self::isAvailable()) {
            return 0;
        }

        $payload = self::payloadFromInput($data);
        $sql = 'INSERT INTO client_family_members (
                    business_id, client_id, linked_client_id,
                    first_name, last_name, relationship, phone,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :client_id, :linked_client_id,
                    :first_name, :last_name, :relationship, :phone,
                    :created_by, :updated_by, NOW(), NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'linked_client_id' => $payload['linked_client_id'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'relationship' => $payload['relationship'],
            'phone' => $payload['phone'],
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $businessId, int $clientId, int $memberId, array $data, int $actorUserId): bool
    {
        if ($businessId <= 0 || $clientId <= 0 || $memberId <= 0 || !self::isAvailable()) {
            return false;
        }

        if (self::findForClient($businessId, $clientId, $memberId) === null) {
            return false;
        }

        $payload = self::payloadFromInput($data);
        $sql = 'UPDATE client_family_members
                SET linked_client_id = :linked_client_id,
                    first_name = :first_name,
                    last_name = :last_name,
                    relationship = :relationship,
                    phone = :phone,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND client_id = :client_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            'linked_client_id' => $payload['linked_client_id'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'relationship' => $payload['relationship'],
            'phone' => $payload['phone'],
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'client_id' => $clientId,
            'id' => $memberId,
        ]);
    }

    public static function delete(int $businessId, int $clientId, int $memberId, int $actorUserId): bool
    {
        if ($businessId <= 0 || $clientId <= 0 || $memberId <= 0 || !self::isAvailable()) {
            return false;
        }

        $sql = 'UPDATE client_family_members
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND client_id = :client_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'client_id' => $clientId,
            'id' => $memberId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFromInput(array $input): array
    {
        $linkedClientId = (int) ($input['linked_client_id'] ?? 0);

        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'relationship' => self::normalizeRelationship($input['relationship'] ?? '') ?? '',
            'phone' => trim((string) ($input['phone'] ?? '')),
            'linked_client_id' => $linkedClientId > 0 ? $linkedClientId : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public static function validate(array $payload): array
    {
        $errors = [];
        $first = trim((string) ($payload['first_name'] ?? ''));
        $last = trim((string) ($payload['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $errors['first_name'] = 'Enter a first or last name.';
        }

        if (self::normalizeRelationship($payload['relationship'] ?? '') === null) {
            $errors['relationship'] = 'Choose a relationship.';
        }

        return $errors;
    }
}
