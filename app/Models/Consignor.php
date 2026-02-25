<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class Consignor
{
    public static function ensureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo = Database::connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS consignors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                first_name VARCHAR(80) NULL,
                last_name VARCHAR(80) NULL,
                business_name VARCHAR(150) NULL,
                phone VARCHAR(30) NULL,
                email VARCHAR(255) NULL,
                address_1 VARCHAR(150) NULL,
                address_2 VARCHAR(150) NULL,
                city VARCHAR(80) NULL,
                state VARCHAR(2) NULL,
                zip VARCHAR(12) NULL,
                consignor_number VARCHAR(40) NULL,
                consignment_start_date DATE NULL,
                consignment_end_date DATE NULL,
                payment_schedule VARCHAR(20) NULL,
                next_payment_due_date DATE NULL,
                inventory_estimate_amount DECIMAL(12,2) NULL,
                inventory_description TEXT NULL,
                note TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_consignors_number (consignor_number),
                KEY idx_consignors_business (business_id),
                KEY idx_consignors_name (last_name, first_name, business_name),
                KEY idx_consignors_active (active, deleted_at),
                KEY idx_consignors_next_payment_due (next_payment_due_date),
                KEY idx_consignors_created_by (created_by),
                KEY idx_consignors_updated_by (updated_by),
                KEY idx_consignors_deleted_by (deleted_by),
                CONSTRAINT fk_consignors_created_by
                    FOREIGN KEY (created_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignors_updated_by
                    FOREIGN KEY (updated_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignors_deleted_by
                    FOREIGN KEY (deleted_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureConsignorColumns($pdo);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS consignor_contacts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                consignor_id BIGINT UNSIGNED NOT NULL,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                link_type VARCHAR(30) NOT NULL DEFAULT \'general\',
                link_id BIGINT UNSIGNED NULL,
                contact_method VARCHAR(20) NOT NULL DEFAULT \'call\',
                direction VARCHAR(10) NOT NULL DEFAULT \'outbound\',
                subject VARCHAR(150) NULL,
                notes TEXT NULL,
                contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                follow_up_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_consignor_contacts_consignor_date (consignor_id, contacted_at),
                KEY idx_consignor_contacts_business (business_id),
                KEY idx_consignor_contacts_link (link_type, link_id),
                KEY idx_consignor_contacts_active (active, deleted_at),
                KEY idx_consignor_contacts_created_by (created_by),
                KEY idx_consignor_contacts_updated_by (updated_by),
                KEY idx_consignor_contacts_deleted_by (deleted_by),
                CONSTRAINT fk_consignor_contacts_consignor
                    FOREIGN KEY (consignor_id) REFERENCES consignors(id)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
                CONSTRAINT fk_consignor_contacts_created_by
                    FOREIGN KEY (created_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_contacts_updated_by
                    FOREIGN KEY (updated_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_contacts_deleted_by
                    FOREIGN KEY (deleted_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS consignor_contracts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                consignor_id BIGINT UNSIGNED NOT NULL,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                contract_title VARCHAR(150) NOT NULL,
                original_file_name VARCHAR(255) NOT NULL,
                stored_file_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(120) NULL,
                file_size BIGINT UNSIGNED NULL,
                contract_signed_at DATE NULL,
                expires_at DATE NULL,
                notes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_consignor_contracts_consignor (consignor_id),
                KEY idx_consignor_contracts_business (business_id),
                KEY idx_consignor_contracts_active (active, deleted_at),
                KEY idx_consignor_contracts_created_by (created_by),
                KEY idx_consignor_contracts_updated_by (updated_by),
                KEY idx_consignor_contracts_deleted_by (deleted_by),
                CONSTRAINT fk_consignor_contracts_consignor
                    FOREIGN KEY (consignor_id) REFERENCES consignors(id)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
                CONSTRAINT fk_consignor_contracts_created_by
                    FOREIGN KEY (created_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_contracts_updated_by
                    FOREIGN KEY (updated_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_contracts_deleted_by
                    FOREIGN KEY (deleted_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS consignor_payouts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                consignor_id BIGINT UNSIGNED NOT NULL,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                payout_date DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                estimate_amount DECIMAL(12,2) NULL,
                payout_method VARCHAR(30) NOT NULL DEFAULT \'other\',
                reference_no VARCHAR(80) NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'paid\',
                notes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_consignor_payouts_consignor_date (consignor_id, payout_date),
                KEY idx_consignor_payouts_business (business_id),
                KEY idx_consignor_payouts_status (status),
                KEY idx_consignor_payouts_active (active, deleted_at),
                KEY idx_consignor_payouts_created_by (created_by),
                KEY idx_consignor_payouts_updated_by (updated_by),
                KEY idx_consignor_payouts_deleted_by (deleted_by),
                CONSTRAINT fk_consignor_payouts_consignor
                    FOREIGN KEY (consignor_id) REFERENCES consignors(id)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
                CONSTRAINT fk_consignor_payouts_created_by
                    FOREIGN KEY (created_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_payouts_updated_by
                    FOREIGN KEY (updated_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT fk_consignor_payouts_deleted_by
                    FOREIGN KEY (deleted_by) REFERENCES users(id)
                    ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }

    private static function ensureConsignorColumns(\PDO $pdo): void
    {
        $alterMap = [
            'business_id' => 'ALTER TABLE consignors ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
            'consignor_number' => 'ALTER TABLE consignors ADD COLUMN consignor_number VARCHAR(40) NULL AFTER zip',
            'consignment_start_date' => 'ALTER TABLE consignors ADD COLUMN consignment_start_date DATE NULL AFTER consignor_number',
            'consignment_end_date' => 'ALTER TABLE consignors ADD COLUMN consignment_end_date DATE NULL AFTER consignment_start_date',
            'payment_schedule' => 'ALTER TABLE consignors ADD COLUMN payment_schedule VARCHAR(20) NULL AFTER consignment_end_date',
            'next_payment_due_date' => 'ALTER TABLE consignors ADD COLUMN next_payment_due_date DATE NULL AFTER payment_schedule',
        ];

        foreach ($alterMap as $column => $sql) {
            if (!Schema::hasColumn('consignors', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // ignore schema drift edge cases; migrations cover full schema.
                }
            }
        }

        try {
            $pdo->exec('CREATE UNIQUE INDEX uniq_consignors_number ON consignors (consignor_number)');
        } catch (Throwable) {
            // index may already exist
        }
        try {
            $pdo->exec('CREATE INDEX idx_consignors_next_payment_due ON consignors (next_payment_due_date)');
        } catch (Throwable) {
            // index may already exist
        }
        try {
            $pdo->exec('CREATE INDEX idx_consignors_business ON consignors (business_id)');
        } catch (Throwable) {
            // index may already exist
        }

        $childColumns = [
            ['table' => 'consignor_contacts', 'column' => 'business_id', 'sql' => 'ALTER TABLE consignor_contacts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id'],
            ['table' => 'consignor_contracts', 'column' => 'business_id', 'sql' => 'ALTER TABLE consignor_contracts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id'],
            ['table' => 'consignor_payouts', 'column' => 'business_id', 'sql' => 'ALTER TABLE consignor_payouts ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER consignor_id'],
        ];

        foreach ($childColumns as $child) {
            if (!Schema::hasColumn($child['table'], $child['column'])) {
                try {
                    $pdo->exec($child['sql']);
                } catch (Throwable) {
                    // ignore drift
                }
            }
        }

        try {
            $pdo->exec('CREATE INDEX idx_consignor_contacts_business ON consignor_contacts (business_id)');
        } catch (Throwable) {
            // index may already exist
        }
        try {
            $pdo->exec('CREATE INDEX idx_consignor_contracts_business ON consignor_contracts (business_id)');
        } catch (Throwable) {
            // index may already exist
        }
        try {
            $pdo->exec('CREATE INDEX idx_consignor_payouts_business ON consignor_payouts (business_id)');
        } catch (Throwable) {
            // index may already exist
        }

        if (Schema::hasColumn('consignors', 'business_id')) {
            try {
                $pdo->exec('UPDATE consignors SET business_id = 1 WHERE business_id IS NULL OR business_id = 0');
            } catch (Throwable) {
                // ignore drift
            }
        }
        if (Schema::hasColumn('consignor_contacts', 'business_id') && Schema::hasColumn('consignors', 'business_id')) {
            try {
                $pdo->exec('UPDATE consignor_contacts cc INNER JOIN consignors c ON c.id = cc.consignor_id SET cc.business_id = c.business_id WHERE cc.business_id IS NULL OR cc.business_id = 0');
            } catch (Throwable) {
                // ignore drift
            }
        }
        if (Schema::hasColumn('consignor_contracts', 'business_id') && Schema::hasColumn('consignors', 'business_id')) {
            try {
                $pdo->exec('UPDATE consignor_contracts cc INNER JOIN consignors c ON c.id = cc.consignor_id SET cc.business_id = c.business_id WHERE cc.business_id IS NULL OR cc.business_id = 0');
            } catch (Throwable) {
                // ignore drift
            }
        }
        if (Schema::hasColumn('consignor_payouts', 'business_id') && Schema::hasColumn('consignors', 'business_id')) {
            try {
                $pdo->exec('UPDATE consignor_payouts cp INNER JOIN consignors c ON c.id = cp.consignor_id SET cp.business_id = c.business_id WHERE cp.business_id IS NULL OR cp.business_id = 0');
            } catch (Throwable) {
                // ignore drift
            }
        }
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
        self::ensureSchema();

        $sql = 'SELECT c.id,
                       c.first_name,
                       c.last_name,
                       c.business_name,
                       c.phone,
                       c.email,
                       c.city,
                       c.state,
                       c.consignor_number,
                       c.consignment_start_date,
                       c.consignment_end_date,
                       c.payment_schedule,
                       c.next_payment_due_date,
                       c.inventory_estimate_amount,
                       c.active,
                       c.deleted_at,
                       c.updated_at,
                       COALESCE(NULLIF(c.business_name, \'\'), NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'), CONCAT(\'Consignor #\', c.id)) AS display_name,
                       (
                           SELECT COUNT(*)
                           FROM consignor_contracts cc
                           WHERE cc.consignor_id = c.id
                             AND cc.deleted_at IS NULL
                             AND COALESCE(cc.active, 1) = 1
                       ) AS contract_count,
                       (
                           SELECT COUNT(*)
                           FROM consignor_contacts cct
                           WHERE cct.consignor_id = c.id
                             AND cct.deleted_at IS NULL
                             AND COALESCE(cct.active, 1) = 1
                       ) AS contact_count,
                       (
                           SELECT COALESCE(SUM(cp.amount), 0)
                           FROM consignor_payouts cp
                           WHERE cp.consignor_id = c.id
                             AND cp.deleted_at IS NULL
                             AND COALESCE(cp.active, 1) = 1
                       ) AS total_paid
                FROM consignors c';

        $where = [];
        $params = [];

        if (Schema::hasColumn('consignors', 'business_id')) {
            $where[] = 'c.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        if ($status === 'active') {
            $where[] = '(c.deleted_at IS NULL AND COALESCE(c.active, 1) = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(c.deleted_at IS NOT NULL OR COALESCE(c.active, 1) = 0)';
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(c.first_name LIKE :term
                        OR c.last_name LIKE :term
                        OR c.business_name LIKE :term
                        OR c.phone LIKE :term
                        OR c.email LIKE :term
                        OR c.consignor_number LIKE :term
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
        self::ensureSchema();

        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT c.id,
                       c.first_name,
                       c.last_name,
                       c.business_name,
                       c.phone,
                       c.email,
                       c.address_1,
                       c.address_2,
                       c.city,
                       c.state,
                       c.zip,
                       c.consignor_number,
                       c.consignment_start_date,
                       c.consignment_end_date,
                       c.payment_schedule,
                       c.next_payment_due_date,
                       c.inventory_estimate_amount,
                       c.inventory_description,
                       c.note,
                       c.active,
                       c.deleted_at,
                       c.created_at,
                       c.updated_at,
                       c.created_by,
                       c.updated_by,
                       c.deleted_by,
                       COALESCE(NULLIF(c.business_name, \'\'), NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'), CONCAT(\'Consignor #\', c.id)) AS display_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', c.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', c.updated_by)) AS updated_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', c.deleted_by)) AS deleted_by_name,
                       (
                           SELECT COUNT(*)
                           FROM consignor_contracts cc
                           WHERE cc.consignor_id = c.id
                             AND cc.deleted_at IS NULL
                             AND COALESCE(cc.active, 1) = 1
                       ) AS contract_count,
                       (
                           SELECT COUNT(*)
                           FROM consignor_contacts cct
                           WHERE cct.consignor_id = c.id
                             AND cct.deleted_at IS NULL
                             AND COALESCE(cct.active, 1) = 1
                       ) AS contact_count,
                       (
                           SELECT COUNT(*)
                           FROM consignor_payouts cp
                           WHERE cp.consignor_id = c.id
                             AND cp.deleted_at IS NULL
                             AND COALESCE(cp.active, 1) = 1
                       ) AS payout_count,
                       (
                           SELECT COALESCE(SUM(cp.amount), 0)
                           FROM consignor_payouts cp
                           WHERE cp.consignor_id = c.id
                             AND cp.deleted_at IS NULL
                             AND COALESCE(cp.active, 1) = 1
                       ) AS total_paid
                FROM consignors c
                LEFT JOIN users u_created ON u_created.id = c.created_by
                LEFT JOIN users u_updated ON u_updated.id = c.updated_by
                LEFT JOIN users u_deleted ON u_deleted.id = c.deleted_by
                WHERE c.id = :id';

        $params = ['id' => $id];
        if (Schema::hasColumn('consignors', 'business_id')) {
            $sql .= ' AND c.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql .= '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureSchema();

        $columns = [
            'first_name',
            'last_name',
            'business_name',
            'phone',
            'email',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'consignor_number',
            'consignment_start_date',
            'consignment_end_date',
            'payment_schedule',
            'next_payment_due_date',
            'inventory_estimate_amount',
            'inventory_description',
            'note',
            'active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':first_name',
            ':last_name',
            ':business_name',
            ':phone',
            ':email',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':consignor_number',
            ':consignment_start_date',
            ':consignment_end_date',
            ':payment_schedule',
            ':next_payment_due_date',
            ':inventory_estimate_amount',
            ':inventory_description',
            ':note',
            ':active',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];
        if (Schema::hasColumn('consignors', 'business_id')) {
            array_unshift($columns, 'business_id');
            array_unshift($values, ':business_id');
        }

        $sql = 'INSERT INTO consignors (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'first_name' => $data['first_name'] !== '' ? $data['first_name'] : null,
            'last_name' => $data['last_name'] !== '' ? $data['last_name'] : null,
            'business_name' => $data['business_name'] !== '' ? $data['business_name'] : null,
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'address_1' => $data['address_1'] !== '' ? $data['address_1'] : null,
            'address_2' => $data['address_2'] !== '' ? $data['address_2'] : null,
            'city' => $data['city'] !== '' ? $data['city'] : null,
            'state' => $data['state'] !== '' ? $data['state'] : null,
            'zip' => $data['zip'] !== '' ? $data['zip'] : null,
            'consignor_number' => $data['consignor_number'] !== '' ? $data['consignor_number'] : null,
            'consignment_start_date' => $data['consignment_start_date'],
            'consignment_end_date' => $data['consignment_end_date'],
            'payment_schedule' => $data['payment_schedule'] !== '' ? $data['payment_schedule'] : null,
            'next_payment_due_date' => $data['next_payment_due_date'],
            'inventory_estimate_amount' => $data['inventory_estimate_amount'],
            'inventory_description' => $data['inventory_description'] !== '' ? $data['inventory_description'] : null,
            'note' => $data['note'] !== '' ? $data['note'] : null,
            'active' => $data['active'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];

        if (Schema::hasColumn('consignors', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sql = 'UPDATE consignors
                SET first_name = :first_name,
                    last_name = :last_name,
                    business_name = :business_name,
                    phone = :phone,
                    email = :email,
                    address_1 = :address_1,
                    address_2 = :address_2,
                    city = :city,
                    state = :state,
                    zip = :zip,
                    consignor_number = :consignor_number,
                    consignment_start_date = :consignment_start_date,
                    consignment_end_date = :consignment_end_date,
                    payment_schedule = :payment_schedule,
                    next_payment_due_date = :next_payment_due_date,
                    inventory_estimate_amount = :inventory_estimate_amount,
                    inventory_description = :inventory_description,
                    note = :note,
                    active = :active,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id';

        $params = [
            'id' => $id,
            'first_name' => $data['first_name'] !== '' ? $data['first_name'] : null,
            'last_name' => $data['last_name'] !== '' ? $data['last_name'] : null,
            'business_name' => $data['business_name'] !== '' ? $data['business_name'] : null,
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'address_1' => $data['address_1'] !== '' ? $data['address_1'] : null,
            'address_2' => $data['address_2'] !== '' ? $data['address_2'] : null,
            'city' => $data['city'] !== '' ? $data['city'] : null,
            'state' => $data['state'] !== '' ? $data['state'] : null,
            'zip' => $data['zip'] !== '' ? $data['zip'] : null,
            'consignor_number' => $data['consignor_number'] !== '' ? $data['consignor_number'] : null,
            'consignment_start_date' => $data['consignment_start_date'],
            'consignment_end_date' => $data['consignment_end_date'],
            'payment_schedule' => $data['payment_schedule'] !== '' ? $data['payment_schedule'] : null,
            'next_payment_due_date' => $data['next_payment_due_date'],
            'inventory_estimate_amount' => $data['inventory_estimate_amount'],
            'inventory_description' => $data['inventory_description'] !== '' ? $data['inventory_description'] : null,
            'note' => $data['note'] !== '' ? $data['note'] : null,
            'active' => $data['active'],
            'updated_by' => $actorId,
        ];

        if (Schema::hasColumn('consignors', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sql = 'UPDATE consignors
                SET active = 0,
                    deleted_at = COALESCE(deleted_at, NOW()),
                    updated_by = :updated_by,
                    deleted_by = COALESCE(deleted_by, :deleted_by),
                    updated_at = NOW()
                WHERE id = :id';

        $params = [
            'id' => $id,
            'updated_by' => $actorId,
            'deleted_by' => $actorId,
        ];

        if (Schema::hasColumn('consignors', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function lookup(string $term, int $limit = 10): array
    {
        self::ensureSchema();

        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT c.id,
                       COALESCE(NULLIF(c.business_name, \'\'), NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'), CONCAT(\'Consignor #\', c.id)) AS label,
                       c.city,
                       c.state
                FROM consignors c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  ' . (Schema::hasColumn('consignors', 'business_id') ? 'AND c.business_id = :business_id' : '') . '
                  AND (
                        c.first_name LIKE :term
                        OR c.last_name LIKE :term
                        OR c.business_name LIKE :term
                        OR c.phone LIKE :term
                        OR c.email LIKE :term
                        OR c.consignor_number LIKE :term
                        OR CAST(c.id AS CHAR) LIKE :term
                  )
                ORDER BY c.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $params = ['term' => '%' . $term . '%'];
        if (Schema::hasColumn('consignors', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findByConsignorNumber(string $number, ?int $excludeId = null): ?array
    {
        self::ensureSchema();

        $normalized = trim($number);
        if ($normalized === '') {
            return null;
        }

        $sql = 'SELECT id, consignor_number
                FROM consignors
                WHERE consignor_number = :consignor_number';
        $params = ['consignor_number' => $normalized];

        if (Schema::hasColumn('consignors', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
