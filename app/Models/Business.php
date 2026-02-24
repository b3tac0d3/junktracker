<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class Business
{
    private static ?bool $available = null;

    public static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS businesses (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(150) NOT NULL,
                    legal_name VARCHAR(190) NULL,
                    email VARCHAR(255) NULL,
                    phone VARCHAR(50) NULL,
                    website VARCHAR(255) NULL,
                    address_line1 VARCHAR(190) NULL,
                    address_line2 VARCHAR(190) NULL,
                    city VARCHAR(120) NULL,
                    state VARCHAR(120) NULL,
                    postal_code VARCHAR(40) NULL,
                    country VARCHAR(80) NULL,
                    tax_id VARCHAR(100) NULL,
                    invoice_default_tax_rate DECIMAL(8,4) NULL,
                    timezone VARCHAR(80) NULL,
                    logo_path VARCHAR(255) NULL,
                    logo_mime_type VARCHAR(120) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_businesses_active (is_active),
                    KEY idx_businesses_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
            // Migration handles DDL in restricted environments.
        }

        self::ensureColumn('businesses', 'legal_name', 'ALTER TABLE businesses ADD COLUMN legal_name VARCHAR(190) NULL AFTER name');
        self::ensureColumn('businesses', 'email', 'ALTER TABLE businesses ADD COLUMN email VARCHAR(255) NULL AFTER legal_name');
        self::ensureColumn('businesses', 'phone', 'ALTER TABLE businesses ADD COLUMN phone VARCHAR(50) NULL AFTER email');
        self::ensureColumn('businesses', 'website', 'ALTER TABLE businesses ADD COLUMN website VARCHAR(255) NULL AFTER phone');
        self::ensureColumn('businesses', 'address_line1', 'ALTER TABLE businesses ADD COLUMN address_line1 VARCHAR(190) NULL AFTER website');
        self::ensureColumn('businesses', 'address_line2', 'ALTER TABLE businesses ADD COLUMN address_line2 VARCHAR(190) NULL AFTER address_line1');
        self::ensureColumn('businesses', 'city', 'ALTER TABLE businesses ADD COLUMN city VARCHAR(120) NULL AFTER address_line2');
        self::ensureColumn('businesses', 'state', 'ALTER TABLE businesses ADD COLUMN state VARCHAR(120) NULL AFTER city');
        self::ensureColumn('businesses', 'postal_code', 'ALTER TABLE businesses ADD COLUMN postal_code VARCHAR(40) NULL AFTER state');
        self::ensureColumn('businesses', 'country', 'ALTER TABLE businesses ADD COLUMN country VARCHAR(80) NULL AFTER postal_code');
        self::ensureColumn('businesses', 'tax_id', 'ALTER TABLE businesses ADD COLUMN tax_id VARCHAR(100) NULL AFTER country');
        self::ensureColumn('businesses', 'invoice_default_tax_rate', 'ALTER TABLE businesses ADD COLUMN invoice_default_tax_rate DECIMAL(8,4) NULL AFTER tax_id');
        self::ensureColumn('businesses', 'timezone', 'ALTER TABLE businesses ADD COLUMN timezone VARCHAR(80) NULL AFTER invoice_default_tax_rate');
        self::ensureColumn('businesses', 'logo_path', 'ALTER TABLE businesses ADD COLUMN logo_path VARCHAR(255) NULL AFTER timezone');
        self::ensureColumn('businesses', 'logo_mime_type', 'ALTER TABLE businesses ADD COLUMN logo_mime_type VARCHAR(120) NULL AFTER logo_path');
        self::ensureColumn('businesses', 'is_active', 'ALTER TABLE businesses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER website');
        self::ensureColumn('businesses', 'created_at', 'ALTER TABLE businesses ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active');
        self::ensureColumn('businesses', 'updated_at', 'ALTER TABLE businesses ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

        try {
            $pdo->exec('CREATE INDEX idx_businesses_active ON businesses (is_active)');
        } catch (Throwable) {
            // Index already exists.
        }

        try {
            $pdo->exec('CREATE INDEX idx_businesses_name ON businesses (name)');
        } catch (Throwable) {
            // Index already exists.
        }

        $ensured = true;
        self::$available = Schema::tableExists('businesses');
    }

    public static function isAvailable(): bool
    {
        self::ensureTable();
        if (self::$available !== null) {
            return self::$available;
        }

        self::$available = Schema::tableExists('businesses');
        return self::$available;
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $query = trim($term);
        $status = in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';

        $sql = 'SELECT b.id,
                       b.name,
                       b.legal_name,
                       b.email,
                       b.phone,
                       b.website,
                       b.address_line1,
                       b.address_line2,
                       b.city,
                       b.state,
                       b.postal_code,
                       b.country,
                       b.tax_id,
                       b.invoice_default_tax_rate,
                       b.timezone,
                       b.logo_path,
                       b.logo_mime_type,
                       b.is_active,
                       b.created_at,
                       b.updated_at';

        if (Schema::tableExists('users') && Schema::hasColumn('users', 'business_id')) {
            $sql .= ',
                    COALESCE(u.total_users, 0) AS users_count,
                    COALESCE(u.active_users, 0) AS active_users_count';
        } else {
            $sql .= ',
                    0 AS users_count,
                    0 AS active_users_count';
        }

        if (Schema::tableExists('jobs') && Schema::hasColumn('jobs', 'business_id')) {
            $sql .= ',
                    COALESCE(j.total_jobs, 0) AS jobs_count';
        } else {
            $sql .= ',
                    0 AS jobs_count';
        }

        $sql .= '
                FROM businesses b';

        if (Schema::tableExists('users') && Schema::hasColumn('users', 'business_id')) {
            $sql .= '
                LEFT JOIN (
                    SELECT business_id,
                           COUNT(*) AS total_users,
                           SUM(CASE WHEN COALESCE(is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_users
                    FROM users
                    GROUP BY business_id
                ) u ON u.business_id = b.id';
        }

        if (Schema::tableExists('jobs') && Schema::hasColumn('jobs', 'business_id')) {
            $deletedFilter = Schema::hasColumn('jobs', 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
            $sql .= '
                LEFT JOIN (
                    SELECT business_id,
                           COUNT(*) AS total_jobs
                    FROM jobs
                    ' . $deletedFilter . '
                    GROUP BY business_id
                ) j ON j.business_id = b.id';
        }

        $where = [];
        $params = [];
        if ($status === 'active') {
            $where[] = 'b.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'b.is_active = 0';
        }

        if ($query !== '') {
            $where[] = '(b.name LIKE :term OR COALESCE(b.legal_name, \'\') LIKE :term OR COALESCE(b.email, \'\') LIKE :term)';
            $params['term'] = '%' . $query . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY b.name ASC, b.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0 || !self::isAvailable()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, legal_name, email, phone, website, address_line1, address_line2, city, state, postal_code, country, tax_id, invoice_default_tax_rate, timezone, logo_path, logo_mime_type, is_active, created_at, updated_at
             FROM businesses
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findWithStats(int $id): ?array
    {
        if ($id <= 0 || !self::isAvailable()) {
            return null;
        }

        $sql = 'SELECT b.id,
                       b.name,
                       b.legal_name,
                       b.email,
                       b.phone,
                       b.website,
                       b.address_line1,
                       b.address_line2,
                       b.city,
                       b.state,
                       b.postal_code,
                       b.country,
                       b.tax_id,
                       b.invoice_default_tax_rate,
                       b.timezone,
                       b.logo_path,
                       b.logo_mime_type,
                       b.is_active,
                       b.created_at,
                       b.updated_at';

        if (Schema::tableExists('users') && Schema::hasColumn('users', 'business_id')) {
            $sql .= ',
                    COALESCE(u.total_users, 0) AS users_count,
                    COALESCE(u.active_users, 0) AS active_users_count';
        } else {
            $sql .= ',
                    0 AS users_count,
                    0 AS active_users_count';
        }

        if (Schema::tableExists('jobs') && Schema::hasColumn('jobs', 'business_id')) {
            $sql .= ',
                    COALESCE(j.total_jobs, 0) AS jobs_count,
                    COALESCE(j.active_jobs, 0) AS active_jobs_count';
        } else {
            $sql .= ',
                    0 AS jobs_count,
                    0 AS active_jobs_count';
        }

        $sql .= '
                FROM businesses b';

        if (Schema::tableExists('users') && Schema::hasColumn('users', 'business_id')) {
            $sql .= '
                LEFT JOIN (
                    SELECT business_id,
                           COUNT(*) AS total_users,
                           SUM(CASE WHEN COALESCE(is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_users
                    FROM users
                    GROUP BY business_id
                ) u ON u.business_id = b.id';
        }

        if (Schema::tableExists('jobs') && Schema::hasColumn('jobs', 'business_id')) {
            $deletedFilter = Schema::hasColumn('jobs', 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
            $activeFilter = Schema::hasColumn('jobs', 'status') ? ' AND LOWER(COALESCE(status, \'\')) = \'active\'' : '';
            $sql .= '
                LEFT JOIN (
                    SELECT business_id,
                           COUNT(*) AS total_jobs,
                           SUM(CASE WHEN 1 = 1' . $activeFilter . ' THEN 1 ELSE 0 END) AS active_jobs
                    FROM jobs
                    ' . $deletedFilter . '
                    GROUP BY business_id
                ) j ON j.business_id = b.id';
        }

        $sql .= '
                WHERE b.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function exists(int $id, bool $activeOnly = false): bool
    {
        if ($id <= 0 || !self::isAvailable()) {
            return false;
        }

        $sql = 'SELECT 1 FROM businesses WHERE id = :id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    public static function nameInUse(string $name, ?int $excludeId = null): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $clean = trim($name);
        if ($clean === '') {
            return false;
        }

        $sql = 'SELECT id
                FROM businesses
                WHERE LOWER(name) = LOWER(:name)';
        $params = ['name' => $clean];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $columns = [
            'name',
            'legal_name',
            'email',
            'phone',
            'website',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'tax_id',
            'invoice_default_tax_rate',
            'timezone',
            'logo_path',
            'logo_mime_type',
            'is_active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':name',
            ':legal_name',
            ':email',
            ':phone',
            ':website',
            ':address_line1',
            ':address_line2',
            ':city',
            ':state',
            ':postal_code',
            ':country',
            ':tax_id',
            ':invoice_default_tax_rate',
            ':timezone',
            ':logo_path',
            ':logo_mime_type',
            ':is_active',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'name' => trim((string) ($data['name'] ?? '')),
            'legal_name' => trim((string) ($data['legal_name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'website' => trim((string) ($data['website'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'country' => trim((string) ($data['country'] ?? '')),
            'tax_id' => trim((string) ($data['tax_id'] ?? '')),
            'invoice_default_tax_rate' => array_key_exists('invoice_default_tax_rate', $data) && $data['invoice_default_tax_rate'] !== null
                ? round((float) $data['invoice_default_tax_rate'], 4)
                : null,
            'timezone' => trim((string) ($data['timezone'] ?? '')),
            'logo_path' => trim((string) ($data['logo_path'] ?? '')),
            'logo_mime_type' => trim((string) ($data['logo_mime_type'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if (Schema::hasColumn('businesses', 'created_by') && $actorId !== null) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if (Schema::hasColumn('businesses', 'updated_by') && $actorId !== null) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO businesses (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        if ($id <= 0 || !self::isAvailable()) {
            return;
        }

        $fields = [
            'id' => $id,
            'name' => trim((string) ($data['name'] ?? '')),
            'legal_name' => trim((string) ($data['legal_name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'website' => trim((string) ($data['website'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'country' => trim((string) ($data['country'] ?? '')),
            'tax_id' => trim((string) ($data['tax_id'] ?? '')),
            'invoice_default_tax_rate' => array_key_exists('invoice_default_tax_rate', $data) && $data['invoice_default_tax_rate'] !== null
                ? round((float) $data['invoice_default_tax_rate'], 4)
                : null,
            'timezone' => trim((string) ($data['timezone'] ?? '')),
            'logo_path' => trim((string) ($data['logo_path'] ?? '')),
            'logo_mime_type' => trim((string) ($data['logo_mime_type'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        $sql = 'UPDATE businesses
                SET name = :name,
                    legal_name = :legal_name,
                    email = :email,
                    phone = :phone,
                    website = :website,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    country = :country,
                    tax_id = :tax_id,
                    invoice_default_tax_rate = :invoice_default_tax_rate,
                    timezone = :timezone,
                    logo_path = :logo_path,
                    logo_mime_type = :logo_mime_type,
                    is_active = :is_active,
                    updated_at = NOW()';

        if (Schema::hasColumn('businesses', 'updated_by') && $actorId !== null) {
            $sql .= ', updated_by = :updated_by';
            $fields['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($fields);
    }

    public static function activeOptions(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->query(
            'SELECT id, name
             FROM businesses
             WHERE is_active = 1
             ORDER BY name ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        if (!Schema::tableExists($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (Throwable) {
            // Migration handles DDL in restricted environments.
        }
    }
}
