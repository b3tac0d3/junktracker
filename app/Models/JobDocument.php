<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class JobDocument
{
    public const TYPES = ['estimate', 'invoice'];
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
                issued_at DATETIME NULL,
                due_at DATETIME NULL,
                sent_at DATETIME NULL,
                approved_at DATETIME NULL,
                paid_at DATETIME NULL,
                note TEXT NULL,
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
                       d.issued_at,
                       d.due_at,
                       d.sent_at,
                       d.approved_at,
                       d.paid_at,
                       d.note,
                       d.created_at,
                       d.updated_at,
                       d.deleted_at,
                       d.created_by,
                       d.updated_by,
                       ' . $createdBySql . ' AS created_by_name,
                       ' . $updatedBySql . ' AS updated_by_name
                FROM job_estimate_invoices d
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

        $createdBySql = self::userLabelSql('uc', 'd.created_by');
        $updatedBySql = self::userLabelSql('uu', 'd.updated_by');
        $deletedBySql = self::userLabelSql('ud', 'd.deleted_by');

        $sql = 'SELECT d.id,
                       d.job_id,
                       d.document_type,
                       d.title,
                       d.status,
                       d.amount,
                       d.issued_at,
                       d.due_at,
                       d.sent_at,
                       d.approved_at,
                       d.paid_at,
                       d.note,
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
                       COALESCE(
                           NULLIF(c.business_name, ""),
                           NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                           CONCAT("Client #", c.id)
                       ) AS client_name,
                       c.email AS client_email,
                       c.phone AS client_phone
                FROM job_estimate_invoices d
                INNER JOIN jobs j ON j.id = d.job_id
                LEFT JOIN clients c ON c.id = j.client_id
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

        $columns = [
            'job_id',
            'document_type',
            'title',
            'status',
            'amount',
            'issued_at',
            'due_at',
            'sent_at',
            'approved_at',
            'paid_at',
            'note',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':job_id',
            ':document_type',
            ':title',
            ':status',
            ':amount',
            ':issued_at',
            ':due_at',
            ':sent_at',
            ':approved_at',
            ':paid_at',
            ':note',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'issued_at' => $data['issued_at'],
            'due_at' => $data['due_at'],
            'sent_at' => $data['sent_at'],
            'approved_at' => $data['approved_at'],
            'paid_at' => $data['paid_at'],
            'note' => $data['note'],
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

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $id = (int) Database::connection()->lastInsertId();
        self::createEvent($jobId, $id, [
            'event_type' => 'created',
            'from_status' => null,
            'to_status' => (string) ($data['status'] ?? 'draft'),
            'event_note' => 'Document created.',
        ], $actorId);

        return $id;
    }

    public static function update(int $jobId, int $documentId, array $data, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sets = [
            'document_type = :document_type',
            'title = :title',
            'status = :status',
            'amount = :amount',
            'issued_at = :issued_at',
            'due_at = :due_at',
            'sent_at = :sent_at',
            'approved_at = :approved_at',
            'paid_at = :paid_at',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'job_id' => $jobId,
            'document_id' => $documentId,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'issued_at' => $data['issued_at'],
            'due_at' => $data['due_at'],
            'sent_at' => $data['sent_at'],
            'approved_at' => $data['approved_at'],
            'paid_at' => $data['paid_at'],
            'note' => $data['note'],
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

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $jobId, int $documentId, ?int $actorId = null): void
    {
        self::ensureSchema();

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

    private static function userLabelSql(string $alias, string $fallbackColumn): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), {$alias}.email, CONCAT('User #', {$fallbackColumn}))";
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
