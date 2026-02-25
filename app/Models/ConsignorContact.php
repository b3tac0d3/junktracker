<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ConsignorContact
{
    public static function forConsignor(int $consignorId): array
    {
        Consignor::ensureSchema();

        if ($consignorId <= 0) {
            return [];
        }

        $sql = 'SELECT cc.id,
                       cc.link_type,
                       cc.link_id,
                       cc.contact_method,
                       cc.direction,
                       cc.subject,
                       cc.notes,
                       cc.contacted_at,
                       cc.follow_up_at,
                       cc.created_at,
                       cc.updated_at,
                       cc.created_by,
                       cc.updated_by,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', cc.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', cc.updated_by)) AS updated_by_name
                FROM consignor_contacts cc
                LEFT JOIN users u_created ON u_created.id = cc.created_by
                LEFT JOIN users u_updated ON u_updated.id = cc.updated_by
                WHERE cc.consignor_id = :consignor_id
                  ' . (Schema::hasColumn('consignor_contacts', 'business_id') ? 'AND cc.business_id = :business_id' : '') . '
                  AND cc.deleted_at IS NULL
                  AND COALESCE(cc.active, 1) = 1
                ORDER BY cc.contacted_at DESC, cc.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $params = ['consignor_id' => $consignorId];
        if (Schema::hasColumn('consignor_contacts', 'business_id')) {
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
            'link_type',
            'link_id',
            'contact_method',
            'direction',
            'subject',
            'notes',
            'contacted_at',
            'follow_up_at',
            'active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':consignor_id',
            ':link_type',
            ':link_id',
            ':contact_method',
            ':direction',
            ':subject',
            ':notes',
            ':contacted_at',
            ':follow_up_at',
            '1',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];

        if (Schema::hasColumn('consignor_contacts', 'business_id')) {
            array_splice($columns, 1, 0, ['business_id']);
            array_splice($values, 1, 0, [':business_id']);
        }

        $sql = 'INSERT INTO consignor_contacts (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'consignor_id' => $consignorId,
            'link_type' => $data['link_type'] ?? 'general',
            'link_id' => $data['link_id'] ?? null,
            'contact_method' => $data['contact_method'],
            'direction' => $data['direction'],
            'subject' => $data['subject'] !== '' ? $data['subject'] : null,
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'contacted_at' => $data['contacted_at'],
            'follow_up_at' => $data['follow_up_at'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
        if (Schema::hasColumn('consignor_contacts', 'business_id')) {
            $params['business_id'] = (int) ($consignor['business_id'] ?? Consignor::currentBusinessId());
        }
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }
}
