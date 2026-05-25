<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class PurchaseQuoteContact
{
    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'phone' => 'Phone',
            'text' => 'Text',
            'email' => 'Email',
            'in_person' => 'In person',
            'other' => 'Other',
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('purchase_quote_contacts');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forPurchaseQuote(int $businessId, int $purchaseQuoteId, int $limit = 100): array
    {
        if (!self::isAvailable() || $businessId <= 0 || $purchaseQuoteId <= 0) {
            return [];
        }

        $createdByNameSql = "''";
        $joins = [];
        if (SchemaInspector::hasColumn('purchase_quote_contacts', 'created_by') && SchemaInspector::hasTable('users')) {
            $join = 'LEFT JOIN users u ON u.id = c.created_by';
            if (SchemaInspector::hasColumn('users', 'business_id')) {
                $join .= ' AND u.business_id = c.business_id';
            }
            if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                $join .= ' AND u.deleted_at IS NULL';
            }
            $joins[] = $join;
            $createdByNameSql = "COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''),
                NULLIF(u.email, ''),
                CONCAT('User #', u.id)
            )";
        }

        $sql = "SELECT
                    c.*,
                    {$createdByNameSql} AS created_by_name
                FROM purchase_quote_contacts c
                " . implode("\n", $joins) . "
                WHERE c.business_id = :business_id
                  AND c.purchase_quote_id = :purchase_quote_id
                  AND c.deleted_at IS NULL
                ORDER BY c.contacted_at DESC, c.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':purchase_quote_id', $purchaseQuoteId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $data): array
    {
        $errors = [];
        $contactType = strtolower(trim((string) ($data['contact_type'] ?? '')));
        if ($contactType !== '' && !array_key_exists($contactType, self::typeOptions())) {
            $errors['contact_type'] = 'Choose a valid contact type.';
        }
        $contactedAt = trim((string) ($data['contacted_at'] ?? ''));
        if ($contactedAt !== '' && strtotime($contactedAt) === false) {
            $errors['contacted_at'] = 'Enter a valid date/time.';
        }

        return $errors;
    }

    public static function create(int $businessId, int $purchaseQuoteId, int $clientId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable() || $businessId <= 0 || $purchaseQuoteId <= 0 || $clientId <= 0) {
            return 0;
        }

        $contactType = strtolower(trim((string) ($data['contact_type'] ?? 'phone')));
        if (!array_key_exists($contactType, self::typeOptions())) {
            $contactType = 'other';
        }

        $contactedAt = trim((string) ($data['contacted_at'] ?? ''));
        $contactedAtDb = $contactedAt === '' ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($contactedAt) ?: time());

        $sql = 'INSERT INTO purchase_quote_contacts (
                    business_id, purchase_quote_id, client_id, contacted_at, contact_type, note,
                    created_by, created_at, updated_at
                ) VALUES (
                    :business_id, :purchase_quote_id, :client_id, :contacted_at, :contact_type, :note,
                    :created_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'purchase_quote_id' => $purchaseQuoteId,
            'client_id' => $clientId,
            'contacted_at' => $contactedAtDb,
            'contact_type' => $contactType,
            'note' => trim((string) ($data['note'] ?? '')),
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
