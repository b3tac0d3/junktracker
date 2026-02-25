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
                  ' . (Schema::hasColumn('consignor_payouts', 'business_id') ? 'AND cp.business_id = :business_id' : '') . '
                  AND cp.deleted_at IS NULL
                  AND COALESCE(cp.active, 1) = 1
                ORDER BY cp.payout_date DESC, cp.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $params = ['consignor_id' => $consignorId];
        if (Schema::hasColumn('consignor_payouts', 'business_id')) {
            $params['business_id'] = Consignor::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(int $consignorId, array $data, ?int $actorId = null): int
    {
        Consignor::ensureSchema();

        $consignor = Consignor::findById($consignorId);
        if (!$consignor) {
            return 0;
        }

        $columns = [
            'consignor_id',
            'payout_date',
            'amount',
            'estimate_amount',
            'payout_method',
            'reference_no',
            'status',
            'notes',
            'active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':consignor_id',
            ':payout_date',
            ':amount',
            ':estimate_amount',
            ':payout_method',
            ':reference_no',
            ':status',
            ':notes',
            '1',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];
        if (Schema::hasColumn('consignor_payouts', 'business_id')) {
            array_splice($columns, 1, 0, ['business_id']);
            array_splice($values, 1, 0, [':business_id']);
        }

        $sql = 'INSERT INTO consignor_payouts (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $params = [
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
        ];
        if (Schema::hasColumn('consignor_payouts', 'business_id')) {
            $params['business_id'] = (int) ($consignor['business_id'] ?? Consignor::currentBusinessId());
        }
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }
}
