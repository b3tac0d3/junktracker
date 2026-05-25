<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class EventFeed
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function range(int $businessId, string $start, string $end, array $filters = []): array
    {
        $start = self::normalizeIsoDateTime($start);
        $end = self::normalizeIsoDateTime($end);

        if ($start === null || $end === null) {
            $start = date('Y-m-01 00:00:00');
            $end = date('Y-m-t 23:59:59');
        }

        $sources = self::parseCsv((string) ($filters['sources'] ?? ''));
        if ($sources === []) {
            $sources = ['tasks', 'jobs', 'events', 'deliveries', 'quotes', 'purchase_quotes', 'estate_sales'];
        }
        $types = self::parseCsv((string) ($filters['types'] ?? ''));
        $q = trim((string) ($filters['q'] ?? ''));

        $events = [];
        if (in_array('tasks', $sources, true)) {
            $events = array_merge($events, self::taskDueEvents($businessId, $start, $end, $q));
        }
        if (in_array('jobs', $sources, true)) {
            $events = array_merge($events, self::jobScheduleEvents($businessId, $start, $end, $q));
        }
        if (in_array('events', $sources, true)) {
            $events = array_merge($events, self::customEvents($businessId, $start, $end, $types, $q));
        }
        if (in_array('deliveries', $sources, true)) {
            $events = array_merge($events, self::deliveryEvents($businessId, $start, $end, $q));
        }
        if (in_array('quotes', $sources, true)) {
            $events = array_merge($events, self::quoteFollowUpEvents($businessId, $start, $end, $q));
        }
        if (in_array('purchase_quotes', $sources, true)) {
            $events = array_merge($events, self::purchaseQuoteFollowUpEvents($businessId, $start, $end, $q));
        }
        if (in_array('estate_sales', $sources, true)) {
            $events = array_merge($events, self::estateSaleEvents($businessId, $start, $end, $q));
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function estateSaleEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return [];
        }

        $hasAddr2 = SchemaInspector::hasColumn('estate_sales', 'address_line2');
        $addr2Or = $hasAddr2 ? ' OR COALESCE(es.address_line2, "") LIKE :q' : '';

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(es.title, "") LIKE :q
                OR COALESCE(es.address_line1, "") LIKE :q'
                . $addr2Or . '
                OR COALESCE(es.city, "") LIKE :q
                OR COALESCE(es.notes, "") LIKE :q
                OR CAST(es.id AS CHAR) LIKE :q
            )'
            : '';

        $sql = "SELECT
                    es.id,
                    es.title,
                    es.start_at,
                    es.end_at,
                    LOWER(es.status) AS status_key,
                    es.city,
                    es.state
                FROM estate_sales es
                WHERE es.business_id = :business_id
                  AND es.deleted_at IS NULL
                  AND es.start_at IS NOT NULL
                  AND es.start_at >= :start_at
                  AND es.start_at < :end_at
                  {$searchWhere}
                ORDER BY es.start_at ASC, es.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $startAt = trim((string) ($row['start_at'] ?? ''));
            if ($id <= 0 || $startAt === '') {
                continue;
            }

            $status = (string) ($row['status_key'] ?? 'scheduled');
            $color = match ($status) {
                'active' => '#15803d',
                'complete' => '#64748b',
                'cancelled' => '#991b1b',
                default => '#9333ea',
            };

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Estate Sale #' . (string) $id;
            }

            $city = trim((string) ($row['city'] ?? ''));
            $state = trim((string) ($row['state'] ?? ''));
            $location = trim(implode(', ', array_filter([$city, $state], static fn (string $v): bool => $v !== '')));
            $endAt = trim((string) ($row['end_at'] ?? ''));

            $events[] = [
                'id' => 'estate_sale:' . $id,
                'title' => $title,
                'start' => self::toIso($startAt),
                'end' => $endAt !== '' ? self::toIso($endAt) : null,
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/estate-sales/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'customerName' => $location,
                    'eventType' => 'Estate Sale',
                ],
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function quoteFollowUpEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('quotes') || !SchemaInspector::hasTable('clients')) {
            return [];
        }

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(q.title, "") LIKE :q
                OR COALESCE(q.notes, "") LIKE :q
                OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :q
                OR CAST(q.id AS CHAR) LIKE :q
            )'
            : '';

        $sql = "SELECT
                    q.id,
                    q.title,
                    q.next_follow_up_at,
                    LOWER(COALESCE(q.status, 'new')) AS status_key,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id)) AS client_name,
                    COALESCE(c.phone, '') AS client_phone
                FROM quotes q
                INNER JOIN clients c ON c.id = q.client_id
                    AND c.business_id = q.business_id
                    AND c.deleted_at IS NULL
                WHERE q.business_id = :business_id
                  AND q.deleted_at IS NULL
                  AND q.next_follow_up_at IS NOT NULL
                  AND q.next_follow_up_at >= :start_at
                  AND q.next_follow_up_at < :end_at
                  AND LOWER(COALESCE(q.status, '')) IN ('new', 'sent', 'follow_up')
                  {$searchWhere}
                ORDER BY q.next_follow_up_at ASC, q.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $followUpAt = trim((string) ($row['next_follow_up_at'] ?? ''));
            if ($id <= 0 || $followUpAt === '') {
                continue;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $clientPhone = trim((string) ($row['client_phone'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Quote #' . (string) $id;
            }

            $color = '#db2777';

            $events[] = [
                'id' => 'quote:' . $id,
                'title' => $title,
                'start' => self::toIso($followUpAt),
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/quotes/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'customerName' => $clientName,
                    'customerPhone' => $clientPhone,
                    'eventType' => 'Quote',
                ],
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function purchaseQuoteFollowUpEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('purchase_quotes') || !SchemaInspector::hasTable('clients')) {
            return [];
        }

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(pq.title, "") LIKE :q
                OR COALESCE(pq.notes, "") LIKE :q
                OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :q
                OR CAST(pq.id AS CHAR) LIKE :q
            )'
            : '';

        $sql = "SELECT
                    pq.id,
                    pq.title,
                    pq.next_follow_up_at,
                    LOWER(COALESCE(pq.status, 'new')) AS status_key,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id)) AS client_name,
                    COALESCE(c.phone, '') AS client_phone
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
                    AND c.deleted_at IS NULL
                WHERE pq.business_id = :business_id
                  AND pq.deleted_at IS NULL
                  AND pq.next_follow_up_at IS NOT NULL
                  AND pq.next_follow_up_at >= :start_at
                  AND pq.next_follow_up_at < :end_at
                  AND LOWER(COALESCE(pq.status, '')) IN ('new', 'sent', 'follow_up')
                  {$searchWhere}
                ORDER BY pq.next_follow_up_at ASC, pq.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $followUpAt = trim((string) ($row['next_follow_up_at'] ?? ''));
            if ($id <= 0 || $followUpAt === '') {
                continue;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $clientPhone = trim((string) ($row['client_phone'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Purchase Quote #' . (string) $id;
            }

            $color = '#ea580c';

            $events[] = [
                'id' => 'purchase_quote:' . $id,
                'title' => $title,
                'start' => self::toIso($followUpAt),
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/purchase-quotes/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'customerName' => $clientName,
                    'customerPhone' => $clientPhone,
                    'eventType' => 'Purchase Quote',
                ],
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function deliveryEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return [];
        }

        $hasDeliveryAddr2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $addr2Or = $hasDeliveryAddr2 ? ' OR COALESCE(d.address_line2, "") LIKE :q' : '';

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :q
                OR COALESCE(d.address_line1, "") LIKE :q'
                . $addr2Or . '
                OR COALESCE(d.notes, "") LIKE :q
            )'
            : '';

        $sql = "SELECT
                    d.id,
                    d.scheduled_at,
                    d.end_at,
                    LOWER(d.status) AS status_key,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id)) AS client_name,
                    COALESCE(c.phone, '') AS client_phone
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id
                    AND c.business_id = d.business_id
                    AND c.deleted_at IS NULL
                WHERE d.business_id = :business_id
                  AND d.deleted_at IS NULL
                  AND d.scheduled_at IS NOT NULL
                  AND d.scheduled_at >= :start_at
                  AND d.scheduled_at < :end_at
                  {$searchWhere}
                ORDER BY d.scheduled_at ASC, d.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $startAt = trim((string) ($row['scheduled_at'] ?? ''));
            if ($id <= 0 || $startAt === '') {
                continue;
            }

            $status = (string) ($row['status_key'] ?? 'scheduled');
            $color = match ($status) {
                'completed' => '#64748b',
                'cancelled' => '#991b1b',
                default => '#0d9488',
            };

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $clientPhone = trim((string) ($row['client_phone'] ?? ''));
            $title = $clientName !== '' ? $clientName : 'Delivery #' . (string) $id;

            $endAt = trim((string) ($row['end_at'] ?? ''));

            $events[] = [
                'id' => 'delivery:' . $id,
                'title' => $title,
                'start' => self::toIso($startAt),
                'end' => $endAt !== '' ? self::toIso($endAt) : null,
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/deliveries/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'customerName' => $clientName,
                    'customerPhone' => $clientPhone,
                    'eventType' => 'Delivery',
                ],
            ];
        }

        return $events;
    }

    private static function normalizeIsoDateTime(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function taskDueEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'due_at')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(t.status)' : "'open'";
        $deletedWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'AND t.deleted_at IS NULL' : '';
        $businessWhere = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $searchWhere = $q !== '' ? "AND ({$titleSql} LIKE :q)" : '';

        $sql = "SELECT
                    t.id,
                    {$titleSql} AS title,
                    t.due_at,
                    {$statusSql} AS status_key
                FROM tasks t
                WHERE {$businessWhere}
                  {$deletedWhere}
                  {$searchWhere}
                  AND t.due_at IS NOT NULL
                  AND t.due_at >= :start_at
                  AND t.due_at < :end_at
                ORDER BY t.due_at ASC, t.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $dueAt = trim((string) ($row['due_at'] ?? ''));
            if ($id <= 0 || $dueAt === '') {
                continue;
            }

            $status = (string) ($row['status_key'] ?? 'open');
            $color = match ($status) {
                'closed' => '#64748b',
                'in_progress' => '#0f766e',
                default => '#1d4ed8',
            };

            $events[] = [
                'id' => 'task:' . $id,
                'title' => (string) ($row['title'] ?? ('Task #' . $id)),
                'start' => self::toIso($dueAt),
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/tasks/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'eventType' => 'Task',
                ],
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function jobScheduleEvents(int $businessId, string $start, string $end, string $q): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $startSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'j.start_date' : null);
        if ($startSql === null) {
            return [];
        }

        $endSql = SchemaInspector::hasColumn('jobs', 'scheduled_end_at')
            ? 'j.scheduled_end_at'
            : (SchemaInspector::hasColumn('jobs', 'end_date') ? 'j.end_date' : 'NULL');
        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'LOWER(j.status)' : "'pending'";
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'LOWER(COALESCE(j.job_type, ""))' : "''";
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
        $activeWhere = SchemaInspector::hasColumn('jobs', 'is_active') ? 'AND COALESCE(j.is_active, 1) = 1' : '';
        $inactiveStatusWhere = SchemaInspector::hasColumn('jobs', 'status') ? "AND LOWER(COALESCE(j.status, '')) <> 'inactive'" : '';
        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $clientNameSql = 'NULL';
        $clientPhoneSql = "''";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $clientPhoneSql = "COALESCE(c.phone, '')";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $bizMatch = SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')
                ? 'AND c.business_id = j.business_id'
                : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$bizMatch} {$joinDeleted}";
        }
        $searchWhere = $q !== ''
            ? ($joinClient
                ? "AND ({$titleSql} LIKE :q OR {$clientNameSql} LIKE :q)"
                : "AND ({$titleSql} LIKE :q)")
            : '';

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at,
                    {$statusSql} AS status_key,
                    {$jobTypeSql} AS job_type_key,
                    {$clientNameSql} AS client_name,
                    {$clientPhoneSql} AS client_phone
                FROM jobs j
                {$joinSql}
                WHERE {$businessWhere}
                  {$deletedWhere}
                  {$activeWhere}
                  {$inactiveStatusWhere}
                  {$searchWhere}
                  AND {$startSql} IS NOT NULL
                  AND {$startSql} >= :start_at
                  AND {$startSql} < :end_at
                ORDER BY {$startSql} ASC, j.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        if ($q !== '') {
            $stmt->bindValue(':q', '%' . $q . '%');
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $startAt = trim((string) ($row['scheduled_start_at'] ?? ''));
            if ($id <= 0 || $startAt === '') {
                continue;
            }

            $status = (string) ($row['status_key'] ?? 'pending');
            $jobType = strtolower(trim((string) ($row['job_type_key'] ?? '')));
            $color = match ($status) {
                'active' => '#15803d',
                'completed' => '#0f766e',
                'cancelled' => '#b91c1c',
                default => '#c2410c',
            };
            if ($jobType === 'quote' && $status !== 'cancelled') {
                $color = '#7c3aed';
            }

            $endAt = trim((string) ($row['scheduled_end_at'] ?? ''));

            $customerName = $joinClient ? trim((string) ($row['client_name'] ?? '')) : '';
            $customerPhone = $joinClient ? trim((string) ($row['client_phone'] ?? '')) : '';

            $events[] = [
                'id' => 'job:' . $id,
                'title' => (string) ($row['title'] ?? ('Job #' . $id)),
                'start' => self::toIso($startAt),
                'end' => $endAt !== '' ? self::toIso($endAt) : null,
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/jobs/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'customerName' => $customerName,
                    'customerPhone' => $customerPhone,
                    'jobType' => $jobType,
                    'eventType' => $jobType === 'quote' ? 'Quote' : 'Job',
                ],
            ];
        }

        return $events;
    }

    /**
     * @param array<int, int> $jobIds
     * @return array<int, string>
     */
    private static function jobClientNamesByJobIds(int $businessId, array $jobIds): array
    {
        $jobIds = array_values(array_unique(array_filter(array_map(static fn($v) => (int) $v, $jobIds), static fn($v) => $v > 0)));
        if ($jobIds === [] || !SchemaInspector::hasTable('jobs')) {
            return [];
        }
        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        if (!$joinClient) {
            return [];
        }

        $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
        $bizMatch = SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')
            ? 'AND c.business_id = j.business_id'
            : '';
        $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$bizMatch} {$joinDeleted}";

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $hasBusiness = SchemaInspector::hasColumn('jobs', 'business_id');
        $sql = "SELECT j.id, {$clientNameSql} AS client_name, COALESCE(c.phone, '') AS client_phone
                FROM jobs j
                {$joinSql}
                WHERE j.id IN ({$placeholders})";
        $params = $jobIds;
        if ($hasBusiness) {
            $sql = "SELECT j.id, {$clientNameSql} AS client_name, COALESCE(c.phone, '') AS client_phone
                    FROM jobs j
                    {$joinSql}
                    WHERE j.business_id = ? AND j.id IN ({$placeholders})";
            $params = array_merge([$businessId], $jobIds);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jid = (int) ($row['id'] ?? 0);
            if ($jid <= 0) {
                continue;
            }
            $out[$jid] = [
                'name' => trim((string) ($row['client_name'] ?? '')),
                'phone' => trim((string) ($row['client_phone'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, string> $types
     * @return array<int, array<string, mixed>>
     */
    private static function customEvents(int $businessId, string $start, string $end, array $types, string $q): array
    {
        $filters = [
            'q' => $q,
            'types' => $types,
            'statuses' => [],
        ];

        $rows = Event::range($businessId, $start, $end, $filters);
        if ($rows === []) {
            return [];
        }

        $jobIdsForNames = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $linkType = strtolower(trim((string) ($row['link_type'] ?? '')));
            $linkId = (int) ($row['link_id'] ?? 0);
            if ($linkType === 'job' && $linkId > 0) {
                $jobIdsForNames[] = $linkId;
            }
        }
        $jobCustomerNames = self::jobClientNamesByJobIds($businessId, $jobIdsForNames);

        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? 'appointment')));
            $status = strtolower(trim((string) ($row['status'] ?? 'scheduled')));
            $startAt = trim((string) ($row['start_at'] ?? ''));
            if ($startAt === '') {
                continue;
            }
            $endAt = trim((string) ($row['end_at'] ?? ''));

            $baseColor = match ($type) {
                'appointment' => '#b91c1c', // red
                'cancellation' => '#2563eb', // blue
                'reminder' => '#16a34a', // green
                'note' => '#7c3aed',
                default => '#334155',
            };
            $color = $status === 'cancelled' ? '#64748b' : $baseColor;

            $linkType = strtolower(trim((string) ($row['link_type'] ?? '')));
            $linkId = (int) ($row['link_id'] ?? 0);
            $customerName = '';
            $customerPhone = '';
            if ($linkType === 'job' && $linkId > 0) {
                $jobClient = $jobCustomerNames[$linkId] ?? null;
                if (is_array($jobClient)) {
                    $customerName = trim((string) ($jobClient['name'] ?? ''));
                    $customerPhone = trim((string) ($jobClient['phone'] ?? ''));
                }
            }

            $events[] = [
                'id' => 'event:' . $id,
                'title' => (string) ($row['title'] ?? ('Event #' . $id)),
                'start' => self::toIso($startAt),
                'end' => $endAt !== '' ? self::toIso($endAt) : null,
                'allDay' => ((int) ($row['all_day'] ?? 0)) === 1,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/events/' . (string) $id),
                'editable' => $status !== 'cancelled',
                'extendedProps' => [
                    'jtType' => $type,
                    'jtStatus' => $status,
                    'jtId' => $id,
                    'customerName' => $customerName,
                    'customerPhone' => $customerPhone,
                    'eventType' => match ($type) {
                        'appointment' => 'Appointment',
                        'cancellation' => 'Cancellation',
                        'reminder' => 'Reminder',
                        'note' => 'Note',
                        default => 'Event',
                    },
                ],
            ];
        }

        return $events;
    }

    private static function toIso(string $value): string
    {
        $stamp = strtotime($value);
        if ($stamp === false) {
            return $value;
        }
        return date('c', $stamp);
    }

    /**
     * @return array<int, string>
     */
    private static function parseCsv(string $value): array
    {
        $raw = array_filter(array_map('trim', explode(',', strtolower($value))));
        $raw = array_values(array_unique(array_filter($raw, static fn($v) => $v !== '')));
        return $raw;
    }
}

