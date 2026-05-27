<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class PurchaseQuote
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return ['new', 'sent', 'follow_up', 'won', 'lost', 'expired'];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('purchase_quotes');
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
        if (!self::isAvailable()) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = array_merge(['dispatch', ''], self::statusOptions());
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'dispatch';
        }

        $where = [
            'pq.business_id = :business_id',
            'pq.deleted_at IS NULL',
        ];
        if ($status === 'dispatch') {
            $where[] = "LOWER(pq.status) IN ('new', 'sent', 'follow_up')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(pq.status) = :status';
        }
        $where[] = '(
            :query = ""
            OR COALESCE(pq.title, "") LIKE :query_like_1
            OR COALESCE(pq.notes, "") LIKE :query_like_2
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_3
            OR CAST(pq.id AS CHAR) LIKE :query_like_4
        )';

        $sql = 'SELECT
                    pq.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name,
                    COALESCE(c.phone, "") AS client_phone
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY
                    CASE WHEN pq.next_follow_up_at IS NULL THEN 1 ELSE 0 END,
                    pq.next_follow_up_at ASC,
                    pq.id DESC
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
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = array_merge(['dispatch', ''], self::statusOptions());
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'dispatch';
        }

        $where = [
            'pq.business_id = :business_id',
            'pq.deleted_at IS NULL',
        ];
        if ($status === 'dispatch') {
            $where[] = "LOWER(pq.status) IN ('new', 'sent', 'follow_up')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(pq.status) = :status';
        }
        $where[] = '(
            :query = ""
            OR COALESCE(pq.title, "") LIKE :query_like_1
            OR COALESCE(pq.notes, "") LIKE :query_like_2
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_3
            OR CAST(pq.id AS CHAR) LIKE :query_like_4
        )';

        $sql = 'SELECT COUNT(*) AS row_count
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
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
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    }

    public static function findForBusiness(int $businessId, int $purchaseQuoteId): ?array
    {
        if (!self::isAvailable() || $purchaseQuoteId <= 0) {
            return null;
        }

        $sql = 'SELECT
                    pq.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name,
                    COALESCE(c.phone, "") AS client_phone
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
                    AND c.deleted_at IS NULL
                WHERE pq.business_id = :business_id
                  AND pq.id = :purchase_quote_id
                  AND pq.deleted_at IS NULL';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':purchase_quote_id', $purchaseQuoteId, \PDO::PARAM_INT);
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
            $errors['title'] = 'Title is required.';
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
        $contactDate = trim((string) ($data['contact_date'] ?? ''));
        if ($contactDate !== '' && strtotime($contactDate) === false) {
            $errors['contact_date'] = 'Enter a valid contact date.';
        }

        return $errors;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $sql = 'INSERT INTO purchase_quotes (
                    business_id, client_id, title, status, contact_date, next_follow_up_at,
                    notes, lost_reason, converted_purchase_id,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :client_id, :title, :status, :contact_date, :next_follow_up_at,
                    :notes, :lost_reason, :converted_purchase_id,
                    :created_by, :updated_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(self::payload($businessId, $data, $actorUserId, true));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $purchaseQuoteId, array $data, int $actorUserId): bool
    {
        if (!self::isAvailable() || $purchaseQuoteId <= 0) {
            return false;
        }

        $sql = 'UPDATE purchase_quotes
                SET client_id = :client_id,
                    title = :title,
                    status = :status,
                    contact_date = :contact_date,
                    next_follow_up_at = :next_follow_up_at,
                    notes = :notes,
                    lost_reason = :lost_reason,
                    converted_purchase_id = :converted_purchase_id,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :purchase_quote_id
                  AND deleted_at IS NULL';
        $params = self::payload($businessId, $data, $actorUserId, false);
        $params['purchase_quote_id'] = $purchaseQuoteId;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function updateStatus(int $businessId, int $purchaseQuoteId, string $status, int $actorUserId): bool
    {
        if (!self::isAvailable() || $purchaseQuoteId <= 0) {
            return false;
        }

        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, self::statusOptions(), true)) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE purchase_quotes
             SET status = :status,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :purchase_quote_id
               AND deleted_at IS NULL'
        );
        $stmt->bindValue(':status', $normalizedStatus);
        $stmt->bindValue(':updated_by', $actorUserId > 0 ? $actorUserId : null, $actorUserId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':purchase_quote_id', $purchaseQuoteId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function hasConversion(array $purchaseQuote): bool
    {
        return (int) ($purchaseQuote['converted_purchase_id'] ?? 0) > 0;
    }

    public static function convertToPurchase(int $businessId, int $purchaseQuoteId, int $actorUserId): int
    {
        $purchaseQuote = self::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null || self::hasConversion($purchaseQuote)) {
            return 0;
        }

        $purchasePrice = PurchaseQuoteOffer::latestAmount($businessId, $purchaseQuoteId);
        $contactDate = trim((string) ($purchaseQuote['contact_date'] ?? ''));
        if ($contactDate === '') {
            $contactDate = date('Y-m-d');
        }

        $purchaseId = Purchase::create($businessId, [
            'client_id' => (int) ($purchaseQuote['client_id'] ?? 0),
            'title' => trim((string) ($purchaseQuote['title'] ?? '')) ?: ('Purchase from Quote #' . (string) $purchaseQuoteId),
            'status' => 'pending',
            'contact_date' => $contactDate,
            'purchase_date' => null,
            'notes' => trim((string) ($purchaseQuote['notes'] ?? '')),
            'purchase_price' => $purchasePrice,
        ], $actorUserId);
        if ($purchaseId <= 0) {
            return 0;
        }

        self::update($businessId, $purchaseQuoteId, self::conversionPayload($purchaseQuote, [
            'status' => 'won',
            'converted_purchase_id' => (string) $purchaseId,
        ]), $actorUserId);

        return $purchaseId;
    }

    public static function markLost(int $businessId, int $purchaseQuoteId, string $lostReason, int $actorUserId): bool
    {
        $purchaseQuote = self::findForBusiness($businessId, $purchaseQuoteId);
        if ($purchaseQuote === null || self::hasConversion($purchaseQuote)) {
            return false;
        }

        return self::update($businessId, $purchaseQuoteId, self::conversionPayload($purchaseQuote, [
            'status' => 'lost',
            'lost_reason' => trim($lostReason),
            'next_follow_up_at' => '',
        ]), $actorUserId);
    }

    /**
     * @param array<string, mixed> $purchaseQuote
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function conversionPayload(array $purchaseQuote, array $overrides): array
    {
        return array_merge([
            'client_id' => (int) ($purchaseQuote['client_id'] ?? 0),
            'title' => trim((string) ($purchaseQuote['title'] ?? '')),
            'status' => strtolower(trim((string) ($purchaseQuote['status'] ?? 'new'))),
            'contact_date' => trim((string) ($purchaseQuote['contact_date'] ?? '')),
            'notes' => trim((string) ($purchaseQuote['notes'] ?? '')),
            'next_follow_up_at' => trim((string) ($purchaseQuote['next_follow_up_at'] ?? '')),
            'lost_reason' => '',
            'converted_purchase_id' => (string) ((int) ($purchaseQuote['converted_purchase_id'] ?? 0)),
        ], $overrides);
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

        $contactDate = trim((string) ($data['contact_date'] ?? ''));
        $contactDateDb = $contactDate === '' ? null : date('Y-m-d', strtotime($contactDate) ?: time());

        $payload = [
            'business_id' => $businessId,
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => $status,
            'contact_date' => $contactDateDb,
            'next_follow_up_at' => $followUpDb,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'lost_reason' => trim((string) ($data['lost_reason'] ?? '')),
            'converted_purchase_id' => (int) ($data['converted_purchase_id'] ?? 0) > 0
                ? (int) $data['converted_purchase_id']
                : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        if ($includeCreate) {
            $payload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        return $payload;
    }
}
