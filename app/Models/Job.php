<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Job
{
    private const BILLING_TYPES = ['deposit', 'bill_sent', 'payment', 'adjustment', 'other'];
    private const OWNER_TYPES = ['client', 'estate', 'company'];

    public static function filter(array $filters): array
    {
        $sql = 'SELECT j.id,
                       j.name,
                       j.city,
                       j.state,
                       j.job_status,
                       j.scheduled_date,
                       j.quote_date,
                       j.total_quote,
                       j.total_billed,
                       c.first_name,
                       c.last_name,
                       c.business_name
                FROM jobs j
                LEFT JOIN clients c ON c.id = j.client_id';

        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(j.name LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q OR c.business_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'j.job_status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['record_status']) && $filters['record_status'] !== 'all') {
            if ($filters['record_status'] === 'active') {
                $where[] = '(j.deleted_at IS NULL AND (j.active IS NULL OR j.active = 1))';
            } elseif ($filters['record_status'] === 'deleted') {
                $where[] = '(j.deleted_at IS NOT NULL OR j.active = 0)';
            }
        }

        if (!empty($filters['start_date'])) {
            $where[] = 'DATE(j.scheduled_date) >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = 'DATE(j.scheduled_date) <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY j.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function lookupForSales(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT j.id,
                       j.name,
                       j.city,
                       j.state,
                       j.job_status
                FROM jobs j
                WHERE j.deleted_at IS NULL
                  AND COALESCE(j.active, 1) = 1
                  AND (
                        j.name LIKE :term
                        OR CAST(j.id AS CHAR) LIKE :term
                        OR j.city LIKE :term
                        OR j.state LIKE :term
                      )
                ORDER BY j.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        $sets = [
            'active = 0',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if (Schema::hasColumn('jobs', 'deleted_at')) {
            $sets[] = 'deleted_at = COALESCE(deleted_at, NOW())';
        }
        if ($actorId !== null && Schema::hasColumn('jobs', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('jobs', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function findById(int $id): ?array
    {
        self::ensureOwnerColumns();

        $sql = 'SELECT j.*,
                       c.first_name AS client_first_name,
                       c.last_name AS client_last_name,
                       c.business_name AS client_business_name,
                       c.phone AS client_phone,
                       c.email AS client_email,
                       COALESCE(j.contact_client_id, j.client_id) AS resolved_contact_client_id,
                       CASE
                           WHEN j.job_owner_type = \'company\' AND om.id IS NOT NULL THEN \'company\'
                           WHEN (j.job_owner_type = \'estate\' OR (j.job_owner_type IS NULL AND j.estate_id IS NOT NULL)) AND oe.id IS NOT NULL THEN \'estate\'
                           ELSE \'client\'
                       END AS resolved_owner_type,
                       COALESCE(
                           CASE WHEN j.job_owner_type = \'company\' THEN om.id END,
                           CASE WHEN j.job_owner_type = \'estate\' THEN oe.id END,
                           CASE WHEN j.job_owner_type = \'client\' THEN oc.id END,
                           CASE WHEN j.job_owner_type IS NULL AND j.estate_id IS NOT NULL THEN oe.id END,
                           CASE WHEN j.job_owner_type IS NULL THEN c.id END
                       ) AS resolved_owner_id,
                       COALESCE(
                           CASE WHEN j.job_owner_type = \'company\' THEN om.name END,
                           CASE WHEN j.job_owner_type = \'estate\' OR (j.job_owner_type IS NULL AND j.estate_id IS NOT NULL) THEN oe.name END,
                           COALESCE(
                               NULLIF(oc.business_name, \'\'),
                               NULLIF(TRIM(CONCAT_WS(\' \', oc.first_name, oc.last_name)), \'\'),
                               CONCAT(\'Client #\', oc.id)
                           )
                       ) AS owner_display_name,
                       COALESCE(
                           NULLIF(cc.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', cc.first_name, cc.last_name)), \'\'),
                           CONCAT(\'Client #\', cc.id)
                       ) AS contact_display_name
                FROM jobs j
                LEFT JOIN clients c ON c.id = j.client_id
                LEFT JOIN clients cc ON cc.id = COALESCE(j.contact_client_id, j.client_id)
                LEFT JOIN clients oc ON oc.id = COALESCE(
                    CASE WHEN j.job_owner_type = \'client\' THEN j.job_owner_id END,
                    CASE WHEN j.job_owner_type IS NULL AND j.estate_id IS NULL THEN j.client_id END
                )
                LEFT JOIN estates oe ON oe.id = COALESCE(
                    CASE WHEN j.job_owner_type = \'estate\' THEN j.job_owner_id END,
                    CASE WHEN j.job_owner_type IS NULL THEN j.estate_id END
                )
                LEFT JOIN companies om ON om.id = CASE WHEN j.job_owner_type = \'company\' THEN j.job_owner_id END
                WHERE j.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $job = $stmt->fetch();

        return $job ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureOwnerColumns();

        $columns = [
            'client_id',
            'estate_id',
            'job_owner_type',
            'job_owner_id',
            'contact_client_id',
            'name',
            'note',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'phone',
            'email',
            'quote_date',
            'scheduled_date',
            'start_date',
            'end_date',
            'billed_date',
            'paid_date',
            'job_status',
            'total_quote',
            'total_billed',
            'active',
            'created_at',
            'updated_at',
        ];

        $values = [
            ':client_id',
            ':estate_id',
            ':job_owner_type',
            ':job_owner_id',
            ':contact_client_id',
            ':name',
            ':note',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':phone',
            ':email',
            ':quote_date',
            ':scheduled_date',
            ':start_date',
            ':end_date',
            ':billed_date',
            ':paid_date',
            ':job_status',
            ':total_quote',
            ':total_billed',
            '1',
            'NOW()',
            'NOW()',
        ];

        $params = [
            'client_id' => $data['client_id'],
            'estate_id' => $data['estate_id'],
            'job_owner_type' => $data['job_owner_type'],
            'job_owner_id' => $data['job_owner_id'],
            'contact_client_id' => $data['contact_client_id'],
            'name' => $data['name'],
            'note' => $data['note'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'quote_date' => $data['quote_date'],
            'scheduled_date' => $data['scheduled_date'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'billed_date' => $data['billed_date'],
            'paid_date' => $data['paid_date'],
            'job_status' => $data['job_status'],
            'total_quote' => $data['total_quote'],
            'total_billed' => $data['total_billed'],
        ];

        if ($actorId !== null && Schema::hasColumn('jobs', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('jobs', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO jobs (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $jobId = (int) Database::connection()->lastInsertId();
        self::syncPaidStatus($jobId);

        return $jobId;
    }

    public static function updateDetails(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureOwnerColumns();

        $sets = [
            'client_id = :client_id',
            'estate_id = :estate_id',
            'job_owner_type = :job_owner_type',
            'job_owner_id = :job_owner_id',
            'contact_client_id = :contact_client_id',
            'name = :name',
            'note = :note',
            'address_1 = :address_1',
            'address_2 = :address_2',
            'city = :city',
            'state = :state',
            'zip = :zip',
            'phone = :phone',
            'email = :email',
            'quote_date = :quote_date',
            'scheduled_date = :scheduled_date',
            'start_date = :start_date',
            'end_date = :end_date',
            'billed_date = :billed_date',
            'paid_date = :paid_date',
            'job_status = :job_status',
            'total_quote = :total_quote',
            'total_billed = :total_billed',
            'updated_at = NOW()',
        ];

        $params = [
            'id' => $id,
            'client_id' => $data['client_id'],
            'estate_id' => $data['estate_id'],
            'job_owner_type' => $data['job_owner_type'],
            'job_owner_id' => $data['job_owner_id'],
            'contact_client_id' => $data['contact_client_id'],
            'name' => $data['name'],
            'note' => $data['note'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'quote_date' => $data['quote_date'],
            'scheduled_date' => $data['scheduled_date'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'billed_date' => $data['billed_date'],
            'paid_date' => $data['paid_date'],
            'job_status' => $data['job_status'],
            'total_quote' => $data['total_quote'],
            'total_billed' => $data['total_billed'],
        ];

        if ($actorId !== null && Schema::hasColumn('jobs', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        self::syncPaidStatus($id);
    }

    public static function searchOwners(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $sql = 'SELECT owner_type, owner_id, label, sub_label
                FROM (
                    SELECT
                        \'client\' AS owner_type,
                        c.id AS owner_id,
                        COALESCE(
                            NULLIF(c.business_name, \'\'),
                            NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                            CONCAT(\'Client #\', c.id)
                        ) AS label,
                        CONCAT_WS(\' • \', \'Client\', NULLIF(c.city, \'\'), NULLIF(c.state, \'\')) AS sub_label
                    FROM clients c
                    WHERE c.deleted_at IS NULL
                      AND c.active = 1
                      AND (
                            c.business_name LIKE :client_term
                            OR c.first_name LIKE :client_term
                            OR c.last_name LIKE :client_term
                            OR c.email LIKE :client_term
                      )

                    UNION ALL

                    SELECT
                        \'estate\' AS owner_type,
                        e.id AS owner_id,
                        e.name AS label,
                        CONCAT_WS(\' • \', \'Estate\', NULLIF(e.city, \'\'), NULLIF(e.state, \'\')) AS sub_label
                    FROM estates e
                    WHERE e.deleted_at IS NULL
                      AND e.active = 1
                      AND (
                            e.name LIKE :estate_term
                            OR e.city LIKE :estate_term
                            OR e.state LIKE :estate_term
                            OR e.email LIKE :estate_term
                      )

                    UNION ALL

                    SELECT
                        \'company\' AS owner_type,
                        co.id AS owner_id,
                        co.name AS label,
                        CONCAT_WS(\' • \', \'Company\', NULLIF(co.city, \'\'), NULLIF(co.state, \'\')) AS sub_label
                    FROM companies co
                    WHERE co.deleted_at IS NULL
                      AND COALESCE(co.active, 1) = 1
                      AND (
                            co.name LIKE :company_term
                            OR co.city LIKE :company_term
                            OR co.state LIKE :company_term
                            OR co.phone LIKE :company_term
                      )
                ) owners
                ORDER BY label ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $search = '%' . $term . '%';
        $stmt->execute([
            'client_term' => $search,
            'estate_term' => $search,
            'company_term' => $search,
        ]);

        return $stmt->fetchAll();
    }

    public static function searchContactClients(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $sql = 'SELECT
                    c.id AS client_id,
                    COALESCE(
                        NULLIF(c.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                        CONCAT(\'Client #\', c.id)
                    ) AS label,
                    CONCAT_WS(\' • \', \'Client\', NULLIF(c.city, \'\'), NULLIF(c.state, \'\')) AS sub_label
                FROM clients c
                WHERE c.deleted_at IS NULL
                  AND c.active = 1
                  AND (
                        c.business_name LIKE :term
                        OR c.first_name LIKE :term
                        OR c.last_name LIKE :term
                        OR c.email LIKE :term
                  )
                ORDER BY label ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }

    public static function findOwnerSummary(string $ownerType, int $ownerId): ?array
    {
        if (!in_array($ownerType, self::OWNER_TYPES, true) || $ownerId <= 0) {
            return null;
        }

        return match ($ownerType) {
            'client' => self::findClientOwnerSummary($ownerId),
            'estate' => self::findEstateOwnerSummary($ownerId),
            'company' => self::findCompanyOwnerSummary($ownerId),
            default => null,
        };
    }

    public static function findClientSummary(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $sql = 'SELECT
                    c.id AS client_id,
                    COALESCE(
                        NULLIF(c.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                        CONCAT(\'Client #\', c.id)
                    ) AS label
                FROM clients c
                WHERE c.id = :id
                  AND c.deleted_at IS NULL
                  AND c.active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $clientId]);
        $client = $stmt->fetch();

        return $client ?: null;
    }

    private static function findClientOwnerSummary(int $ownerId): ?array
    {
        $sql = 'SELECT
                    c.id AS owner_id,
                    COALESCE(
                        NULLIF(c.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                        CONCAT(\'Client #\', c.id)
                    ) AS label
                FROM clients c
                WHERE c.id = :id
                  AND c.deleted_at IS NULL
                  AND c.active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $ownerId]);
        $owner = $stmt->fetch();
        if (!$owner) {
            return null;
        }

        return [
            'owner_type' => 'client',
            'owner_id' => (int) $owner['owner_id'],
            'label' => (string) $owner['label'],
            'related_client_id' => (int) $owner['owner_id'],
        ];
    }

    private static function findEstateOwnerSummary(int $ownerId): ?array
    {
        $sql = 'SELECT e.id AS owner_id, e.name AS label, e.client_id AS related_client_id
                FROM estates e
                WHERE e.id = :id
                  AND e.deleted_at IS NULL
                  AND e.active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $ownerId]);
        $owner = $stmt->fetch();
        if (!$owner) {
            return null;
        }

        return [
            'owner_type' => 'estate',
            'owner_id' => (int) $owner['owner_id'],
            'label' => (string) $owner['label'],
            'related_client_id' => isset($owner['related_client_id']) ? (int) $owner['related_client_id'] : null,
        ];
    }

    private static function findCompanyOwnerSummary(int $ownerId): ?array
    {
        $sql = 'SELECT co.id AS owner_id, co.name AS label
                FROM companies co
                WHERE co.id = :id
                  AND co.deleted_at IS NULL
                  AND COALESCE(co.active, 1) = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $ownerId]);
        $owner = $stmt->fetch();
        if (!$owner) {
            return null;
        }

        return [
            'owner_type' => 'company',
            'owner_id' => (int) $owner['owner_id'],
            'label' => (string) $owner['label'],
            'related_client_id' => null,
        ];
    }

    private static function ensureOwnerColumns(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $dbName = (string) config('database.database', '');
        if ($dbName === '') {
            $ensured = true;
            return;
        }

        $sql = 'SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = \'jobs\'
                  AND COLUMN_NAME IN (\'job_owner_type\', \'job_owner_id\', \'contact_client_id\')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['schema' => $dbName]);
        $existing = array_map(
            static fn (array $row): string => (string) ($row['COLUMN_NAME'] ?? ''),
            $stmt->fetchAll()
        );

        if (!in_array('job_owner_type', $existing, true)) {
            Database::connection()->exec('ALTER TABLE jobs ADD COLUMN job_owner_type VARCHAR(20) NULL AFTER estate_id');
        }
        if (!in_array('job_owner_id', $existing, true)) {
            Database::connection()->exec('ALTER TABLE jobs ADD COLUMN job_owner_id BIGINT UNSIGNED NULL AFTER job_owner_type');
        }
        if (!in_array('contact_client_id', $existing, true)) {
            Database::connection()->exec('ALTER TABLE jobs ADD COLUMN contact_client_id BIGINT UNSIGNED NULL AFTER job_owner_id');
        }

        $ensured = true;
    }

    private static function ensureExpenseCategoryColumn(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $dbName = (string) config('database.database', '');
        if ($dbName === '') {
            $ensured = true;
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS expense_categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                note TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_expense_categories_name (name),
                KEY idx_expense_categories_deleted_at (deleted_at),
                KEY idx_expense_categories_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $sql = 'SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = \'expenses\'
                  AND COLUMN_NAME = \'expense_category_id\'';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['schema' => $dbName]);
        $columnExists = (bool) $stmt->fetch();

        if (!$columnExists) {
            Database::connection()->exec('ALTER TABLE expenses ADD COLUMN expense_category_id BIGINT UNSIGNED NULL AFTER disposal_location_id');
        }

        $idxSql = 'SELECT INDEX_NAME
                   FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = :schema
                     AND TABLE_NAME = \'expenses\'
                     AND INDEX_NAME = \'idx_expenses_category_id\'';
        $idxStmt = Database::connection()->prepare($idxSql);
        $idxStmt->execute(['schema' => $dbName]);
        $indexExists = (bool) $idxStmt->fetch();

        if (!$indexExists) {
            Database::connection()->exec('CREATE INDEX idx_expenses_category_id ON expenses (expense_category_id)');
        }

        $fkSql = 'SELECT CONSTRAINT_NAME
                  FROM information_schema.REFERENTIAL_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = :schema
                    AND CONSTRAINT_NAME = \'fk_expenses_category\'';
        $fkStmt = Database::connection()->prepare($fkSql);
        $fkStmt->execute(['schema' => $dbName]);
        $fkExists = (bool) $fkStmt->fetch();

        if (!$fkExists) {
            Database::connection()->exec(
                'ALTER TABLE expenses
                 ADD CONSTRAINT fk_expenses_category
                 FOREIGN KEY (expense_category_id)
                 REFERENCES expense_categories(id)
                 ON DELETE SET NULL
                 ON UPDATE CASCADE'
            );
        }

        $ensured = true;
    }

    public static function markPaid(int $jobId, ?string $paidAt = null, ?int $actorId = null): void
    {
        $paidAt = $paidAt ?? date('Y-m-d H:i:s');
        $sets = [
            'paid = 1',
            'paid_date = COALESCE(paid_date, :paid_at)',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $jobId,
            'paid_at' => $paidAt,
        ];
        if ($actorId !== null && Schema::hasColumn('jobs', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function markUnpaid(int $jobId, ?int $actorId = null): void
    {
        $sets = [
            'paid = 0',
            'paid_date = NULL',
            'updated_at = NOW()',
        ];
        $params = ['id' => $jobId];
        if ($actorId !== null && Schema::hasColumn('jobs', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function syncPaidStatus(int $jobId): void
    {
        $sql = 'SELECT COALESCE(j.total_billed, 0) AS invoice_amount,
                       COALESCE(SUM(CASE WHEN ja.action_type = \'payment\' THEN COALESCE(ja.amount, 0) ELSE 0 END), 0) AS payment_total
                FROM jobs j
                LEFT JOIN job_actions ja ON ja.job_id = j.id
                WHERE j.id = :id
                GROUP BY j.id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $jobId]);
        $totals = $stmt->fetch();

        if (!$totals) {
            return;
        }

        $invoiceAmount = (float) ($totals['invoice_amount'] ?? 0);
        $paymentTotal = (float) ($totals['payment_total'] ?? 0);

        if ($invoiceAmount > 0 && $paymentTotal >= $invoiceAmount) {
            self::markPaid($jobId);
            return;
        }

        self::markUnpaid($jobId);
    }

    public static function actions(int $jobId): array
    {
        $sql = 'SELECT id, action_type, action_at, amount, ref_table, ref_id, note, created_at
                FROM job_actions
                WHERE job_id = :job_id
                ORDER BY action_at DESC, id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function findAction(int $jobId, int $actionId): ?array
    {
        $sql = 'SELECT id, job_id, action_type, action_at, amount, ref_table, ref_id, note
                FROM job_actions
                WHERE job_id = :job_id AND id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'id' => $actionId,
        ]);

        $action = $stmt->fetch();
        return $action ?: null;
    }

    public static function createAction(int $jobId, array $data, ?int $actorId = null): int
    {
        $columns = ['job_id', 'action_type', 'action_at', 'amount', 'ref_table', 'ref_id', 'note', 'created_at'];
        $values = [':job_id', ':action_type', ':action_at', ':amount', ':ref_table', ':ref_id', ':note', 'NOW()'];
        $params = [
            'job_id' => $jobId,
            'action_type' => $data['action_type'],
            'action_at' => $data['action_at'],
            'amount' => $data['amount'],
            'ref_table' => $data['ref_table'],
            'ref_id' => $data['ref_id'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('job_actions', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }

        $sql = 'INSERT INTO job_actions (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateAction(int $jobId, int $actionId, array $data, ?int $actorId = null): void
    {
        $sets = [
            'action_type = :action_type',
            'action_at = :action_at',
            'amount = :amount',
            'ref_table = :ref_table',
            'ref_id = :ref_id',
            'note = :note',
        ];
        $params = [
            'id' => $actionId,
            'job_id' => $jobId,
            'action_type' => $data['action_type'],
            'action_at' => $data['action_at'],
            'amount' => $data['amount'],
            'ref_table' => $data['ref_table'],
            'ref_id' => $data['ref_id'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('job_actions', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE job_actions
                SET ' . implode(', ', $sets) . '
                WHERE id = :id AND job_id = :job_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deleteAction(int $jobId, int $actionId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM job_actions WHERE id = :id AND job_id = :job_id');
        $stmt->execute([
            'id' => $actionId,
            'job_id' => $jobId,
        ]);
    }

    public static function billingEntries(int $jobId): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::BILLING_TYPES), '?'));
        $sql = 'SELECT id, action_type, action_at, amount, note
                FROM job_actions
                WHERE job_id = ?
                  AND action_type IN (' . $placeholders . ')
                ORDER BY action_at DESC, id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$jobId], self::BILLING_TYPES));

        return $stmt->fetchAll();
    }

    public static function expenses(int $jobId): array
    {
        self::ensureExpenseCategoryColumn();

        $sql = 'SELECT e.id, e.expense_category_id, e.category, e.description, e.amount, e.expense_date, e.created_at, ec.name AS expense_category_name, dl.name AS disposal_location_name
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                LEFT JOIN disposal_locations dl ON dl.id = e.disposal_location_id
                WHERE e.job_id = :job_id
                  AND (e.deleted_at IS NULL)
                ORDER BY e.expense_date DESC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function findExpense(int $jobId, int $expenseId): ?array
    {
        self::ensureExpenseCategoryColumn();

        $sql = 'SELECT id, job_id, disposal_location_id, expense_category_id, category, description, amount, expense_date
                FROM expenses
                WHERE id = :id
                  AND job_id = :job_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $expenseId,
            'job_id' => $jobId,
        ]);

        $expense = $stmt->fetch();
        return $expense ?: null;
    }

    public static function createExpense(int $jobId, array $data, ?int $actorId = null): int
    {
        self::ensureExpenseCategoryColumn();

        $columns = ['job_id', 'disposal_location_id', 'expense_category_id', 'category', 'description', 'amount', 'expense_date', 'is_active', 'created_at'];
        $values = [':job_id', ':disposal_location_id', ':expense_category_id', ':category', ':description', ':amount', ':expense_date', '1', 'NOW()'];
        $params = [
            'job_id' => $jobId,
            'disposal_location_id' => $data['disposal_location_id'],
            'expense_category_id' => $data['expense_category_id'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ];

        if ($actorId !== null && Schema::hasColumn('expenses', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }

        $sql = 'INSERT INTO expenses (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateExpense(int $jobId, int $expenseId, array $data, ?int $actorId = null): void
    {
        self::ensureExpenseCategoryColumn();

        $sets = [
            'disposal_location_id = :disposal_location_id',
            'expense_category_id = :expense_category_id',
            'category = :category',
            'description = :description',
            'amount = :amount',
            'expense_date = :expense_date',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $expenseId,
            'job_id' => $jobId,
            'disposal_location_id' => $data['disposal_location_id'],
            'expense_category_id' => $data['expense_category_id'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ];

        if ($actorId !== null && Schema::hasColumn('expenses', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $sets) . '
                WHERE id = :id AND job_id = :job_id AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deleteExpense(int $jobId, int $expenseId, ?int $actorId = null): void
    {
        $sets = ['is_active = 0', 'deleted_at = NOW()', 'updated_at = NOW()'];
        $params = [
            'id' => $expenseId,
            'job_id' => $jobId,
        ];
        if ($actorId !== null && Schema::hasColumn('expenses', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('expenses', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $sets) . '
                WHERE id = :id AND job_id = :job_id AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function disposals(int $jobId): array
    {
        $sql = 'SELECT d.id, d.event_date, d.type, d.amount, d.note, dl.name AS disposal_location_name
                FROM job_disposal_events d
                LEFT JOIN disposal_locations dl ON dl.id = d.disposal_location_id
                WHERE d.job_id = :job_id
                  AND d.deleted_at IS NULL
                ORDER BY d.event_date DESC, d.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function findDisposal(int $jobId, int $disposalId): ?array
    {
        $sql = 'SELECT id, job_id, disposal_location_id, event_date, type, amount, note
                FROM job_disposal_events
                WHERE id = :id
                  AND job_id = :job_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $disposalId,
            'job_id' => $jobId,
        ]);

        $disposal = $stmt->fetch();
        return $disposal ?: null;
    }

    public static function createDisposal(int $jobId, array $data, ?int $actorId = null): int
    {
        $columns = ['job_id', 'disposal_location_id', 'event_date', 'type', 'amount', 'note', 'active', 'created_at', 'updated_at'];
        $values = [':job_id', ':disposal_location_id', ':event_date', ':type', ':amount', ':note', '1', 'NOW()', 'NOW()'];
        $params = [
            'job_id' => $jobId,
            'disposal_location_id' => $data['disposal_location_id'],
            'event_date' => $data['event_date'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('job_disposal_events', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_disposal_events', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO job_disposal_events (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateDisposal(int $jobId, int $disposalId, array $data, ?int $actorId = null): void
    {
        $sets = [
            'disposal_location_id = :disposal_location_id',
            'event_date = :event_date',
            'type = :type',
            'amount = :amount',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $disposalId,
            'job_id' => $jobId,
            'disposal_location_id' => $data['disposal_location_id'],
            'event_date' => $data['event_date'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('job_disposal_events', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE job_disposal_events
                SET ' . implode(', ', $sets) . '
                WHERE id = :id AND job_id = :job_id AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deleteDisposal(int $jobId, int $disposalId, ?int $actorId = null): void
    {
        $sets = ['active = 0', 'deleted_at = NOW()', 'updated_at = NOW()'];
        $params = [
            'id' => $disposalId,
            'job_id' => $jobId,
        ];
        if ($actorId !== null && Schema::hasColumn('job_disposal_events', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_disposal_events', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE job_disposal_events
                SET ' . implode(', ', $sets) . '
                WHERE id = :id AND job_id = :job_id AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function disposalLocations(): array
    {
        $sql = 'SELECT id, name
                FROM disposal_locations
                WHERE deleted_at IS NULL
                  AND active = 1
                ORDER BY name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function crewMembers(int $jobId): array
    {
        self::ensureCrewTable();

        $sql = 'SELECT jc.id,
                       jc.job_id,
                       jc.employee_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS employee_name,
                       e.email,
                       e.phone
                FROM job_crew jc
                INNER JOIN employees e ON e.id = jc.employee_id
                WHERE jc.job_id = :job_id
                  AND jc.deleted_at IS NULL
                  AND COALESCE(jc.active, 1) = 1
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                ORDER BY employee_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function searchCrewCandidates(int $jobId, string $term, int $limit = 10): array
    {
        self::ensureCrewTable();

        $term = trim($term);
        if ($jobId <= 0 || $term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT e.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name,
                       e.email,
                       e.phone
                FROM employees e
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND (
                        e.first_name LIKE :term
                        OR e.last_name LIKE :term
                        OR CONCAT_WS(\' \', e.first_name, e.last_name) LIKE :term
                        OR e.email LIKE :term
                        OR e.phone LIKE :term
                        OR CAST(e.id AS CHAR) LIKE :term
                      )
                  AND NOT EXISTS (
                      SELECT 1
                      FROM job_crew jc
                      WHERE jc.job_id = :job_id
                        AND jc.employee_id = e.id
                        AND jc.deleted_at IS NULL
                        AND COALESCE(jc.active, 1) = 1
                  )
                ORDER BY name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'term' => '%' . $term . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function isCrewMember(int $jobId, int $employeeId): bool
    {
        self::ensureCrewTable();

        if ($jobId <= 0 || $employeeId <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM job_crew
                WHERE job_id = :job_id
                  AND employee_id = :employee_id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function addCrewMember(int $jobId, int $employeeId, ?int $actorId = null): void
    {
        self::ensureCrewTable();

        $updateSets = [
            'active = 1',
            'deleted_at = NULL',
            'updated_at = NOW()',
        ];
        $updateParams = [
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ];
        if ($actorId !== null && Schema::hasColumn('job_crew', 'updated_by')) {
            $updateSets[] = 'updated_by = :updated_by';
            $updateParams['updated_by'] = $actorId;
        }

        $updateSql = 'UPDATE job_crew
                      SET ' . implode(', ', $updateSets) . '
                      WHERE job_id = :job_id
                        AND employee_id = :employee_id';
        $updateStmt = Database::connection()->prepare($updateSql);
        $updateStmt->execute($updateParams);

        if ($updateStmt->rowCount() > 0) {
            return;
        }

        $columns = ['job_id', 'employee_id', 'active', 'created_at', 'updated_at'];
        $values = [':job_id', ':employee_id', '1', 'NOW()', 'NOW()'];
        $params = [
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ];

        if ($actorId !== null && Schema::hasColumn('job_crew', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_crew', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO job_crew (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function removeCrewMember(int $jobId, int $employeeId, ?int $actorId = null): void
    {
        self::ensureCrewTable();

        $sets = [
            'active = 0',
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ];

        if ($actorId !== null && Schema::hasColumn('job_crew', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_crew', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE job_crew
                SET ' . implode(', ', $sets) . '
                WHERE job_id = :job_id
                  AND employee_id = :employee_id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function summary(int $jobId): array
    {
        $sql = 'SELECT
                    COALESCE((SELECT SUM(CASE WHEN ja.action_type = \'deposit\' THEN COALESCE(ja.amount, 0) ELSE 0 END)
                              FROM job_actions ja
                              WHERE ja.job_id = :job_id_1), 0) AS deposit_total,
                    COALESCE((SELECT SUM(COALESCE(e.amount, 0))
                              FROM expenses e
                              WHERE e.job_id = :job_id_2
                                AND e.deleted_at IS NULL), 0) AS expense_total,
                    COALESCE((SELECT SUM(COALESCE(d.amount, 0))
                              FROM job_disposal_events d
                              WHERE d.job_id = :job_id_3
                                AND d.deleted_at IS NULL), 0) AS disposal_total,
                    COALESCE((SELECT COUNT(*)
                              FROM job_actions ja2
                              WHERE ja2.job_id = :job_id_4), 0) AS action_count';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id_1' => $jobId,
            'job_id_2' => $jobId,
            'job_id_3' => $jobId,
            'job_id_4' => $jobId,
        ]);

        $summary = $stmt->fetch();
        return $summary ?: [
            'deposit_total' => 0,
            'expense_total' => 0,
            'disposal_total' => 0,
            'action_count' => 0,
        ];
    }

    public static function profitabilitySnapshot(int $jobId): array
    {
        $sql = 'SELECT
                    COALESCE(j.total_billed, j.total_quote, 0) AS invoice_amount,
                    COALESCE((
                        SELECT SUM(
                            CASE
                                WHEN ja.action_type IN (\'payment\', \'deposit\', \'adjustment\')
                                THEN COALESCE(ja.amount, 0)
                                ELSE 0
                            END
                        )
                        FROM job_actions ja
                        WHERE ja.job_id = :job_id_actions
                    ), 0) AS billing_collected,
                    COALESCE((
                        SELECT SUM(CASE WHEN d.type = \'scrap\' THEN COALESCE(d.amount, 0) ELSE 0 END)
                        FROM job_disposal_events d
                        WHERE d.job_id = :job_id_disposals_1
                          AND d.deleted_at IS NULL
                    ), 0) AS scrap_total,
                    COALESCE((
                        SELECT SUM(CASE WHEN d.type = \'dump\' THEN COALESCE(d.amount, 0) ELSE 0 END)
                        FROM job_disposal_events d
                        WHERE d.job_id = :job_id_disposals_2
                          AND d.deleted_at IS NULL
                    ), 0) AS dump_total,
                    COALESCE((
                        SELECT SUM(COALESCE(e.amount, 0))
                        FROM expenses e
                        WHERE e.job_id = :job_id_expenses
                          AND e.deleted_at IS NULL
                    ), 0) AS expense_total,
                    COALESCE((
                        SELECT SUM(COALESCE(te.total_paid, 0))
                        FROM employee_time_entries te
                        WHERE te.job_id = :job_id_time
                          AND te.deleted_at IS NULL
                          AND COALESCE(te.active, 1) = 1
                    ), 0) AS labor_total
                FROM jobs j
                WHERE j.id = :job_id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'job_id_actions' => $jobId,
            'job_id_disposals_1' => $jobId,
            'job_id_disposals_2' => $jobId,
            'job_id_expenses' => $jobId,
            'job_id_time' => $jobId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return [
                'invoice_amount' => 0.0,
                'billing_collected' => 0.0,
                'scrap_total' => 0.0,
                'dump_total' => 0.0,
                'expense_total' => 0.0,
                'labor_total' => 0.0,
                'revenue_total' => 0.0,
                'cost_total' => 0.0,
                'net_estimate' => 0.0,
            ];
        }

        $invoiceAmount = (float) ($row['invoice_amount'] ?? 0);
        $billingCollected = (float) ($row['billing_collected'] ?? 0);
        $scrapTotal = (float) ($row['scrap_total'] ?? 0);
        $dumpTotal = (float) ($row['dump_total'] ?? 0);
        $expenseTotal = (float) ($row['expense_total'] ?? 0);
        $laborTotal = (float) ($row['labor_total'] ?? 0);

        $revenueTotal = $invoiceAmount + $scrapTotal;
        $costTotal = $dumpTotal + $expenseTotal + $laborTotal;

        return [
            'invoice_amount' => $invoiceAmount,
            'billing_collected' => $billingCollected,
            'scrap_total' => $scrapTotal,
            'dump_total' => $dumpTotal,
            'expense_total' => $expenseTotal,
            'labor_total' => $laborTotal,
            'revenue_total' => $revenueTotal,
            'cost_total' => $costTotal,
            'net_estimate' => $revenueTotal - $costTotal,
        ];
    }

    private static function ensureCrewTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS job_crew (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id BIGINT UNSIGNED NOT NULL,
                employee_id BIGINT UNSIGNED NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_job_crew_member (job_id, employee_id),
                KEY idx_job_crew_employee (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }
}
