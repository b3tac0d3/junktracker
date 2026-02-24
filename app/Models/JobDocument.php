<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class JobDocument
{
    public const TYPES = ['estimate', 'invoice'];
    public const DEFAULT_ITEM_TYPES = [
        ['code' => 'service', 'label' => 'Service', 'sort_order' => 10],
        ['code' => 'labor', 'label' => 'Labor', 'sort_order' => 20],
        ['code' => 'tools', 'label' => 'Tools / Equipment', 'sort_order' => 30],
        ['code' => 'supplies', 'label' => 'Supplies', 'sort_order' => 40],
        ['code' => 'disposal', 'label' => 'Disposal / Dump Fee', 'sort_order' => 50],
        ['code' => 'travel', 'label' => 'Travel / Delivery', 'sort_order' => 60],
        ['code' => 'other', 'label' => 'Other', 'sort_order' => 99],
    ];
    public const STATUSES = [
        'draft',
        'quote_sent',
        'approved',
        'invoiced',
        'partially_paid',
        'paid',
        'void',
        'cancelled',
    ];

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS job_estimate_invoices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(20) NOT NULL,
                title VARCHAR(190) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "draft",
                amount DECIMAL(12,2) NULL,
                subtotal_amount DECIMAL(12,2) NULL,
                tax_rate DECIMAL(8,4) NULL,
                tax_amount DECIMAL(12,2) NULL,
                issued_at DATETIME NULL,
                due_at DATETIME NULL,
                sent_at DATETIME NULL,
                approved_at DATETIME NULL,
                paid_at DATETIME NULL,
                note TEXT NULL,
                customer_note TEXT NULL,
                source_estimate_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_job_estimate_invoices_job (job_id),
                KEY idx_job_estimate_invoices_type_status (document_type, status),
                KEY idx_job_estimate_invoices_deleted (deleted_at),
                KEY idx_job_estimate_invoices_source_estimate (source_estimate_id),
                KEY idx_job_estimate_invoices_created_by (created_by),
                KEY idx_job_estimate_invoices_updated_by (updated_by),
                KEY idx_job_estimate_invoices_deleted_by (deleted_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS job_estimate_invoice_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                from_status VARCHAR(30) NULL,
                to_status VARCHAR(30) NULL,
                event_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_job_estimate_invoice_events_doc (document_id, created_at),
                KEY idx_job_estimate_invoice_events_job (job_id, created_at),
                KEY idx_job_estimate_invoice_events_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS business_document_item_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id BIGINT UNSIGNED NOT NULL,
                item_code VARCHAR(50) NOT NULL,
                item_label VARCHAR(120) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_business_document_item_types_code (business_id, item_code),
                KEY idx_business_document_item_types_active (business_id, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS job_estimate_invoice_line_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                item_type_id BIGINT UNSIGNED NULL,
                item_type_label VARCHAR(120) NOT NULL,
                item_description VARCHAR(255) NOT NULL,
                line_note VARCHAR(255) NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                is_taxable TINYINT(1) NOT NULL DEFAULT 1,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_job_estimate_invoice_line_items_doc (document_id, sort_order, id),
                KEY idx_job_estimate_invoice_line_items_job (job_id),
                KEY idx_job_estimate_invoice_line_items_type (item_type_id),
                KEY idx_job_estimate_invoice_line_items_created_by (created_by),
                KEY idx_job_estimate_invoice_line_items_updated_by (updated_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureColumn(
            'job_estimate_invoices',
            'customer_note',
            'ALTER TABLE job_estimate_invoices ADD COLUMN customer_note TEXT NULL AFTER note'
        );
        self::ensureColumn(
            'job_estimate_invoices',
            'subtotal_amount',
            'ALTER TABLE job_estimate_invoices ADD COLUMN subtotal_amount DECIMAL(12,2) NULL AFTER amount'
        );
        self::ensureColumn(
            'job_estimate_invoices',
            'tax_rate',
            'ALTER TABLE job_estimate_invoices ADD COLUMN tax_rate DECIMAL(8,4) NULL AFTER subtotal_amount'
        );
        self::ensureColumn(
            'job_estimate_invoices',
            'tax_amount',
            'ALTER TABLE job_estimate_invoices ADD COLUMN tax_amount DECIMAL(12,2) NULL AFTER tax_rate'
        );
        self::ensureColumn(
            'job_estimate_invoices',
            'source_estimate_id',
            'ALTER TABLE job_estimate_invoices ADD COLUMN source_estimate_id BIGINT UNSIGNED NULL AFTER customer_note'
        );
        self::ensureColumn(
            'job_estimate_invoice_line_items',
            'is_taxable',
            'ALTER TABLE job_estimate_invoice_line_items ADD COLUMN is_taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER unit_price'
        );
        self::ensureIndex(
            'job_estimate_invoices',
            'idx_job_estimate_invoices_source_estimate',
            'CREATE INDEX idx_job_estimate_invoices_source_estimate ON job_estimate_invoices (source_estimate_id)'
        );

        $schema = trim((string) config('database.database', ''));
        if ($schema !== '') {
            self::ensureForeignKey(
                'fk_job_estimate_invoices_job',
                'ALTER TABLE job_estimate_invoices
                 ADD CONSTRAINT fk_job_estimate_invoices_job
                 FOREIGN KEY (job_id) REFERENCES jobs(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_events_doc',
                'ALTER TABLE job_estimate_invoice_events
                 ADD CONSTRAINT fk_job_estimate_invoice_events_doc
                 FOREIGN KEY (document_id) REFERENCES job_estimate_invoices(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_events_job',
                'ALTER TABLE job_estimate_invoice_events
                 ADD CONSTRAINT fk_job_estimate_invoice_events_job
                 FOREIGN KEY (job_id) REFERENCES jobs(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_events_created_by',
                'ALTER TABLE job_estimate_invoice_events
                 ADD CONSTRAINT fk_job_estimate_invoice_events_created_by
                 FOREIGN KEY (created_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoices_created_by',
                'ALTER TABLE job_estimate_invoices
                 ADD CONSTRAINT fk_job_estimate_invoices_created_by
                 FOREIGN KEY (created_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoices_updated_by',
                'ALTER TABLE job_estimate_invoices
                 ADD CONSTRAINT fk_job_estimate_invoices_updated_by
                 FOREIGN KEY (updated_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoices_deleted_by',
                'ALTER TABLE job_estimate_invoices
                 ADD CONSTRAINT fk_job_estimate_invoices_deleted_by
                 FOREIGN KEY (deleted_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoices_source_estimate',
                'ALTER TABLE job_estimate_invoices
                 ADD CONSTRAINT fk_job_estimate_invoices_source_estimate
                 FOREIGN KEY (source_estimate_id) REFERENCES job_estimate_invoices(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_business_document_item_types_business',
                'ALTER TABLE business_document_item_types
                 ADD CONSTRAINT fk_business_document_item_types_business
                 FOREIGN KEY (business_id) REFERENCES businesses(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_line_items_doc',
                'ALTER TABLE job_estimate_invoice_line_items
                 ADD CONSTRAINT fk_job_estimate_invoice_line_items_doc
                 FOREIGN KEY (document_id) REFERENCES job_estimate_invoices(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_line_items_job',
                'ALTER TABLE job_estimate_invoice_line_items
                 ADD CONSTRAINT fk_job_estimate_invoice_line_items_job
                 FOREIGN KEY (job_id) REFERENCES jobs(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_line_items_type',
                'ALTER TABLE job_estimate_invoice_line_items
                 ADD CONSTRAINT fk_job_estimate_invoice_line_items_type
                 FOREIGN KEY (item_type_id) REFERENCES business_document_item_types(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_line_items_created_by',
                'ALTER TABLE job_estimate_invoice_line_items
                 ADD CONSTRAINT fk_job_estimate_invoice_line_items_created_by
                 FOREIGN KEY (created_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_job_estimate_invoice_line_items_updated_by',
                'ALTER TABLE job_estimate_invoice_line_items
                 ADD CONSTRAINT fk_job_estimate_invoice_line_items_updated_by
                 FOREIGN KEY (updated_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
        }

        self::$schemaEnsured = true;
    }

    public static function typeLabel(string $type): string
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'estimate' => 'Estimate',
            'invoice' => 'Invoice',
            default => 'Document',
        };
    }

    public static function statusLabel(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return 'Draft';
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }

    public static function statusesForType(string $type): array
    {
        $normalized = strtolower(trim($type));

        if ($normalized === 'estimate') {
            return ['draft', 'quote_sent', 'approved', 'invoiced', 'void', 'cancelled'];
        }
        if ($normalized === 'invoice') {
            return ['draft', 'invoiced', 'partially_paid', 'paid', 'void', 'cancelled'];
        }

        return self::STATUSES;
    }

    public static function forJob(int $jobId): array
    {
        self::ensureSchema();

        $createdBySql = self::userLabelSql('uc', 'd.created_by');
        $updatedBySql = self::userLabelSql('uu', 'd.updated_by');

        $sql = 'SELECT d.id,
                       d.job_id,
                       d.document_type,
                       d.title,
                       d.status,
                       d.amount,
                       d.subtotal_amount,
                       d.tax_rate,
                       d.tax_amount,
                       d.issued_at,
                       d.due_at,
                       d.sent_at,
                       d.approved_at,
                       d.paid_at,
                       d.note,
                       d.customer_note,
                       d.source_estimate_id,
                       d.created_at,
                       d.updated_at,
                       d.deleted_at,
                       d.created_by,
                       d.updated_by,
                       COALESCE(li.item_count, 0) AS line_item_count,
                       ' . $createdBySql . ' AS created_by_name,
                       ' . $updatedBySql . ' AS updated_by_name
                FROM job_estimate_invoices d
                LEFT JOIN (
                    SELECT document_id, COUNT(*) AS item_count
                    FROM job_estimate_invoice_line_items
                    GROUP BY document_id
                ) li ON li.document_id = d.id
                LEFT JOIN users uc ON uc.id = d.created_by
                LEFT JOIN users uu ON uu.id = d.updated_by
                WHERE d.job_id = :job_id
                  AND d.deleted_at IS NULL
                ORDER BY d.created_at DESC, d.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function summaryForJob(int $jobId): array
    {
        self::ensureSchema();

        $sql = 'SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN d.document_type = "estimate" THEN 1 ELSE 0 END), 0) AS estimate_count,
                    COALESCE(SUM(CASE WHEN d.document_type = "invoice" THEN 1 ELSE 0 END), 0) AS invoice_count,
                    COALESCE(SUM(CASE WHEN d.status = "quote_sent" THEN 1 ELSE 0 END), 0) AS quote_sent_count,
                    COALESCE(SUM(CASE WHEN d.status = "approved" THEN 1 ELSE 0 END), 0) AS approved_count,
                    COALESCE(SUM(CASE WHEN d.status = "invoiced" THEN 1 ELSE 0 END), 0) AS invoiced_count,
                    COALESCE(SUM(CASE WHEN d.status = "paid" THEN 1 ELSE 0 END), 0) AS paid_count,
                    COALESCE(SUM(CASE WHEN d.document_type = "estimate" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS estimate_amount_total,
                    COALESCE(SUM(CASE WHEN d.document_type = "invoice" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS invoice_amount_total
                FROM job_estimate_invoices d
                WHERE d.job_id = :job_id
                  AND d.deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        $row = $stmt->fetch();
        return $row ?: [
            'total_count' => 0,
            'estimate_count' => 0,
            'invoice_count' => 0,
            'quote_sent_count' => 0,
            'approved_count' => 0,
            'invoiced_count' => 0,
            'paid_count' => 0,
            'estimate_amount_total' => 0,
            'invoice_amount_total' => 0,
        ];
    }

    public static function findByIdForJob(int $jobId, int $documentId): ?array
    {
        self::ensureSchema();
        Business::ensureTable();

        $createdBySql = self::userLabelSql('uc', 'd.created_by');
        $updatedBySql = self::userLabelSql('uu', 'd.updated_by');
        $deletedBySql = self::userLabelSql('ud', 'd.deleted_by');

        $sql = 'SELECT d.id,
                       d.job_id,
                       d.document_type,
                       d.title,
                       d.status,
                       d.amount,
                       d.subtotal_amount,
                       d.tax_rate,
                       d.tax_amount,
                       d.issued_at,
                       d.due_at,
                       d.sent_at,
                       d.approved_at,
                       d.paid_at,
                       d.note,
                       d.customer_note,
                       d.source_estimate_id,
                       d.created_at,
                       d.updated_at,
                       d.deleted_at,
                       d.created_by,
                       d.updated_by,
                       d.deleted_by,
                       ' . $createdBySql . ' AS created_by_name,
                       ' . $updatedBySql . ' AS updated_by_name,
                       ' . $deletedBySql . ' AS deleted_by_name,
                       j.name AS job_name,
                       j.address_1 AS job_address_1,
                       j.address_2 AS job_address_2,
                       j.city AS job_city,
                       j.state AS job_state,
                       j.zip AS job_zip,
                       j.phone AS job_phone,
                       j.email AS job_email,
                       j.business_id,
                       COALESCE(
                           NULLIF(c.business_name, ""),
                           NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                           CONCAT("Client #", c.id)
                       ) AS client_name,
                       c.email AS client_email,
                       c.phone AS client_phone,
                       COALESCE(NULLIF(e.name, ""), CONCAT("Estate #", e.id)) AS estate_name,
                       b.name AS business_name,
                       b.legal_name AS business_legal_name,
                       b.email AS business_email,
                       b.phone AS business_phone,
                       b.website AS business_website,
                       b.address_line1 AS business_address_line1,
                       b.address_line2 AS business_address_line2,
                       b.city AS business_city,
                       b.state AS business_state,
                       b.postal_code AS business_postal_code,
                       b.country AS business_country,
                       b.tax_id AS business_tax_id,
                       b.logo_path AS business_logo_path,
                       b.logo_mime_type AS business_logo_mime_type
                FROM job_estimate_invoices d
                INNER JOIN jobs j ON j.id = d.job_id
                LEFT JOIN clients c ON c.id = j.client_id
                LEFT JOIN estates e ON e.id = j.estate_id
                LEFT JOIN businesses b ON b.id = j.business_id
                LEFT JOIN users uc ON uc.id = d.created_by
                LEFT JOIN users uu ON uu.id = d.updated_by
                LEFT JOIN users ud ON ud.id = d.deleted_by
                WHERE d.job_id = :job_id
                  AND d.id = :document_id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'document_id' => $documentId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $jobId, array $data, ?int $actorId = null): int
    {
        self::ensureSchema();

        $pdo = Database::connection();
        $columns = [
            'job_id',
            'document_type',
            'title',
            'status',
            'amount',
            'subtotal_amount',
            'tax_rate',
            'tax_amount',
            'issued_at',
            'due_at',
            'sent_at',
            'approved_at',
            'paid_at',
            'note',
            'customer_note',
            'source_estimate_id',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':job_id',
            ':document_type',
            ':title',
            ':status',
            ':amount',
            ':subtotal_amount',
            ':tax_rate',
            ':tax_amount',
            ':issued_at',
            ':due_at',
            ':sent_at',
            ':approved_at',
            ':paid_at',
            ':note',
            ':customer_note',
            ':source_estimate_id',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'subtotal_amount' => $data['subtotal_amount'] ?? null,
            'tax_rate' => $data['tax_rate'] ?? null,
            'tax_amount' => $data['tax_amount'] ?? null,
            'issued_at' => $data['issued_at'],
            'due_at' => $data['due_at'],
            'sent_at' => $data['sent_at'],
            'approved_at' => $data['approved_at'],
            'paid_at' => $data['paid_at'],
            'note' => $data['note'],
            'customer_note' => $data['customer_note'] ?? null,
            'source_estimate_id' => $data['source_estimate_id'] ?? null,
        ];

        if ($actorId !== null && Schema::hasColumn('job_estimate_invoices', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_estimate_invoices', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO job_estimate_invoices (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $id = (int) $pdo->lastInsertId();
            self::syncLineItems($jobId, $id, $data['line_items'] ?? [], $actorId);
            self::createEvent($jobId, $id, [
                'event_type' => 'created',
                'from_status' => null,
                'to_status' => (string) ($data['status'] ?? 'draft'),
                'event_note' => 'Document created.',
            ], $actorId);

            if (strtolower((string) ($data['document_type'] ?? '')) === 'estimate') {
                Job::updateQuoteAmount($jobId, self::documentAmount($id), $actorId);
            }
            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $jobId, int $documentId, array $data, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sets = [
            'document_type = :document_type',
            'title = :title',
            'status = :status',
            'amount = :amount',
            'subtotal_amount = :subtotal_amount',
            'tax_rate = :tax_rate',
            'tax_amount = :tax_amount',
            'issued_at = :issued_at',
            'due_at = :due_at',
            'sent_at = :sent_at',
            'approved_at = :approved_at',
            'paid_at = :paid_at',
            'note = :note',
            'customer_note = :customer_note',
            'source_estimate_id = :source_estimate_id',
            'updated_at = NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'document_id' => $documentId,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'subtotal_amount' => $data['subtotal_amount'] ?? null,
            'tax_rate' => $data['tax_rate'] ?? null,
            'tax_amount' => $data['tax_amount'] ?? null,
            'issued_at' => $data['issued_at'],
            'due_at' => $data['due_at'],
            'sent_at' => $data['sent_at'],
            'approved_at' => $data['approved_at'],
            'paid_at' => $data['paid_at'],
            'note' => $data['note'],
            'customer_note' => $data['customer_note'] ?? null,
            'source_estimate_id' => $data['source_estimate_id'] ?? null,
        ];

        if ($actorId !== null && Schema::hasColumn('job_estimate_invoices', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE job_estimate_invoices
                SET ' . implode(', ', $sets) . '
                WHERE job_id = :job_id
                  AND id = :document_id
                  AND deleted_at IS NULL';

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            self::syncLineItems($jobId, $documentId, $data['line_items'] ?? [], $actorId);
            if (strtolower((string) ($data['document_type'] ?? '')) === 'estimate') {
                Job::updateQuoteAmount($jobId, self::documentAmount($documentId), $actorId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function softDelete(int $jobId, int $documentId, ?int $actorId = null): void
    {
        self::ensureSchema();
        $current = self::findByIdForJob($jobId, $documentId);

        $sets = [
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'document_id' => $documentId,
        ];

        if ($actorId !== null && Schema::hasColumn('job_estimate_invoices', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('job_estimate_invoices', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE job_estimate_invoices
                SET ' . implode(', ', $sets) . '
                WHERE job_id = :job_id
                  AND id = :document_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        self::createEvent($jobId, $documentId, [
            'event_type' => 'deleted',
            'from_status' => null,
            'to_status' => null,
            'event_note' => 'Document deleted.',
        ], $actorId);

        if (strtolower((string) ($current['document_type'] ?? '')) === 'estimate') {
            self::syncLatestEstimateToJobQuote($jobId, $actorId);
        }
    }

    public static function events(int $jobId, int $documentId): array
    {
        self::ensureSchema();

        $createdBySql = self::userLabelSql('uc', 'e.created_by');

        $sql = 'SELECT e.id,
                       e.document_id,
                       e.job_id,
                       e.event_type,
                       e.from_status,
                       e.to_status,
                       e.event_note,
                       e.created_at,
                       e.created_by,
                       ' . $createdBySql . ' AS created_by_name
                FROM job_estimate_invoice_events e
                LEFT JOIN users uc ON uc.id = e.created_by
                WHERE e.job_id = :job_id
                  AND e.document_id = :document_id
                ORDER BY e.created_at DESC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'document_id' => $documentId,
        ]);

        return $stmt->fetchAll();
    }

    public static function createEvent(int $jobId, int $documentId, array $data, ?int $actorId = null): int
    {
        self::ensureSchema();

        $columns = [
            'document_id',
            'job_id',
            'event_type',
            'from_status',
            'to_status',
            'event_note',
            'created_at',
        ];
        $values = [
            ':document_id',
            ':job_id',
            ':event_type',
            ':from_status',
            ':to_status',
            ':event_note',
            'NOW()',
        ];
        $params = [
            'document_id' => $documentId,
            'job_id' => $jobId,
            'event_type' => (string) ($data['event_type'] ?? 'updated'),
            'from_status' => $data['from_status'] ?? null,
            'to_status' => $data['to_status'] ?? null,
            'event_note' => $data['event_note'] ?? null,
        ];

        if ($actorId !== null && Schema::hasColumn('job_estimate_invoice_events', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }

        $sql = 'INSERT INTO job_estimate_invoice_events (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function itemTypesForBusiness(int $businessId): array
    {
        self::ensureSchema();

        if ($businessId <= 0) {
            return [];
        }

        self::ensureDefaultItemTypes($businessId);
        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, item_code, item_label, sort_order, is_active
             FROM business_document_item_types
             WHERE business_id = :business_id
               AND is_active = 1
             ORDER BY sort_order ASC, item_label ASC, id ASC'
        );
        $stmt->execute(['business_id' => $businessId]);
        return $stmt->fetchAll();
    }

    public static function lineItems(int $jobId, int $documentId): array
    {
        self::ensureSchema();

        if ($jobId <= 0 || $documentId <= 0) {
            return [];
        }

        $sql = 'SELECT li.id,
                       li.document_id,
                       li.job_id,
                       li.item_type_id,
                       li.item_type_label,
                       li.item_description,
                       li.line_note,
                       li.quantity,
                       li.unit_price,
                       li.is_taxable,
                       li.line_total,
                       li.sort_order
                FROM job_estimate_invoice_line_items li
                WHERE li.job_id = :job_id
                  AND li.document_id = :document_id
                ORDER BY li.sort_order ASC, li.id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'document_id' => $documentId,
        ]);

        return $stmt->fetchAll();
    }

    public static function estimateAlreadyConverted(int $estimateId): bool
    {
        self::ensureSchema();
        if ($estimateId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM job_estimate_invoices
             WHERE source_estimate_id = :estimate_id
               AND document_type = "invoice"
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['estimate_id' => $estimateId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function convertEstimateToInvoice(int $jobId, int $estimateId, ?int $actorId = null): ?int
    {
        self::ensureSchema();

        if ($jobId <= 0 || $estimateId <= 0) {
            return null;
        }

        $estimate = self::findByIdForJob($jobId, $estimateId);
        if (!$estimate || !empty($estimate['deleted_at'])) {
            return null;
        }
        if (strtolower((string) ($estimate['document_type'] ?? '')) !== 'estimate') {
            return null;
        }
        if (self::estimateAlreadyConverted($estimateId)) {
            return null;
        }

        $lineItems = self::lineItems($jobId, $estimateId);
        if (empty($lineItems) && isset($estimate['amount']) && (float) $estimate['amount'] > 0) {
            $lineItems = [[
                'item_type_id' => null,
                'item_type_label' => 'Service',
                'item_description' => trim((string) ($estimate['title'] ?? 'Service')),
                'line_note' => '',
                'quantity' => 1.0,
                'unit_price' => (float) $estimate['amount'],
                'sort_order' => 10,
            ]];
        }
        $invoiceData = [
            'document_type' => 'invoice',
            'title' => self::invoiceTitleFromEstimate((string) ($estimate['title'] ?? '')),
            'status' => 'draft',
            'amount' => (float) ($estimate['amount'] ?? 0),
            'subtotal_amount' => (float) ($estimate['subtotal_amount'] ?? 0),
            'tax_rate' => $estimate['tax_rate'] !== null ? (float) $estimate['tax_rate'] : 0.0,
            'tax_amount' => (float) ($estimate['tax_amount'] ?? 0),
            'issued_at' => date('Y-m-d H:i:s'),
            'due_at' => $estimate['due_at'] ?? null,
            'sent_at' => null,
            'approved_at' => null,
            'paid_at' => null,
            'note' => (string) ($estimate['note'] ?? ''),
            'customer_note' => (string) ($estimate['customer_note'] ?? ''),
            'source_estimate_id' => $estimateId,
            'line_items' => array_map(static function (array $row): array {
                return [
                    'item_type_id' => isset($row['item_type_id']) && $row['item_type_id'] !== null ? (int) $row['item_type_id'] : null,
                    'item_type_label' => trim((string) ($row['item_type_label'] ?? '')),
                    'item_description' => trim((string) ($row['item_description'] ?? '')),
                    'line_note' => trim((string) ($row['line_note'] ?? '')),
                    'quantity' => (float) ($row['quantity'] ?? 1),
                    'unit_price' => (float) ($row['unit_price'] ?? 0),
                    'is_taxable' => isset($row['is_taxable']) && (int) $row['is_taxable'] === 0 ? 0 : 1,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            }, $lineItems),
        ];

        $invoiceId = self::create($jobId, $invoiceData, $actorId);
        self::update($jobId, $estimateId, [
            'document_type' => 'estimate',
            'title' => (string) ($estimate['title'] ?? ''),
            'status' => 'invoiced',
            'amount' => self::documentAmount($estimateId),
            'subtotal_amount' => (float) ($estimate['subtotal_amount'] ?? 0),
            'tax_rate' => $estimate['tax_rate'] !== null ? (float) $estimate['tax_rate'] : 0.0,
            'tax_amount' => (float) ($estimate['tax_amount'] ?? 0),
            'issued_at' => $estimate['issued_at'] ?? null,
            'due_at' => $estimate['due_at'] ?? null,
            'sent_at' => $estimate['sent_at'] ?? null,
            'approved_at' => $estimate['approved_at'] ?? null,
            'paid_at' => $estimate['paid_at'] ?? null,
            'note' => (string) ($estimate['note'] ?? ''),
            'customer_note' => (string) ($estimate['customer_note'] ?? ''),
            'source_estimate_id' => null,
            'line_items' => $invoiceData['line_items'],
        ], $actorId);
        self::createEvent($jobId, $estimateId, [
            'event_type' => 'converted_to_invoice',
            'from_status' => (string) ($estimate['status'] ?? 'draft'),
            'to_status' => 'invoiced',
            'event_note' => 'Converted to invoice #' . $invoiceId . '.',
        ], $actorId);

        return $invoiceId;
    }

    public static function convertLatestEstimateForAcceptedJob(int $jobId, ?int $actorId = null): ?int
    {
        self::ensureSchema();
        if ($jobId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id
             FROM job_estimate_invoices
             WHERE job_id = :job_id
               AND document_type = "estimate"
               AND status IN ("draft", "quote_sent", "approved")
               AND deleted_at IS NULL
             ORDER BY COALESCE(approved_at, sent_at, issued_at, created_at) DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['job_id' => $jobId]);
        $estimateId = (int) ($stmt->fetchColumn() ?: 0);
        if ($estimateId <= 0) {
            return null;
        }

        return self::convertEstimateToInvoice($jobId, $estimateId, $actorId);
    }

    public static function businessLogoDataUri(?array $document): ?string
    {
        $path = trim((string) ($document['business_logo_path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $absolute = BASE_PATH . '/' . ltrim($path, '/');
        if (!is_file($absolute) || !is_readable($absolute)) {
            return null;
        }

        $content = @file_get_contents($absolute);
        if ($content === false || $content === '') {
            return null;
        }

        $mime = trim((string) ($document['business_logo_mime_type'] ?? ''));
        if ($mime === '' || !str_starts_with($mime, 'image/')) {
            $detected = @mime_content_type($absolute);
            $mime = is_string($detected) && str_starts_with($detected, 'image/') ? $detected : 'image/png';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private static function ensureDefaultItemTypes(int $businessId): void
    {
        if ($businessId <= 0) {
            return;
        }

        $pdo = Database::connection();
        foreach (self::DEFAULT_ITEM_TYPES as $itemType) {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM business_document_item_types
                 WHERE business_id = :business_id
                   AND item_code = :item_code
                 LIMIT 1'
            );
            $stmt->execute([
                'business_id' => $businessId,
                'item_code' => (string) ($itemType['code'] ?? ''),
            ]);
            if ($stmt->fetchColumn()) {
                continue;
            }

            $insert = $pdo->prepare(
                'INSERT INTO business_document_item_types (
                    business_id,
                    item_code,
                    item_label,
                    sort_order,
                    is_active,
                    created_at,
                    updated_at
                 ) VALUES (
                    :business_id,
                    :item_code,
                    :item_label,
                    :sort_order,
                    1,
                    NOW(),
                    NOW()
                 )'
            );
            $insert->execute([
                'business_id' => $businessId,
                'item_code' => (string) ($itemType['code'] ?? ''),
                'item_label' => (string) ($itemType['label'] ?? ''),
                'sort_order' => (int) ($itemType['sort_order'] ?? 0),
            ]);
        }
    }

    private static function syncLineItems(int $jobId, int $documentId, array $lineItems, ?int $actorId = null): void
    {
        $pdo = Database::connection();
        $delete = $pdo->prepare(
            'DELETE FROM job_estimate_invoice_line_items
             WHERE document_id = :document_id
               AND job_id = :job_id'
        );
        $delete->execute([
            'document_id' => $documentId,
            'job_id' => $jobId,
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO job_estimate_invoice_line_items (
                document_id,
                job_id,
                item_type_id,
                item_type_label,
                item_description,
                line_note,
                quantity,
                unit_price,
                is_taxable,
                line_total,
                sort_order,
                created_at,
                updated_at,
                created_by,
                updated_by
             ) VALUES (
                :document_id,
                :job_id,
                :item_type_id,
                :item_type_label,
                :item_description,
                :line_note,
                :quantity,
                :unit_price,
                :is_taxable,
                :line_total,
                :sort_order,
                NOW(),
                NOW(),
                :created_by,
                :updated_by
             )'
        );

        $sortOrder = 10;
        foreach ($lineItems as $row) {
            $description = trim((string) ($row['item_description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = (float) ($row['quantity'] ?? 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $unitPrice = (float) ($row['unit_price'] ?? 0);
            if ($unitPrice < 0) {
                $unitPrice = 0.0;
            }

            $itemTypeId = isset($row['item_type_id']) && $row['item_type_id'] !== null
                ? max(0, (int) $row['item_type_id'])
                : 0;
            $itemTypeLabel = trim((string) ($row['item_type_label'] ?? ''));
            if ($itemTypeLabel === '') {
                $itemTypeLabel = 'Service';
            }
            $lineTotal = round($quantity * $unitPrice, 2);
            $isTaxable = isset($row['is_taxable']) && (int) $row['is_taxable'] === 0 ? 0 : 1;

            $insert->execute([
                'document_id' => $documentId,
                'job_id' => $jobId,
                'item_type_id' => $itemTypeId > 0 ? $itemTypeId : null,
                'item_type_label' => $itemTypeLabel,
                'item_description' => $description,
                'line_note' => trim((string) ($row['line_note'] ?? '')) ?: null,
                'quantity' => round($quantity, 2),
                'unit_price' => round($unitPrice, 2),
                'is_taxable' => $isTaxable,
                'line_total' => $lineTotal,
                'sort_order' => (int) ($row['sort_order'] ?? $sortOrder),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $sortOrder += 10;
        }

        self::updateDocumentAmountFromLineItems($documentId);
    }

    private static function updateDocumentAmountFromLineItems(int $documentId): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(line_total), 0) AS subtotal_amount,
                    COALESCE(SUM(CASE WHEN COALESCE(is_taxable, 1) = 1 THEN line_total ELSE 0 END), 0) AS taxable_subtotal
             FROM job_estimate_invoice_line_items
             WHERE document_id = :document_id'
        );
        $stmt->execute(['document_id' => $documentId]);
        $totals = $stmt->fetch() ?: [];
        $subtotal = round((float) ($totals['subtotal_amount'] ?? 0), 2);
        $taxableSubtotal = round((float) ($totals['taxable_subtotal'] ?? 0), 2);

        $rateStmt = Database::connection()->prepare(
            'SELECT tax_rate
             FROM job_estimate_invoices
             WHERE id = :id
             LIMIT 1'
        );
        $rateStmt->execute(['id' => $documentId]);
        $taxRateRaw = $rateStmt->fetchColumn();
        $taxRate = $taxRateRaw !== false && $taxRateRaw !== null ? (float) $taxRateRaw : 0.0;
        if ($taxRate < 0) {
            $taxRate = 0.0;
        }

        $taxAmount = round($taxableSubtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $update = Database::connection()->prepare(
            'UPDATE job_estimate_invoices
             SET amount = :amount,
                 subtotal_amount = :subtotal_amount,
                 tax_amount = :tax_amount,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'id' => $documentId,
            'amount' => $total,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
        ]);
    }

    private static function documentAmount(int $documentId): ?float
    {
        if ($documentId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT amount
             FROM job_estimate_invoices
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $documentId]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (float) $value : null;
    }

    private static function syncLatestEstimateToJobQuote(int $jobId, ?int $actorId = null): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT amount
             FROM job_estimate_invoices
             WHERE job_id = :job_id
               AND document_type = "estimate"
               AND deleted_at IS NULL
             ORDER BY COALESCE(approved_at, sent_at, issued_at, created_at) DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['job_id' => $jobId]);
        $value = $stmt->fetchColumn();
        $amount = $value !== false && $value !== null ? (float) $value : null;
        Job::updateQuoteAmount($jobId, $amount, $actorId);
    }

    private static function invoiceTitleFromEstimate(string $estimateTitle): string
    {
        $clean = trim($estimateTitle);
        if ($clean === '') {
            return 'Invoice';
        }
        if (stripos($clean, 'estimate') === 0) {
            return 'Invoice' . substr($clean, 8);
        }
        return 'Invoice - ' . $clean;
    }

    private static function userLabelSql(string $alias, string $fallbackColumn): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), {$alias}.email, CONCAT('User #', {$fallbackColumn}))";
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        if (!Schema::tableExists($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Migration handles DDL in restricted environments.
        }
    }

    private static function ensureIndex(string $table, string $indexName, string $sql): void
    {
        if (!Schema::tableExists($table)) {
            return;
        }

        $schema = trim((string) config('database.database', ''));
        if ($schema === '') {
            return;
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'table_name' => $table,
            'index_name' => $indexName,
        ]);
        if ($stmt->fetch()) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Migration handles DDL in restricted environments.
        }
    }

    private static function ensureForeignKey(string $constraintName, string $sql, string $schema): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :schema
               AND CONSTRAINT_NAME = :constraint
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'constraint' => $constraintName,
        ]);

        if ($stmt->fetch()) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Keep runtime stable when constraints cannot be applied on an existing environment.
        }
    }
}
