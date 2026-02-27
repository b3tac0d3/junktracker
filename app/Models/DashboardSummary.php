<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DashboardSummary
{
    public static function byBusiness(int $businessId, int $ownerUserId): array
    {
        $pdo = Database::connection();

        $queries = [
            'clients_total' => 'SELECT COUNT(*) FROM clients WHERE business_id = :business_id AND deleted_at IS NULL',
            'jobs_open' => "SELECT COUNT(*) FROM jobs WHERE business_id = :business_id AND deleted_at IS NULL AND status IN ('pending','active')",
            'tasks_mine_open' => "SELECT COUNT(*) FROM tasks WHERE business_id = :business_id AND owner_user_id = :owner_user_id AND deleted_at IS NULL AND status IN ('open','in_progress')",
            'time_open_entries' => 'SELECT COUNT(*) FROM employee_time_entries WHERE business_id = :business_id AND deleted_at IS NULL AND clock_out_at IS NULL',
            'invoices_open' => "SELECT COUNT(*) FROM invoices WHERE business_id = :business_id AND deleted_at IS NULL AND status IN ('draft','sent','partial')",
        ];

        $result = [];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $params = ['business_id' => $businessId];
            if (str_contains($sql, ':owner_user_id')) {
                $params['owner_user_id'] = $ownerUserId;
            }
            $stmt->execute($params);
            $result[$key] = (int) $stmt->fetchColumn();
        }

        return $result;
    }
}
