<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Attachment
{
    public const LINK_TYPES = ['job', 'client', 'prospect', 'sale'];
    public const TAGS = ['photo', 'invoice', 'contract', 'receipt', 'note', 'other'];

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
