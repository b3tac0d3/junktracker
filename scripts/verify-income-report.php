<?php

declare(strict_types=1);

/**
 * Verify income report figures against the live database.
 * Usage: php scripts/verify-income-report.php [business_id] [from YYYY-MM-DD] [to YYYY-MM-DD]
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $root . '/app/bootstrap.php';

use App\Models\ReportSummary;
use App\Models\Sale;
use Core\Database;

$businessId = max(1, (int) ($argv[1] ?? 1));
$fromDate = trim((string) ($argv[2] ?? date('Y-01-01')));
$toDate = trim((string) ($argv[3] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    fwrite(STDERR, "Invalid date range.\n");
    exit(1);
}

$money = static fn (float $v): string => '$' . number_format($v, 2);
$close = static fn (float $a, float $b, float $tol = 0.02): bool => abs($a - $b) <= $tol;

try {
    Database::connection()->query('SELECT 1');
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$report = ReportSummary::build($businessId, $fromDate, $toDate);
$sales = is_array($report['sales'] ?? null) ? $report['sales'] : [];
$estateSales = is_array($report['estate_sales'] ?? null) ? $report['estate_sales'] : [];
$service = is_array($report['service'] ?? null) ? $report['service'] : [];
$expenses = is_array($report['expenses'] ?? null) ? $report['expenses'] : [];
$purchases = is_array($report['purchases'] ?? null) ? $report['purchases'] : [];
$overall = is_array($report['overall'] ?? null) ? $report['overall'] : [];

$servicePayments = ReportSummary::servicePaymentsTotalsForRange($businessId, $fromDate, $toDate);
$salesList = ReportSummary::salesInRange($businessId, $fromDate, $toDate);
$salesTotals = ReportSummary::salesTotalsForRange($businessId, $fromDate, $toDate);
$invoiceList = ReportSummary::invoicesInRange($businessId, $fromDate, $toDate);
$purchaseList = ReportSummary::purchasesInRange($businessId, $fromDate, $toDate);
$expenseData = ReportSummary::expenseReportData($businessId, $fromDate, $toDate);

$sumList = static function (array $rows, string $field): float {
    $total = 0.0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $total += (float) ($row[$field] ?? 0);
    }
    return round($total, 2);
};

$listSalesGross = $sumList($salesList, 'gross_amount');
$listSalesNet = $sumList($salesList, 'net_amount');
$listInvoiceGross = $sumList($invoiceList, 'total');
$listPurchaseTotal = $sumList($purchaseList, 'purchase_price');

$categoryTotal = 0.0;
foreach (is_array($expenseData['by_category'] ?? null) ? $expenseData['by_category'] : [] as $row) {
    if (is_array($row)) {
        $categoryTotal += (float) ($row['total'] ?? 0);
    }
}
$categoryTotal = round($categoryTotal, 2);

$estateByEvent = is_array($estateSales['by_event'] ?? null) ? $estateSales['by_event'] : [];
$estateEventGross = 0.0;
$estateEventNet = 0.0;
$estateEventTxn = 0;
foreach ($estateByEvent as $row) {
    if (!is_array($row)) {
        continue;
    }
    $estateEventGross += (float) ($row['gross'] ?? 0);
    $estateEventNet += (float) ($row['net'] ?? 0);
    $estateEventTxn += (int) ($row['transaction_count'] ?? 0);
}
$estateEventGross = round($estateEventGross, 2);
$estateEventNet = round($estateEventNet, 2);

$expectedGross = round(
    (float) ($sales['gross'] ?? 0) + (float) ($estateSales['gross'] ?? 0) + (float) ($service['gross'] ?? 0),
    2
);
$expectedNet = round(
    (float) ($sales['net'] ?? 0) + (float) ($estateSales['net'] ?? 0) + (float) ($service['net'] ?? 0) - (float) ($expenses['general_total'] ?? 0),
    2
);
$expectedNetMinusPurchases = round($expectedNet - (float) ($purchases['total'] ?? 0), 2);
$expectedServiceNet = round((float) ($service['gross'] ?? 0) - (float) ($service['job_expenses'] ?? 0), 2);
$expectedExpenseTotal = round((float) ($expenses['job_total'] ?? 0) + (float) ($expenses['general_total'] ?? 0), 2);

$checks = [
    'Overall gross = sales + estate + service (invoices)' => [
        'report' => (float) ($overall['gross'] ?? 0),
        'expected' => $expectedGross,
    ],
    'Overall net = sales + estate + service net − general expenses' => [
        'report' => (float) ($overall['net'] ?? 0),
        'expected' => $expectedNet,
    ],
    'After purchases = overall net − purchases' => [
        'report' => (float) ($overall['net_minus_purchases'] ?? 0),
        'expected' => $expectedNetMinusPurchases,
    ],
    'Service net = service gross − job expenses' => [
        'report' => (float) ($service['net'] ?? 0),
        'expected' => $expectedServiceNet,
    ],
    'Expenses total = job + general' => [
        'report' => (float) ($expenses['total'] ?? 0),
        'expected' => $expectedExpenseTotal,
    ],
    'Expense categories sum = expense total' => [
        'report' => (float) ($expenses['total'] ?? 0),
        'expected' => $categoryTotal,
    ],
    'Sales totals API matches build()' => [
        'report' => (float) ($sales['gross'] ?? 0),
        'expected' => (float) ($salesTotals['gross'] ?? 0),
    ],
    'Expense report API matches build()' => [
        'report' => (float) ($expenses['general_total'] ?? 0),
        'expected' => (float) ($expenseData['general_total'] ?? 0),
    ],
    'Estate events gross sum = estate gross' => [
        'report' => (float) ($estateSales['gross'] ?? 0),
        'expected' => $estateEventGross,
    ],
    'Estate events net sum = estate net' => [
        'report' => (float) ($estateSales['net'] ?? 0),
        'expected' => $estateEventNet,
    ],
    'Estate events txn sum = estate transaction count' => [
        'report' => (float) ($estateSales['transaction_count'] ?? $estateSales['count'] ?? 0),
        'expected' => (float) $estateEventTxn,
    ],
];

$listChecks = [];
if (count($salesList) < 200) {
    $listChecks['Sales list gross sum = sales gross'] = [
        'report' => (float) ($sales['gross'] ?? 0),
        'expected' => $listSalesGross,
    ];
    $listChecks['Sales list net sum = sales net'] = [
        'report' => (float) ($sales['net'] ?? 0),
        'expected' => $listSalesNet,
    ];
} else {
    $listChecks['Sales list (capped at 200 — gross sum may be low)'] = [
        'report' => (float) ($sales['gross'] ?? 0),
        'expected' => $listSalesGross,
        'warn_only' => true,
    ];
}

if (count($invoiceList) < 200) {
    $listChecks['Invoice list gross sum = service gross'] = [
        'report' => (float) ($service['gross'] ?? 0),
        'expected' => $listInvoiceGross,
    ];
} else {
    $listChecks['Invoice list (capped at 200 — gross sum may be low)'] = [
        'report' => (float) ($service['gross'] ?? 0),
        'expected' => $listInvoiceGross,
        'warn_only' => true,
    ];
}

if (count($purchaseList) < 200) {
    $listChecks['Purchase list sum = purchases total'] = [
        'report' => (float) ($purchases['total'] ?? 0),
        'expected' => $listPurchaseTotal,
    ];
}

echo "Income report verification\n";
echo "Business #{$businessId} · {$fromDate} to {$toDate}\n";
echo str_repeat('=', 78) . "\n\n";

echo "ReportSummary::build() totals\n";
echo str_repeat('-', 78) . "\n";
printf("%-28s %14s\n", 'Sales gross', $money((float) ($sales['gross'] ?? 0)));
printf("%-28s %14s\n", 'Sales net', $money((float) ($sales['net'] ?? 0)));
printf("%-28s %14s\n", 'Estate sales gross', $money((float) ($estateSales['gross'] ?? 0)));
printf("%-28s %14s\n", 'Estate sales net', $money((float) ($estateSales['net'] ?? 0)));
printf("%-28s %14s\n", 'Estate sales (events)', (string) ((int) ($estateSales['estate_sale_count'] ?? 0)));
printf("%-28s %14s\n", 'Estate transactions', (string) ((int) ($estateSales['transaction_count'] ?? $estateSales['count'] ?? 0)));
printf("%-28s %14s\n", 'Service gross (invoices)', $money((float) ($service['gross'] ?? 0)));
printf("%-28s %14s\n", 'Service payments received', $money((float) ($servicePayments['gross'] ?? 0)));
printf("%-28s %14s\n", 'Service job expenses', $money((float) ($service['job_expenses'] ?? 0)));
printf("%-28s %14s\n", 'Service net', $money((float) ($service['net'] ?? 0)));
printf("%-28s %14s\n", 'General expenses', $money((float) ($expenses['general_total'] ?? 0)));
printf("%-28s %14s\n", 'Job expenses (all)', $money((float) ($expenses['job_total'] ?? 0)));
printf("%-28s %14s\n", 'Purchases', $money((float) ($purchases['total'] ?? 0)));
printf("%-28s %14s\n", 'Overall gross', $money((float) ($overall['gross'] ?? 0)));
printf("%-28s %14s\n", 'Overall net (profit)', $money((float) ($overall['net'] ?? 0)));
printf("%-28s %14s\n", 'After purchases', $money((float) ($overall['net_minus_purchases'] ?? 0)));
echo "\n";

$dashboardNet = ReportSummary::overallNetForRange($businessId, $fromDate, $toDate);
printf("%-28s %14s  (dashboard uses payments for service)\n", 'Dashboard-style net', $money((float) ($dashboardNet['net'] ?? 0)));

$failures = 0;
$warnings = 0;

echo "\nInternal consistency checks\n";
echo str_repeat('-', 78) . "\n";
printf("%-52s %8s\n", 'Check', 'Result');
foreach (array_merge($checks, $listChecks) as $label => $row) {
    $ok = $close((float) $row['report'], (float) $row['expected']);
    $warnOnly = !empty($row['warn_only']);
    if (!$ok && $warnOnly) {
        printf("%-52s %8s\n", $label, 'WARN');
        $warnings++;
        continue;
    }
    if (!$ok) {
        printf("%-52s %8s  (report {$money((float) $row['report'])}, expected {$money((float) $row['expected'])})\n", $label, 'FAIL');
        $failures++;
        continue;
    }
    printf("%-52s %8s\n", $label, 'OK');
}

echo "\n";
if ($failures > 0) {
    echo "FAILED: {$failures} check(s) did not match.\n";
    exit(1);
}
if ($warnings > 0) {
    echo "PASSED with {$warnings} list-cap warning(s).\n";
    exit(0);
}
echo "PASSED: all checks match the database.\n";
