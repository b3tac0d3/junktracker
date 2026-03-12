<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DashboardSummary
{
    public static function byBusiness(int $businessId, int $ownerUserId): array
    {
        return [
            'sales' => self::salesSummary($businessId),
            'jobs' => self::jobsSummary($businessId),
            'tasks' => self::tasksSummary($businessId, $ownerUserId),
            'lists' => [
                'dispatch_jobs' => self::dispatchJobs($businessId),
                'prospects' => self::prospectJobs($businessId),
                'purchase_prospects' => self::purchaseProspects($businessId),
                'my_tasks_due' => self::myTasksDue($businessId, $ownerUserId),
                'recent_sales' => self::recentSales($businessId),
            ],
        ];
    }

    private static function salesSummary(int $businessId): array
    {
        $summary = [
            'mtd_gross' => 0.0,
            'mtd_net' => 0.0,
            'mtd_count' => 0,
        ];

        if (!SchemaInspector::hasTable('sales')) {
            return $summary;
        }

        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 'DATE(s.sale_date)' : 'DATE(s.created_at)';

        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
        ];

        $sql = "SELECT
                    COUNT(*) AS mtd_count,
                    COALESCE(SUM({$grossSql}), 0) AS mtd_gross,
                    COALESCE(SUM({$netSql}), 0) AS mtd_net
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                  AND {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                  AND {$dateSql} <= CURDATE()";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'mtd_gross' => (float) ($row['mtd_gross'] ?? 0),
            'mtd_net' => (float) ($row['mtd_net'] ?? 0),
            'mtd_count' => (int) ($row['mtd_count'] ?? 0),
        ];
    }

    private static function jobsSummary(int $businessId): array
    {
        $summary = [
            'dispatch' => 0,
            'prospect' => 0,
            'active' => 0,
        ];

        if (!SchemaInspector::hasTable('jobs')) {
            return $summary;
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'deleted_at IS NULL' : '1=1',
        ];

        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'LOWER(status)' : "'pending'";
        $sql = "SELECT
                    SUM(CASE WHEN {$statusSql} IN ('pending','active') THEN 1 ELSE 0 END) AS dispatch_count,
                    SUM(CASE WHEN {$statusSql} = 'prospect' THEN 1 ELSE 0 END) AS prospect_count,
                    SUM(CASE WHEN {$statusSql} = 'active' THEN 1 ELSE 0 END) AS active_count
                FROM jobs
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'dispatch' => (int) ($row['dispatch_count'] ?? 0),
            'prospect' => (int) ($row['prospect_count'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
        ];
    }

    private static function tasksSummary(int $businessId, int $ownerUserId): array
    {
        $summary = [
            'mine_open' => 0,
            'mine_due_today' => 0,
            'mine_overdue' => 0,
        ];

        if (!SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'owner_user_id')) {
            return $summary;
        }

        $where = [
            SchemaInspector::hasColumn('tasks', 'business_id') ? 'business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'deleted_at IS NULL' : '1=1',
            'owner_user_id = :owner_user_id',
        ];

        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(status)' : "'open'";
        $dueSql = SchemaInspector::hasColumn('tasks', 'due_at') ? 'DATE(due_at)' : 'NULL';

        $sql = "SELECT
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') THEN 1 ELSE 0 END) AS mine_open,
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') AND {$dueSql} = CURDATE() THEN 1 ELSE 0 END) AS mine_due_today,
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') AND {$dueSql} < CURDATE() THEN 1 ELSE 0 END) AS mine_overdue
                FROM tasks
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':owner_user_id', $ownerUserId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'mine_open' => (int) ($row['mine_open'] ?? 0),
            'mine_due_today' => (int) ($row['mine_due_today'] ?? 0),
            'mine_overdue' => (int) ($row['mine_overdue'] ?? 0),
        ];
    }

    private static function dispatchJobs(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title') ? 'j.title' : "CONCAT('Job #', j.id)";
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $scheduledSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at') ? 'j.scheduled_start_at' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = j.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $joinSql .= ' AND c.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1',
            "LOWER({$statusSql}) IN ('pending','active')",
        ];

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    LOWER({$statusSql}) AS status,
                    {$scheduledSql} AS scheduled_start_at,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE WHEN {$scheduledSql} IS NULL THEN 1 ELSE 0 END,
                    {$scheduledSql} ASC,
                    j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function prospectJobs(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title') ? 'j.title' : "CONCAT('Job #', j.id)";
        $scheduledSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at') ? 'j.scheduled_start_at' : 'NULL';
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'prospect'";

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = j.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $joinSql .= ' AND c.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1',
            "LOWER({$statusSql}) = 'prospect'",
        ];

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$scheduledSql} AS scheduled_start_at,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function myTasksDue(int $businessId, int $ownerUserId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'owner_user_id')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 't.status' : "'open'";
        $dueSql = SchemaInspector::hasColumn('tasks', 'due_at') ? 't.due_at' : 'NULL';

        $where = [
            SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1',
            't.owner_user_id = :owner_user_id',
            "LOWER({$statusSql}) IN ('open','in_progress')",
        ];

        $sql = "SELECT
                    t.id,
                    {$titleSql} AS title,
                    LOWER({$statusSql}) AS status,
                    {$dueSql} AS due_at
                FROM tasks t
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE WHEN {$dueSql} IS NULL THEN 1 ELSE 0 END,
                    {$dueSql} ASC,
                    t.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':owner_user_id', $ownerUserId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function purchaseProspects(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('purchases', 'title') ? 'p.title' : "CONCAT('Purchase #', p.id)";
        $statusSql = SchemaInspector::hasColumn('purchases', 'status') ? 'p.status' : "''";
        $purchaseDateSql = SchemaInspector::hasColumn('purchases', 'purchase_date') ? 'p.purchase_date' : 'NULL';
        $contactDateSql = SchemaInspector::hasColumn('purchases', 'contact_date') ? 'p.contact_date' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('purchases', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = p.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('purchases', 'business_id')) {
                $joinSql .= ' AND c.business_id = p.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [
            SchemaInspector::hasColumn('purchases', 'business_id') ? 'p.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('purchases', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1',
        ];
        if (SchemaInspector::hasColumn('purchases', 'status')) {
            $where[] = "LOWER(COALESCE({$statusSql}, '')) NOT IN ('complete','cancelled')";
        }

        $sql = "SELECT
                    p.id,
                    {$titleSql} AS title,
                    LOWER(COALESCE({$statusSql}, '')) AS status,
                    {$purchaseDateSql} AS purchase_date,
                    {$contactDateSql} AS contact_date,
                    {$clientNameSql} AS client_name
                FROM purchases p
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('purchases', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function recentSales(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : "'sale'";
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 's.created_at';
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;

        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
            'DATE(' . $dateSql . ") >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'DATE(' . $dateSql . ') <= CURDATE()',
        ];

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$dateSql} DESC, s.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
