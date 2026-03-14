<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReportSummary;
use Core\Controller;

final class ReportsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $fromDate = trim((string) ($_GET['from'] ?? $monthStart));
        $toDate = trim((string) ($_GET['to'] ?? $today));

        if (!$this->isValidDate($fromDate)) {
            $fromDate = $monthStart;
        }
        if (!$this->isValidDate($toDate)) {
            $toDate = $today;
        }
        if (strtotime($fromDate) > strtotime($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $businessId = current_business_id();
        $report = ReportSummary::build($businessId, $fromDate, $toDate);

        $this->render('reports/index', [
            'pageTitle' => 'Reports',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'report' => $report,
        ]);
    }

    private function isValidDate(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
