<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PerformanceSnapshot;
use App\Models\ReportingHub;
use Core\Controller;

final class ReportsController extends Controller
{
    private const REPORT_KEYS = [
        'overview',
        'job_profitability',
        'disposal_performance',
        'employee_labor',
        'sales_by_source',
    ];

    public function index(): void
    {
        require_permission('reports', 'view');

        $userId = auth_user_id() ?? 0;
        $presets = ReportingHub::presetsForUser($userId);
        $selectedPresetId = $this->toIntOrNull($_GET['preset_id'] ?? null);

        $range = ReportingHub::normalizeDateRange(
            (string) ($_GET['start_date'] ?? ''),
            (string) ($_GET['end_date'] ?? '')
        );

        $activeReport = strtolower(trim((string) ($_GET['report'] ?? 'overview')));
        if (!in_array($activeReport, self::REPORT_KEYS, true)) {
            $activeReport = 'overview';
        }

        if ($selectedPresetId !== null && $selectedPresetId > 0) {
            foreach ($presets as $preset) {
                if ((int) ($preset['id'] ?? 0) !== $selectedPresetId) {
                    continue;
                }

                $range = ReportingHub::normalizeDateRange(
                    (string) ($preset['start_date'] ?? ''),
                    (string) ($preset['end_date'] ?? '')
                );
                $activeReport = strtolower(trim((string) ($preset['report_key'] ?? 'overview')));
                if (!in_array($activeReport, self::REPORT_KEYS, true)) {
                    $activeReport = 'overview';
                }
                break;
            }
        }

        $jobProfitability = ReportingHub::jobProfitability($range['start_date'], $range['end_date']);
        $disposalPerformance = ReportingHub::disposalSpendVsScrapRevenue($range['start_date'], $range['end_date']);
        $employeeLabor = ReportingHub::employeeLaborCost($range['start_date'], $range['end_date']);
        $salesBySource = ReportingHub::salesBySource($range['start_date'], $range['end_date']);
        $totals = ReportingHub::totals($range['start_date'], $range['end_date']);

        if ((string) ($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv(
                $activeReport,
                $range,
                $jobProfitability,
                $disposalPerformance,
                $employeeLabor,
                $salesBySource,
                $totals
            );
            return;
        }

        $this->render('reports/index', [
            'pageTitle' => 'Reporting Hub',
            'activeReport' => $activeReport,
            'range' => $range,
            'totals' => $totals,
            'jobProfitability' => $jobProfitability,
            'disposalPerformance' => $disposalPerformance,
            'employeeLabor' => $employeeLabor,
            'salesBySource' => $salesBySource,
            'presets' => $presets,
            'selectedPresetId' => $selectedPresetId,
            'reportKeys' => self::REPORT_KEYS,
        ]);
    }

    public function savePreset(): void
    {
        require_permission('reports', 'create');

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/reports');
        }

        $name = trim((string) ($_POST['preset_name'] ?? ''));
        if ($name === '') {
            flash('error', 'Preset name is required.');
            redirect('/reports');
        }

        $reportKey = strtolower(trim((string) ($_POST['report'] ?? 'overview')));
        if (!in_array($reportKey, self::REPORT_KEYS, true)) {
            $reportKey = 'overview';
        }

        $range = ReportingHub::normalizeDateRange(
            (string) ($_POST['start_date'] ?? ''),
            (string) ($_POST['end_date'] ?? '')
        );

        $presetId = ReportingHub::savePreset($userId, $name, $reportKey, $range['start_date'], $range['end_date'], [
            'report' => $reportKey,
        ]);

        log_user_action('report_preset_saved', 'report_presets', $presetId > 0 ? $presetId : null, 'Saved report preset "' . $name . '".');
        flash('success', 'Report preset saved.');

        $query = http_build_query([
            'report' => $reportKey,
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'preset_id' => $presetId > 0 ? $presetId : null,
        ]);
        redirect('/reports' . ($query !== '' ? '?' . $query : ''));
    }

    public function deletePreset(array $params): void
    {
        require_permission('reports', 'delete');

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/reports');
        }

        $presetId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($presetId <= 0) {
            flash('error', 'Invalid report preset.');
            redirect('/reports');
        }

