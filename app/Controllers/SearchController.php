<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Core\Controller;

final class SearchController extends Controller
{
    public function index(): void
    {
        require_auth();

        $query = trim((string) ($_GET['global_q'] ?? ''));
        $isGlobalSiteAdminContext = is_site_admin() && current_business_id() <= 0;

        $results = [
            'businesses' => [],
            'site_admin_users' => [],
            'clients' => [],
            'jobs' => [],
            'tasks' => [],
            'sales' => [],
            'purchases' => [],
            'billing' => [],
            'expenses' => [],
            'time_entries' => [],
        ];
        $totals = [
            'businesses' => 0,
            'site_admin_users' => 0,
            'clients' => 0,
            'jobs' => 0,
            'tasks' => 0,
            'sales' => 0,
            'purchases' => 0,
            'billing' => 0,
            'expenses' => 0,
            'time_entries' => 0,
        ];

        if ($query !== '') {
            if ($isGlobalSiteAdminContext) {
                $results['businesses'] = $this->searchBusinesses($query, 12);
                $results['site_admin_users'] = User::indexListGlobal($query, 12, 0);
                $totals['businesses'] = count($results['businesses']);
                $totals['site_admin_users'] = User::indexCountGlobal($query);
            } else {
                $businessId = current_business_id();
                $limit = 8;
                $workspaceRole = workspace_role();

                if ($workspaceRole === 'punch_only') {
                    $userId = (int) (auth_user_id() ?? 0);
                    $employee = $userId > 0 ? Employee::findByUserForBusiness($businessId, $userId) : null;
                    $scopeEmployeeId = (int) ($employee['id'] ?? 0);
                    if ($scopeEmployeeId > 0) {
                        $results['time_entries'] = TimeEntry::indexList($businessId, $query, '', $limit, 0, $scopeEmployeeId);
                        $totals['time_entries'] = TimeEntry::indexCount($businessId, $query, '', $scopeEmployeeId);
                    }
                } else {
                    $results['clients'] = Client::indexList($businessId, $query, $limit, 0);
                    $results['jobs'] = Job::indexList($businessId, $query, '', $limit, 0);
                    $results['tasks'] = Task::indexList($businessId, $query, '', $limit, 0);
                    $results['sales'] = Sale::indexList($businessId, $query, '', $limit, 0);
                    $results['purchases'] = Purchase::indexList($businessId, $query, '', $limit, 0);
                    $results['billing'] = Invoice::indexList($businessId, $query, '', $limit, 0);
                    $results['expenses'] = Expense::indexList($businessId, $query, 'all', $limit, 0);
                    $results['time_entries'] = TimeEntry::indexList($businessId, $query, '', $limit, 0, null);

                    $totals['clients'] = Client::indexCount($businessId, $query);
                    $totals['jobs'] = Job::indexCount($businessId, $query, '');
                    $totals['tasks'] = Task::indexCount($businessId, $query, '');
                    $totals['sales'] = Sale::indexCount($businessId, $query, '');
                    $totals['purchases'] = Purchase::indexCount($businessId, $query, '');
                    $totals['billing'] = Invoice::indexCount($businessId, $query, '');
                    $totals['expenses'] = Expense::indexCount($businessId, $query, 'all');
                    $totals['time_entries'] = TimeEntry::indexCount($businessId, $query, '', null);
                }
            }
        }

        $this->render('search/index', [
            'pageTitle' => 'Search',
            'query' => $query,
            'isGlobalSiteAdminContext' => $isGlobalSiteAdminContext,
            'results' => $results,
            'totals' => $totals,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchBusinesses(string $query, int $limit = 12): array
    {
        $rows = Business::allActive(500, 0);
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        $matches = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $legal = trim((string) ($row['legal_name'] ?? ''));
            $haystack = mb_strtolower($name . ' ' . $legal . ' #' . (string) $id);

            if ($needle !== '' && mb_strpos($haystack, $needle) === false) {
                continue;
            }

            $matches[] = $row;
            if (count($matches) >= max(1, $limit)) {
                break;
            }
        }

        return $matches;
    }
}
