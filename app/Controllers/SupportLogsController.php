<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\DevTrackerItem;
use App\Models\DevTrackerLog;
use Core\Controller;

final class SupportLogsController extends Controller
{
    private const ROUTE_PREFIX = '/admin/support-logs';

    public function index(): void
    {
        $this->requireSubmissionAccess();

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $type = strtolower(trim((string) ($_GET['type'] ?? '')));
        if ($type !== '' && !DevTrackerItem::isCompanySubmissionType($type)) {
            $type = '';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null, 25);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = DevTrackerItem::companySubmissionsCountForBusiness($businessId, $search, $type);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $items = DevTrackerItem::companySubmissionsListForBusiness($businessId, $search, $type, $perPage, $offset);

        $this->render('support_logs/index', [
            'pageTitle' => 'Support Logs',
            'search' => $search,
            'type' => $type,
            'items' => $items,
            'pagination' => pagination_meta($page, $perPage, $totalRows, count($items)),
            'business' => Business::findById($businessId),
            'routePrefix' => self::ROUTE_PREFIX,
        ]);
    }

    public function show(array $params): void
    {
        $this->requireSubmissionAccess();

        $item = $this->itemOr404((int) ($params['id'] ?? 0));
        if ($item === null) {
            return;
        }

        $itemType = strtolower(trim((string) ($item['item_type'] ?? '')));
        $labels = DevTrackerItem::submissionLabels($itemType);

        $this->render('company_submissions/show', [
            'pageTitle' => $labels['section_singular'],
            'item' => $item,
            'logEntries' => DevTrackerLog::forItem((int) ($item['id'] ?? 0)),
            'business' => Business::findById((int) ($item['business_id'] ?? 0)),
            'labels' => $labels,
            'routePrefix' => self::ROUTE_PREFIX,
        ]);
    }

    protected function requireSubmissionAccess(): void
    {
        business_context_required();
        if (!can_manage_bug_reports()) {
            \Core\ErrorHandler::renderHttpError(403, 'Access denied', 'Support logs are limited to workspace admins.');
            exit;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function itemOr404(int $itemId): ?array
    {
        if ($itemId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        $item = DevTrackerItem::find($itemId);
        if (
            $item === null
            || !DevTrackerItem::belongsToBusiness($item, current_business_id())
            || !DevTrackerItem::isCompanySubmissionType((string) ($item['item_type'] ?? ''))
        ) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        return $item;
    }
}
