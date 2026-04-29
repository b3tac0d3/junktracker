<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Quote
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return ['new', 'sent', 'follow_up', 'won', 'lost', 'expired'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(
        int $businessId,
        string $search = '',
        string $status = '',
        int $limit = 25,
        int $offset = 0
    ): array {
        if (!SchemaInspector::hasTable('quotes')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = array_merge(['dispatch', ''], self::statusOptions());
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'dispatch';
        }

        $where = [
            'q.business_id = :business_id',
            'q.deleted_at IS NULL',
        ];
        if ($status === 'dispatch') {
            $where[] = "LOWER(q.status) IN ('new', 'sent', 'follow_up')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(q.status) = :status';
        }
        $where[] = '(
            :query = ""
            OR COALESCE(q.title, "") LIKE :query_like_1
            OR COALESCE(q.service_type, "") LIKE :query_like_2
            OR COALESCE(q.notes, "") LIKE :query_like_3
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_4
            OR CAST(q.id AS CHAR) LIKE :query_like_5
        )';

        $sql = 'SELECT
                    q.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM quotes q
                INNER JOIN clients c ON c.id = q.client_id
                    AND c.business_id = q.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY
                    CASE WHEN q.next_follow_up_at IS NULL THEN 1 ELSE 0 END,
                    q.next_follow_up_at ASC,
                    q.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!SchemaInspector::hasTable('quotes')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = array_merge(['dispatch', ''], self::statusOptions());
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'dispatch';
        }

        $where = [
            'q.business_id = :business_id',
            'q.deleted_at IS NULL',
        ];
        if ($status === 'dispatch') {
            $where[] = "LOWER(q.status) IN ('new', 'sent', 'follow_up')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(q.status) = :status';
        }
        $where[] = '(
            :query = ""
            OR COALESCE(q.title, "") LIKE :query_like_1
            OR COALESCE(q.service_type, "") LIKE :query_like_2
            OR COALESCE(q.notes, "") LIKE :query_like_3
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_4
            OR CAST(q.id AS CHAR) LIKE :query_like_5
        )';

        $sql = 'SELECT COUNT(*) AS row_count
                FROM quotes q
                INNER JOIN clients c ON c.id = q.client_id
                    AND c.business_id = q.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    }

    public static function findForBusiness(int $businessId, int $quoteId): ?array
    {
        if (!SchemaInspector::hasTable('quotes') || $quoteId <= 0) {
            return null;
        }

        $sql = 'SELECT
                    q.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM quotes q
                INNER JOIN clients c ON c.id = q.client_id
                    AND c.business_id = q.business_id
                    AND c.deleted_at IS NULL
                WHERE q.business_id = :business_id
                  AND q.id = :quote_id
                  AND q.deleted_at IS NULL';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':quote_id', $quoteId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $data, int $businessId): array
    {
        $errors = [];
        $title = trim((string) ($data['title'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? 'new')));
        $clientId = (int) ($data['client_id'] ?? 0);

        if ($title === '') {
            $errors['title'] = 'Quote title is required.';
        }
        if (!in_array($status, self::statusOptions(), true)) {
            $errors['status'] = 'Choose a valid status.';
        }
        if ($clientId <= 0 || Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Choose a valid client.';
        }
        $nextFollowUpAt = trim((string) ($data['next_follow_up_at'] ?? ''));
        if ($nextFollowUpAt !== '' && strtotime($nextFollowUpAt) === false) {
            $errors['next_follow_up_at'] = 'Enter a valid follow-up date/time.';
        }

        return $errors;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('quotes')) {
            return 0;
        }

        $sql = 'INSERT INTO quotes (
                    business_id, client_id, title, status, service_type, quoted_amount, notes,
                    next_follow_up_at, lost_reason, converted_job_id, source, priority,
                    address_line1, address_line2, city, state, postal_code,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :client_id, :title, :status, :service_type, :quoted_amount, :notes,
                    :next_follow_up_at, :lost_reason, :converted_job_id, :source, :priority,
                    :address_line1, :address_line2, :city, :state, :postal_code,
                    :created_by, :updated_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(self::payload($businessId, $data, $actorUserId, true));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $quoteId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('quotes') || $quoteId <= 0) {
            return false;
        }

        $sql = 'UPDATE quotes
                SET client_id = :client_id,
                    title = :title,
                    status = :status,
                    service_type = :service_type,
                    quoted_amount = :quoted_amount,
                    notes = :notes,
                    next_follow_up_at = :next_follow_up_at,
                    lost_reason = :lost_reason,
                    converted_job_id = :converted_job_id,
                    source = :source,
                    priority = :priority,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :quote_id
                  AND deleted_at IS NULL';
        $params = self::payload($businessId, $data, $actorUserId, false);
        $params['quote_id'] = $quoteId;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function convertToJob(int $businessId, int $quoteId, int $actorUserId): int
    {
        $quote = self::findForBusiness($businessId, $quoteId);
        if ($quote === null) {
            return 0;
        }

        $resolvedJobType = self::resolveJobTypeForConversion($businessId, (string) ($quote['service_type'] ?? ''));

        $jobId = Job::create($businessId, [
            'client_id' => (int) ($quote['client_id'] ?? 0),
            'title' => trim((string) ($quote['title'] ?? 'Quote Conversion')),
            'job_type' => $resolvedJobType,
            'status' => 'pending',
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'actual_start_at' => null,
            'actual_end_at' => null,
            'address_line1' => trim((string) ($quote['address_line1'] ?? '')),
            'address_line2' => trim((string) ($quote['address_line2'] ?? '')),
            'city' => trim((string) ($quote['city'] ?? '')),
            'state' => trim((string) ($quote['state'] ?? '')),
            'postal_code' => trim((string) ($quote['postal_code'] ?? '')),
            'notes' => trim((string) ($quote['notes'] ?? '')),
        ], $actorUserId);
        if ($jobId <= 0) {
            return 0;
        }

        self::update($businessId, $quoteId, [
            'client_id' => (int) ($quote['client_id'] ?? 0),
            'title' => trim((string) ($quote['title'] ?? '')),
            'status' => 'won',
            'service_type' => trim((string) ($quote['service_type'] ?? '')),
            'quoted_amount' => (string) ($quote['quoted_amount'] ?? ''),
            'notes' => trim((string) ($quote['notes'] ?? '')),
            'next_follow_up_at' => '',
            'lost_reason' => '',
            'converted_job_id' => (string) $jobId,
            'source' => trim((string) ($quote['source'] ?? '')),
            'priority' => trim((string) ($quote['priority'] ?? '')),
            'address_line1' => trim((string) ($quote['address_line1'] ?? '')),
            'address_line2' => trim((string) ($quote['address_line2'] ?? '')),
            'city' => trim((string) ($quote['city'] ?? '')),
            'state' => trim((string) ($quote['state'] ?? '')),
            'postal_code' => trim((string) ($quote['postal_code'] ?? '')),
        ], $actorUserId);

        if (SchemaInspector::hasTable('invoices') && SchemaInspector::hasColumn('invoices', 'quote_id') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $stmt = Database::connection()->prepare('UPDATE invoices
                SET job_id = :job_id, updated_by = :updated_by, updated_at = NOW()
                WHERE business_id = :business_id
                  AND quote_id = :quote_id
                  AND deleted_at IS NULL');
            $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
            $stmt->bindValue(':updated_by', $actorUserId > 0 ? $actorUserId : null, $actorUserId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            $stmt->bindValue(':quote_id', $quoteId, \PDO::PARAM_INT);
            $stmt->execute();
        }

        return $jobId;
    }

    private static function resolveJobTypeForConversion(int $businessId, string $serviceType): string
    {
        $raw = strtolower(trim($serviceType));
        if ($raw === '') {
            return '';
        }

        $normalized = preg_replace('/[\s\-]+/', '_', $raw) ?? $raw;
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? $normalized;
        $normalized = trim((string) $normalized, '_');
        if ($normalized === '') {
            return '';
        }

        $allowed = FormSelectValue::optionsForSection($businessId, 'job_type');
        $allowedNormalized = [];
        foreach ($allowed as $optionRaw) {
            $candidate = strtolower(trim((string) $optionRaw));
            if ($candidate === '') {
                continue;
            }
            $candidate = preg_replace('/[\s\-]+/', '_', $candidate) ?? $candidate;
            $candidate = preg_replace('/[^a-z0-9_]+/', '', $candidate) ?? $candidate;
            $candidate = trim((string) $candidate, '_');
            if ($candidate !== '') {
                $allowedNormalized[$candidate] = true;
            }
        }

        if ($allowedNormalized !== [] && !isset($allowedNormalized[$normalized])) {
            return '';
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function estimatesByQuote(int $businessId, int $quoteId): array
    {
        if (
            !SchemaInspector::hasTable('invoices')
            || !SchemaInspector::hasColumn('invoices', 'quote_id')
            || !SchemaInspector::hasColumn('invoices', 'type')
        ) {
            return [];
        }

        $sql = 'SELECT i.*
                FROM invoices i
                WHERE i.business_id = :business_id
                  AND i.quote_id = :quote_id
                  AND LOWER(COALESCE(i.type, "")) = "estimate"
                  AND i.deleted_at IS NULL
                ORDER BY i.id DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':quote_id', $quoteId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(int $businessId, array $data, int $actorUserId, bool $includeCreate): array
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'new')));
        if (!in_array($status, self::statusOptions(), true)) {
            $status = 'new';
        }
        $followUp = trim((string) ($data['next_follow_up_at'] ?? ''));
        $followUpDb = $followUp === '' ? null : date('Y-m-d H:i:s', strtotime($followUp) ?: time());

        $payload = [
            'business_id' => $businessId,
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => $status,
            'service_type' => trim((string) ($data['service_type'] ?? '')),
            'quoted_amount' => trim((string) ($data['quoted_amount'] ?? '')) === '' ? null : (float) $data['quoted_amount'],
            'notes' => trim((string) ($data['notes'] ?? '')),
            'next_follow_up_at' => $followUpDb,
            'lost_reason' => trim((string) ($data['lost_reason'] ?? '')),
            'converted_job_id' => (int) ($data['converted_job_id'] ?? 0) > 0 ? (int) $data['converted_job_id'] : null,
            'source' => trim((string) ($data['source'] ?? '')),
            'priority' => trim((string) ($data['priority'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        if ($includeCreate) {
            $payload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        return $payload;
    }
}

