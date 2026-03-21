<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientBoloProfile
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('client_bolo_profiles')
            && SchemaInspector::hasTable('client_bolo_lines');
    }

    public static function hasActiveFlag(): bool
    {
        return self::isAvailable() && SchemaInspector::hasColumn('client_bolo_profiles', 'is_active');
    }

    /**
     * Paginated BOLO directory for a business. Search matches client name, profile notes, and line items.
     *
     * @return list<array<string, mixed>>
     */
    public static function indexList(
        int $businessId,
        string $search = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
        string $status = 'active'
    ): array {
        if (!self::isAvailable()) {
            return [];
        }

        $pdo = Database::connection();
        [$sql, $params] = self::indexQueryParts($businessId, $search, $sortBy, $sortDir, $status, $limit, $offset, true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(
        int $businessId,
        string $search = '',
        string $status = 'active'
    ): int {
        if (!self::isAvailable()) {
            return 0;
        }

        $pdo = Database::connection();
        [$sql, $params] = self::indexQueryParts($businessId, $search, 'name', 'asc', $status, 0, 0, false);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Global search: each row is a matching BOLO line item or profile note, with client columns.
     * Only active BOLO profiles when is_active exists.
     *
     * @return list<array<string, mixed>>
     */
    public static function searchMatches(int $businessId, string $query, int $limit = 8): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $pdo = Database::connection();
        $sql = self::searchMatchesUnionSql(false, max(1, min($limit, 50)));
        if ($sql === '') {
            return [];
        }

        $stmt = $pdo->prepare($sql);
        self::bindSearchMatchesParams($stmt, $businessId, $q);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function searchMatchesCount(int $businessId, string $query): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $q = trim($query);
        if ($q === '') {
            return 0;
        }

        $pdo = Database::connection();
        $sql = self::searchMatchesUnionSql(true, 0);
        if ($sql === '') {
            return 0;
        }

        $stmt = $pdo->prepare($sql);
        self::bindSearchMatchesParams($stmt, $businessId, $q);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function bindSearchMatchesParams(\PDOStatement $stmt, int $businessId, string $query): void
    {
        $like = '%' . $query . '%';
        $stmt->bindValue(':business_id_1', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':business_id_2', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':q_like_line', $like);
        $stmt->bindValue(':q_like_note', $like);
    }

    /**
     * @return string SQL or empty if BOLO tables missing
     */
    private static function searchMatchesUnionSql(bool $forCount, int $limit): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        $hasClientDeleted = SchemaInspector::hasColumn('clients', 'deleted_at');
        $deletedWhere = $hasClientDeleted ? 'c.deleted_at IS NULL' : '1=1';

        $activeWhere = self::hasActiveFlag()
            ? 'p.is_active = 1'
            : '1=1';

        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : "''";

        $lineJoinWhere = "FROM client_bolo_lines l
            INNER JOIN client_bolo_profiles p ON p.id = l.bolo_profile_id
            INNER JOIN clients c ON c.id = p.client_id AND c.business_id = p.business_id
            WHERE p.business_id = :business_id_1
              AND {$deletedWhere}
              AND {$activeWhere}
              AND l.item_text LIKE :q_like_line";

        $noteJoinWhere = "FROM client_bolo_profiles p
            INNER JOIN clients c ON c.id = p.client_id AND c.business_id = p.business_id
            WHERE p.business_id = :business_id_2
              AND {$deletedWhere}
              AND {$activeWhere}
              AND COALESCE(p.notes, '') LIKE :q_like_note";

        if ($forCount) {
            return 'SELECT COUNT(*) FROM (
                SELECT l.id AS mid ' . $lineJoinWhere . '
                UNION ALL
                SELECT p.id AS mid ' . $noteJoinWhere . '
            ) t';
        }

        $lim = (int) $limit;

        return '(SELECT
                \'line\' AS match_type,
                l.item_text AS match_text,
                c.id AS client_id,
                c.first_name AS first_name,
                c.last_name AS last_name,
                ' . $companySql . ' AS company_name
            ' . $lineJoinWhere . ')
            UNION ALL
            (SELECT
                \'note\' AS match_type,
                COALESCE(p.notes, \'\') AS match_text,
                c.id AS client_id,
                c.first_name AS first_name,
                c.last_name AS last_name,
                ' . $companySql . ' AS company_name
            ' . $noteJoinWhere . ')
            ORDER BY client_id ASC, match_type ASC
            LIMIT ' . (string) $lim;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function indexQueryParts(
        int $businessId,
        string $search,
        string $sortBy,
        string $sortDir,
        string $status,
        int $limit,
        int $offset,
        bool $withOrderLimit
    ): array {
        $hasActive = self::hasActiveFlag();
        $hasClientDeleted = SchemaInspector::hasColumn('clients', 'deleted_at');

        $query = trim($search);
        $queryLike = '%' . $query . '%';

        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : "''";
        $nameExpr = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id))";

        $linesConcatSql = '(SELECT GROUP_CONCAT(l.item_text ORDER BY l.sort_order ASC, l.id ASC SEPARATOR \' · \')
            FROM client_bolo_lines l WHERE l.bolo_profile_id = p.id)';

        $activeSelect = $hasActive ? 'p.is_active' : '1 AS is_active';

        $deletedWhere = $hasClientDeleted ? 'c.deleted_at IS NULL' : '1=1';

        $status = strtolower(trim($status));
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'active';
        }

        $statusWhere = '1=1';
        if ($hasActive) {
            if ($status === 'active') {
                $statusWhere = 'p.is_active = 1';
            } elseif ($status === 'inactive') {
                $statusWhere = 'p.is_active = 0';
            }
        }

        // Named placeholders must be unique per statement for native PDO (cannot repeat :q_like).
        $searchWhere = '(:q = \'\' OR LOWER(COALESCE(p.notes, \'\')) LIKE LOWER(:q_like_1)
            OR LOWER(' . $nameExpr . ') LIKE LOWER(:q_like_2)
            OR EXISTS (
                SELECT 1 FROM client_bolo_lines bl
                WHERE bl.bolo_profile_id = p.id AND LOWER(bl.item_text) LIKE LOWER(:q_like_3)
            ))';

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'name' => "{$nameExpr} {$sortDir}, c.id {$sortDir}",
            'updated' => "p.updated_at {$sortDir}, c.id {$sortDir}",
            'client_id' => "c.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['name'];

        $params = [
            'business_id' => $businessId,
            'q' => $query,
            'q_like_1' => $queryLike,
            'q_like_2' => $queryLike,
            'q_like_3' => $queryLike,
        ];

        $selectList = "p.id AS profile_id,
                    p.client_id,
                    p.notes,
                    p.updated_at,
                    {$activeSelect},
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    {$linesConcatSql} AS lines_concat";

        $fromWhere = "FROM client_bolo_profiles p
                INNER JOIN clients c ON c.id = p.client_id AND c.business_id = p.business_id
                WHERE p.business_id = :business_id
                  AND {$deletedWhere}
                  AND {$statusWhere}
                  AND {$searchWhere}";

        if ($withOrderLimit) {
            $lim = max(1, min($limit, 500));
            $off = max(0, $offset);
            $sql = "SELECT {$selectList} {$fromWhere} ORDER BY {$orderBy} LIMIT " . (string) (int) $lim . ' OFFSET ' . (string) (int) $off;

            return [$sql, $params];
        }

        $sql = "SELECT COUNT(*) {$fromWhere}";

        return [$sql, $params];
    }

    /**
     * @return array{profile: array<string, mixed>, lines: list<array<string, mixed>>}|null
     */
    public static function findForClient(int $businessId, int $clientId): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $pdo = Database::connection();
        $activeSql = self::hasActiveFlag() ? 'p.is_active' : '1 AS is_active';
        $sql = "SELECT p.id, p.business_id, p.client_id, p.notes, p.created_at, p.updated_at, {$activeSql}
                FROM client_bolo_profiles p
                WHERE p.business_id = :business_id
                  AND p.client_id = :client_id
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
        ]);
        $profile = $stmt->fetch();
        if (!is_array($profile)) {
            return null;
        }

        $profileId = (int) ($profile['id'] ?? 0);
        if ($profileId <= 0) {
            return null;
        }

        $lineSql = 'SELECT id, bolo_profile_id, sort_order, item_text
                    FROM client_bolo_lines
                    WHERE bolo_profile_id = :profile_id
                    ORDER BY sort_order ASC, id ASC';
        $lineStmt = $pdo->prepare($lineSql);
        $lineStmt->execute(['profile_id' => $profileId]);
        $lines = $lineStmt->fetchAll();
        if (!is_array($lines)) {
            $lines = [];
        }

        /** @var list<array<string, mixed>> $lineList */
        $lineList = [];
        foreach ($lines as $row) {
            if (is_array($row)) {
                $lineList[] = $row;
            }
        }

        return [
            'profile' => $profile,
            'lines' => $lineList,
        ];
    }

    /**
     * @param list<string> $itemLines Non-empty trimmed lines become rows; empty list with empty notes deletes the profile.
     */
    public static function save(int $businessId, int $clientId, string $notes, array $itemLines): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $notes = trim($notes);
        $cleanLines = [];
        foreach ($itemLines as $line) {
            $t = trim((string) $line);
            if ($t !== '') {
                $cleanLines[] = $t;
            }
        }

        $pdo = Database::connection();

        if ($notes === '' && $cleanLines === []) {
            self::deleteForClient($businessId, $clientId);

            return;
        }

        $pdo->beginTransaction();
        try {
            $existingId = self::profileIdForClient($pdo, $businessId, $clientId);

            if ($existingId === null) {
                if (self::hasActiveFlag()) {
                    $ins = $pdo->prepare(
                        'INSERT INTO client_bolo_profiles (business_id, client_id, notes, is_active, created_at, updated_at)
                         VALUES (:business_id, :client_id, :notes, 1, NOW(), NOW())'
                    );
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO client_bolo_profiles (business_id, client_id, notes, created_at, updated_at)
                         VALUES (:business_id, :client_id, :notes, NOW(), NOW())'
                    );
                }
                $ins->execute([
                    'business_id' => $businessId,
                    'client_id' => $clientId,
                    'notes' => $notes === '' ? null : $notes,
                ]);
                $existingId = (int) $pdo->lastInsertId();
            } else {
                if (self::hasActiveFlag()) {
                    $upd = $pdo->prepare(
                        'UPDATE client_bolo_profiles
                         SET notes = :notes, is_active = 1, updated_at = NOW()
                         WHERE id = :id
                           AND business_id = :business_id
                           AND client_id = :client_id'
                    );
                } else {
                    $upd = $pdo->prepare(
                        'UPDATE client_bolo_profiles
                         SET notes = :notes, updated_at = NOW()
                         WHERE id = :id
                           AND business_id = :business_id
                           AND client_id = :client_id'
                    );
                }
                $upd->execute([
                    'notes' => $notes === '' ? null : $notes,
                    'id' => $existingId,
                    'business_id' => $businessId,
                    'client_id' => $clientId,
                ]);
            }

            if ($existingId <= 0) {
                $pdo->rollBack();

                return;
            }

            $del = $pdo->prepare('DELETE FROM client_bolo_lines WHERE bolo_profile_id = :pid');
            $del->execute(['pid' => $existingId]);

            $insLine = $pdo->prepare(
                'INSERT INTO client_bolo_lines (bolo_profile_id, sort_order, item_text)
                 VALUES (:bolo_profile_id, :sort_order, :item_text)'
            );
            foreach ($cleanLines as $i => $text) {
                if (function_exists('mb_substr')) {
                    $text = mb_substr($text, 0, 500);
                } else {
                    $text = substr($text, 0, 500);
                }
                $insLine->execute([
                    'bolo_profile_id' => $existingId,
                    'sort_order' => $i,
                    'item_text' => $text,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function setProfileActive(int $businessId, int $clientId, bool $active): bool
    {
        if (!self::isAvailable() || !self::hasActiveFlag()) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE client_bolo_profiles
             SET is_active = :is_active, updated_at = NOW()
             WHERE business_id = :business_id
               AND client_id = :client_id'
        );

        return $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'is_active' => $active ? 1 : 0,
        ]);
    }

    public static function deleteForClient(int $businessId, int $clientId): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'DELETE FROM client_bolo_profiles
             WHERE business_id = :business_id
               AND client_id = :client_id'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
        ]);
    }

    private static function profileIdForClient(\PDO $pdo, int $businessId, int $clientId): ?int
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM client_bolo_profiles
             WHERE business_id = :business_id AND client_id = :client_id
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
