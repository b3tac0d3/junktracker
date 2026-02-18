<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ConsignorPayout
{
    public static function forConsignor(int $consignorId): array
    {
        Consignor::ensureSchema();

        if ($consignorId <= 0) {
            return [];
        }

        $sql = 'SELECT cp.id,
                       cp.payout_date,
                       cp.amount,
                       cp.estimate_amount,
                       cp.payout_method,
                       cp.reference_no,
                       cp.status,
                       cp.notes,
                       cp.created_at,
                       cp.updated_at,
                       cp.created_by,
                       cp.updated_by,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', cp.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', cp.updated_by)) AS updated_by_name
                FROM consignor_payouts cp
                LEFT JOIN users u_created ON u_created.id = cp.created_by
                LEFT JOIN users u_updated ON u_updated.id = cp.updated_by
                WHERE cp.consignor_id = :consignor_id
                  AND cp.deleted_at IS NULL
                  AND COALESCE(cp.active, 1) = 1
                ORDER BY cp.payout_date DESC, cp.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['consignor_id' => $consignorId]);

        return $stmt->fetchAll();
    }

    public static function create(int $consignorId, array $data, ?int $actorId = null): int
    {
        Consignor::ensureSchema();

        $sql = 'INSERT INTO consignor_payouts (
                    consignor_id,
                    payout_date,
                    amount,
                    estimate_amount,
                    payout_method,
                    reference_no,
                    status,
                    notes,
                    active,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :consignor_id,
                    :payout_date,
                    :amount,
                    :estimate_amount,
                    :payout_method,
                    :reference_no,
                    :status,
                    :notes,
                    1,
                    :created_by,
                    :updated_by,
                    NOW(),
                    NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'consignor_id' => $consignorId,
            'payout_date' => $data['payout_date'],
            'amount' => $data['amount'],
            'estimate_amount' => $data['estimate_amount'],
            'payout_method' => $data['payout_method'],
            'reference_no' => $data['reference_no'] !== '' ? $data['reference_no'] : null,
            'status' => $data['status'],
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
