<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Business
{
    public static function allActive(int $limit = 25, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, legal_name, is_active
             FROM businesses
             WHERE deleted_at IS NULL
             ORDER BY name ASC
             LIMIT :row_limit
             OFFSET :row_offset'
        );
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function activeCount(): int
    {
        $stmt = Database::connection()->query(
            'SELECT COUNT(*)
             FROM businesses
             WHERE deleted_at IS NULL'
        );

        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        if (!SchemaInspector::hasTable('businesses')) {
            return null;
        }

        $primaryContactSql = SchemaInspector::hasColumn('businesses', 'primary_contact_name') ? 'primary_contact_name' : 'NULL';
        $websiteSql = SchemaInspector::hasColumn('businesses', 'website_url') ? 'website_url' : 'NULL';
        $einSql = SchemaInspector::hasColumn('businesses', 'ein_number') ? 'ein_number' : 'NULL';
        $mailingSameSql = SchemaInspector::hasColumn('businesses', 'mailing_same_as_physical') ? 'mailing_same_as_physical' : '1';
        $mailingAddress1Sql = SchemaInspector::hasColumn('businesses', 'mailing_address_line1') ? 'mailing_address_line1' : 'NULL';
        $mailingAddress2Sql = SchemaInspector::hasColumn('businesses', 'mailing_address_line2') ? 'mailing_address_line2' : 'NULL';
        $mailingCitySql = SchemaInspector::hasColumn('businesses', 'mailing_city') ? 'mailing_city' : 'NULL';
        $mailingStateSql = SchemaInspector::hasColumn('businesses', 'mailing_state') ? 'mailing_state' : 'NULL';
        $mailingPostalSql = SchemaInspector::hasColumn('businesses', 'mailing_postal_code') ? 'mailing_postal_code' : 'NULL';
        $mailingCountrySql = SchemaInspector::hasColumn('businesses', 'mailing_country') ? 'mailing_country' : 'NULL';

        $stmt = Database::connection()->prepare(
            "SELECT
                id,
                name,
                legal_name,
                email,
                phone,
                address_line1,
                address_line2,
                city,
                state,
                postal_code,
                country,
                {$primaryContactSql} AS primary_contact_name,
                {$websiteSql} AS website_url,
                {$einSql} AS ein_number,
                {$mailingSameSql} AS mailing_same_as_physical,
                {$mailingAddress1Sql} AS mailing_address_line1,
                {$mailingAddress2Sql} AS mailing_address_line2,
                {$mailingCitySql} AS mailing_city,
                {$mailingStateSql} AS mailing_state,
                {$mailingPostalSql} AS mailing_postal_code,
                {$mailingCountrySql} AS mailing_country,
                is_active
             FROM businesses
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1"
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

    public static function updateDetails(int $businessId, array $data, int $actorUserId): void
    {
        if (!SchemaInspector::hasTable('businesses')) {
            return;
        }

        $set = [];
        $params = [
            'business_id' => $businessId,
            'updated_by' => $actorUserId,
        ];

        $columnMap = [
            'name' => 'name',
            'legal_name' => 'legal_name',
            'phone' => 'phone',
            'address_line1' => 'address_line1',
            'address_line2' => 'address_line2',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal_code',
            'country' => 'country',
            'primary_contact_name' => 'primary_contact_name',
            'website_url' => 'website_url',
            'ein_number' => 'ein_number',
            'mailing_same_as_physical' => 'mailing_same_as_physical',
            'mailing_address_line1' => 'mailing_address_line1',
            'mailing_address_line2' => 'mailing_address_line2',
            'mailing_city' => 'mailing_city',
            'mailing_state' => 'mailing_state',
            'mailing_postal_code' => 'mailing_postal_code',
            'mailing_country' => 'mailing_country',
        ];

        foreach ($columnMap as $key => $column) {
            if (!SchemaInspector::hasColumn('businesses', $column) || !array_key_exists($key, $data)) {
                continue;
            }

            $set[] = "{$column} = :{$key}";
            $params[$key] = $data[$key];
        }

        if (SchemaInspector::hasColumn('businesses', 'updated_by')) {
            $set[] = 'updated_by = :updated_by';
        }
        if (SchemaInspector::hasColumn('businesses', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        if ($set === []) {
            return;
        }

        $sql = 'UPDATE businesses
                SET ' . implode(', ', $set) . '
                WHERE id = :business_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }
}
