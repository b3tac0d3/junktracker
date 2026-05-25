<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class PurchaseQuoteOffer
{
    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'our_offer' => 'Our offer',
            'their_ask' => 'Their ask',
            'counter' => 'Counter',
            'accepted' => 'Accepted',
        ];
    }

    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('purchase_quote_offers');
    }

    public static function latestAmount(int $businessId, int $purchaseQuoteId): float
    {
        if (!self::isAvailable() || $businessId <= 0 || $purchaseQuoteId <= 0) {
            return 0.0;
        }

        $sql = 'SELECT amount
                FROM purchase_quote_offers
                WHERE business_id = :business_id
                  AND purchase_quote_id = :purchase_quote_id
                  AND deleted_at IS NULL
                  AND amount IS NOT NULL
                ORDER BY offered_at DESC, id DESC
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'purchase_quote_id' => $purchaseQuoteId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? round((float) ($row['amount'] ?? 0), 2) : 0.0;
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
        if (SchemaInspector::hasColumn('purchase_quote_offers', 'created_by') && SchemaInspector::hasTable('users')) {
            $join = 'LEFT JOIN users u ON u.id = o.created_by';
            if (SchemaInspector::hasColumn('users', 'business_id')) {
                $join .= ' AND u.business_id = o.business_id';
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
                    o.*,
                    {$createdByNameSql} AS created_by_name
                FROM purchase_quote_offers o
                " . implode("\n", $joins) . "
                WHERE o.business_id = :business_id
                  AND o.purchase_quote_id = :purchase_quote_id
                  AND o.deleted_at IS NULL
                ORDER BY o.offered_at DESC, o.id DESC
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
        $offerType = strtolower(trim((string) ($data['offer_type'] ?? '')));
        if (!array_key_exists($offerType, self::typeOptions())) {
            $errors['offer_type'] = 'Choose a valid offer type.';
        }
        $amountRaw = trim((string) ($data['amount'] ?? ''));
        if ($amountRaw !== '' && !is_numeric($amountRaw)) {
            $errors['amount'] = 'Enter a valid amount.';
        }
        $offeredAt = trim((string) ($data['offered_at'] ?? ''));
        if ($offeredAt !== '' && strtotime($offeredAt) === false) {
            $errors['offered_at'] = 'Enter a valid date/time.';
        }

        return $errors;
    }

    public static function create(int $businessId, int $purchaseQuoteId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable() || $businessId <= 0 || $purchaseQuoteId <= 0) {
            return 0;
        }

        $offerType = strtolower(trim((string) ($data['offer_type'] ?? 'our_offer')));
        if (!array_key_exists($offerType, self::typeOptions())) {
            $offerType = 'our_offer';
        }

        $amountRaw = trim((string) ($data['amount'] ?? ''));
        $amount = $amountRaw === '' ? null : round((float) $amountRaw, 2);

        $offeredAt = trim((string) ($data['offered_at'] ?? ''));
        $offeredAtDb = $offeredAt === '' ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($offeredAt) ?: time());

        $sql = 'INSERT INTO purchase_quote_offers (
                    business_id, purchase_quote_id, offer_type, amount, note, offered_at, created_by, created_at
                ) VALUES (
                    :business_id, :purchase_quote_id, :offer_type, :amount, :note, :offered_at, :created_by, NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'purchase_quote_id' => $purchaseQuoteId,
            'offer_type' => $offerType,
            'amount' => $amount,
            'note' => trim((string) ($data['note'] ?? '')),
            'offered_at' => $offeredAtDb,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
