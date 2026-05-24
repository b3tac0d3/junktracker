<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstateSale;
use Core\Controller;

final class EstateSaleCustomersController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = EstateSale::indexCountAllCustomers($businessId, $search);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $customers = EstateSale::indexListAllCustomers($businessId, $search, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($customers));

        $this->render('estate-customers/index', [
            'pageTitle' => 'Estate Customers',
            'search' => $search,
            'customers' => $customers,
            'pagination' => $pagination,
            'contactMethodOptions' => EstateSale::futureSalesContactMethodOptions(),
        ]);
    }

    public function checkDuplicates(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $candidate = [
            'first_name' => trim((string) ($_GET['first_name'] ?? '')),
            'last_name' => trim((string) ($_GET['last_name'] ?? '')),
            'email' => trim((string) ($_GET['email'] ?? '')),
            'phone' => trim((string) ($_GET['phone'] ?? '')),
        ];
        $exclude = (int) ($_GET['exclude_id'] ?? 0);
        $estateSaleId = (int) ($_GET['estate_sale_id'] ?? 0);
        $matches = EstateSale::findDuplicateCustomerMatches(
            $businessId,
            $candidate,
            $exclude > 0 ? $exclude : null,
            $estateSaleId > 0 ? $estateSaleId : null
        );

        $this->json(['ok' => true, 'matches' => $matches]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