        ReportingHub::deletePreset($presetId, $userId);
        log_user_action('report_preset_deleted', 'report_presets', $presetId, 'Deleted report preset #' . $presetId . '.');

        flash('success', 'Report preset deleted.');
        redirect('/reports');
    }

    public function snapshot(): void
    {
        require_permission('reports', 'view');

        $businessId = function_exists('current_business_id') ? (int) current_business_id() : 1;
        if ($businessId <= 0) {
            flash('warning', 'Select a business workspace to use snapshots.');
            redirect('/site-admin');
        }

        $snapshotId = (int) ($this->toIntOrNull($_GET['snapshot_id'] ?? null) ?? 0);
        $preset = trim((string) ($_GET['preset'] ?? 'month'));
        if ($preset === '') {
            $preset = 'month';
        }

        $range = PerformanceSnapshot::resolveRange(
            $preset,
            (string) ($_GET['start_date'] ?? ''),
            (string) ($_GET['end_date'] ?? '')
        );

        $selectedSnapshot = null;
        $report = [];
        $isSavedSnapshot = false;

        if ($snapshotId > 0) {
            $selectedSnapshot = PerformanceSnapshot::findSnapshot($snapshotId);
            if (is_array($selectedSnapshot)) {
                $savedPayload = is_array($selectedSnapshot['payload'] ?? null) ? $selectedSnapshot['payload'] : [];
                $report = $savedPayload;
                $range = ReportingHub::normalizeDateRange(
                    (string) ($selectedSnapshot['start_date'] ?? $range['start_date']),
                    (string) ($selectedSnapshot['end_date'] ?? $range['end_date'])
                );
                $isSavedSnapshot = true;
                $preset = 'custom';
            } else {
                flash('warning', 'Snapshot not found for this business.');
            }
        }

        if (empty($report)) {
            $report = PerformanceSnapshot::buildReport($range['start_date'], $range['end_date']);
        }

        $snapshots = PerformanceSnapshot::snapshots(24);
        $comparisonRanges = is_array($report['comparison_ranges'] ?? null) ? $report['comparison_ranges'] : [];
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $comparison = is_array($report['comparison'] ?? null) ? $report['comparison'] : [];
        $expenseBreakdown = is_array($report['expense_breakdown'] ?? null) ? $report['expense_breakdown'] : [];
        $jobs = is_array($report['jobs'] ?? null) ? $report['jobs'] : [];
        $sales = is_array($report['sales'] ?? null) ? $report['sales'] : [];
        $charts = is_array($report['charts'] ?? null) ? $report['charts'] : [];

        $pageScripts = implode("\n", [
            '<script src="' . asset('js/reports-snapshot.js') . '?v=' . rawurlencode((string) config('app.version', 'dev')) . '"></script>',
        ]);

        $this->render('reports/snapshot', [
            'pageTitle' => 'Snapshot Hub',
            'range' => $range,
            'preset' => $preset,
            'summary' => $summary,
            'comparison' => $comparison,
            'comparisonRanges' => $comparisonRanges,
            'expenseBreakdown' => $expenseBreakdown,
            'jobs' => $jobs,
            'sales' => $sales,
            'charts' => $charts,
            'snapshots' => $snapshots,
            'snapshotId' => $snapshotId,
            'selectedSnapshot' => $selectedSnapshot,
            'isSavedSnapshot' => $isSavedSnapshot,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function saveSnapshot(): void
    {
        require_permission('reports', 'create');

        $businessId = function_exists('current_business_id') ? (int) current_business_id() : 1;
        if ($businessId <= 0) {
            flash('warning', 'Select a business workspace to save snapshots.');
            redirect('/site-admin');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/reports/snapshot');
        }

        $preset = trim((string) ($_POST['preset'] ?? 'month'));
        if ($preset === '') {
            $preset = 'month';
        }

        $range = PerformanceSnapshot::resolveRange(
            $preset,
            (string) ($_POST['start_date'] ?? ''),
            (string) ($_POST['end_date'] ?? '')
        );
        $label = trim((string) ($_POST['snapshot_label'] ?? ''));

        $report = PerformanceSnapshot::buildReport($range['start_date'], $range['end_date']);
        $snapshotId = PerformanceSnapshot::saveSnapshot(
            auth_user_id() ?? 0,
            $label,
            $range['start_date'],
            $range['end_date'],
            $report
        );

        if ($snapshotId <= 0) {
            flash('error', 'Unable to save snapshot right now. Please try again.');
            $query = http_build_query([
                'preset' => $preset,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
            ]);
            redirect('/reports/snapshot' . ($query !== '' ? '?' . $query : ''));
        }

        log_user_action(
            'snapshot_saved',
            'performance_snapshots',
            $snapshotId,
            'Saved performance snapshot "' . ($label !== '' ? $label : ('Snapshot ' . $range['start_date'] . ' to ' . $range['end_date'])) . '".'
        );

        flash('success', 'Snapshot saved.');
        redirect('/reports/snapshot?snapshot_id=' . $snapshotId);
    }

    private function exportCsv(
        string $report,
        array $range,
        array $jobProfitability,
        array $disposalPerformance,
        array $employeeLabor,
        array $salesBySource,
        array $totals
    ): void {
        $suffix = $range['start_date'] . '_to_' . $range['end_date'];

        if ($report === 'job_profitability') {
            $rows = [];
            foreach ($jobProfitability as $row) {
                $rows[] = [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['client_name'] ?? ''),
                    (string) ($row['job_status'] ?? ''),
                    number_format((float) ($row['invoice_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['scrap_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['dump_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['expense_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['labor_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['revenue_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['cost_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['net_total'] ?? 0), 2, '.', ''),
                ];
            }

            stream_csv_download(
                'job-profitability-' . $suffix . '.csv',
                ['Job ID', 'Job Name', 'Client', 'Status', 'Invoice', 'Scrap', 'Dump', 'Expenses', 'Labor', 'Revenue', 'Costs', 'Net'],
                $rows
            );
            return;
        }

        if ($report === 'disposal_performance') {
            $rows = [];
            foreach ($disposalPerformance as $row) {
                $rows[] = [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['type'] ?? ''),
                    number_format((float) ($row['scrap_revenue'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['dump_spend'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['expense_spend'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['net_total'] ?? 0), 2, '.', ''),
                ];
            }

            stream_csv_download(
                'disposal-performance-' . $suffix . '.csv',
                ['Location ID', 'Location', 'Type', 'Scrap Revenue', 'Dump Spend', 'Expense Spend', 'Net'],
                $rows
            );
            return;
        }

        if ($report === 'employee_labor') {
            $rows = [];
            foreach ($employeeLabor as $row) {
                $rows[] = [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['employee_name'] ?? ''),
                    (string) ($row['entry_count'] ?? ''),
                    (string) ($row['total_minutes'] ?? ''),
                    number_format((float) ($row['total_paid'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['hourly_effective_rate'] ?? 0), 2, '.', ''),
                    (string) ($row['non_job_minutes'] ?? ''),
                ];
            }

            stream_csv_download(
                'employee-labor-' . $suffix . '.csv',
                ['Employee ID', 'Employee', 'Entries', 'Minutes', 'Paid', 'Effective Rate', 'Non-Job Minutes'],
                $rows
            );
            return;
        }

        if ($report === 'sales_by_source') {
            $rows = [];
            foreach ($salesBySource as $row) {
                $rows[] = [
                    (string) ($row['source'] ?? ''),
                    (string) ($row['sale_count'] ?? ''),
                    number_format((float) ($row['gross_total'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['net_total'] ?? 0), 2, '.', ''),
                ];
            }

            stream_csv_download(
                'sales-by-source-' . $suffix . '.csv',
                ['Source', 'Count', 'Gross', 'Net'],
                $rows
            );
            return;
        }

        $rows = [[
            number_format((float) ($totals['jobs_revenue'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['jobs_cost'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['jobs_net'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['sales_gross'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['sales_net'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['combined_gross'] ?? 0), 2, '.', ''),
            number_format((float) ($totals['combined_net'] ?? 0), 2, '.', ''),
        ]];

        stream_csv_download(
            'report-overview-' . $suffix . '.csv',
            ['Jobs Revenue', 'Jobs Costs', 'Jobs Net', 'Sales Gross', 'Sales Net', 'Combined Gross', 'Combined Net'],
            $rows
        );
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
