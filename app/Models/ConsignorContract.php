<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ConsignorContract
{
    public static function forConsignor(int $consignorId): array
    {
        Consignor::ensureSchema();

        if ($consignorId <= 0) {
            return [];
        }

        $sql = 'SELECT cc.id,
                       cc.contract_title,
                       cc.original_file_name,
                       cc.stored_file_name,
                       cc.storage_path,
                       cc.mime_type,
                       cc.file_size,
                       cc.contract_signed_at,
                       cc.expires_at,
                       cc.notes,
                       cc.created_at,
                       cc.updated_at,
                       cc.created_by,
                       cc.updated_by,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', cc.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', cc.updated_by)) AS updated_by_name
                FROM consignor_contracts cc
                LEFT JOIN users u_created ON u_created.id = cc.created_by
                LEFT JOIN users u_updated ON u_updated.id = cc.updated_by
                WHERE cc.consignor_id = :consignor_id
                  AND cc.deleted_at IS NULL
                  AND COALESCE(cc.active, 1) = 1
                ORDER BY cc.contract_signed_at DESC, cc.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['consignor_id' => $consignorId]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        Consignor::ensureSchema();

        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT cc.*
                FROM consignor_contracts cc
                WHERE cc.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $consignorId, array $data, ?int $actorId = null): int
    {
        Consignor::ensureSchema();

        $sql = 'INSERT INTO consignor_contracts (
                    consignor_id,
                    contract_title,
                    original_file_name,
                    stored_file_name,
                    storage_path,
                    mime_type,
                    file_size,
                    contract_signed_at,
                    expires_at,
                    notes,
                    active,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :consignor_id,
                    :contract_title,
                    :original_file_name,
                    :stored_file_name,
                    :storage_path,
                    :mime_type,
                    :file_size,
                    :contract_signed_at,
                    :expires_at,
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
            'contract_title' => $data['contract_title'],
            'original_file_name' => $data['original_file_name'],
            'stored_file_name' => $data['stored_file_name'],
            'storage_path' => $data['storage_path'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'contract_signed_at' => $data['contract_signed_at'],
            'expires_at' => $data['expires_at'],
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        Consignor::ensureSchema();

        $sql = 'UPDATE consignor_contracts
                SET active = 0,
                    deleted_at = COALESCE(deleted_at, NOW()),
                    updated_by = :updated_by,
                    deleted_by = COALESCE(deleted_by, :deleted_by),
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'updated_by' => $actorId,
            'deleted_by' => $actorId,
        ]);
    }
}
