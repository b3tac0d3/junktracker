<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class JobSubcontractorAssignment
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return ['assigned', 'in_progress', 'completed', 'cancelled'];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('job_subcontractor_assignments');
    }

    /**
     * Active jobs not already subbed out.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function jobsAvailableForSubOut(int $businessId, int $limit = 100): array
    {
        if (!self::isAvailable() || !SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $jobStatusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';

        $jobDeletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';

        $sql = 'SELECT j.id AS job_id,
                       ' . $jobTitleSql . ' AS job_title,
                       ' . $jobStatusSql . ' AS job_status,
                       ' . $citySql . ' AS job_city
                FROM jobs j
                LEFT JOIN job_subcontractor_assignments a
                    ON a.job_id = j.id
                   AND a.business_id = j.business_id
                   AND a.deleted_at IS NULL
                WHERE j.business_id = :business_id
                  AND ' . $jobDeletedWhere . '
                  AND a.id IS NULL
                ORDER BY j.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForJob(int $businessId, int $jobId): ?array
    {
        if (!self::isAvailable() || $jobId <= 0) {
            return null;
        }

        $subNameSql = Subcontractor::isAvailable() ? Subcontractor::displayNameSql('sc') : "''";
        $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");

        $sql = 'SELECT a.*,
                       ' . $subNameSql . ' AS subcontractor_name,
                       sc.first_name AS subcontractor_first_name,
                       sc.last_name AS subcontractor_last_name,
                       sc.company AS subcontractor_company,
                       sc.phone AS subcontractor_phone,
                       sc.email AS subcontractor_email
                FROM job_subcontractor_assignments a
                LEFT JOIN subcontractors sc ON sc.id = a.subcontractor_id AND sc.business_id = a.business_id AND sc.deleted_at IS NULL
                WHERE a.business_id = :business_id
                  AND a.job_id = :job_id
                  AND a.deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'job_id' => $jobId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listBySubcontractor(int $businessId, int $subcontractorId, int $limit = 100, int $offset = 0): array
    {
        if (!self::isAvailable() || $subcontractorId <= 0 || !SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $jobStatusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $jobAddressLine1Sql = SchemaInspector::hasColumn('jobs', 'address_line1') ? 'j.address_line1' : 'NULL';
        $jobAddressLine2Sql = SchemaInspector::hasColumn('jobs', 'address_line2') ? 'j.address_line2' : 'NULL';
        $jobCitySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';
        $jobStateSql = SchemaInspector::hasColumn('jobs', 'state') ? 'j.state' : 'NULL';
        $jobPostalSql = SchemaInspector::hasColumn('jobs', 'postal_code') ? 'j.postal_code' : 'NULL';

        $sql = 'SELECT a.*,
                       j.id AS job_id,
                       ' . $jobTitleSql . ' AS job_title,
                       ' . $jobStatusSql . ' AS job_status,
                       ' . $jobAddressLine1Sql . ' AS job_address_line1,
                       ' . $jobAddressLine2Sql . ' AS job_address_line2,
                       ' . $jobCitySql . ' AS job_city,
                       ' . $jobStateSql . ' AS job_state,
                       ' . $jobPostalSql . ' AS job_postal_code
                FROM job_subcontractor_assignments a
                INNER JOIN jobs j ON j.id = a.job_id AND j.business_id = a.business_id
                WHERE a.business_id = :business_id
                  AND a.subcontractor_id = :subcontractor_id
                  AND a.deleted_at IS NULL
                  AND j.deleted_at IS NULL
                ORDER BY COALESCE(a.completed_at, a.assigned_at, a.created_at) DESC, a.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':subcontractor_id', $subcontractorId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{job_count: int, completed_count: int, sub_total: float, our_cut_total: float}
     */
    public static function earningsSummary(int $businessId, int $subcontractorId): array
    {
        if (!self::isAvailable() || $subcontractorId <= 0) {
            return ['job_count' => 0, 'completed_count' => 0, 'sub_total' => 0.0, 'our_cut_total' => 0.0];
        }

        $sql = 'SELECT
                    COUNT(*) AS job_count,
                    SUM(CASE WHEN LOWER(a.status) = "completed" THEN 1 ELSE 0 END) AS completed_count,
                    COALESCE(SUM(CASE WHEN LOWER(a.status) = "completed" THEN a.sub_amount ELSE 0 END), 0) AS sub_total,
                    COALESCE(SUM(CASE WHEN LOWER(a.status) = "completed" THEN a.our_cut ELSE 0 END), 0) AS our_cut_total
                FROM job_subcontractor_assignments a
                WHERE a.business_id = :business_id
                  AND a.subcontractor_id = :subcontractor_id
                  AND a.deleted_at IS NULL
                  AND LOWER(a.status) <> "cancelled"';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'subcontractor_id' => $subcontractorId,
        ]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return ['job_count' => 0, 'completed_count' => 0, 'sub_total' => 0.0, 'our_cut_total' => 0.0];
        }

        return [
            'job_count' => (int) ($row['job_count'] ?? 0),
            'completed_count' => (int) ($row['completed_count'] ?? 0),
            'sub_total' => round((float) ($row['sub_total'] ?? 0), 2),
            'our_cut_total' => round((float) ($row['our_cut_total'] ?? 0), 2),
        ];
    }

    public static function create(int $businessId, int $jobId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable() || $jobId <= 0) {
            return 0;
        }

        if (self::findForJob($businessId, $jobId) !== null) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO job_subcontractor_assignments (
                business_id, job_id, subcontractor_id, status, client_amount, sub_amount, our_cut,
                notes, assigned_at, completed_at, created_by, updated_by, created_at, updated_at
             ) VALUES (
                :business_id, :job_id, :subcontractor_id, :status, :client_amount, :sub_amount, :our_cut,
                :notes, :assigned_at, :completed_at, :created_by, :updated_by, NOW(), NOW()
             )'
        );
        $payload = self::payload($businessId, $jobId, $data, $actorUserId, true);
        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $assignmentId, array $data, int $actorUserId): bool
    {
        if (!self::isAvailable() || $assignmentId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE job_subcontractor_assignments
             SET subcontractor_id = :subcontractor_id,
                 status = :status,
                 client_amount = :client_amount,
                 sub_amount = :sub_amount,
                 our_cut = :our_cut,
                 notes = :notes,
                 assigned_at = :assigned_at,
                 completed_at = :completed_at,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :assignment_id
               AND deleted_at IS NULL'
        );
        $payload = self::payload($businessId, 0, $data, $actorUserId, false);
        $payload['assignment_id'] = $assignmentId;
        $stmt->execute($payload);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $assignmentId, int $actorUserId): bool
    {
        if (!self::isAvailable() || $assignmentId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE job_subcontractor_assignments
             SET status = "cancelled",
                 deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :assignment_id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'assignment_id' => $assignmentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $data, bool $requireSubcontractor = true): array
    {
        $errors = [];
        $subcontractorId = (int) ($data['subcontractor_id'] ?? 0);
        $status = strtolower(trim((string) ($data['status'] ?? 'assigned')));

        if ($requireSubcontractor && $subcontractorId <= 0) {
            $errors['subcontractor_id'] = 'Choose a sub-contractor.';
        }
        if (!in_array($status, self::statusOptions(), true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        foreach (['client_amount', 'sub_amount', 'our_cut'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                continue;
            }
            if (!is_numeric($data[$field])) {
                $errors[$field] = 'Enter a valid dollar amount.';
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(int $businessId, int $jobId, array $data, int $actorUserId, bool $includeCreate): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'assigned'))) ?: 'assigned';
        $clientAmount = self::nullableAmount($data['client_amount'] ?? null);
        $subAmount = self::nullableAmount($data['sub_amount'] ?? null);
        $ourCut = self::nullableAmount($data['our_cut'] ?? null);

        if ($ourCut === null && $clientAmount !== null && $subAmount !== null) {
            $ourCut = round($clientAmount - $subAmount, 2);
        }

        $assignedAt = trim((string) ($data['assigned_at'] ?? ''));
        if ($assignedAt === '' && in_array($status, ['assigned', 'in_progress', 'completed'], true)) {
            $assignedAt = date('Y-m-d H:i:s');
        }

        $completedAt = trim((string) ($data['completed_at'] ?? ''));
        if ($completedAt === '' && $status === 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        }
        if ($status !== 'completed') {
            $completedAt = $completedAt !== '' ? $completedAt : null;
        }

        $payload = [
            'subcontractor_id' => (int) ($data['subcontractor_id'] ?? 0),
            'status' => $status,
            'client_amount' => $clientAmount,
            'sub_amount' => $subAmount,
            'our_cut' => $ourCut,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'assigned_at' => $assignedAt !== '' ? $assignedAt : null,
            'completed_at' => $completedAt !== '' && $completedAt !== null ? $completedAt : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];

        if ($includeCreate) {
            $payload['business_id'] = $businessId;
            $payload['job_id'] = $jobId;
            $payload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        } else {
            $payload['business_id'] = $businessId;
        }

        return $payload;
    }

    private static function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Completed sub-outs in range: replace invoice gross with our_cut in service metrics.
     *
     * @return array{count: int, our_cut_total: float, invoice_gross_total: float}
     */
    public static function completedMetricsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        if (!self::isAvailable() || !SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'job_id')) {
            return ['count' => 0, 'our_cut_total' => 0.0, 'invoice_gross_total' => 0.0];
        }

        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $invoiceBusiness = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = a.business_id' : '1=1';
        $invoiceDeleted = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        $invoiceType = SchemaInspector::hasColumn('invoices', 'type')
            ? "AND (LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')"
            : '';
        $invoiceStatus = SchemaInspector::hasColumn('invoices', 'status')
            ? "AND LOWER(COALESCE(i.status, '')) NOT IN ('cancelled','declined')"
            : '';

        $sql = 'SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM(COALESCE(a.our_cut, 0)), 0) AS our_cut_total,
                    COALESCE(SUM(job_invoices.invoice_gross), 0) AS invoice_gross_total
                FROM job_subcontractor_assignments a
                LEFT JOIN (
                    SELECT i.job_id,
                           SUM(' . $totalSql . ') AS invoice_gross
                    FROM invoices i
                    WHERE ' . (SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1') . '
                      AND ' . $invoiceDeleted . '
                      ' . $invoiceType . '
                      ' . $invoiceStatus . '
                    GROUP BY i.job_id
                ) job_invoices ON job_invoices.job_id = a.job_id
                WHERE a.business_id = :business_id
                  AND a.deleted_at IS NULL
                  AND LOWER(a.status) = "completed"
                  AND DATE(COALESCE(a.completed_at, a.updated_at, a.created_at)) BETWEEN :from_date AND :to_date';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->bindValue(':to_date', $toDate);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return ['count' => 0, 'our_cut_total' => 0.0, 'invoice_gross_total' => 0.0];
        }

        return [
            'count' => (int) ($row['item_count'] ?? 0),
            'our_cut_total' => round((float) ($row['our_cut_total'] ?? 0), 2),
            'invoice_gross_total' => round((float) ($row['invoice_gross_total'] ?? 0), 2),
        ];
    }
}
