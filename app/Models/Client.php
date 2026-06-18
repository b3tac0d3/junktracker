<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Client
{
    /**
     * Matches directory row logic: inactive if status is "inactive" or is_active = 0 when present.
     */
    private static function activeFilterWhereSql(string $filter): string
    {
        $filter = strtolower(trim($filter));
        if (!in_array($filter, ['active', 'inactive', 'all'], true)) {
            $filter = 'active';
        }
        if ($filter === 'all') {
            return '';
        }

        $inactiveOr = [];
        if (self::hasColumn('clients', 'is_active')) {
            $inactiveOr[] = 'c.is_active = 0';
        }
        if (self::hasColumn('clients', 'status')) {
            $inactiveOr[] = "LOWER(TRIM(COALESCE(c.status, ''))) = 'inactive'";
        }

        if ($inactiveOr === []) {
            return $filter === 'inactive' ? ' AND 1 = 0' : '';
        }

        $inactiveExpr = '(' . implode(' OR ', $inactiveOr) . ')';

        return $filter === 'inactive'
            ? ' AND ' . $inactiveExpr
            : ' AND NOT (' . $inactiveExpr . ')';
    }

    public static function indexList(
        int $businessId,
        string $search = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'name',
        string $sortDir = 'asc',
        string $activeFilter = 'active'
    ): array {
        $pdo = Database::connection();
        $query = trim($search);

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $statusSql = self::hasColumn('clients', 'status') ? 'c.status' : "'active'";
        $activeSql = self::hasColumn('clients', 'is_active') ? 'c.is_active' : '1';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';
        $activeWhere = self::activeFilterWhereSql($activeFilter);

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'desc' ? 'DESC' : 'ASC';
        $sortNameExpr = "COALESCE(NULLIF(c.last_name, ''), {$companySql}, c.first_name, '')";
        $sortMap = [
            'name' => "{$sortNameExpr} {$sortDir}, c.id {$sortDir}",
            'id' => "c.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['name'];

        $boloMatch = self::boloSearchExistsSql();

        $sql = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    {$phoneSql} AS phone,
                    {$citySql} AS city,
                    {$statusSql} AS status,
                    {$activeSql} AS is_active
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  {$activeWhere}
                  AND (
                    :query = ''
                    OR CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                    {$boloMatch}
                  )
                ORDER BY {$orderBy}
                LIMIT :row_limit
                OFFSET :row_offset";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        if ($boloMatch !== '') {
            $stmt->bindValue(':query_like_4', $queryLike);
            $stmt->bindValue(':query_like_5', $queryLike);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        if ($query !== '') {
            $rows = self::attachSearchMatchTypes($businessId, $query, $rows);
        }

        return $rows;
    }

    public static function indexCount(int $businessId, string $search = '', string $activeFilter = 'active'): int
    {
        $pdo = Database::connection();
        $query = trim($search);

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';
        $activeWhere = self::activeFilterWhereSql($activeFilter);

        $boloMatch = self::boloSearchExistsSql();

        $sql = "SELECT COUNT(*)
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  {$activeWhere}
                  AND (
                    :query = ''
                    OR CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                    {$boloMatch}
                  )";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        if ($boloMatch !== '') {
            $stmt->bindValue(':query_like_4', $queryLike);
            $stmt->bindValue(':query_like_5', $queryLike);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Extra OR-clause for client directory / global search: match active BOLO profile notes or line items.
     * Uses unique placeholders :query_like_4 and :query_like_5 (native PDO cannot repeat names).
     */
    private static function boloSearchExistsSql(): string
    {
        if (!self::hasTable('client_bolo_profiles') || !self::hasTable('client_bolo_lines')) {
            return '';
        }

        $activeClause = self::hasColumn('client_bolo_profiles', 'is_active')
            ? 'p.is_active = 1'
            : '1 = 1';

        return " OR EXISTS (
            SELECT 1
            FROM client_bolo_profiles p
            WHERE p.client_id = c.id
              AND p.business_id = c.business_id
              AND {$activeClause}
              AND (
                  COALESCE(p.notes, '') LIKE :query_like_4
                  OR EXISTS (
                      SELECT 1 FROM client_bolo_lines l
                      WHERE l.bolo_profile_id = p.id
                        AND l.item_text LIKE :query_like_5
                  )
              )
        )";
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function attachSearchMatchTypes(int $businessId, string $query, array $rows): array
    {
        $query = trim($query);
        if ($query === '' || $rows === []) {
            return $rows;
        }

        $clientIds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clientId = (int) ($row['id'] ?? 0);
            if ($clientId > 0) {
                $clientIds[] = $clientId;
            }
        }

        $boloTypes = self::boloSearchMatchTypesByClientIds($businessId, $query, $clientIds);
        $queryLower = mb_strtolower($query);

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $types = [];
            $nameHaystack = mb_strtolower(trim(
                trim((string) ($row['first_name'] ?? '')) . ' '
                . trim((string) ($row['last_name'] ?? '')) . ' '
                . trim((string) ($row['company_name'] ?? ''))
            ));
            if ($nameHaystack !== '' && mb_strpos($nameHaystack, $queryLower) !== false) {
                $types[] = 'Name';
            }

            $phone = mb_strtolower(trim((string) ($row['phone'] ?? '')));
            if ($phone !== '' && mb_strpos($phone, $queryLower) !== false) {
                $types[] = 'Phone';
            }

            $city = mb_strtolower(trim((string) ($row['city'] ?? '')));
            if ($city !== '' && mb_strpos($city, $queryLower) !== false) {
                $types[] = 'City';
            }

            $clientId = (int) ($row['id'] ?? 0);
            if ($clientId > 0 && isset($boloTypes[$clientId])) {
                foreach ($boloTypes[$clientId] as $boloType) {
                    $types[] = $boloType;
                }
            }

            $row['search_match_types'] = array_values(array_unique($types));
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<int> $clientIds
     * @return array<int, list<string>>
     */
    private static function boloSearchMatchTypesByClientIds(int $businessId, string $query, array $clientIds): array
    {
        if (!self::hasTable('client_bolo_profiles') || !self::hasTable('client_bolo_lines')) {
            return [];
        }

        $clientIds = array_values(array_unique(array_filter(array_map('intval', $clientIds), static fn (int $id): bool => $id > 0)));
        if ($businessId <= 0 || trim($query) === '' || $clientIds === []) {
            return [];
        }

        $activeClause = self::hasColumn('client_bolo_profiles', 'is_active')
            ? 'p.is_active = 1'
            : '1 = 1';

        $inPlaceholders = [];
        $params = [
            'business_id' => $businessId,
            'q_like' => '%' . $query . '%',
        ];
        foreach ($clientIds as $index => $clientId) {
            $key = 'client_id_' . (string) $index;
            $inPlaceholders[] = ':' . $key;
            $params[$key] = $clientId;
        }
        $inSql = implode(', ', $inPlaceholders);

        $map = [];

        $noteSql = "SELECT DISTINCT p.client_id
                    FROM client_bolo_profiles p
                    WHERE p.business_id = :business_id
                      AND p.client_id IN ({$inSql})
                      AND {$activeClause}
                      AND COALESCE(p.notes, '') LIKE :q_like";
        $stmt = Database::connection()->prepare($noteSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        $noteRows = $stmt->fetchAll();
        if (is_array($noteRows)) {
            foreach ($noteRows as $noteRow) {
                if (!is_array($noteRow)) {
                    continue;
                }
                $clientId = (int) ($noteRow['client_id'] ?? 0);
                if ($clientId > 0) {
                    $map[$clientId][] = 'BOLO note';
                }
            }
        }

        $lineSql = "SELECT DISTINCT p.client_id
                    FROM client_bolo_lines l
                    INNER JOIN client_bolo_profiles p ON p.id = l.bolo_profile_id
                    WHERE p.business_id = :business_id
                      AND p.client_id IN ({$inSql})
                      AND {$activeClause}
                      AND l.item_text LIKE :q_like";
        $stmt = Database::connection()->prepare($lineSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        $lineRows = $stmt->fetchAll();
        if (is_array($lineRows)) {
            foreach ($lineRows as $lineRow) {
                if (!is_array($lineRow)) {
                    continue;
                }
                $clientId = (int) ($lineRow['client_id'] ?? 0);
                if ($clientId > 0) {
                    $map[$clientId][] = 'Line item';
                }
            }
        }

        foreach ($map as $clientId => $types) {
            $map[$clientId] = array_values(array_unique($types));
        }

        return $map;
    }

    public static function searchOptions(int $businessId, string $query, int $limit = 8, int $excludeClientId = 0): array
    {
        $pdo = Database::connection();
        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $addressLine1Sql = self::hasColumn('clients', 'address_line1') ? 'c.address_line1' : 'NULL';
        $addressLine2Sql = self::hasColumn('clients', 'address_line2') ? 'c.address_line2' : 'NULL';
        $stateSql = self::hasColumn('clients', 'state') ? 'c.state' : 'NULL';
        $postalCodeSql = self::hasColumn('clients', 'postal_code') ? 'c.postal_code' : 'NULL';
        $statusSql = self::hasColumn('clients', 'status') ? 'c.status' : "'active'";
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';
        $excludeSql = ($excludeClientId > 0) ? ' AND c.id <> :exclude_client_id' : '';

        $sql = "SELECT
                    c.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id)) AS name,
                    COALESCE({$phoneSql}, '') AS phone,
                    COALESCE({$companySql}, '') AS company_name,
                    COALESCE({$citySql}, '') AS city,
                    COALESCE({$addressLine1Sql}, '') AS address_line1,
                    COALESCE({$addressLine2Sql}, '') AS address_line2,
                    COALESCE({$stateSql}, '') AS state,
                    COALESCE({$postalCodeSql}, '') AS postal_code,
                    COALESCE({$statusSql}, 'active') AS status
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  {$excludeSql}
                  AND (
                    CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                    OR CAST(c.id AS CHAR) LIKE :query_like_4
                  )
                ORDER BY
                    COALESCE(NULLIF(c.last_name, ''), {$companySql}, c.first_name, '') ASC,
                    COALESCE(NULLIF(c.first_name, ''), '') ASC,
                    c.id DESC
                LIMIT :row_limit";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $needle . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($excludeClientId > 0) {
            $stmt->bindValue(':exclude_client_id', $excludeClientId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 50)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Clients this person referred (same business).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function referralsSentBy(int $businessId, int $referrerClientId, int $limit = 100): array
    {
        if (!self::hasColumn('clients', 'referred_by_client_id') || $referrerClientId <= 0) {
            return [];
        }

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    COALESCE({$phoneSql}, '') AS phone,
                    COALESCE({$citySql}, '') AS city
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND c.referred_by_client_id = :referrer_id
                ORDER BY c.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':referrer_id', $referrerClientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $clientId): ?array
    {
        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $emailSql = self::hasColumn('clients', 'email') ? 'c.email' : 'NULL';
        $newsletterSubSql = self::hasColumn('clients', 'newsletter_subscribed') ? 'c.newsletter_subscribed' : '0';
        $newsletterTokenSql = self::hasColumn('clients', 'newsletter_unsubscribe_token') ? 'c.newsletter_unsubscribe_token' : 'NULL';
        $secondaryPhoneColumn = self::secondaryPhoneColumn();
        $secondaryPhoneSql = $secondaryPhoneColumn !== null ? 'c.' . $secondaryPhoneColumn : 'NULL';
        $canTextColumn = self::primaryCanTextColumn();
        $canTextSql = $canTextColumn !== null ? 'c.' . $canTextColumn : 'NULL';
        $secondaryCanTextColumn = self::secondaryCanTextColumn();
        $secondaryCanTextSql = $secondaryCanTextColumn !== null ? 'c.' . $secondaryCanTextColumn : 'NULL';
        $primaryNoteSql = self::hasColumn('clients', 'primary_note')
            ? 'c.primary_note'
            : (self::hasColumn('clients', 'notes')
                ? 'c.notes'
                : (self::hasColumn('clients', 'note') ? 'c.note' : 'NULL'));
        $addressLine1Sql = self::hasColumn('clients', 'address_line1') ? 'c.address_line1' : 'NULL';
        $addressLine2Sql = self::hasColumn('clients', 'address_line2') ? 'c.address_line2' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $stateSql = self::hasColumn('clients', 'state') ? 'c.state' : 'NULL';
        $postalCodeSql = self::hasColumn('clients', 'postal_code') ? 'c.postal_code' : 'NULL';
        $statusSql = self::hasColumn('clients', 'status') ? 'c.status' : "'active'";
        $activeSql = self::hasColumn('clients', 'is_active') ? 'c.is_active' : '1';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $refJoin = '';
        $refSelect = '';
        if (self::hasColumn('clients', 'referred_by_client_id')) {
            $refDeleted = self::hasColumn('clients', 'deleted_at') ? 'ref.deleted_at IS NULL' : '1=1';
            $refJoin = ' LEFT JOIN clients ref ON ref.id = c.referred_by_client_id AND ref.business_id = c.business_id AND ' . $refDeleted;
            $refCompanyRef = self::hasColumn('clients', 'company_name') ? 'ref.company_name' : 'NULL';
            $refSelect = ',
                    c.referred_by_client_id AS referred_by_client_id,
                    ref.first_name AS referrer_first_name,
                    ref.last_name AS referrer_last_name,
                    ' . $refCompanyRef . ' AS referrer_company_name';
        }

        $sql = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    {$emailSql} AS email,
                    {$newsletterSubSql} AS newsletter_subscribed,
                    {$newsletterTokenSql} AS newsletter_unsubscribe_token,
                    {$phoneSql} AS phone,
                    {$secondaryPhoneSql} AS secondary_phone,
                    {$canTextSql} AS can_text,
                    {$secondaryCanTextSql} AS secondary_can_text,
                    {$primaryNoteSql} AS primary_note,
                    {$addressLine1Sql} AS address_line1,
                    {$addressLine2Sql} AS address_line2,
                    {$citySql} AS city,
                    {$stateSql} AS state,
                    {$postalCodeSql} AS postal_code,
                    {$statusSql} AS status,
                    {$activeSql} AS is_active
                    {$refSelect}
                FROM clients c
                {$refJoin}
                WHERE {$businessWhere}
                  AND c.id = :client_id
                  AND {$deletedWhere}
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function displayName(array $client): string
    {
        $name = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        $company = trim((string) ($client['company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        return 'Client #' . (string) ((int) ($client['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array{first_name: string, last_name: string, company_name: string, phone_digits: string, email: string}
     */
    private static function normalizedDuplicateCandidateFields(array $candidate): array
    {
        $firstName = self::normalizeIdentity((string) ($candidate['first_name'] ?? ''));
        $lastName = self::normalizeIdentity((string) ($candidate['last_name'] ?? ''));
        $companyName = self::normalizeIdentity((string) ($candidate['company_name'] ?? ''));
        $phoneDigits = self::normalizePhone((string) ($candidate['phone'] ?? ''));
        if ($phoneDigits !== '' && strlen($phoneDigits) < 7) {
            $phoneDigits = '';
        }
        $emailNorm = strtolower(trim((string) ($candidate['email'] ?? '')));
        if ($emailNorm !== '' && filter_var($emailNorm, FILTER_VALIDATE_EMAIL) === false) {
            $emailNorm = '';
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'phone_digits' => $phoneDigits,
            'email' => $emailNorm,
        ];
    }

    /**
     * Possible duplicate rows for the client form (name, company, phone, email).
     *
     * @return list<array{id: int, display_name: string, reasons: list<string>}>
     */
    public static function findDuplicateMatches(int $businessId, array $candidate, ?int $excludeClientId = null): array
    {
        if (!self::hasTable('clients')) {
            return [];
        }

        $n = self::normalizedDuplicateCandidateFields($candidate);
        $firstName = $n['first_name'];
        $lastName = $n['last_name'];
        $companyName = $n['company_name'];
        $phoneDigits = $n['phone_digits'];
        $emailNorm = $n['email'];

        $or = [];
        $params = [];

        if ($firstName !== '' && $lastName !== '') {
            $or[] = '(LOWER(TRIM(COALESCE(c.first_name, \'\'))) = :dup_first_name AND LOWER(TRIM(COALESCE(c.last_name, \'\'))) = :dup_last_name)';
            $params['dup_first_name'] = $firstName;
            $params['dup_last_name'] = $lastName;
        }

        if ($companyName !== '' && self::hasColumn('clients', 'company_name')) {
            $or[] = "LOWER(TRIM(COALESCE(c.company_name, ''))) = :dup_company_name";
            $params['dup_company_name'] = $companyName;
        }

        if ($phoneDigits !== '' && self::hasColumn('clients', 'phone')) {
            $digitsExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '-', ''), '(', ''), ')', ''), ' ', ''), '.', ''), '+', '')";
            $primaryExpr = sprintf($digitsExpr, 'c.phone');
            // Native PDO (EMULATE_PREPARES false) does not allow the same named placeholder twice; use distinct names.
            $phoneOr = '(' . $primaryExpr . ' = :dup_phone_primary';
            $params['dup_phone_primary'] = $phoneDigits;
            if (self::hasColumn('clients', 'secondary_phone')) {
                $secondaryExpr = sprintf($digitsExpr, 'c.secondary_phone');
                $phoneOr .= ' OR ' . $secondaryExpr . ' = :dup_phone_secondary';
                $params['dup_phone_secondary'] = $phoneDigits;
            }
            $phoneOr .= ')';
            $or[] = $phoneOr;
        }

        if ($emailNorm !== '' && self::hasColumn('clients', 'email')) {
            $or[] = "LOWER(TRIM(COALESCE(c.email, ''))) = :dup_email";
            $params['dup_email'] = $emailNorm;
        }

        if ($or === []) {
            return [];
        }

        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';
        $excludeSql = ($excludeClientId !== null && $excludeClientId > 0) ? ' AND c.id <> :exclude_id' : '';

        $sql = 'SELECT DISTINCT c.id
                FROM clients c
                WHERE ' . $businessWhere . '
                  AND ' . $deletedWhere . $excludeSql . '
                  AND (' . implode(' OR ', $or) . ')
                ORDER BY c.id DESC
                LIMIT 50';

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($excludeClientId !== null && $excludeClientId > 0) {
            $stmt->bindValue(':exclude_id', $excludeClientId, \PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $existing = self::findForBusiness($businessId, $id);
            if ($existing === null) {
                continue;
            }

            $reasons = self::duplicateReasonsAgainstCandidate($candidate, $existing);
            if ($reasons === []) {
                continue;
            }

            $out[] = [
                'id' => $id,
                'display_name' => self::displayName($existing),
                'reasons' => $reasons,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $existing
     * @return list<string>
     */
    private static function duplicateReasonsAgainstCandidate(array $candidate, array $existing): array
    {
        $n = self::normalizedDuplicateCandidateFields($candidate);
        $firstName = $n['first_name'];
        $lastName = $n['last_name'];
        $companyName = $n['company_name'];
        $phoneDigits = $n['phone_digits'];
        $emailNorm = $n['email'];

        $existingFirst = self::normalizeIdentity((string) ($existing['first_name'] ?? ''));
        $existingLast = self::normalizeIdentity((string) ($existing['last_name'] ?? ''));
        $existingCompany = self::normalizeIdentity((string) ($existing['company_name'] ?? ''));
        $existingPrimary = self::normalizePhone((string) ($existing['phone'] ?? ''));
        $existingSecondary = self::normalizePhone((string) ($existing['secondary_phone'] ?? ''));
        $existingEmail = strtolower(trim((string) ($existing['email'] ?? '')));

        $reasons = [];

        if ($firstName !== '' && $lastName !== '' && $existingFirst === $firstName && $existingLast === $lastName) {
            $reasons[] = 'name';
        }

        if ($companyName !== '' && $existingCompany !== '' && $existingCompany === $companyName) {
            $reasons[] = 'company';
        }

        if ($phoneDigits !== '' && (($existingPrimary !== '' && $existingPrimary === $phoneDigits) || ($existingSecondary !== '' && $existingSecondary === $phoneDigits))) {
            $reasons[] = 'phone';
        }

        if ($emailNorm !== '' && $existingEmail !== '' && $emailNorm === $existingEmail) {
            $reasons[] = 'email';
        }

        return $reasons;
    }

    public static function findPotentialDuplicate(int $businessId, array $candidate): ?array
    {
        if (!self::hasTable('clients')) {
            return null;
        }

        $n = self::normalizedDuplicateCandidateFields($candidate);
        $firstName = $n['first_name'];
        $lastName = $n['last_name'];
        $companyName = $n['company_name'];
        $phoneDigits = $n['phone_digits'];
        $emailNorm = $n['email'];

        $or = [];
        $params = [];

        if ($firstName !== '' && $lastName !== '') {
            $or[] = '(LOWER(TRIM(COALESCE(c.first_name, \'\'))) = :dup_first_name AND LOWER(TRIM(COALESCE(c.last_name, \'\'))) = :dup_last_name)';
            $params['dup_first_name'] = $firstName;
            $params['dup_last_name'] = $lastName;
        }

        if ($companyName !== '' && self::hasColumn('clients', 'company_name')) {
            $or[] = "LOWER(TRIM(COALESCE(c.company_name, ''))) = :dup_company_name";
            $params['dup_company_name'] = $companyName;
        }

        if ($phoneDigits !== '' && self::hasColumn('clients', 'phone')) {
            $digitsExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '-', ''), '(', ''), ')', ''), ' ', ''), '.', ''), '+', '')";
            $primaryExpr = sprintf($digitsExpr, 'c.phone');
            $phoneOr = '(' . $primaryExpr . ' = :dup_phone_primary';
            $params['dup_phone_primary'] = $phoneDigits;
            if (self::hasColumn('clients', 'secondary_phone')) {
                $secondaryExpr = sprintf($digitsExpr, 'c.secondary_phone');
                $phoneOr .= ' OR ' . $secondaryExpr . ' = :dup_phone_secondary';
                $params['dup_phone_secondary'] = $phoneDigits;
            }
            $phoneOr .= ')';
            $or[] = $phoneOr;
        }

        if ($emailNorm !== '' && self::hasColumn('clients', 'email')) {
            $or[] = "LOWER(TRIM(COALESCE(c.email, ''))) = :dup_email";
            $params['dup_email'] = $emailNorm;
        }

        if ($or === []) {
            return null;
        }

        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = 'SELECT c.id
                FROM clients c
                WHERE ' . $businessWhere . '
                  AND ' . $deletedWhere . '
                  AND (' . implode(' OR ', $or) . ')
                ORDER BY c.id DESC
                LIMIT 25';

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $existing = self::findForBusiness($businessId, $id);
            if ($existing === null) {
                continue;
            }

            $existingFirst = self::normalizeIdentity((string) ($existing['first_name'] ?? ''));
            $existingLast = self::normalizeIdentity((string) ($existing['last_name'] ?? ''));
            $existingCompany = self::normalizeIdentity((string) ($existing['company_name'] ?? ''));
            $existingPrimary = self::normalizePhone((string) ($existing['phone'] ?? ''));
            $existingSecondary = self::normalizePhone((string) ($existing['secondary_phone'] ?? ''));
            $existingEmail = strtolower(trim((string) ($existing['email'] ?? '')));

            $nameMatch = ($firstName !== '' && $lastName !== '' && $existingFirst === $firstName && $existingLast === $lastName);
            $companyMatch = ($companyName !== '' && $existingCompany !== '' && $existingCompany === $companyName);
            $phoneMatch = ($phoneDigits !== '' && (($existingPrimary !== '' && $existingPrimary === $phoneDigits) || ($existingSecondary !== '' && $existingSecondary === $phoneDigits)));
            $emailMatch = ($emailNorm !== '' && $existingEmail !== '' && $emailNorm === $existingEmail);

            if ($emailMatch) {
                return $existing;
            }

            if (($nameMatch || $companyMatch) && ($phoneDigits === '' || $phoneMatch)) {
                return $existing;
            }

            if (!$nameMatch && !$companyMatch && $phoneMatch) {
                return $existing;
            }
        }

        return null;
    }

    public static function financialSummary(int $businessId, int $clientId): array
    {
        $gross = 0.0;
        $expenses = 0.0;
        $salesGross = 0.0;
        $salesNet = 0.0;
        $purchaseSpend = 0.0;

        if (self::hasTable('invoices')) {
            $invoiceTotalExpr = self::hasColumn('invoices', 'total')
                ? 'i.total'
                : (self::hasColumn('invoices', 'subtotal')
                    ? 'i.subtotal'
                    : (self::hasColumn('invoices', 'amount') ? 'i.amount' : '0'));

            $invoiceWhere = ['i.business_id = :business_id', 'i.client_id = :client_id'];
            if (self::hasColumn('invoices', 'deleted_at')) {
                $invoiceWhere[] = 'i.deleted_at IS NULL';
            }
            if (self::hasColumn('invoices', 'type')) {
                $invoiceWhere[] = "(i.type = 'invoice' OR i.type IS NULL)";
            }
            if (self::hasColumn('invoices', 'status')) {
                $invoiceWhere[] = "(i.status IS NULL OR i.status <> 'cancelled')";
            }

            $sql = 'SELECT COALESCE(SUM(' . $invoiceTotalExpr . '), 0) FROM invoices i WHERE ' . implode(' AND ', $invoiceWhere);
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'business_id' => $businessId,
                'client_id' => $clientId,
            ]);
            $gross = (float) $stmt->fetchColumn();
        }

        if (self::hasTable('expenses') && self::hasColumn('expenses', 'amount')) {
            $deletedCondition = self::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
            $hasExpenseBusinessId = self::hasColumn('expenses', 'business_id');
            $businessCondition = $hasExpenseBusinessId ? 'e.business_id = :expenses_business_id' : '1=1';

            if (self::hasColumn('expenses', 'client_id')) {
                $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                        FROM expenses e
                        WHERE ' . $businessCondition . '
                          AND e.client_id = :client_id
                          AND ' . $deletedCondition;

                $stmt = Database::connection()->prepare($sql);
                $params = ['client_id' => $clientId];
                if ($hasExpenseBusinessId) {
                    $params['expenses_business_id'] = $businessId;
                }
                $stmt->execute($params);
                $expenses = (float) $stmt->fetchColumn();
            } elseif (self::hasColumn('expenses', 'job_id') && self::hasTable('jobs')) {
                $hasJobBusinessId = self::hasColumn('jobs', 'business_id');
                $jobBusinessCondition = $hasJobBusinessId ? 'j.business_id = :jobs_business_id' : '1=1';
                $jobDeletedCondition = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';

                $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                        FROM expenses e
                        INNER JOIN jobs j ON j.id = e.job_id
                        WHERE ' . $businessCondition . '
                          AND ' . $deletedCondition . '
                          AND j.client_id = :client_id
                          AND ' . $jobBusinessCondition . '
                          AND ' . $jobDeletedCondition;

                $stmt = Database::connection()->prepare($sql);
                $params = ['client_id' => $clientId];
                if ($hasExpenseBusinessId) {
                    $params['expenses_business_id'] = $businessId;
                }
                if ($hasJobBusinessId) {
                    $params['jobs_business_id'] = $businessId;
                }
                $stmt->execute($params);
                $expenses = (float) $stmt->fetchColumn();
            }
        }

        $salesTotals = Sale::salesTotalsByClient($businessId, $clientId);
        $salesGross = (float) ($salesTotals['gross'] ?? 0);
        $salesNet = (float) ($salesTotals['net'] ?? 0);
        $salesCount = (int) ($salesTotals['count'] ?? 0);

        $purchaseTotals = Purchase::totalsByClient($businessId, $clientId);
        $purchaseSpend = (float) ($purchaseTotals['total_purchase_price'] ?? 0);
        $purchaseCount = (int) ($purchaseTotals['count'] ?? 0);

        $labor = Job::laborCostByClient($businessId, $clientId);
        $serviceNet = $gross - $expenses - $labor;

        return [
            'service_gross' => $gross,
            'service_expenses' => $expenses,
            'service_labor' => $labor,
            'service_net' => $serviceNet,
            'sales_gross' => $salesGross,
            'sales_net' => $salesNet,
            'sales_count' => $salesCount,
            'purchase_spend' => $purchaseSpend,
            'purchase_count' => $purchaseCount,
            'gross_income' => $gross,
            'expenses' => $expenses,
            'net_income' => $serviceNet,
        ];
    }

    public static function jobsByStatus(int $businessId, int $clientId): array
    {
        $summary = [
            'prospect' => 0,
            'pending' => 0,
            'active' => 0,
            'complete' => 0,
            'cancelled' => 0,
        ];

        if (!self::hasTable('jobs')) {
            return $summary;
        }

        $businessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1 = 1';
        $statusExpr = self::hasColumn('jobs', 'status') ? 'LOWER(j.status)' : "'pending'";

        $sql = "SELECT {$statusExpr} AS status_key, COUNT(*) AS total
                FROM jobs j
                WHERE {$businessWhere}
                  AND j.client_id = :client_id
                  AND {$deletedWhere}
                GROUP BY {$statusExpr}";

        $stmt = Database::connection()->prepare($sql);
        $params = ['client_id' => $clientId];
        if (self::hasColumn('jobs', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower((string) ($row['status_key'] ?? ''));
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    public static function jobHistory(int $businessId, int $clientId, int $limit = 50): array
    {
        if (!self::hasTable('jobs')) {
            return [];
        }

        $titleSql = self::hasColumn('jobs', 'title')
            ? 'j.title'
            : (self::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = self::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $startSql = self::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (self::hasColumn('jobs', 'start_date') ? 'j.start_date' : 'NULL');
        $endSql = self::hasColumn('jobs', 'scheduled_end_at')
            ? 'j.scheduled_end_at'
            : (self::hasColumn('jobs', 'end_date') ? 'j.end_date' : 'NULL');
        $businessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$statusSql} AS status,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at
                FROM jobs j
                WHERE {$businessWhere}
                  AND j.client_id = :client_id
                  AND {$deletedWhere}
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Recent scheduled items for search / quick context: jobs, calendar events, quote follow-ups, deliveries.
     *
     * @param list<int> $clientIds
     * @return array<int, list<array{at:string, kind:string, title:string, status:string, url:string}>>
     */
    public static function appointmentHistoryByClientIds(int $businessId, array $clientIds, int $limitPerClient = 5): array
    {
        if ($businessId <= 0) {
            return [];
        }

        $clientIds = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $clientIds
        ), static fn (int $id): bool => $id > 0)));

        if ($clientIds === []) {
            return [];
        }

        $limitPerClient = max(1, min($limitPerClient, 20));
        $buckets = [];
        foreach ($clientIds as $clientId) {
            $buckets[$clientId] = [];
        }

        $inSql = [];
        $inParams = [];
        foreach ($clientIds as $i => $clientId) {
            $key = 'client_id_' . $i;
            $inSql[] = ':' . $key;
            $inParams[$key] = $clientId;
        }
        $inClause = implode(', ', $inSql);

        $appendRows = static function (array $rows, string $kind, callable $urlBuilder) use (&$buckets): void {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clientId = (int) ($row['client_id'] ?? 0);
                if ($clientId <= 0 || !array_key_exists($clientId, $buckets)) {
                    continue;
                }
                $at = trim((string) ($row['at'] ?? ''));
                if ($at === '') {
                    continue;
                }
                $recordId = (int) ($row['record_id'] ?? 0);
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '' && $recordId > 0) {
                    $title = ucfirst($kind) . ' #' . (string) $recordId;
                }
                $buckets[$clientId][] = [
                    'at' => $at,
                    'kind' => self::appointmentKindLabel($kind, (string) ($row['subtype'] ?? '')),
                    'title' => $title,
                    'status' => strtolower(trim((string) ($row['status'] ?? ''))),
                    'url' => $urlBuilder($row),
                ];
            }
        };

        if (self::hasTable('jobs') && self::hasColumn('jobs', 'client_id')) {
            $startSql = self::hasColumn('jobs', 'scheduled_start_at')
                ? 'j.scheduled_start_at'
                : (self::hasColumn('jobs', 'start_date') ? 'j.start_date' : null);
            if ($startSql !== null) {
                $titleSql = self::hasColumn('jobs', 'title')
                    ? 'j.title'
                    : (self::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
                $statusSql = self::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
                $businessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1 = 1';
                $deletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';

                $sql = "SELECT
                            j.client_id,
                            j.id AS record_id,
                            {$titleSql} AS title,
                            {$startSql} AS at,
                            {$statusSql} AS status
                        FROM jobs j
                        WHERE {$businessWhere}
                          AND j.client_id IN ({$inClause})
                          {$deletedWhere}
                          AND {$startSql} IS NOT NULL
                        ORDER BY {$startSql} DESC, j.id DESC
                        LIMIT 500";

                $stmt = Database::connection()->prepare($sql);
                if (self::hasColumn('jobs', 'business_id')) {
                    $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
                }
                foreach ($inParams as $key => $value) {
                    $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
                }
                $stmt->execute();
                $jobRows = $stmt->fetchAll();
                $appendRows(
                    is_array($jobRows) ? $jobRows : [],
                    'job',
                    static fn (array $row): string => url('/jobs/' . (string) ((int) ($row['record_id'] ?? 0)))
                );
            }
        }

        if (self::hasTable('events')) {
            $businessWhere = self::hasColumn('events', 'business_id') ? 'e.business_id = :business_id' : '1 = 1';
            $deletedWhere = self::hasColumn('events', 'deleted_at') ? 'AND e.deleted_at IS NULL' : '';

            $sql = "SELECT
                        e.link_id AS client_id,
                        e.id AS record_id,
                        e.title,
                        e.start_at AS at,
                        e.status,
                        e.type AS subtype
                    FROM events e
                    WHERE {$businessWhere}
                      {$deletedWhere}
                      AND LOWER(COALESCE(e.link_type, '')) = 'client'
                      AND e.link_id IN ({$inClause})
                      AND e.start_at IS NOT NULL
                    ORDER BY e.start_at DESC, e.id DESC
                    LIMIT 500";

            $stmt = Database::connection()->prepare($sql);
            if (self::hasColumn('events', 'business_id')) {
                $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            }
            foreach ($inParams as $key => $value) {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $clientEventRows = $stmt->fetchAll();
            $appendRows(
                is_array($clientEventRows) ? $clientEventRows : [],
                'event',
                static fn (array $row): string => url('/events/' . (string) ((int) ($row['record_id'] ?? 0)))
            );

            if (self::hasTable('jobs') && self::hasColumn('jobs', 'client_id')) {
                $jobBusinessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :job_business_id' : '1 = 1';
                $jobDeletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';

                $sql = "SELECT
                            j.client_id,
                            e.id AS record_id,
                            e.title,
                            e.start_at AS at,
                            e.status,
                            e.type AS subtype
                        FROM events e
                        INNER JOIN jobs j ON j.id = e.link_id
                            AND LOWER(COALESCE(e.link_type, '')) = 'job'
                            {$jobDeletedWhere}
                        WHERE {$businessWhere}
                          {$deletedWhere}
                          AND {$jobBusinessWhere}
                          AND j.client_id IN ({$inClause})
                          AND e.start_at IS NOT NULL
                        ORDER BY e.start_at DESC, e.id DESC
                        LIMIT 500";

                $stmt = Database::connection()->prepare($sql);
                if (self::hasColumn('events', 'business_id')) {
                    $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
                }
                if (self::hasColumn('jobs', 'business_id')) {
                    $stmt->bindValue(':job_business_id', $businessId, \PDO::PARAM_INT);
                }
                foreach ($inParams as $key => $value) {
                    $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
                }
                $stmt->execute();
                $jobEventRows = $stmt->fetchAll();
                $appendRows(
                    is_array($jobEventRows) ? $jobEventRows : [],
                    'event',
                    static fn (array $row): string => url('/events/' . (string) ((int) ($row['record_id'] ?? 0)))
                );
            }
        }

        if (self::hasTable('quotes') && self::hasColumn('quotes', 'client_id') && self::hasColumn('quotes', 'next_follow_up_at')) {
            $businessWhere = self::hasColumn('quotes', 'business_id') ? 'q.business_id = :business_id' : '1 = 1';
            $deletedWhere = self::hasColumn('quotes', 'deleted_at') ? 'AND q.deleted_at IS NULL' : '';
            $titleSql = self::hasColumn('quotes', 'title') ? 'q.title' : "CONCAT('Quote #', q.id)";
            $statusSql = self::hasColumn('quotes', 'status') ? 'q.status' : "'new'";

            $sql = "SELECT
                        q.client_id,
                        q.id AS record_id,
                        {$titleSql} AS title,
                        q.next_follow_up_at AS at,
                        {$statusSql} AS status
                    FROM quotes q
                    WHERE {$businessWhere}
                      AND q.client_id IN ({$inClause})
                      {$deletedWhere}
                      AND q.next_follow_up_at IS NOT NULL
                    ORDER BY q.next_follow_up_at DESC, q.id DESC
                    LIMIT 500";

            $stmt = Database::connection()->prepare($sql);
            if (self::hasColumn('quotes', 'business_id')) {
                $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            }
            foreach ($inParams as $key => $value) {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $quoteRows = $stmt->fetchAll();
            $appendRows(
                is_array($quoteRows) ? $quoteRows : [],
                'quote',
                static fn (array $row): string => url('/quotes/' . (string) ((int) ($row['record_id'] ?? 0)))
            );
        }

        if (self::hasTable('client_deliveries') && self::hasColumn('client_deliveries', 'scheduled_at')) {
            $businessWhere = self::hasColumn('client_deliveries', 'business_id') ? 'd.business_id = :business_id' : '1 = 1';
            $deletedWhere = self::hasColumn('client_deliveries', 'deleted_at') ? 'AND d.deleted_at IS NULL' : '';
            $statusSql = self::hasColumn('client_deliveries', 'status') ? 'd.status' : "'scheduled'";

            $sql = "SELECT
                        d.client_id,
                        d.id AS record_id,
                        CONCAT('Delivery #', d.id) AS title,
                        d.scheduled_at AS at,
                        {$statusSql} AS status
                    FROM client_deliveries d
                    WHERE {$businessWhere}
                      AND d.client_id IN ({$inClause})
                      {$deletedWhere}
                      AND d.scheduled_at IS NOT NULL
                    ORDER BY d.scheduled_at DESC, d.id DESC
                    LIMIT 500";

            $stmt = Database::connection()->prepare($sql);
            if (self::hasColumn('client_deliveries', 'business_id')) {
                $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            }
            foreach ($inParams as $key => $value) {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $deliveryRows = $stmt->fetchAll();
            $appendRows(
                is_array($deliveryRows) ? $deliveryRows : [],
                'delivery',
                static fn (array $row): string => url('/deliveries/' . (string) ((int) ($row['record_id'] ?? 0)))
            );
        }

        if (self::hasTable('purchase_quotes') && self::hasColumn('purchase_quotes', 'client_id')) {
            $hasFollowUp = self::hasColumn('purchase_quotes', 'next_follow_up_at');
            $hasContactDate = self::hasColumn('purchase_quotes', 'contact_date');
            if ($hasFollowUp || $hasContactDate) {
                $atSql = $hasFollowUp && $hasContactDate
                    ? 'COALESCE(pq.next_follow_up_at, CONCAT(pq.contact_date, " 09:00:00"))'
                    : ($hasFollowUp ? 'pq.next_follow_up_at' : 'CONCAT(pq.contact_date, " 09:00:00")');
                $businessWhere = self::hasColumn('purchase_quotes', 'business_id') ? 'pq.business_id = :business_id' : '1 = 1';
                $deletedWhere = self::hasColumn('purchase_quotes', 'deleted_at') ? 'AND pq.deleted_at IS NULL' : '';
                $titleSql = self::hasColumn('purchase_quotes', 'title') ? 'pq.title' : "CONCAT('Purchase quote #', pq.id)";
                $statusSql = self::hasColumn('purchase_quotes', 'status') ? 'pq.status' : "'new'";

                $sql = "SELECT
                            pq.client_id,
                            pq.id AS record_id,
                            {$titleSql} AS title,
                            {$atSql} AS at,
                            {$statusSql} AS status
                        FROM purchase_quotes pq
                        WHERE {$businessWhere}
                          AND pq.client_id IN ({$inClause})
                          {$deletedWhere}
                          AND {$atSql} IS NOT NULL
                        ORDER BY {$atSql} DESC, pq.id DESC
                        LIMIT 500";

                $stmt = Database::connection()->prepare($sql);
                if (self::hasColumn('purchase_quotes', 'business_id')) {
                    $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
                }
                foreach ($inParams as $key => $value) {
                    $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
                }
                $stmt->execute();
                $purchaseQuoteRows = $stmt->fetchAll();
                $appendRows(
                    is_array($purchaseQuoteRows) ? $purchaseQuoteRows : [],
                    'purchase_quote',
                    static fn (array $row): string => url('/purchase-quotes/' . (string) ((int) ($row['record_id'] ?? 0)))
                );
            }
        }

        $out = [];
        foreach ($clientIds as $clientId) {
            $items = $buckets[$clientId] ?? [];
            usort($items, static function (array $a, array $b): int {
                return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
            });
            $out[$clientId] = array_slice($items, 0, $limitPerClient);
        }

        return $out;
    }

    private static function appointmentKindLabel(string $kind, string $subtype = ''): string
    {
        $kind = strtolower(trim($kind));
        if ($kind === 'event') {
            $subtype = strtolower(trim($subtype));
            return match ($subtype) {
                'appointment' => 'Appointment',
                'cancellation' => 'Cancellation',
                'reminder' => 'Reminder',
                'note' => 'Note',
                default => $subtype !== '' ? ucwords(str_replace('_', ' ', $subtype)) : 'Appointment',
            };
        }

        return match ($kind) {
            'job' => 'Job',
            'quote' => 'Quote follow-up',
            'purchase_quote' => 'Purchase quote',
            'delivery' => 'Delivery',
            default => ucwords(str_replace('_', ' ', $kind)),
        };
    }

    public static function salesHistory(int $businessId, int $clientId, int $limit = 50): array
    {
        return Sale::salesByClient($businessId, $clientId, $limit);
    }

    public static function purchaseHistory(int $businessId, int $clientId, int $limit = 50): array
    {
        return Purchase::listByClient($businessId, $clientId, $limit);
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $columns = ['business_id', 'first_name', 'last_name'];
        $values = [':business_id', ':first_name', ':last_name'];
        $params = [
            'business_id' => $businessId,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
        ];

        $optional = [
            'company_name',
            'email',
            'newsletter_subscribed',
            'newsletter_unsubscribe_token',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'client_type',
            'status',
            'primary_note',
            'notes',
        ];

        foreach ($optional as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            if (!self::hasColumn('clients', $column)) {
                continue;
            }

            $columns[] = $column;
            $values[] = ':' . $column;

            $value = $data[$column] ?? null;
            if (in_array($column, ['can_text', 'secondary_can_text', 'newsletter_subscribed'], true)) {
                $params[$column] = ((int) $value) === 1 ? 1 : 0;
            } else {
                $params[$column] = is_string($value) ? trim($value) : $value;
            }
        }

        if (array_key_exists('secondary_phone', $data)) {
            $secondaryPhoneColumn = self::secondaryPhoneColumn();
            if ($secondaryPhoneColumn !== null) {
                $columns[] = $secondaryPhoneColumn;
                $values[] = ':secondary_phone_value';
                $params['secondary_phone_value'] = trim((string) ($data['secondary_phone'] ?? ''));
            }
        }

        if (array_key_exists('can_text', $data)) {
            $canTextColumn = self::primaryCanTextColumn();
            if ($canTextColumn !== null) {
                $columns[] = $canTextColumn;
                $values[] = ':can_text_value';
                $params['can_text_value'] = ((int) ($data['can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('secondary_can_text', $data)) {
            $secondaryCanTextColumn = self::secondaryCanTextColumn();
            if ($secondaryCanTextColumn !== null) {
                $columns[] = $secondaryCanTextColumn;
                $values[] = ':secondary_can_text_value';
                $params['secondary_can_text_value'] = ((int) ($data['secondary_can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('referred_by_client_id', $data) && self::hasColumn('clients', 'referred_by_client_id')) {
            $rid = (int) ($data['referred_by_client_id'] ?? 0);
            $columns[] = 'referred_by_client_id';
            $values[] = ':referred_by_client_id';
            $params['referred_by_client_id'] = $rid > 0 ? $rid : null;
        }

        if (self::hasColumn('clients', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }
        if (self::hasColumn('clients', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $sql = 'INSERT INTO clients (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $clientId, array $data, int $actorUserId): bool
    {
        $sets = [
            'first_name = :first_name',
            'last_name = :last_name',
        ];
        $params = [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'business_id' => $businessId,
            'client_id' => $clientId,
        ];

        $optional = [
            'company_name',
            'email',
            'newsletter_subscribed',
            'newsletter_unsubscribe_token',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'client_type',
            'status',
            'primary_note',
            'notes',
        ];

        foreach ($optional as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            if (!self::hasColumn('clients', $column)) {
                continue;
            }

            $sets[] = $column . ' = :' . $column;
            $value = $data[$column] ?? null;
            if (in_array($column, ['can_text', 'secondary_can_text', 'newsletter_subscribed'], true)) {
                $params[$column] = ((int) $value) === 1 ? 1 : 0;
            } else {
                $params[$column] = is_string($value) ? trim($value) : $value;
            }
        }

        if (array_key_exists('secondary_phone', $data)) {
            $secondaryPhoneColumn = self::secondaryPhoneColumn();
            if ($secondaryPhoneColumn !== null) {
                $sets[] = $secondaryPhoneColumn . ' = :secondary_phone_value';
                $params['secondary_phone_value'] = trim((string) ($data['secondary_phone'] ?? ''));
            }
        }

        if (array_key_exists('can_text', $data)) {
            $canTextColumn = self::primaryCanTextColumn();
            if ($canTextColumn !== null) {
                $sets[] = $canTextColumn . ' = :can_text_value';
                $params['can_text_value'] = ((int) ($data['can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('secondary_can_text', $data)) {
            $secondaryCanTextColumn = self::secondaryCanTextColumn();
            if ($secondaryCanTextColumn !== null) {
                $sets[] = $secondaryCanTextColumn . ' = :secondary_can_text_value';
                $params['secondary_can_text_value'] = ((int) ($data['secondary_can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('referred_by_client_id', $data) && self::hasColumn('clients', 'referred_by_client_id')) {
            $rid = (int) ($data['referred_by_client_id'] ?? 0);
            $sets[] = 'referred_by_client_id = :referred_by_client_id';
            $params['referred_by_client_id'] = $rid > 0 ? $rid : null;
        }

        if (self::hasColumn('clients', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $sql = 'UPDATE clients
                SET ' . implode(', ', $sets) . '
                WHERE id = :client_id
                  AND business_id = :business_id' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function deactivate(int $businessId, int $clientId, int $actorUserId): bool
    {
        $sets = [];
        $params = [
            'business_id' => $businessId,
            'client_id' => $clientId,
        ];

        if (self::hasColumn('clients', 'is_active')) {
            $sets[] = 'is_active = 0';
        }
        if (self::hasColumn('clients', 'status')) {
            $sets[] = "status = 'inactive'";
        }
        if (self::hasColumn('clients', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($sets === []) {
            return false;
        }

        $businessWhere = self::hasColumn('clients', 'business_id') ? ' AND business_id = :business_id' : '';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? ' AND deleted_at IS NULL' : '';

        $sql = 'UPDATE clients
                SET ' . implode(', ', $sets) . '
                WHERE id = :client_id' . $businessWhere . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    private static function hasTable(string $table): bool
    {
        return SchemaInspector::hasTable($table);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return SchemaInspector::hasColumn($table, $column);
    }

    private static function secondaryPhoneColumn(): ?string
    {
        foreach (['secondary_phone', 'phone_secondary', 'alt_phone'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function primaryCanTextColumn(): ?string
    {
        foreach (['can_text', 'can_text_primary', 'phone_can_text'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function secondaryCanTextColumn(): ?string
    {
        foreach (['secondary_can_text', 'can_text_secondary', 'secondary_phone_can_text'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function normalizeIdentity(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    private static function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
