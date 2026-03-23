<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ClientBoloProfile;
use Core\Controller;

final class BoloController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!ClientBoloProfile::isAvailable()) {
            flash('error', 'BOLO tables are missing. Run the latest database migration.');
            redirect('/clients');
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'name')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'asc')));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'active')));

        if (!in_array($sortBy, ['name', 'updated', 'client_id'], true)) {
            $sortBy = 'name';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = ClientBoloProfile::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $rows = ClientBoloProfile::indexList($businessId, $search, $perPage, $offset, $sortBy, $sortDir, $status);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($rows));

        $this->render('bolo/index', [
            'pageTitle' => 'BOLO list',
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'status' => $status,
            'rows' => $rows,
            'pagination' => $pagination,
            'hasActiveFlag' => ClientBoloProfile::hasActiveFlag(),
        ]);
    }
}
