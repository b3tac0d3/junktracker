<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class EventFeed
{
    /**
     * Personal-time blocks are calendar-only; exclude from dashboard/search metrics.
     *
     * @param array<string, mixed> $event
     */
    public static function isPersonalTimeEvent(array $event): bool
    {
        $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];
        $jtType = strtolower(trim((string) ($props['jtType'] ?? '')));
        if ($jtType === 'personal') {
            return true;
        }

        $eventType = strtolower(trim((string) ($props['eventType'] ?? '')));
        return $eventType === 'personal' || $eventType === 'personal time';
    }

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

        if (!empty($filters['exclude_personal'])) {
            $events = array_values(array_filter(
                $events,
                static fn (array $event): bool => !self::isPersonalTimeEvent($event)
            ));
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

        $hasAddr1 = SchemaInspector::hasColumn('estate_sales', 'address_line1');
        $hasAddr2 = SchemaInspector::hasColumn('estate_sales', 'address_line2');
        $addr1Or = $hasAddr1 ? ' OR COALESCE(es.address_line1, "") LIKE :q' : '';
        $addr2Or = $hasAddr2 ? ' OR COALESCE(es.address_line2, "") LIKE :q' : '';

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(es.title, "") LIKE :q'
                . $addr1Or . $addr2Or . '
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
                    " . self::addressSelectColumns('estate_sales', 'es') . "
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
                    'eventAddress' => self::resolveEventAddress($row),
                    'eventType' => 'Estate Sale',
                    'jtStatus' => $status,
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
                    COALESCE(c.phone, '') AS client_phone,
                    " . self::addressSelectColumns('quotes', 'q') . ",
                    " . self::addressSelectColumns('clients', 'c', 'client_') . "
                FROM quotes q
                INNER JOIN clients c ON c.id = q.client_id
                    AND c.business_id = q.business_id
                    AND c.deleted_at IS NULL
                WHERE q.business_id = :business_id
                  AND q.deleted_at IS NULL
                  AND q.next_follow_up_at IS NOT NULL
                  AND q.next_follow_up_at >= :start_at
                  AND q.next_follow_up_at < :end_at
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

            $status = (string) ($row['status_key'] ?? 'new');
            $color = match ($status) {
                'won' => '#64748b',
                'lost', 'expired' => '#94a3b8',
                default => '#db2777',
            };

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
                    'eventAddress' => self::resolveEventAddress($row, '', 'client_'),
                    'eventType' => 'Quote',
                    'jtStatus' => $status,
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
                    pq.contact_date,
                    LOWER(COALESCE(pq.status, 'new')) AS status_key,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id)) AS client_name,
                    COALESCE(c.phone, '') AS client_phone,
                    " . self::addressSelectColumns('clients', 'c', 'client_') . "
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
                    AND c.deleted_at IS NULL
                WHERE pq.business_id = :business_id
                  AND pq.deleted_at IS NULL
                  AND (
                    pq.next_follow_up_at IS NOT NULL
                    OR pq.contact_date IS NOT NULL
                  )
                  AND COALESCE(
                    pq.next_follow_up_at,
                    CONCAT(pq.contact_date, ' 09:00:00')
                  ) >= :start_at
                  AND COALESCE(
                    pq.next_follow_up_at,
                    CONCAT(pq.contact_date, ' 09:00:00')
                  ) < :end_at
                  {$searchWhere}
                ORDER BY COALESCE(pq.next_follow_up_at, CONCAT(pq.contact_date, ' 09:00:00')) ASC, pq.id ASC
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
            if ($followUpAt === '') {
                $contactDate = trim((string) ($row['contact_date'] ?? ''));
                if ($contactDate !== '') {
                    $followUpAt = $contactDate . ' 09:00:00';
                }
            }
            if ($id <= 0 || $followUpAt === '') {
                continue;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $clientPhone = trim((string) ($row['client_phone'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Purchase Quote #' . (string) $id;
            }

            $status = (string) ($row['status_key'] ?? 'new');
            $color = match ($status) {
                'won' => '#64748b',
                'lost', 'expired' => '#94a3b8',
                default => '#ea580c',
            };

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
                    'eventAddress' => self::eventAddressFromRow($row, 'client_'),
                    'eventType' => 'Purchase Quote',
                    'jtStatus' => $status,
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

        $hasDeliveryAddr1 = SchemaInspector::hasColumn('client_deliveries', 'address_line1');
        $hasDeliveryAddr2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $addr1Or = $hasDeliveryAddr1 ? ' OR COALESCE(d.address_line1, "") LIKE :q' : '';
        $addr2Or = $hasDeliveryAddr2 ? ' OR COALESCE(d.address_line2, "") LIKE :q' : '';

        $searchWhere = $q !== ''
            ? 'AND (
                COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :q'
                . $addr1Or . $addr2Or . '
                OR COALESCE(d.notes, "") LIKE :q
            )'
            : '';

        $sql = "SELECT
                    d.id,
                    d.scheduled_at,
                    d.end_at,
                    LOWER(d.status) AS status_key,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id)) AS client_name,
                    COALESCE(c.phone, '') AS client_phone,
                    " . self::addressSelectColumns('client_deliveries', 'd') . ",
                    " . self::addressSelectColumns('clients', 'c', 'client_') . "
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
                    'eventAddress' => self::resolveEventAddress($row, '', 'client_'),
                    'eventType' => 'Delivery',
                    'jtStatus' => $status,
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
        $clientJoin = Task::linkedClientJoinParts('t');
        if ($q !== '' && $clientJoin['searchable']) {
            $searchWhere = "AND ({$titleSql} LIKE :q OR {$clientJoin['clientNameSql']} LIKE :q)";
        }
        $hasClientJoin = trim($clientJoin['join']) !== '';

        $sql = "SELECT
                    t.id,
                    {$titleSql} AS title,
                    t.due_at,
                    {$statusSql} AS status_key,
                    {$clientJoin['clientNameSql']} AS client_name,
                    " . ($hasClientJoin ? self::addressSelectColumns('clients', 'task_client', 'client_') : self::emptyAddressSelectColumns('client_')) . "
                FROM tasks t
                {$clientJoin['join']}
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

            $customerName = trim((string) ($row['client_name'] ?? ''));

            $events[] = [
                'id' => 'task:' . $id,
                'title' => Task::displayTitle([
                    'id' => $id,
                    'title' => (string) ($row['title'] ?? ('Task #' . $id)),
                    'client_name' => $customerName,
                ]),
                'start' => self::toIso($dueAt),
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/tasks/' . (string) $id),
                'editable' => false,
                'extendedProps' => [
                    'eventType' => 'Task',
                    'jtStatus' => $status,
                    'customerName' => $customerName,
                    'eventAddress' => self::eventAddressFromRow($row, 'client_'),
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
        $inactiveStatusWhere = SchemaInspector::hasColumn('jobs', 'status')
            ? "AND LOWER(COALESCE(j.status, '')) NOT IN ('inactive', 'cancelled')"
            : '';
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

        $addressSelect = self::addressSelectColumns('jobs', 'j');
        $clientAddressSelect = $joinClient
            ? self::addressSelectColumns('clients', 'c', 'client_')
            : self::emptyAddressSelectColumns('client_');

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at,
                    {$statusSql} AS status_key,
                    {$jobTypeSql} AS job_type_key,
                    {$clientNameSql} AS client_name,
                    {$clientPhoneSql} AS client_phone,
                    {$addressSelect},
                    {$clientAddressSelect}
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
                    'eventAddress' => self::resolveEventAddress($row, '', 'client_'),
                    'jobType' => $jobType,
                    'eventType' => $jobType === 'quote' ? 'Quote' : 'Job',
                    'jtStatus' => $status,
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

        $jobAddressSelect = self::addressSelectColumns('jobs', 'j');
        $clientAddressSelect = self::addressSelectColumns('clients', 'c', 'client_');
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $hasBusiness = SchemaInspector::hasColumn('jobs', 'business_id');
        $sql = "SELECT j.id, {$clientNameSql} AS client_name, COALESCE(c.phone, '') AS client_phone,
                    {$clientAddressSelect},
                    {$jobAddressSelect}
                FROM jobs j
                {$joinSql}
                WHERE j.id IN ({$placeholders})";
        $params = $jobIds;
        if ($hasBusiness) {
            $sql = "SELECT j.id, {$clientNameSql} AS client_name, COALESCE(c.phone, '') AS client_phone,
                    {$clientAddressSelect},
                    {$jobAddressSelect}
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
                'address' => self::resolveEventAddress($row, '', 'client_'),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, int> $clientIds
     * @return array<int, array{name: string, phone: string}>
     */
    private static function clientContactsByClientIds(int $businessId, array $clientIds): array
    {
        $clientIds = array_values(array_unique(array_filter(array_map(static fn($v) => (int) $v, $clientIds), static fn($v) => $v > 0)));
        if ($clientIds === [] || !SchemaInspector::hasTable('clients')) {
            return [];
        }

        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        $phoneSql = SchemaInspector::hasColumn('clients', 'phone') ? "COALESCE(c.phone, '')" : "''";
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $params = $clientIds;
        $where = ['c.id IN (' . $placeholders . ')'];
        if (SchemaInspector::hasColumn('clients', 'business_id')) {
            array_unshift($where, 'c.business_id = ?');
            $params = array_merge([$businessId], $clientIds);
        }
        if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
            $where[] = 'c.deleted_at IS NULL';
        }

        $sql = "SELECT c.id, {$nameSql} AS client_name, {$phoneSql} AS client_phone,
                    " . self::addressSelectColumns('clients', 'c', 'client_') . "
                FROM clients c
                WHERE " . implode(' AND ', $where);

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
            $cid = (int) ($row['id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $out[$cid] = [
                'name' => trim((string) ($row['client_name'] ?? '')),
                'phone' => trim((string) ($row['client_phone'] ?? '')),
                'address' => self::eventAddressFromRow($row, 'client_'),
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
        $clientIdsForNames = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $linkType = strtolower(trim((string) ($row['link_type'] ?? '')));
            $linkId = (int) ($row['link_id'] ?? 0);
            if ($linkType === 'job' && $linkId > 0) {
                $jobIdsForNames[] = $linkId;
            } elseif ($linkType === 'client' && $linkId > 0) {
                $clientIdsForNames[] = $linkId;
            }
        }
        $jobCustomerNames = self::jobClientNamesByJobIds($businessId, $jobIdsForNames);
        $clientContacts = self::clientContactsByClientIds($businessId, $clientIdsForNames);

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
                'personal' => '#475569', // slate — blocks booking
                'reminder' => '#16a34a', // green
                'note' => '#7c3aed',
                default => '#334155',
            };
            $color = $status === 'cancelled' ? '#64748b' : $baseColor;

            $linkType = strtolower(trim((string) ($row['link_type'] ?? '')));
            $linkId = (int) ($row['link_id'] ?? 0);
            $customerName = '';
            $customerPhone = '';
            $eventAddress = '';
            if ($linkType === 'job' && $linkId > 0) {
                $jobClient = $jobCustomerNames[$linkId] ?? null;
                if (is_array($jobClient)) {
                    $customerName = trim((string) ($jobClient['name'] ?? ''));
                    $customerPhone = trim((string) ($jobClient['phone'] ?? ''));
                    $eventAddress = trim((string) ($jobClient['address'] ?? ''));
                }
            } elseif ($linkType === 'client' && $linkId > 0) {
                $clientContact = $clientContacts[$linkId] ?? null;
                if (is_array($clientContact)) {
                    $customerName = trim((string) ($clientContact['name'] ?? ''));
                    $customerPhone = trim((string) ($clientContact['phone'] ?? ''));
                    $eventAddress = trim((string) ($clientContact['address'] ?? ''));
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
                    'eventAddress' => $eventAddress,
                    'eventType' => match ($type) {
                        'appointment' => 'Appointment',
                        'cancellation' => 'Cancellation',
                        'personal' => 'Personal time',
                        'reminder' => 'Reminder',
                        'note' => 'Note',
                        default => 'Event',
                    },
                ],
            ];
        }

        return $events;
    }

    /**
     * Calendar items that should sync to Google (matches default Events calendar sources).
     *
     * @return list<array{source_type: string, source_id: int}>
     */
    public static function googleSyncItemRefs(int $businessId, string $start, string $end): array
    {
        $startNorm = self::normalizeIsoDateTime($start);
        $endNorm = self::normalizeIsoDateTime($end);
        if ($startNorm === null || $endNorm === null) {
            $startNorm = date('Y-m-01 00:00:00');
            $endNorm = date('Y-m-t 23:59:59');
        }

        $refs = [];
        $append = static function (array $events, string $sourceType) use (&$refs): void {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $feedId = trim((string) ($event['id'] ?? ''));
                if ($feedId === '' || !str_contains($feedId, ':')) {
                    continue;
                }
                [$prefix, $rawId] = explode(':', $feedId, 2);
                if ($prefix !== $sourceType) {
                    continue;
                }
                $sourceId = (int) $rawId;
                if ($sourceId <= 0) {
                    continue;
                }
                $refs[] = [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ];
            }
        };

        $append(self::taskDueEvents($businessId, $startNorm, $endNorm, ''), 'task');
        $append(self::jobScheduleEvents($businessId, $startNorm, $endNorm, ''), 'job');
        $append(self::customEvents($businessId, $startNorm, $endNorm, [], ''), 'event');
        $append(self::deliveryEvents($businessId, $startNorm, $endNorm, ''), 'delivery');
        $append(self::quoteFollowUpEvents($businessId, $startNorm, $endNorm, ''), 'quote');
        $append(self::purchaseQuoteFollowUpEvents($businessId, $startNorm, $endNorm, ''), 'purchase_quote');
        $append(self::estateSaleEvents($businessId, $startNorm, $endNorm, ''), 'estate_sale');

        return $refs;
    }

    private static function toIso(string $value): string
    {
        $stamp = strtotime($value);
        if ($stamp === false) {
            return $value;
        }
        return date('c', $stamp);
    }

    private static function columnSelect(string $table, string $column, string $alias, string $asName): string
    {
        if (SchemaInspector::hasTable($table) && SchemaInspector::hasColumn($table, $column)) {
            return "{$alias}.{$column} AS {$asName}";
        }

        return "'' AS {$asName}";
    }

    private static function addressSelectColumns(string $table, string $alias, string $asPrefix = ''): string
    {
        $fields = ['address_line1', 'address_line2', 'city', 'state', 'postal_code'];
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = self::columnSelect($table, $field, $alias, $asPrefix . $field);
        }

        return implode(",\n                    ", $parts);
    }

    private static function emptyAddressSelectColumns(string $asPrefix = ''): string
    {
        $fields = ['address_line1', 'address_line2', 'city', 'state', 'postal_code'];
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = "'' AS {$asPrefix}{$field}";
        }

        return implode(",\n                    ", $parts);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function eventAddressFromRow(array $row, string $prefix = ''): string
    {
        return BusinessLocation::formatAddress(
            trim((string) ($row[$prefix . 'address_line1'] ?? '')),
            trim((string) ($row[$prefix . 'address_line2'] ?? '')),
            trim((string) ($row[$prefix . 'city'] ?? '')),
            trim((string) ($row[$prefix . 'state'] ?? '')),
            trim((string) ($row[$prefix . 'postal_code'] ?? ''))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function resolveEventAddress(array $row, string $primaryPrefix = '', string $fallbackPrefix = 'client_'): string
    {
        $address = self::eventAddressFromRow($row, $primaryPrefix);
        if ($address !== '') {
            return $address;
        }

        if ($fallbackPrefix === '') {
            return '';
        }

        return self::eventAddressFromRow($row, $fallbackPrefix);
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

