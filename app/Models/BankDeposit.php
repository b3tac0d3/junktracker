<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class BankDeposit
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('bank_deposits')
            && SchemaInspector::hasTable('bank_deposit_payments');
    }

    public static function indexList(int $businessId, int $limit = 100): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, deposit_date, amount, note, created_at
             FROM bank_deposits
             WHERE business_id = :business_id
               AND deleted_at IS NULL
             ORDER BY deposit_date DESC, id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $depositId): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, deposit_date, amount, note, created_at
             FROM bank_deposits
             WHERE id = :id AND business_id = :business_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $depositId, 'business_id' => $businessId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO bank_deposits (business_id, deposit_date, amount, note, created_by)
             VALUES (:business_id, :deposit_date, :amount, :note, :created_by)'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'deposit_date' => $data['deposit_date'] ?? date('Y-m-d'),
            'amount' => $data['amount'] ?? 0,
            'note' => $data['note'] ?? null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function softDelete(int $businessId, int $depositId, int $actorUserId): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE bank_deposits SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND business_id = :business_id AND deleted_at IS NULL'
        );
        return $stmt->execute(['id' => $depositId, 'business_id' => $businessId]);
    }

    /** @return list<array<string,mixed>> */
    public static function linkedPayments(int $businessId, int $depositId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.invoice_id, p.amount, p.paid_at, p.method, p.reference_number
             FROM bank_deposit_payments bdp
             INNER JOIN payments p ON p.id = bdp.payment_id AND p.business_id = :business_id AND p.deleted_at IS NULL
             WHERE bdp.deposit_id = :deposit_id'
        );
        $stmt->execute(['business_id' => $businessId, 'deposit_id' => $depositId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function totalLinkedAmount(int $businessId, int $depositId): float
    {
        $rows = self::linkedPayments($businessId, $depositId);
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float) ($r['amount'] ?? 0);
        }

        return round($sum, 2);
    }

    public static function linkPayment(int $businessId, int $depositId, int $paymentId): bool
    {
        if (!self::isAvailable() || $depositId <= 0 || $paymentId <= 0) {
            return false;
        }

        $payment = Invoice::findPaymentForBusiness($businessId, $paymentId);
        if ($payment === null) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO bank_deposit_payments (deposit_id, payment_id) VALUES (:d, :p)'
        );

        return $stmt->execute(['d' => $depositId, 'p' => $paymentId]);
    }

    public static function unlinkPayment(int $businessId, int $depositId, int $paymentId): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'DELETE bdp FROM bank_deposit_payments bdp
             INNER JOIN bank_deposits bd ON bd.id = bdp.deposit_id AND bd.business_id = :business_id
             WHERE bdp.deposit_id = :deposit_id AND bdp.payment_id = :payment_id'
        );

        return $stmt->execute([
            'business_id' => $businessId,
            'deposit_id' => $depositId,
            'payment_id' => $paymentId,
        ]);
    }

    /** Payments for business not linked to any deposit (optional filter). */
    public static function unassignedPayments(int $businessId, int $limit = 200): array
    {
        if (!SchemaInspector::hasTable('payments')) {
            return [];
        }

        if (!SchemaInspector::hasTable('bank_deposit_payments')) {
            $sql = 'SELECT p.id, p.invoice_id, p.amount, p.paid_at, p.method, p.reference_number
                    FROM payments p
                    WHERE p.business_id = :business_id
                      AND p.deleted_at IS NULL
                    ORDER BY p.paid_at DESC, p.id DESC
                    LIMIT :lim';
        } else {
            $sql = 'SELECT p.id, p.invoice_id, p.amount, p.paid_at, p.method, p.reference_number
                    FROM payments p
                    WHERE p.business_id = :business_id
                      AND p.deleted_at IS NULL
                      AND NOT EXISTS (
                        SELECT 1 FROM bank_deposit_payments bdp WHERE bdp.payment_id = p.id
                      )
                    ORDER BY p.paid_at DESC, p.id DESC
                    LIMIT :lim';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
