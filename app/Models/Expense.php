<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Expense
{
    public static function filter(array $filters): array
    {
        [$whereSql, $params] = self::buildWhere($filters);

        $sql = 'SELECT e.id,
                       e.job_id,
                       e.expense_category_id,
                       e.category,
                       e.description,
                       e.amount,
                       e.expense_date,
                       e.created_at,
                       e.updated_at,
                       e.deleted_at,
                       j.name AS job_name,
                       COALESCE(NULLIF(ec.name, \'\'), NULLIF(e.category, \'\'), \'—\') AS category_label
                FROM expenses e
                LEFT JOIN jobs j ON j.id = e.job_id
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                WHERE ' . $whereSql . '
                ORDER BY e.expense_date DESC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function summary(array $filters): array
    {
        [$whereSql, $params] = self::buildWhere($filters);

        $sql = 'SELECT COALESCE(SUM(e.amount), 0) AS total_amount,
                       COUNT(*) AS expense_count,
                       COALESCE(SUM(CASE WHEN e.job_id IS NOT NULL AND e.job_id > 0 THEN e.amount ELSE 0 END), 0) AS linked_amount,
                       COALESCE(SUM(CASE WHEN e.job_id IS NULL OR e.job_id = 0 THEN e.amount ELSE 0 END), 0) AS unlinked_amount
                FROM expenses e
                LEFT JOIN jobs j ON j.id = e.job_id
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'total_amount' => 0,
            'expense_count' => 0,
            'linked_amount' => 0,
            'unlinked_amount' => 0,
        ];
    }

    public static function summaryByJob(array $filters): array
    {
        [$whereSql, $params] = self::buildWhere($filters);

        $sql = 'SELECT e.job_id,
                       COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', e.job_id)) AS job_name,
                       COALESCE(SUM(e.amount), 0) AS total_amount,
                       COUNT(*) AS expense_count
                FROM expenses e
                LEFT JOIN jobs j ON j.id = e.job_id
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                WHERE ' . $whereSql . '
                  AND e.job_id IS NOT NULL
                  AND e.job_id > 0
                GROUP BY e.job_id, j.name
                ORDER BY total_amount DESC, e.job_id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function categories(): array
    {
        $sql = 'SELECT id, name
                FROM expense_categories
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';
        $params = [];
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                return [];
            }
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        $sql .= '
                ORDER BY name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (Schema::hasColumn('expenses', 'business_id')) {
            $businessId = self::currentBusinessId();
            if ($businessId <= 0) {
                $where[] = '1 = 0';
            } else {
                $where[] = 'e.business_id = :business_id';
                $params['business_id'] = $businessId;
            }
        }

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = '(e.deleted_at IS NULL AND COALESCE(e.is_active, 1) = 1)';
        } elseif ($recordStatus === 'deleted') {
            $where[] = '(e.deleted_at IS NOT NULL OR COALESCE(e.is_active, 1) = 0)';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(e.id AS CHAR) LIKE :q
                        OR e.category LIKE :q
                        OR e.description LIKE :q
                        OR ec.name LIKE :q
                        OR j.name LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : 0;
        if ($categoryId > 0) {
            $where[] = 'e.expense_category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $jobLink = (string) ($filters['job_link'] ?? 'all');
        if ($jobLink === 'linked') {
            $where[] = '(e.job_id IS NOT NULL AND e.job_id > 0)';
        } elseif ($jobLink === 'unlinked') {
            $where[] = '(e.job_id IS NULL OR e.job_id = 0)';
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $where[] = 'DATE(e.expense_date) >= :start_date';
            $params['start_date'] = $startDate;
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $where[] = 'DATE(e.expense_date) <= :end_date';
            $params['end_date'] = $endDate;
        }

        if (empty($where)) {
            $where[] = '1=1';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return (int) current_business_id();
        }

        return (int) config('app.default_business_id', 1);
    }
}
