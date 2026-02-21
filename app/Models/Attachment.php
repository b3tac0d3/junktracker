<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Attachment
{
    public const LINK_TYPES = ['job', 'client', 'prospect', 'sale'];
    public const TAGS = [
        'photo',
        'before_photo',
        'during_photo',
        'after_photo',
        'invoice',
        'contract',
        'receipt',
        'note',
        'other',
    ];
    public const PHOTO_TAGS = ['before_photo', 'during_photo', 'after_photo'];

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS attachments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                link_type VARCHAR(40) NOT NULL,
                link_id BIGINT UNSIGNED NOT NULL,
                tag VARCHAR(40) NOT NULL DEFAULT "other",
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(120) NULL,
                file_size BIGINT UNSIGNED NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_attachments_link (link_type, link_id),
                KEY idx_attachments_tag (tag),
                KEY idx_attachments_deleted (deleted_at),
                KEY idx_attachments_created_by (created_by),
                KEY idx_attachments_updated_by (updated_by),
                KEY idx_attachments_deleted_by (deleted_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $schema = trim((string) config('database.database', ''));
        if ($schema !== '') {
            self::ensureForeignKey(
                'fk_attachments_created_by',
                'ALTER TABLE attachments
                 ADD CONSTRAINT fk_attachments_created_by
                 FOREIGN KEY (created_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_attachments_updated_by',
                'ALTER TABLE attachments
                 ADD CONSTRAINT fk_attachments_updated_by
                 FOREIGN KEY (updated_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
            self::ensureForeignKey(
                'fk_attachments_deleted_by',
                'ALTER TABLE attachments
                 ADD CONSTRAINT fk_attachments_deleted_by
                 FOREIGN KEY (deleted_by) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE',
                $schema
            );
        }

        self::ensureStorageRoot();
        self::$schemaEnsured = true;
    }

    public static function storageRoot(): string
    {
        $configured = trim((string) setting('files.attachments_path', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return BASE_PATH . '/storage/attachments';
    }

    public static function ensureStorageRoot(): void
    {
        $path = self::storageRoot();
        if (is_dir($path)) {
            return;
        }

        @mkdir($path, 0775, true);
    }

    public static function forLink(string $linkType, int $linkId): array
    {
        self::ensureSchema();

        $normalizedType = self::normalizeLinkType($linkType);
        if ($normalizedType === null || $linkId <= 0) {
            return [];
        }

        $createdBySql = self::userLabelSql('uc', 'a.created_by');

        $sql = 'SELECT a.id,
                       a.link_type,
                       a.link_id,
                       a.tag,
                       a.original_name,
                       a.stored_name,
                       a.storage_path,
                       a.mime_type,
                       a.file_size,
                       a.note,
                       a.created_at,
                       a.created_by,
                       ' . $createdBySql . ' AS created_by_name
                FROM attachments a
                LEFT JOIN users uc ON uc.id = a.created_by
                WHERE a.link_type = :link_type
                  AND a.link_id = :link_id
                  AND a.deleted_at IS NULL
                ORDER BY a.created_at DESC, a.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'link_type' => $normalizedType,
            'link_id' => $linkId,
        ]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureSchema();

        $createdBySql = self::userLabelSql('uc', 'a.created_by');
        $updatedBySql = self::userLabelSql('uu', 'a.updated_by');
        $deletedBySql = self::userLabelSql('ud', 'a.deleted_by');

        $sql = 'SELECT a.id,
                       a.link_type,
                       a.link_id,
                       a.tag,
                       a.original_name,
                       a.stored_name,
                       a.storage_path,
                       a.mime_type,
                       a.file_size,
                       a.note,
                       a.created_at,
                       a.updated_at,
                       a.deleted_at,
                       a.created_by,
                       a.updated_by,
                       a.deleted_by,
                       ' . $createdBySql . ' AS created_by_name,
                       ' . $updatedBySql . ' AS updated_by_name,
                       ' . $deletedBySql . ' AS deleted_by_name
                FROM attachments a
                LEFT JOIN users uc ON uc.id = a.created_by
                LEFT JOIN users uu ON uu.id = a.updated_by
                LEFT JOIN users ud ON ud.id = a.deleted_by
                WHERE a.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureSchema();

        $columns = [
            'link_type',
            'link_id',
            'tag',
            'original_name',
            'stored_name',
            'storage_path',
            'mime_type',
            'file_size',
            'note',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':link_type',
            ':link_id',
            ':tag',
            ':original_name',
            ':stored_name',
            ':storage_path',
            ':mime_type',
            ':file_size',
            ':note',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'link_type' => $data['link_type'],
            'link_id' => $data['link_id'],
            'tag' => $data['tag'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'storage_path' => $data['storage_path'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('attachments', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('attachments', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO attachments (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sets = [
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('attachments', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('attachments', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE attachments
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function updateNote(int $id, ?string $note, ?int $actorId = null): void
    {
        self::ensureSchema();

        $sets = [
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'note' => $note,
        ];

        if ($actorId !== null && Schema::hasColumn('attachments', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE attachments
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function normalizeLinkType(string $linkType): ?string
    {
        $normalized = strtolower(trim($linkType));
        return in_array($normalized, self::LINK_TYPES, true) ? $normalized : null;
    }

    public static function normalizeTag(string $tag): string
    {
        $normalized = strtolower(trim($tag));
        return in_array($normalized, self::TAGS, true) ? $normalized : 'other';
    }

    public static function tagLabel(string $tag): string
    {
        $normalized = self::normalizeTag($tag);
        return match ($normalized) {
            'before_photo' => 'Before',
            'during_photo' => 'During',
            'after_photo' => 'After',
            default => ucwords(str_replace('_', ' ', $normalized)),
        };
    }

    public static function photoTags(): array
    {
        return ['photo', ...self::PHOTO_TAGS];
    }

    public static function photoLibrary(array $filters, array $allowedLinkTypes = self::LINK_TYPES, int $limit = 500): array
    {
        self::ensureSchema();

        $allowed = array_values(array_filter(array_unique(array_map(
            static fn (mixed $type): string => strtolower(trim((string) $type)),
            $allowedLinkTypes
        )), static fn (string $type): bool => in_array($type, self::LINK_TYPES, true)));

        if (empty($allowed)) {
            return [];
        }

        $limit = max(1, min($limit, 1000));
        $where = [
            'a.deleted_at IS NULL',
            "COALESCE(a.mime_type, '') LIKE 'image/%'",
        ];
        $params = [];

        $allowedPlaceholders = [];
        foreach ($allowed as $index => $type) {
            $key = 'allowed_type_' . $index;
            $allowedPlaceholders[] = ':' . $key;
            $params[$key] = $type;
        }
        $where[] = 'a.link_type IN (' . implode(', ', $allowedPlaceholders) . ')';

        $requestedType = strtolower(trim((string) ($filters['link_type'] ?? 'all')));
        if ($requestedType !== 'all' && in_array($requestedType, $allowed, true)) {
            $where[] = 'a.link_type = :filter_link_type';
            $params['filter_link_type'] = $requestedType;
        }

        $requestedTag = strtolower(trim((string) ($filters['tag'] ?? 'all')));
        if ($requestedTag !== 'all' && in_array($requestedTag, self::photoTags(), true)) {
            $where[] = 'a.tag = :filter_tag';
            $params['filter_tag'] = $requestedTag;
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $where[] = 'DATE(a.created_at) >= :start_date';
            $params['start_date'] = $startDate;
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $where[] = 'DATE(a.created_at) <= :end_date';
            $params['end_date'] = $endDate;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $prospectSearchClauses = [
                'CAST(p.id AS CHAR) LIKE :search',
                'pc.first_name LIKE :search',
                'pc.last_name LIKE :search',
                'pc.business_name LIKE :search',
            ];
            if (Schema::hasColumn('prospects', 'next_step')) {
                $prospectSearchClauses[] = 'p.next_step LIKE :search';
            }
            if (Schema::hasColumn('prospects', 'note')) {
                $prospectSearchClauses[] = 'p.note LIKE :search';
            }

            $where[] = '(
                a.original_name LIKE :search
                OR a.note LIKE :search
                OR CAST(a.id AS CHAR) LIKE :search
                OR j.name LIKE :search
                OR CONCAT_WS(\' \', c.first_name, c.last_name) LIKE :search
                OR c.business_name LIKE :search
                OR (' . implode(' OR ', $prospectSearchClauses) . ')
                OR s.name LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        $sql = 'SELECT
                    a.id,
                    a.link_type,
                    a.link_id,
                    a.tag,
                    a.original_name,
                    a.storage_path,
                    a.mime_type,
                    a.file_size,
                    a.note,
                    a.created_at,
                    a.created_by,
                    ' . self::userLabelSql('u', 'a.created_by') . ' AS created_by_name,
                    CASE
                        WHEN a.link_type = \'job\' THEN COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', a.link_id))
                        WHEN a.link_type = \'client\' THEN COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'), CONCAT(\'Client #\', a.link_id))
                        WHEN a.link_type = \'prospect\' THEN COALESCE(
                            NULLIF(TRIM(CONCAT_WS(\' \', pc.first_name, pc.last_name)), \'\'),
                            NULLIF(pc.business_name, \'\'),
                            CONCAT(\'Prospect #\', a.link_id)
                        )
                        WHEN a.link_type = \'sale\' THEN COALESCE(NULLIF(s.name, \'\'), CONCAT(\'Sale #\', a.link_id))
                        ELSE CONCAT(UPPER(a.link_type), \' #\', a.link_id)
                    END AS linked_label
                FROM attachments a
                LEFT JOIN users u ON u.id = a.created_by
                LEFT JOIN jobs j ON a.link_type = \'job\' AND j.id = a.link_id
                LEFT JOIN clients c ON a.link_type = \'client\' AND c.id = a.link_id
                LEFT JOIN prospects p ON a.link_type = \'prospect\' AND p.id = a.link_id
                LEFT JOIN clients pc ON p.client_id = pc.id
                LEFT JOIN sales s ON a.link_type = \'sale\' AND s.id = a.link_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function jobPhotoJobs(string $search = '', int $limit = 300): array
    {
        self::ensureSchema();

        $limit = max(1, min($limit, 1000));
        $params = [];
        $tagPlaceholders = [];
        foreach (self::PHOTO_TAGS as $index => $tag) {
            $key = 'tag_' . $index;
            $tagPlaceholders[] = ':' . $key;
            $params[$key] = $tag;
        }

        $jobWhere = [];
        if (Schema::hasColumn('jobs', 'deleted_at')) {
            $jobWhere[] = 'j.deleted_at IS NULL';
        }
        if (Schema::hasColumn('jobs', 'active')) {
            $jobWhere[] = 'COALESCE(j.active, 1) = 1';
        }

        $search = trim($search);
        $searchClause = '';
        if ($search !== '') {
            $searchClause = ' AND (j.name LIKE :search OR CAST(j.id AS CHAR) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql = 'SELECT
                    j.id AS job_id,
                    COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS job_name,
                    COUNT(*) AS photo_count,
                    SUM(CASE WHEN a.tag = \'before_photo\' THEN 1 ELSE 0 END) AS before_count,
                    SUM(CASE WHEN a.tag = \'during_photo\' THEN 1 ELSE 0 END) AS during_count,
                    SUM(CASE WHEN a.tag = \'after_photo\' THEN 1 ELSE 0 END) AS after_count,
                    MAX(a.created_at) AS last_photo_at,
                    (
                        SELECT a2.id
                        FROM attachments a2
                        WHERE a2.link_type = \'job\'
                          AND a2.link_id = j.id
                          AND a2.deleted_at IS NULL
                          AND COALESCE(a2.mime_type, \'\') LIKE \'image/%\'
                          AND a2.tag IN (' . implode(', ', $tagPlaceholders) . ')
                        ORDER BY
                            CASE a2.tag
                                WHEN \'after_photo\' THEN 1
                                WHEN \'during_photo\' THEN 2
                                WHEN \'before_photo\' THEN 3
                                ELSE 4
                            END,
                            a2.created_at DESC,
                            a2.id DESC
                        LIMIT 1
                    ) AS cover_attachment_id
                FROM attachments a
                INNER JOIN jobs j ON j.id = a.link_id
                WHERE a.link_type = \'job\'
                  AND a.deleted_at IS NULL
                  AND COALESCE(a.mime_type, \'\') LIKE \'image/%\'
                  AND a.tag IN (' . implode(', ', $tagPlaceholders) . ')' .
                  (!empty($jobWhere) ? ' AND ' . implode(' AND ', $jobWhere) : '') .
                  $searchClause . '
                GROUP BY j.id, j.name
                ORDER BY MAX(a.created_at) DESC, j.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function jobPhotoJob(int $jobId): ?array
    {
        self::ensureSchema();
        if ($jobId <= 0) {
            return null;
        }

        $sql = 'SELECT j.id,
                       COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS name
                FROM jobs j
                WHERE j.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function jobPhotoTagGroups(int $jobId): array
    {
        self::ensureSchema();
        if ($jobId <= 0) {
            return [];
        }

        $params = ['job_id' => $jobId];
        $tagPlaceholders = [];
        foreach (self::PHOTO_TAGS as $index => $tag) {
            $key = 'tag_' . $index;
            $tagPlaceholders[] = ':' . $key;
            $params[$key] = $tag;
        }

        $sql = 'SELECT
                    a.tag,
                    COUNT(*) AS photo_count,
                    MAX(a.created_at) AS last_photo_at,
                    (
                        SELECT a2.id
                        FROM attachments a2
                        WHERE a2.link_type = \'job\'
                          AND a2.link_id = :job_id
                          AND a2.deleted_at IS NULL
                          AND COALESCE(a2.mime_type, \'\') LIKE \'image/%\'
                          AND a2.tag = a.tag
                        ORDER BY a2.created_at DESC, a2.id DESC
                        LIMIT 1
                    ) AS cover_attachment_id
                FROM attachments a
                WHERE a.link_type = \'job\'
                  AND a.link_id = :job_id
                  AND a.deleted_at IS NULL
                  AND COALESCE(a.mime_type, \'\') LIKE \'image/%\'
                  AND a.tag IN (' . implode(', ', $tagPlaceholders) . ')
                GROUP BY a.tag';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $groups = [];
        foreach (self::PHOTO_TAGS as $tag) {
            $groups[$tag] = [
                'tag' => $tag,
                'label' => self::tagLabel($tag),
                'photo_count' => 0,
                'last_photo_at' => null,
                'cover_attachment_id' => null,
            ];
        }

        foreach ($rows as $row) {
            $tag = strtolower(trim((string) ($row['tag'] ?? '')));
            if (!isset($groups[$tag])) {
                continue;
            }
            $groups[$tag]['photo_count'] = (int) ($row['photo_count'] ?? 0);
            $groups[$tag]['last_photo_at'] = $row['last_photo_at'] ?? null;
            $groups[$tag]['cover_attachment_id'] = isset($row['cover_attachment_id']) ? (int) $row['cover_attachment_id'] : null;
        }

        return array_values($groups);
    }

    public static function jobPhotosByTag(int $jobId, string $tag, string $search = '', int $limit = 1200): array
    {
        self::ensureSchema();

        if ($jobId <= 0) {
            return [];
        }

        $normalizedTag = strtolower(trim($tag));
        if (!in_array($normalizedTag, self::PHOTO_TAGS, true)) {
            return [];
        }

        $limit = max(1, min($limit, 2000));
        $params = [
            'job_id' => $jobId,
            'tag' => $normalizedTag,
        ];
        $where = [
            'a.link_type = \'job\'',
            'a.link_id = :job_id',
            'a.tag = :tag',
            'a.deleted_at IS NULL',
            "COALESCE(a.mime_type, '') LIKE 'image/%'",
        ];

        $search = trim($search);
        if ($search !== '') {
            $where[] = '(a.original_name LIKE :search OR a.note LIKE :search OR CAST(a.id AS CHAR) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql = 'SELECT
                    a.id,
                    a.link_type,
                    a.link_id,
                    a.tag,
                    a.original_name,
                    a.storage_path,
                    a.mime_type,
                    a.file_size,
                    a.note,
                    a.created_at,
                    a.created_by,
                    ' . self::userLabelSql('u', 'a.created_by') . ' AS created_by_name
                FROM attachments a
                LEFT JOIN users u ON u.id = a.created_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findPhotosByIds(array $ids, array $allowedLinkTypes = self::LINK_TYPES): array
    {
        self::ensureSchema();

        $normalizedIds = [];
        foreach ($ids as $id) {
            $raw = trim((string) $id);
            if ($raw === '' || !ctype_digit($raw)) {
                continue;
            }
            $value = (int) $raw;
            if ($value > 0) {
                $normalizedIds[] = $value;
            }
        }
        $normalizedIds = array_values(array_unique($normalizedIds));
        if (empty($normalizedIds)) {
            return [];
        }

        $allowed = array_values(array_filter(array_unique(array_map(
            static fn (mixed $type): string => strtolower(trim((string) $type)),
            $allowedLinkTypes
        )), static fn (string $type): bool => in_array($type, self::LINK_TYPES, true)));
        if (empty($allowed)) {
            return [];
        }

        $params = [];
        $idPlaceholders = [];
        foreach ($normalizedIds as $index => $id) {
            $key = 'id_' . $index;
            $idPlaceholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $typePlaceholders = [];
        foreach ($allowed as $index => $type) {
            $key = 'type_' . $index;
            $typePlaceholders[] = ':' . $key;
            $params[$key] = $type;
        }

        $sql = 'SELECT
                    a.id,
                    a.link_type,
                    a.link_id,
                    a.tag,
                    a.original_name,
                    a.storage_path,
                    a.mime_type,
                    a.file_size,
                    a.created_at
                FROM attachments a
                WHERE a.id IN (' . implode(', ', $idPlaceholders) . ')
                  AND a.deleted_at IS NULL
                  AND COALESCE(a.mime_type, \'\') LIKE \'image/%\'
                  AND a.link_type IN (' . implode(', ', $typePlaceholders) . ')
                ORDER BY a.created_at DESC, a.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function absoluteStoragePath(string $storagePath): ?string
    {
        self::ensureSchema();

        $root = realpath(self::storageRoot());
        if ($root === false) {
            return null;
        }

        $relative = ltrim(trim($storagePath), '/');
        if ($relative === '') {
            return null;
        }

        $realPath = realpath(self::storageRoot() . '/' . $relative);
        if ($realPath === false || !str_starts_with($realPath, $root) || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    private static function userLabelSql(string $alias, string $fallbackColumn): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), {$alias}.email, CONCAT('User #', {$fallbackColumn}))";
    }

    private static function ensureForeignKey(string $constraintName, string $sql, string $schema): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :schema
               AND CONSTRAINT_NAME = :constraint
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'constraint' => $constraintName,
        ]);

        if ($stmt->fetch()) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Keep runtime stable when constraints cannot be applied on an existing environment.
        }
    }
}
