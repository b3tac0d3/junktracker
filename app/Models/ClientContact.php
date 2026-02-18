<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientContact
{
    public const CONTACT_METHODS = ['call', 'text', 'email', 'in_person', 'other'];
    public const DIRECTIONS = ['outbound', 'inbound'];

    public static function filter(array $filters): array
    {
        self::ensureTable();

        $sql = 'SELECT cc.id,
                       cc.client_id,
                       cc.link_type,
                       cc.link_id,
                       cc.contact_method,
                       cc.direction,
                       cc.subject,
                       cc.notes,
                       cc.contacted_at,
                       cc.follow_up_at,
                       cc.active,
                       cc.deleted_at,
                       cc.created_at,
                       cc.updated_at,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS client_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), CONCAT(\'User #\', u.id)) AS created_by_name
                FROM client_contacts cc
                LEFT JOIN clients c ON c.id = cc.client_id
                LEFT JOIN users u ON u.id = cc.created_by';

        $where = [];
        $params = [];

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = '(cc.deleted_at IS NULL AND cc.active = 1)';
        } elseif ($recordStatus === 'inactive') {
            $where[] = '(cc.deleted_at IS NOT NULL OR cc.active = 0)';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(cc.subject LIKE :q
                        OR cc.notes LIKE :q
                        OR c.first_name LIKE :q
                        OR c.last_name LIKE :q
                        OR c.business_name LIKE :q
                        OR c.phone LIKE :q
                        OR c.email LIKE :q
                        OR CAST(cc.id AS CHAR) LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $clientId = isset($filters['client_id']) ? (int) $filters['client_id'] : 0;
        if ($clientId > 0) {
            $where[] = 'cc.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY cc.contacted_at DESC, cc.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $linkType = (string) ($row['link_type'] ?? 'general');
            $linkId = isset($row['link_id']) ? (int) $row['link_id'] : null;
            $link = Task::resolveLink($linkType, $linkId);
            $row['link_label'] = $link['label'] ?? '—';
            $row['link_url'] = $link['url'] ?? null;
            $row['link_type_label'] = $link['type_label'] ?? Task::linkTypeLabel($linkType);
        }
        unset($row);

        return $rows;
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();

        $sql = 'SELECT cc.*,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS client_name,
                       c.phone AS client_phone,
                       c.email AS client_email,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uc.first_name, uc.last_name)), \'\'), CONCAT(\'User #\', uc.id)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uu.first_name, uu.last_name)), \'\'), CONCAT(\'User #\', uu.id)) AS updated_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ud.first_name, ud.last_name)), \'\'), CONCAT(\'User #\', ud.id)) AS deleted_by_name
                FROM client_contacts cc
                LEFT JOIN clients c ON c.id = cc.client_id
                LEFT JOIN users uc ON uc.id = cc.created_by
                LEFT JOIN users uu ON uu.id = cc.updated_by
                LEFT JOIN users ud ON ud.id = cc.deleted_by
                WHERE cc.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch();
        if (!$contact) {
            return null;
        }

        $linkType = (string) ($contact['link_type'] ?? 'general');
        $linkId = isset($contact['link_id']) ? (int) $contact['link_id'] : null;
        $link = Task::resolveLink($linkType, $linkId);
        $contact['link_label'] = $link['label'] ?? '—';
        $contact['link_url'] = $link['url'] ?? null;
        $contact['link_type_label'] = $link['type_label'] ?? Task::linkTypeLabel($linkType);

        return $contact;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $sql = 'INSERT INTO client_contacts
                    (client_id, link_type, link_id, contact_method, direction, subject, notes, contacted_at, follow_up_at, created_by, updated_by, active, created_at, updated_at)
                VALUES
                    (:client_id, :link_type, :link_id, :contact_method, :direction, :subject, :notes, :contacted_at, :follow_up_at, :created_by, :updated_by, 1, NOW(), NOW())';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'client_id' => $data['client_id'],
            'link_type' => $data['link_type'],
            'link_id' => $data['link_id'],
            'contact_method' => $data['contact_method'],
            'direction' => $data['direction'],
            'subject' => $data['subject'],
            'notes' => $data['notes'],
            'contacted_at' => $data['contacted_at'],
            'follow_up_at' => $data['follow_up_at'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function clientExists(int $clientId): bool
    {
        $sql = 'SELECT id
                FROM clients
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $clientId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function forProspect(int $prospectId, ?int $clientId = null): array
    {
        self::ensureTable();

        if ($prospectId <= 0) {
            return [];
        }

        $sql = 'SELECT cc.id,
                       cc.client_id,
                       cc.link_type,
                       cc.link_id,
                       cc.contact_method,
                       cc.direction,
                       cc.subject,
                       cc.notes,
                       cc.contacted_at,
                       cc.follow_up_at,
                       cc.active,
                       cc.deleted_at,
                       cc.created_at,
                       cc.updated_at,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS client_name
                FROM client_contacts cc
                LEFT JOIN clients c ON c.id = cc.client_id
                WHERE cc.deleted_at IS NULL
                  AND cc.active = 1
                  AND (
                      (cc.link_type = :prospect_link_type AND cc.link_id = :prospect_id)';

        $params = [
            'prospect_link_type' => 'prospect',
            'prospect_id' => $prospectId,
        ];

        if ($clientId !== null && $clientId > 0) {
            $sql .= ' OR cc.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        $sql .= ')
                ORDER BY cc.contacted_at DESC, cc.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $linkType = (string) ($row['link_type'] ?? 'general');
            $linkId = isset($row['link_id']) ? (int) $row['link_id'] : null;
            $link = Task::resolveLink($linkType, $linkId);
            $row['link_label'] = $link['label'] ?? '—';
            $row['link_url'] = $link['url'] ?? null;
            $row['link_type_label'] = $link['type_label'] ?? Task::linkTypeLabel($linkType);
        }
        unset($row);

        return $rows;
    }

    private static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS client_contacts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                link_type VARCHAR(30) NOT NULL DEFAULT \'general\',
                link_id BIGINT UNSIGNED NULL,
                contact_method VARCHAR(20) NOT NULL DEFAULT \'call\',
                direction VARCHAR(10) NOT NULL DEFAULT \'outbound\',
                subject VARCHAR(150) NULL,
                notes TEXT NULL,
                contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                follow_up_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_client_contacts_client_date (client_id, contacted_at),
                KEY idx_client_contacts_link (link_type, link_id),
                KEY idx_client_contacts_method (contact_method),
                KEY idx_client_contacts_active (active, deleted_at),
                KEY idx_client_contacts_created_by (created_by),
                KEY idx_client_contacts_updated_by (updated_by),
                KEY idx_client_contacts_deleted_by (deleted_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }
}
