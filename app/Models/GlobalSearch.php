<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class GlobalSearch
{
    public static function search(string $query, int $limitPerType = 8): array
    {
        $query = trim($query);
        $limitPerType = max(1, min($limitPerType, 25));

        if ($query === '') {
            return [
                'query' => '',
                'sections' => [],
                'total' => 0,
            ];
        }

        $sections = [];

        self::appendSection(
            $sections,
            'jobs',
            'Jobs',
            'fas fa-briefcase',
            self::mapJobs(self::safe(static fn (): array => Job::lookupForSales($query, min(25, $limitPerType * 3)))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'clients',
            'Clients',
            'fas fa-user',
            self::mapClients(self::safe(static fn (): array => Client::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'consignors',
            'Consignors',
            'fas fa-handshake',
            self::mapConsignors(self::safe(static fn (): array => Consignor::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'companies',
            'Companies',
            'fas fa-building',
            self::mapCompanies(self::safe(static fn (): array => Company::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'estates',
            'Estates',
            'fas fa-house',
            self::mapEstates(self::safe(static fn (): array => Estate::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'prospects',
            'Prospects',
            'fas fa-user-plus',
            self::mapProspects(self::safe(static fn (): array => Prospect::filter([
                'q' => $query,
                'status' => 'all',
                'record_status' => 'active',
            ]))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'sales',
            'Sales',
            'fas fa-dollar-sign',
            self::mapSales(self::safe(static fn (): array => Sale::filter([
                'q' => $query,
                'type' => 'all',
                'record_status' => 'active',
                'start_date' => '',
                'end_date' => '',
            ]))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'expenses',
            'Expenses',
            'fas fa-receipt',
            self::mapExpenses(self::safe(static fn (): array => Expense::filter([
                'q' => $query,
                'record_status' => 'active',
                'category_id' => 0,
                'job_link' => 'all',
                'start_date' => '',
                'end_date' => '',
            ]))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'tasks',
            'Tasks',
            'fas fa-list-check',
            self::mapTasks(self::safe(static fn (): array => Task::filter([
                'q' => $query,
                'status' => 'all',
                'importance' => 0,
                'link_type' => 'all',
                'assigned_user_id' => 0,
                'due_start' => '',
                'due_end' => '',
                'record_status' => 'active',
            ]))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'employees',
            'Employees',
            'fas fa-id-badge',
            self::mapEmployees(self::safe(static fn (): array => Employee::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'time_entries',
            'Time Entries',
            'fas fa-user-clock',
            self::mapTimeEntries(self::safe(static fn (): array => TimeEntry::filter([
                'q' => $query,
                'employee_id' => null,
                'job_id' => null,
                'start_date' => '',
                'end_date' => '',
                'record_status' => 'active',
            ]))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'users',
            'Users',
            'fas fa-users-cog',
            self::mapUsers(self::safe(static fn (): array => User::search($query, 'active'))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'disposal_locations',
            'Disposal Locations',
            'fas fa-recycle',
            self::mapDisposalLocations(self::safe(static fn (): array => self::searchDisposalLocations($query, min(50, $limitPerType * 3)))),
            $limitPerType
        );

        self::appendSection(
            $sections,
            'expense_categories',
            'Expense Categories',
            'fas fa-tags',
            self::mapExpenseCategories(self::safe(static fn (): array => self::searchExpenseCategories($query, min(50, $limitPerType * 3)))),
            $limitPerType
        );

        $total = 0;
        foreach ($sections as $section) {
            $total += (int) ($section['total'] ?? 0);
        }

        return [
            'query' => $query,
            'sections' => $sections,
            'total' => $total,
        ];
    }

    private static function appendSection(array &$sections, string $key, string $label, string $icon, array $items, int $limit): void
    {
        $total = count($items);
        if ($total === 0) {
            return;
        }

        $sections[] = [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'total' => $total,
            'has_more' => $total > $limit,
            'items' => array_slice($items, 0, $limit),
        ];
    }

    private static function mapJobs(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $title = '#' . $id . ' - ' . ($name !== '' ? $name : ('Job #' . $id));
            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));
            $status = trim((string) ($row['job_status'] ?? ''));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($location, $status !== '' ? 'Status: ' . $status : ''),
                'url' => '/jobs/' . $id,
            ];
        }

        return $items;
    }

    private static function mapClients(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $business = trim((string) ($row['business_name'] ?? ''));
            $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $title = $business !== '' ? $business : ($fullName !== '' ? $fullName : ('Client #' . $id));
            $companyNames = trim((string) ($row['company_names'] ?? ''));
            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($companyNames, trim((string) ($row['phone'] ?? '')), trim((string) ($row['email'] ?? '')), $location),
                'url' => '/clients/' . $id,
            ];
        }

        return $items;
    }

    private static function mapCompanies(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['name'] ?? ''));
            if ($title === '') {
                $title = 'Company #' . $id;
            }

            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($location, trim((string) ($row['phone'] ?? ''))),
                'url' => '/companies/' . $id,
            ];
        }

        return $items;
    }

    private static function mapConsignors(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['display_name'] ?? ''));
            if ($title === '') {
                $title = 'Consignor #' . $id;
            }

            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));
            $estimate = isset($row['inventory_estimate_amount']) && $row['inventory_estimate_amount'] !== null
                ? ('$' . number_format((float) $row['inventory_estimate_amount'], 2))
                : '';
            $consignorNumber = trim((string) ($row['consignor_number'] ?? ''));
            $schedule = trim((string) ($row['payment_schedule'] ?? ''));
            $nextDue = trim((string) ($row['next_payment_due_date'] ?? ''));
            $scheduleText = $schedule !== '' ? ('Schedule: ' . ucfirst($schedule)) : '';
            $dueText = $nextDue !== '' ? ('Next Due: ' . $nextDue) : '';
            $numberText = $consignorNumber !== '' ? ('#: ' . $consignorNumber) : '';

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta(trim((string) ($row['phone'] ?? '')), trim((string) ($row['email'] ?? '')), $location, $numberText, $scheduleText, $dueText, $estimate !== '' ? 'Est: ' . $estimate : ''),
                'url' => '/consignors/' . $id,
            ];
        }

        return $items;
    }

    private static function mapEstates(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['name'] ?? ''));
            if ($title === '') {
                $title = 'Estate #' . $id;
            }

            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta(trim((string) ($row['primary_client_name'] ?? '')), $location),
                'url' => '/estates/' . $id,
            ];
        }

        return $items;
    }

    private static function mapProspects(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $title = 'Prospect #' . $id . ($clientName !== '' ? ' - ' . $clientName : '');
            $status = trim((string) ($row['status'] ?? ''));
            $nextStep = trim((string) ($row['next_step'] ?? ''));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($status !== '' ? 'Status: ' . $status : '', $nextStep !== '' ? 'Next: ' . $nextStep : ''),
                'url' => '/prospects/' . $id,
                'badge' => $status,
            ];
        }

        return $items;
    }

    private static function mapSales(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $title = $name !== '' ? $name : ('Sale #' . $id);
            $type = trim((string) ($row['type'] ?? ''));
            $gross = isset($row['gross_amount']) ? '$' . number_format((float) $row['gross_amount'], 2) : '';
            $net = isset($row['net_amount']) ? '$' . number_format((float) $row['net_amount'], 2) : '';

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($type !== '' ? 'Type: ' . $type : '', $gross !== '' ? 'Gross: ' . $gross : '', $net !== '' ? 'Net: ' . $net : ''),
                'url' => '/sales/' . $id,
                'badge' => $type,
            ];
        }

        return $items;
    }

    private static function mapExpenses(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $category = trim((string) ($row['category_label'] ?? ''));
            $title = 'Expense #' . $id . ($category !== '' ? ' - ' . $category : '');
            $amount = isset($row['amount']) ? '$' . number_format((float) $row['amount'], 2) : '';
            $date = trim((string) ($row['expense_date'] ?? ''));
            $jobName = trim((string) ($row['job_name'] ?? ''));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($amount, $date, $jobName),
                'url' => '/expenses?q=' . rawurlencode((string) $id),
            ];
        }

        return $items;
    }

    private static function mapTasks(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Task #' . $id;
            }

            $status = trim((string) ($row['status'] ?? ''));
            $importance = isset($row['importance']) ? (int) $row['importance'] : 0;
            $linkLabel = trim((string) ($row['link_label'] ?? ''));
            if ($linkLabel === '—') {
                $linkLabel = '';
            }

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($status !== '' ? 'Status: ' . $status : '', $importance > 0 ? 'Priority: ' . $importance : '', $linkLabel !== '' ? 'Linked: ' . $linkLabel : ''),
                'url' => '/tasks/' . $id,
                'badge' => $status,
            ];
        }

        return $items;
    }

    private static function mapEmployees(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $title = $fullName !== '' ? $fullName : ('Employee #' . $id);

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta(trim((string) ($row['email'] ?? '')), trim((string) ($row['phone'] ?? ''))),
                'url' => '/employees/' . $id,
            ];
        }

        return $items;
    }

    private static function mapTimeEntries(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $employee = trim((string) ($row['employee_name'] ?? ''));
            $title = 'Time Entry #' . $id . ($employee !== '' ? ' - ' . $employee : '');
            $job = trim((string) ($row['job_name'] ?? ''));
            $date = trim((string) ($row['work_date'] ?? ''));
            $minutes = isset($row['minutes_worked']) ? (int) $row['minutes_worked'] : 0;

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($job, $date, $minutes > 0 ? self::minutesLabel($minutes) : ''),
                'url' => '/time-tracking/' . $id,
            ];
        }

        return $items;
    }

    private static function mapUsers(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $title = $fullName !== '' ? $fullName : ('User #' . $id);
            $roleRaw = isset($row['role']) ? (int) $row['role'] : null;
            $role = function_exists('role_label') ? role_label($roleRaw) : ((string) ($row['role'] ?? ''));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta(trim((string) ($row['email'] ?? '')), $role !== '' ? 'Role: ' . $role : ''),
                'url' => '/users/' . $id,
            ];
        }

        return $items;
    }

    private static function mapDisposalLocations(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['name'] ?? ''));
            if ($title === '') {
                $title = 'Location #' . $id;
            }

            $type = trim((string) ($row['type'] ?? ''));
            $location = self::joinMeta(trim((string) ($row['city'] ?? '')), trim((string) ($row['state'] ?? '')));

            $items[] = [
                'title' => $title,
                'meta' => self::joinMeta($type !== '' ? ucfirst($type) : '', $location),
                'url' => '/admin/disposal-locations/' . $id . '/edit',
            ];
        }

        return $items;
    }

    private static function mapExpenseCategories(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($row['name'] ?? ''));
            if ($title === '') {
                $title = 'Category #' . $id;
            }

            $note = self::excerpt(trim((string) ($row['note'] ?? '')));

            $items[] = [
                'title' => $title,
                'meta' => $note,
                'url' => '/admin/expense-categories/' . $id . '/edit',
            ];
        }

        return $items;
    }

    private static function searchDisposalLocations(string $query, int $limit): array
    {
        $sql = 'SELECT id, name, type, city, state
                FROM disposal_locations
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                  AND (
                        name LIKE :term
                        OR city LIKE :term
                        OR state LIKE :term
                        OR type LIKE :term
                      )
                ORDER BY name ASC
                LIMIT ' . max(1, min($limit, 100));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $query . '%']);

        return $stmt->fetchAll();
    }

    private static function searchExpenseCategories(string $query, int $limit): array
    {
        $sql = 'SELECT id, name, note
                FROM expense_categories
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                  AND (
                        name LIKE :term
                        OR note LIKE :term
                      )
                ORDER BY name ASC
                LIMIT ' . max(1, min($limit, 100));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $query . '%']);

        return $stmt->fetchAll();
    }

    private static function safe(callable $resolver): array
    {
        try {
            $result = $resolver();
            return is_array($result) ? $result : [];
        } catch (Throwable) {
            return [];
        }
    }

    private static function joinMeta(string ...$parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return implode(' • ', $clean);
    }

    private static function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $remain, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private static function excerpt(string $value, int $max = 100): string
    {
        if ($value === '' || strlen($value) <= $max) {
            return $value;
        }

        return rtrim(substr($value, 0, max(1, $max - 3))) . '...';
    }
}
