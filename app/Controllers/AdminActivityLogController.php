<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Core\Controller;

final class AdminActivityLogController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $userId = (int) ($_GET['user_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));
        $entity = trim((string) ($_GET['entity'] ?? ''));

        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = '';
        }

        $filters = [
            'q' => $search,
            'user_id' => $userId,
            'date' => $date,
            'entity' => $entity,
        ];

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = AuditLog::indexCount($businessId, $filters);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $entries = AuditLog::indexList($businessId, $filters, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($entries));

        $userOptions = User::indexListForBusiness($businessId, '', 'all', 500, 0);
        $entityOptions = AuditLog::distinctEntities($businessId);

        $today = date('Y-m-d');
        $filterQuery = array_filter([
            'q' => $search,
            'user_id' => $userId > 0 ? (string) $userId : '',
            'date' => $date,
            'entity' => $entity,
            'per_page' => (string) $perPage,
        ], static fn (string $value): bool => $value !== '');

        $this->render('admin/activity-log/index', [
            'pageTitle' => 'Activity Log',
            'search' => $search,
            'userId' => $userId,
            'date' => $date,
            'entity' => $entity,
            'today' => $today,
            'entries' => $entries,
            'pagination' => $pagination,
            'userOptions' => $userOptions,
            'entityOptions' => $entityOptions,
            'filterQuery' => $filterQuery,
        ]);
    }
}
