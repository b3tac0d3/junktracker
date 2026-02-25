<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Contact
{
    private const SOURCE_TYPES = ['manual', 'user', 'employee', 'client'];
    private static bool $tableEnsured = false;

    public static function search(string $term = '', string $status = 'active', string $type = 'all'): array
    {
        self::ensureTable();

        $sql = 'SELECT c.id,
                       c.contact_type,
                       c.first_name,
                       c.last_name,
                       c.display_name,
                       c.phone,
                       c.email,
                       c.company_id,
                       c.linked_client_id,
                       c.source_type,
                       c.source_id,
                       c.is_active,
                       c.deleted_at,
                       c.updated_at,
                       co.name AS company_name,
                       cl.first_name AS linked_client_first_name,
                       cl.last_name AS linked_client_last_name
                FROM contacts c
                LEFT JOIN companies co ON co.id = c.company_id
                LEFT JOIN clients cl ON cl.id = c.linked_client_id';

        $where = [];
        $params = [];

        if (Schema::hasColumn('contacts', 'business_id')) {
            $where[] = 'c.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        if ($status === 'active') {
            $where[] = '(c.deleted_at IS NULL AND COALESCE(c.is_active, 1) = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(c.deleted_at IS NOT NULL OR COALESCE(c.is_active, 1) = 0)';
        }

        $type = strtolower(trim($type));
        if ($type !== '' && $type !== 'all') {
            $where[] = 'LOWER(TRIM(COALESCE(c.contact_type, \'\'))) = :contact_type';
            $params['contact_type'] = $type;
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(c.display_name LIKE :term
                        OR c.first_name LIKE :term
                        OR c.last_name LIKE :term
                        OR c.phone LIKE :term
                        OR c.email LIKE :term
                        OR co.name LIKE :term
                        OR c.city LIKE :term
                        OR c.state LIKE :term
                        OR CAST(c.id AS CHAR) LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY c.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT c.*,
                       co.name AS company_name,
                       cl.first_name AS linked_client_first_name,
                       cl.last_name AS linked_client_last_name,
                       cl.email AS linked_client_email,
                       cl.phone AS linked_client_phone,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', c.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', c.updated_by)) AS updated_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', c.deleted_by)) AS deleted_by_name
                FROM contacts c
                LEFT JOIN companies co ON co.id = c.company_id
                LEFT JOIN clients cl ON cl.id = c.linked_client_id
                LEFT JOIN users u_created ON u_created.id = c.created_by
                LEFT JOIN users u_updated ON u_updated.id = c.updated_by
                LEFT JOIN users u_deleted ON u_deleted.id = c.deleted_by
                WHERE c.id = :id
                  ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND c.business_id = :business_id_scope' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $columns = [
            'contact_type',
            'first_name',
            'last_name',
            'display_name',
            'phone',
            'email',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'company_id',
            'linked_client_id',
            'source_type',
            'source_id',
            'note',
            'is_active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':contact_type',
            ':first_name',
            ':last_name',
            ':display_name',
            ':phone',
            ':email',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':company_id',
            ':linked_client_id',
            ':source_type',
            ':source_id',
            ':note',
            ':is_active',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
        }

        $sql = 'INSERT INTO contacts (
                    ' . implode(', ', $columns) . '
                ) VALUES (
                    ' . implode(', ', $values) . '
                )';

        $params = [
            'contact_type' => self::normalizeType((string) ($data['contact_type'] ?? 'general')),
            'first_name' => self::nullIfEmpty((string) ($data['first_name'] ?? '')),
            'last_name' => self::nullIfEmpty((string) ($data['last_name'] ?? '')),
            'display_name' => self::displayName($data),
            'phone' => self::nullIfEmpty((string) ($data['phone'] ?? '')),
            'email' => self::nullIfEmpty((string) ($data['email'] ?? '')),
            'address_1' => self::nullIfEmpty((string) ($data['address_1'] ?? '')),
            'address_2' => self::nullIfEmpty((string) ($data['address_2'] ?? '')),
            'city' => self::nullIfEmpty((string) ($data['city'] ?? '')),
            'state' => self::nullIfEmpty((string) ($data['state'] ?? '')),
            'zip' => self::nullIfEmpty((string) ($data['zip'] ?? '')),
            'company_id' => self::toPositiveIntOrNull($data['company_id'] ?? null),
            'linked_client_id' => self::toPositiveIntOrNull($data['linked_client_id'] ?? null),
            'source_type' => self::normalizeSourceType($data['source_type'] ?? null),
            'source_id' => self::toPositiveIntOrNull($data['source_id'] ?? null),
            'note' => self::nullIfEmpty((string) ($data['note'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();
        if ($id <= 0) {
            return;
        }

        $sql = 'UPDATE contacts
                SET contact_type = :contact_type,
                    first_name = :first_name,
                    last_name = :last_name,
                    display_name = :display_name,
                    phone = :phone,
                    email = :email,
                    address_1 = :address_1,
                    address_2 = :address_2,
                    city = :city,
                    state = :state,
                    zip = :zip,
                    company_id = :company_id,
                    linked_client_id = :linked_client_id,
                    note = :note,
                    is_active = :is_active,
                    updated_by = :updated_by,
                    updated_at = NOW(),
                    deleted_at = CASE WHEN :is_active = 1 THEN NULL ELSE COALESCE(deleted_at, NOW()) END
                WHERE id = :id
                  ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '';

        $params = [
            'id' => $id,
            'contact_type' => self::normalizeType((string) ($data['contact_type'] ?? 'general')),
            'first_name' => self::nullIfEmpty((string) ($data['first_name'] ?? '')),
            'last_name' => self::nullIfEmpty((string) ($data['last_name'] ?? '')),
            'display_name' => self::displayName($data),
            'phone' => self::nullIfEmpty((string) ($data['phone'] ?? '')),
            'email' => self::nullIfEmpty((string) ($data['email'] ?? '')),
            'address_1' => self::nullIfEmpty((string) ($data['address_1'] ?? '')),
            'address_2' => self::nullIfEmpty((string) ($data['address_2'] ?? '')),
            'city' => self::nullIfEmpty((string) ($data['city'] ?? '')),
            'state' => self::nullIfEmpty((string) ($data['state'] ?? '')),
            'zip' => self::nullIfEmpty((string) ($data['zip'] ?? '')),
            'company_id' => self::toPositiveIntOrNull($data['company_id'] ?? null),
            'linked_client_id' => self::toPositiveIntOrNull($data['linked_client_id'] ?? null),
            'note' => self::nullIfEmpty((string) ($data['note'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'updated_by' => $actorId,
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deactivate(int $id, ?int $actorId = null): void
    {
        self::ensureTable();
        if ($id <= 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE contacts
             SET is_active = 0,
                 deleted_at = COALESCE(deleted_at, NOW()),
                 deleted_by = COALESCE(deleted_by, :actor_id),
                 updated_by = :actor_id,
                 updated_at = NOW()
             WHERE id = :id
               ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND business_id = :business_id_scope' : '') . ''
        );
        $params = [
            'id' => $id,
            'actor_id' => $actorId,
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
    }

    public static function upsertFromUser(array $userData, ?int $actorId = null): ?int
    {
        self::ensureTable();
        $userId = isset($userData['id']) ? (int) $userData['id'] : 0;
        if ($userId <= 0) {
            return null;
        }

        $existing = self::findBySource('user', $userId);
        $data = [
            'contact_type' => $existing['contact_type'] ?? 'general',
            'first_name' => trim((string) ($userData['first_name'] ?? '')),
            'last_name' => trim((string) ($userData['last_name'] ?? '')),
            'display_name' => trim((string) (($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''))),
            'phone' => $existing['phone'] ?? '',
            'email' => trim((string) ($userData['email'] ?? '')),
            'address_1' => $existing['address_1'] ?? '',
            'address_2' => $existing['address_2'] ?? '',
            'city' => $existing['city'] ?? '',
            'state' => $existing['state'] ?? '',
            'zip' => $existing['zip'] ?? '',
            'company_id' => $existing['company_id'] ?? null,
            'linked_client_id' => $existing['linked_client_id'] ?? null,
            'source_type' => 'user',
            'source_id' => $userId,
            'note' => $existing['note'] ?? '',
            'is_active' => !empty($userData['is_active']) ? 1 : 0,
        ];

        if ($existing) {
            self::update((int) ($existing['id'] ?? 0), $data, $actorId);
            return (int) ($existing['id'] ?? 0);
        }

        return self::create($data, $actorId);
    }

    public static function upsertFromEmployee(array $employeeData, ?int $actorId = null): ?int
    {
        self::ensureTable();
        $employeeId = isset($employeeData['id']) ? (int) $employeeData['id'] : 0;
        if ($employeeId <= 0) {
            return null;
        }

        $existing = self::findBySource('employee', $employeeId);
        $active = empty($employeeData['deleted_at']) && (int) ($employeeData['active'] ?? 1) === 1;
        $data = [
            'contact_type' => $existing['contact_type'] ?? 'specialist',
            'first_name' => trim((string) ($employeeData['first_name'] ?? '')),
            'last_name' => trim((string) ($employeeData['last_name'] ?? '')),
            'display_name' => trim((string) (($employeeData['first_name'] ?? '') . ' ' . ($employeeData['last_name'] ?? ''))),
            'phone' => trim((string) ($employeeData['phone'] ?? ($existing['phone'] ?? ''))),
            'email' => trim((string) ($employeeData['email'] ?? ($existing['email'] ?? ''))),
            'address_1' => $existing['address_1'] ?? '',
            'address_2' => $existing['address_2'] ?? '',
            'city' => $existing['city'] ?? '',
            'state' => $existing['state'] ?? '',
            'zip' => $existing['zip'] ?? '',
            'company_id' => $existing['company_id'] ?? null,
            'linked_client_id' => $existing['linked_client_id'] ?? null,
            'source_type' => 'employee',
            'source_id' => $employeeId,
            'note' => $existing['note'] ?? '',
            'is_active' => $active ? 1 : 0,
        ];

        if ($existing) {
            self::update((int) ($existing['id'] ?? 0), $data, $actorId);
            return (int) ($existing['id'] ?? 0);
        }

        return self::create($data, $actorId);
    }

    public static function createFromClientId(int $clientId, ?int $actorId = null): ?int
    {
        self::ensureTable();
        if ($clientId <= 0) {
            return null;
        }

        $client = Client::findById($clientId);
        if (!$client) {
            return null;
        }

        $name = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
        $company = Client::primaryCompany($clientId);
        $contactType = (string) ($client['client_type'] ?? 'client');
        if (!in_array($contactType, ['general', 'specialist', 'vendor', 'realtor', 'other'], true)) {
            $contactType = 'general';
        }
        if ($contactType === 'client') {
            $contactType = 'general';
        }

        $data = [
            'contact_type' => $contactType,
            'first_name' => trim((string) ($client['first_name'] ?? '')),
            'last_name' => trim((string) ($client['last_name'] ?? '')),
            'display_name' => $name,
            'phone' => trim((string) ($client['phone'] ?? '')),
            'email' => trim((string) ($client['email'] ?? '')),
            'address_1' => trim((string) ($client['address_1'] ?? '')),
            'address_2' => trim((string) ($client['address_2'] ?? '')),
            'city' => trim((string) ($client['city'] ?? '')),
            'state' => trim((string) ($client['state'] ?? '')),
            'zip' => trim((string) ($client['zip'] ?? '')),
            'company_id' => isset($company['id']) ? (int) ($company['id'] ?? 0) : null,
            'linked_client_id' => $clientId,
            'source_type' => 'client',
            'source_id' => $clientId,
            'note' => trim((string) ($client['note'] ?? '')),
            'is_active' => (empty($client['deleted_at']) && !empty($client['active'])) ? 1 : 0,
        ];

        $existing = self::findByLinkedClientId($clientId);
        if ($existing) {
            self::update((int) ($existing['id'] ?? 0), $data, $actorId);
            return (int) ($existing['id'] ?? 0);
        }

        return self::create($data, $actorId);
    }

    public static function createClientFromContactId(int $contactId, ?int $actorId = null): ?int
    {
        self::ensureTable();
        $contact = self::findById($contactId);
        if (!$contact) {
            return null;
        }

        $existingLinkedClientId = isset($contact['linked_client_id']) ? (int) $contact['linked_client_id'] : 0;
        if ($existingLinkedClientId > 0 && Client::findById($existingLinkedClientId)) {
            return $existingLinkedClientId;
        }

        $clientId = Client::create([
            'first_name' => trim((string) ($contact['first_name'] ?? '')),
            'last_name' => trim((string) ($contact['last_name'] ?? '')),
            'business_name' => '',
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'can_text' => 0,
            'email' => trim((string) ($contact['email'] ?? '')),
            'address_1' => trim((string) ($contact['address_1'] ?? '')),
            'address_2' => trim((string) ($contact['address_2'] ?? '')),
            'city' => trim((string) ($contact['city'] ?? '')),
            'state' => trim((string) ($contact['state'] ?? '')),
            'zip' => trim((string) ($contact['zip'] ?? '')),
            'client_type' => 'client',
            'note' => trim((string) ($contact['note'] ?? '')),
            'active' => 1,
        ], $actorId);

        $companyId = isset($contact['company_id']) ? (int) $contact['company_id'] : 0;
        if ($companyId > 0) {
            Client::syncCompanyLink($clientId, $companyId, $actorId);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE contacts
             SET linked_client_id = :client_id,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND business_id = :business_id_scope' : '') . ''
        );
        $params = [
            'id' => $contactId,
            'client_id' => $clientId,
            'updated_by' => $actorId,
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $clientId;
    }

    public static function findBySource(string $sourceType, int $sourceId): ?array
    {
        self::ensureTable();
        $normalizedType = self::normalizeSourceType($sourceType);
        if ($normalizedType === null || $sourceId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM contacts
             WHERE source_type = :source_type
               AND source_id = :source_id
               ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
             ORDER BY id DESC
             LIMIT 1'
        );
        $params = [
            'source_type' => $normalizedType,
            'source_id' => $sourceId,
        ];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByLinkedClientId(int $clientId): ?array
    {
        self::ensureTable();
        if ($clientId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM contacts
             WHERE linked_client_id = :client_id
               ' . (Schema::hasColumn('contacts', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
             ORDER BY id DESC
             LIMIT 1'
        );
        $params = ['client_id' => $clientId];
        if (Schema::hasColumn('contacts', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function contactTypeOptions(): array
    {
        $fallback = [
            ['value_key' => 'general', 'label' => 'General', 'active' => 1],
            ['value_key' => 'specialist', 'label' => 'Specialist', 'active' => 1],
            ['value_key' => 'vendor', 'label' => 'Vendor', 'active' => 1],
            ['value_key' => 'realtor', 'label' => 'Realtor', 'active' => 1],
            ['value_key' => 'other', 'label' => 'Other', 'active' => 1],
        ];
        $rows = lookup_options('contact_type', $fallback);

        $options = [];
        foreach ($rows as $row) {
            if (!empty($row['deleted_at']) || (isset($row['active']) && (int) $row['active'] !== 1)) {
                continue;
            }
            $value = trim((string) ($row['value_key'] ?? ''));
            if ($value === '') {
                continue;
            }
            $options[$value] = (string) ($row['label'] ?? ucwords(str_replace('_', ' ', $value)));
        }

        if (empty($options)) {
            foreach ($fallback as $row) {
                $options[(string) $row['value_key']] = (string) $row['label'];
            }
        }

        return $options;
    }

    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS contacts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                contact_type VARCHAR(40) NOT NULL DEFAULT \'general\',
                first_name VARCHAR(80) NULL,
                last_name VARCHAR(80) NULL,
                display_name VARCHAR(160) NULL,
                phone VARCHAR(40) NULL,
                email VARCHAR(255) NULL,
                address_1 VARCHAR(255) NULL,
                address_2 VARCHAR(255) NULL,
                city VARCHAR(120) NULL,
                state VARCHAR(8) NULL,
                zip VARCHAR(20) NULL,
                company_id BIGINT UNSIGNED NULL,
                linked_client_id BIGINT UNSIGNED NULL,
                source_type VARCHAR(30) NULL,
                source_id BIGINT UNSIGNED NULL,
                note TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                deleted_by BIGINT UNSIGNED NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_contacts_business (business_id),
                KEY idx_contacts_name (display_name),
                KEY idx_contacts_type (contact_type),
                KEY idx_contacts_company (company_id),
                KEY idx_contacts_client (linked_client_id),
                KEY idx_contacts_source (source_type, source_id),
                KEY idx_contacts_email (email),
                KEY idx_contacts_active (is_active, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$tableEnsured = true;
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }

    private static function normalizeType(string $type): string
    {
        $value = strtolower(trim($type));
        return $value !== '' ? $value : 'general';
    }

    private static function normalizeSourceType(mixed $sourceType): ?string
    {
        $value = strtolower(trim((string) ($sourceType ?? '')));
        if ($value === '' || !in_array($value, self::SOURCE_TYPES, true)) {
            return null;
        }

        return $value;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function toPositiveIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $int = (int) $raw;
        return $int > 0 ? $int : null;
    }

    private static function displayName(array $data): ?string
    {
        $display = trim((string) ($data['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        $name = trim((string) (($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        return $phone !== '' ? $phone : null;
    }
}
