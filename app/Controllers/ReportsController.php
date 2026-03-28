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

        $this->render('reports/index', [
            'pageTitle' => 'Reports',
        ]);
    }

    public function income(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $report = ReportSummary::build($businessId, $fromDate, $toDate);

        $this->render('reports/income', [
            'pageTitle' => 'Income report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'report' => $report,
        ]);
    }

    public function jobsReport(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $jobs = ReportSummary::jobsInRange($businessId, $fromDate, $toDate);
        $jobsTotalCount = ReportSummary::jobsCountForRange($businessId, $fromDate, $toDate);
        $marginTotals = ReportSummary::marginTotalsForRange($businessId, $fromDate, $toDate);

        $this->render('reports/jobs_report', [
            'pageTitle' => 'Jobs report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'jobs' => $jobs,
            'jobsTotalCount' => $jobsTotalCount,
            'marginTotals' => $marginTotals,
        ]);
    }

    public function salesReport(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $salesList = ReportSummary::salesInRange($businessId, $fromDate, $toDate);
        $salesTotals = ReportSummary::salesTotalsForRange($businessId, $fromDate, $toDate);

        $this->render('reports/sales_report', [
            'pageTitle' => 'Sales report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'salesList' => $salesList,
            'salesTotals' => $salesTotals,
        ]);
    }

    public function purchasesReport(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $purchasesList = ReportSummary::purchasesInRange($businessId, $fromDate, $toDate);
        $purchaseTotals = ReportSummary::purchaseTotalsForRange($businessId, $fromDate, $toDate);

        $this->render('reports/purchases_report', [
            'pageTitle' => 'Purchases report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'purchasesList' => $purchasesList,
            'purchaseTotals' => $purchaseTotals,
        ]);
    }

    public function expensesReport(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $expenseReport = ReportSummary::expenseReportData($businessId, $fromDate, $toDate);

        $this->render('reports/expenses_report', [
            'pageTitle' => 'Expenses report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'expenseReport' => $expenseReport,
        ]);
    }

    public function serviceReport(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $invoicesList = ReportSummary::invoicesInRange($businessId, $fromDate, $toDate);
        $serviceTotals = ReportSummary::serviceTotalsForRange($businessId, $fromDate, $toDate);

        $this->render('reports/service_report', [
            'pageTitle' => 'Service (invoices) report',
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'invoicesList' => $invoicesList,
            'serviceTotals' => $serviceTotals,
        ]);
    }

    public function exportIncomeCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $report = ReportSummary::build($businessId, $fromDate, $toDate);

        $sales = is_array($report['sales'] ?? null) ? $report['sales'] : [];
        $service = is_array($report['service'] ?? null) ? $report['service'] : [];
        $expenses = is_array($report['expenses'] ?? null) ? $report['expenses'] : [];
        $purchases = is_array($report['purchases'] ?? null) ? $report['purchases'] : [];
        $overall = is_array($report['overall'] ?? null) ? $report['overall'] : [];
        $purchasesList = ReportSummary::purchasesInRange($businessId, $fromDate, $toDate);
        $marginByJob = is_array($report['margin_by_job'] ?? null) ? $report['margin_by_job'] : [];
        $expensesByCategory = is_array($expenses['by_category'] ?? null) ? $expenses['by_category'] : [];

        $out = $this->beginCsvStream('income-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Income report']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['Totals']);
        fputcsv($out, ['Metric', 'Amount']);
        fputcsv($out, ['Overall gross', $this->csvMoney($overall['gross'] ?? 0)]);
        fputcsv($out, ['Overall net', $this->csvMoney($overall['net'] ?? 0)]);
        fputcsv($out, ['Net minus purchases', $this->csvMoney($overall['net_minus_purchases'] ?? 0)]);
        fputcsv($out, ['Expense total', $this->csvMoney($expenses['total'] ?? 0)]);
        fputcsv($out, ['Purchase total', $this->csvMoney($purchases['total'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Service (invoices)']);
        fputcsv($out, ['Field', 'Value']);
        fputcsv($out, ['Count', (string) ((int) ($service['count'] ?? 0))]);
        fputcsv($out, ['Gross', $this->csvMoney($service['gross'] ?? 0)]);
        fputcsv($out, ['Job expenses', $this->csvMoney($service['job_expenses'] ?? 0)]);
        fputcsv($out, ['Net', $this->csvMoney($service['net'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Sales (summary)']);
        fputcsv($out, ['Field', 'Value']);
        fputcsv($out, ['Count', (string) ((int) ($sales['count'] ?? 0))]);
        fputcsv($out, ['Gross', $this->csvMoney($sales['gross'] ?? 0)]);
        fputcsv($out, ['Net', $this->csvMoney($sales['net'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Expenses']);
        fputcsv($out, ['Field', 'Value']);
        fputcsv($out, ['Count', (string) ((int) ($expenses['count'] ?? 0))]);
        fputcsv($out, ['Job expenses', $this->csvMoney($expenses['job_total'] ?? 0)]);
        fputcsv($out, ['General expenses', $this->csvMoney($expenses['general_total'] ?? 0)]);
        fputcsv($out, ['Total', $this->csvMoney($expenses['total'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Purchasing (summary)']);
        fputcsv($out, ['Field', 'Value']);
        fputcsv($out, ['Count', (string) ((int) ($purchases['count'] ?? 0))]);
        fputcsv($out, ['Total cost', $this->csvMoney($purchases['total'] ?? 0)]);
        fputcsv($out, []);

        if ($marginByJob !== []) {
            fputcsv($out, ['Margin by job']);
            fputcsv($out, ['Job ID', 'Title', 'Sales net', 'Purchase COGS', 'Margin']);
            foreach ($marginByJob as $row) {
                if (!is_array($row)) {
                    continue;
                }
                fputcsv($out, [
                    (string) ((int) ($row['job_id'] ?? 0)),
                    (string) ($row['title'] ?? ''),
                    $this->csvMoney($row['sales_net'] ?? 0),
                    $this->csvMoney($row['purchase_cogs'] ?? 0),
                    $this->csvMoney($row['margin'] ?? 0),
                ]);
            }
            fputcsv($out, []);
        }

        fputcsv($out, ['Purchases (within range)']);
        fputcsv($out, ['ID', 'Title', 'Client', 'Status', 'Purchase date', 'Price']);
        foreach ($purchasesList as $purchase) {
            if (!is_array($purchase)) {
                continue;
            }
            $pid = (int) ($purchase['id'] ?? 0);
            $status = trim((string) ($purchase['status'] ?? ''));
            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '';
            fputcsv($out, [
                (string) $pid,
                trim((string) ($purchase['title'] ?? '')) ?: ('Purchase #' . (string) $pid),
                trim((string) ($purchase['client_name'] ?? '')),
                $statusLabel,
                trim((string) ($purchase['purchase_date'] ?? '')),
                $this->csvMoney($purchase['purchase_price'] ?? 0),
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['Expenses by category']);
        fputcsv($out, ['Category', 'Record count', 'Total']);
        foreach ($expensesByCategory as $row) {
            if (!is_array($row)) {
                continue;
            }
            fputcsv($out, [
                trim((string) ($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized',
                (string) ((int) ($row['count'] ?? 0)),
                $this->csvMoney($row['total'] ?? 0),
            ]);
        }

        fclose($out);
    }

    public function exportJobsCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $jobs = ReportSummary::jobsInRange($businessId, $fromDate, $toDate);

        $out = $this->beginCsvStream('jobs-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Jobs within range']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['Job ID', 'Title', 'Client', 'Status', 'Scheduled start']);
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $jid = (int) ($job['id'] ?? 0);
            $status = trim((string) ($job['status'] ?? ''));
            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '';
            fputcsv($out, [
                (string) $jid,
                trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jid),
                trim((string) ($job['client_name'] ?? '')),
                $statusLabel,
                trim((string) ($job['scheduled_start_at'] ?? '')),
            ]);
        }

        fclose($out);
    }

    public function exportSalesCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $salesList = ReportSummary::salesInRange($businessId, $fromDate, $toDate);

        $out = $this->beginCsvStream('sales-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Sales within range']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['Sale ID', 'Name', 'Type', 'Sale date', 'Gross', 'Net']);
        foreach ($salesList as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $sid = (int) ($sale['id'] ?? 0);
            $type = trim((string) ($sale['type'] ?? ''));
            $typeLabel = $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '';
            fputcsv($out, [
                (string) $sid,
                trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $sid),
                $typeLabel,
                trim((string) ($sale['sale_date'] ?? '')),
                $this->csvMoney($sale['gross_amount'] ?? 0),
                $this->csvMoney($sale['net_amount'] ?? 0),
            ]);
        }

        fclose($out);
    }

    public function exportPurchasesCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $purchasesList = ReportSummary::purchasesInRange($businessId, $fromDate, $toDate);

        $out = $this->beginCsvStream('purchases-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Purchases within range']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['ID', 'Title', 'Client', 'Status', 'Purchase date', 'Price']);
        foreach ($purchasesList as $purchase) {
            if (!is_array($purchase)) {
                continue;
            }
            $pid = (int) ($purchase['id'] ?? 0);
            $status = trim((string) ($purchase['status'] ?? ''));
            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '';
            fputcsv($out, [
                (string) $pid,
                trim((string) ($purchase['title'] ?? '')) ?: ('Purchase #' . (string) $pid),
                trim((string) ($purchase['client_name'] ?? '')),
                $statusLabel,
                trim((string) ($purchase['purchase_date'] ?? '')),
                $this->csvMoney($purchase['purchase_price'] ?? 0),
            ]);
        }

        fclose($out);
    }

    public function exportExpensesCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $data = ReportSummary::expenseReportData($businessId, $fromDate, $toDate);

        $out = $this->beginCsvStream('expenses-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Expenses within range']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['Summary']);
        fputcsv($out, ['Field', 'Value']);
        fputcsv($out, ['Record count', (string) ((int) ($data['count'] ?? 0))]);
        fputcsv($out, ['Job-linked total', $this->csvMoney($data['job_total'] ?? 0)]);
        fputcsv($out, ['General total', $this->csvMoney($data['general_total'] ?? 0)]);
        fputcsv($out, ['Total', $this->csvMoney($data['total'] ?? 0)]);
        fputcsv($out, []);

        $byCategory = is_array($data['by_category'] ?? null) ? $data['by_category'] : [];
        fputcsv($out, ['By category']);
        fputcsv($out, ['Category', 'Record count', 'Total']);
        foreach ($byCategory as $row) {
            if (!is_array($row)) {
                continue;
            }
            fputcsv($out, [
                trim((string) ($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized',
                (string) ((int) ($row['count'] ?? 0)),
                $this->csvMoney($row['total'] ?? 0),
            ]);
        }

        fclose($out);
    }

    public function exportServiceCsv(): void
    {
        require_business_role(['general_user', 'admin']);

        [$fromDate, $toDate] = $this->resolveDateRange();
        $businessId = current_business_id();
        $invoicesList = ReportSummary::invoicesInRange($businessId, $fromDate, $toDate);

        $out = $this->beginCsvStream('service-invoices-report-' . $fromDate . '-to-' . $toDate . '.csv');
        if ($out === null) {
            return;
        }

        fputcsv($out, ['Service invoices within range']);
        fputcsv($out, ['Period from', $fromDate]);
        fputcsv($out, ['Period to', $toDate]);
        fputcsv($out, []);

        fputcsv($out, ['Invoice ID', 'Invoice #', 'Client', 'Status', 'Issue date', 'Due date', 'Total', 'Job ID']);
        foreach ($invoicesList as $inv) {
            if (!is_array($inv)) {
                continue;
            }
            $iid = (int) ($inv['id'] ?? 0);
            $status = trim((string) ($inv['status'] ?? ''));
            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '';
            fputcsv($out, [
                (string) $iid,
                trim((string) ($inv['invoice_number'] ?? '')),
                trim((string) ($inv['client_name'] ?? '')),
                $statusLabel,
                trim((string) ($inv['issue_date'] ?? '')),
                trim((string) ($inv['due_date'] ?? '')),
                $this->csvMoney($inv['total'] ?? 0),
                (string) ((int) ($inv['job_id'] ?? 0)),
            ]);
        }

        fclose($out);
    }

    /** @return resource|null */
    private function beginCsvStream(string $filename)
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?? $filename;
        if ($safe === '' || $safe === '.csv') {
            $safe = 'report.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($out === false) {
            return null;
        }

        return $out;
    }

    private function csvMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(): array
    {
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

        return [$fromDate, $toDate];
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
