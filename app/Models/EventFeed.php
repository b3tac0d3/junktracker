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
            $sources = ['tasks', 'jobs', 'events'];
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
                'title' => 'Task: ' . (string) ($row['title'] ?? ('Task #' . $id)),
                'start' => self::toIso($dueAt),
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/tasks/' . (string) $id),
                'editable' => false,
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
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $searchWhere = $q !== '' ? "AND ({$titleSql} LIKE :q)" : '';

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at,
                    {$statusSql} AS status_key
                FROM jobs j
                WHERE {$businessWhere}
                  {$deletedWhere}
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
            $color = match ($status) {
                'active' => '#15803d',
                'completed' => '#0f766e',
                'cancelled' => '#b91c1c',
                default => '#c2410c',
            };

            $endAt = trim((string) ($row['scheduled_end_at'] ?? ''));

            $events[] = [
                'id' => 'job:' . $id,
                'title' => 'Job: ' . (string) ($row['title'] ?? ('Job #' . $id)),
                'start' => self::toIso($startAt),
                'end' => $endAt !== '' ? self::toIso($endAt) : null,
                'allDay' => false,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => url('/jobs/' . (string) $id),
                'editable' => false,
            ];
        }

        return $events;
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

            $titlePrefix = match ($type) {
                'appointment' => 'Appt',
                'cancellation' => 'Cancel',
                'reminder' => 'Reminder',
                'note' => 'Note',
                default => 'Event',
            };

            $events[] = [
                'id' => 'event:' . $id,
                'title' => $titlePrefix . ': ' . (string) ($row['title'] ?? ('Event #' . $id)),
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

