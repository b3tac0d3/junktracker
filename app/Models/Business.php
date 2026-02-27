<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Business
{
    public static function allActive(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, legal_name, is_active
             FROM businesses
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, legal_name, email, phone, city, state, is_active
             FROM businesses
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(array $data, int $actorUserId): int
    {
        $sql = 'INSERT INTO businesses (
                    name, legal_name, email, phone, address_line1, address_line2,
                    city, state, postal_code, country, is_active, created_by, updated_by,
                    created_at, updated_at
                ) VALUES (
                    :name, :legal_name, :email, :phone, :address_line1, :address_line2,
                    :city, :state, :postal_code, :country, 1, :created_by, :updated_by,
                    NOW(), NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'name' => trim((string) ($data['name'] ?? '')),
            'legal_name' => trim((string) ($data['legal_name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'country' => trim((string) ($data['country'] ?? 'US')),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
