<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class SaleFeeDefault
{
    /**
     * @return array<string, array{fee_kind: string, fee_value: float}>
     */
    public static function mapForBusiness(int $businessId): array
    {
        if (!SchemaInspector::hasTable('business_sale_type_fees') || $businessId <= 0) {
            return [];
        }

        $sql = 'SELECT sale_type, fee_kind, fee_value
                FROM business_sale_type_fees
                WHERE business_id = :business_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $t = strtolower(trim((string) ($row['sale_type'] ?? '')));
            if ($t === '') {
                continue;
            }
            $kind = strtolower(trim((string) ($row['fee_kind'] ?? '')));
            if ($kind !== 'percent' && $kind !== 'amount') {
                continue;
            }
            $out[$t] = [
                'fee_kind' => $kind,
                'fee_value' => (float) ($row['fee_value'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{fee_kind: string, fee_value: float}|null
     */
    public static function findForType(int $businessId, string $saleType): ?array
    {
        $saleType = strtolower(trim($saleType));
        if ($saleType === '') {
            return null;
        }
        $map = self::mapForBusiness($businessId);
        return $map[$saleType] ?? null;
    }

    /**
     * Replace all defaults for a business (admin save).
     *
     * @param array<int, array{sale_type: string, fee_kind: string, fee_value: float}> $rows
     */
    public static function replaceForBusiness(int $businessId, array $rows): void
    {
        if (!SchemaInspector::hasTable('business_sale_type_fees') || $businessId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM business_sale_type_fees WHERE business_id = :business_id');
            $del->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            $del->execute();

            $ins = $pdo->prepare(
                'INSERT INTO business_sale_type_fees (business_id, sale_type, fee_kind, fee_value, created_at, updated_at)
                 VALUES (:business_id, :sale_type, :fee_kind, :fee_value, NOW(), NOW())'
            );

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $t = strtolower(trim((string) ($row['sale_type'] ?? '')));
                $kind = strtolower(trim((string) ($row['fee_kind'] ?? '')));
                if ($t === '' || ($kind !== 'percent' && $kind !== 'amount')) {
                    continue;
                }
                $val = (float) ($row['fee_value'] ?? 0);
                if ($kind === 'percent' && ($val < 0 || $val > 100)) {
                    continue;
                }
                if ($kind === 'amount' && $val < 0) {
                    continue;
                }

                $ins->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
                $ins->bindValue(':sale_type', $t);
                $ins->bindValue(':fee_kind', $kind);
                $ins->bindValue(':fee_value', round($val, 4));
                $ins->execute();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
