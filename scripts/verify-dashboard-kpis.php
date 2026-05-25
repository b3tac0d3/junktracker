<?php

declare(strict_types=1);

/**
 * Offline verification of dashboard KPI math against a SQL dump.
 * Usage: php scripts/verify-dashboard-kpis.php "/path/to/dump.sql" [as-of-date YYYY-MM-DD]
 */

$dumpPath = $argv[1] ?? '';
$asOf = $argv[2] ?? '2026-05-25';
$businessId = 1;

if ($dumpPath === '' || !is_file($dumpPath)) {
    fwrite(STDERR, "Usage: php scripts/verify-dashboard-kpis.php /path/to/dump.sql [YYYY-MM-DD]\n");
    exit(1);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    fwrite(STDERR, "Invalid as-of date: {$asOf}\n");
    exit(1);
}

$dump = file_get_contents($dumpPath);
if ($dump === false) {
    fwrite(STDERR, "Could not read dump.\n");
    exit(1);
}

/**
 * @return list<array<string, mixed>>
 */
function parseInsertRows(string $dump, string $table): array
{
    if (!preg_match('/INSERT INTO `' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s*/s', $dump, $match, PREG_OFFSET_CAPTURE)) {
        return [];
    }

    $columns = array_map(
        static fn (string $c): string => trim($c, " `\t\n\r"),
        explode(',', $match[1][0])
    );

    $start = $match[0][1] + strlen($match[0][0]);
    $end = strpos($dump, ";\n", $start);
    if ($end === false) {
        $end = strpos($dump, ';', $start);
    }
    if ($end === false) {
        return [];
    }

    $valuesBlob = substr($dump, $start, $end - $start);
    $lines = preg_split('/\r\n|\n|\r/', $valuesBlob) ?: [];
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] !== '(') {
            continue;
        }
        $line = rtrim($line, ',');
        if (str_ends_with($line, ';')) {
            $line = substr($line, 0, -1);
        }
        if (!str_ends_with($line, ')')) {
            continue;
        }
        $inner = substr($line, 1, -1);
        $values = str_getcsv($inner, ',', "'", '\\');
        if (count($values) !== count($columns)) {
            continue;
        }
        $row = [];
        foreach ($columns as $i => $col) {
            $v = trim((string) $values[$i]);
            if ($v === '' || strtoupper($v) === 'NULL') {
                $row[$col] = null;
            } elseif (is_numeric($v)) {
                $row[$col] = str_contains($v, '.') ? (float) $v : (int) $v;
            } else {
                $row[$col] = $v;
            }
        }
        $rows[] = $row;
    }

    return $rows;
}

function inRange(?string $date, string $from, string $to): bool
{
    if ($date === null || trim($date) === '') {
        return false;
    }
    $d = substr(trim($date), 0, 10);
    return $d >= $from && $d <= $to;
}

function money(float $n): string
{
    return '$' . number_format($n, 2);
}

$mtdFrom = date('Y-m-01', strtotime($asOf));
$ytdFrom = date('Y-01-01', strtotime($asOf));

$sales = parseInsertRows($dump, 'sales');
$payments = parseInsertRows($dump, 'payments');
$invoices = parseInsertRows($dump, 'invoices');
$expenses = parseInsertRows($dump, 'expenses');
$purchases = parseInsertRows($dump, 'purchases');
$estateSales = parseInsertRows($dump, 'estate_sales');

$filterBiz = static fn (array $row): bool => (int) ($row['business_id'] ?? 0) === $businessId && ($row['deleted_at'] ?? null) === null;

$sales = array_values(array_filter($sales, $filterBiz));
$payments = array_values(array_filter($payments, $filterBiz));
$invoices = array_values(array_filter($invoices, $filterBiz));
$expenses = array_values(array_filter($expenses, $filterBiz));
$purchases = array_values(array_filter($purchases, $filterBiz));
$estateSales = array_values(array_filter($estateSales, $filterBiz));

// --- Sales (general scope: exclude estate_sale_id) ---
$salesGeneral = array_values(array_filter($sales, static fn (array $r): bool => empty($r['estate_sale_id'])));
$sumSales = static function (array $rows, string $from, string $to) use ($asOf): array {
    $gross = 0.0;
    $net = 0.0;
    $count = 0;
    foreach ($rows as $r) {
        $d = substr((string) ($r['sale_date'] ?? ''), 0, 10);
        if (!inRange($d, $from, $to)) {
            continue;
        }
        $count++;
        $gross += (float) ($r['gross_amount'] ?? 0);
        $net += (float) ($r['net_amount'] ?? ($r['gross_amount'] ?? 0));
    }
    return ['gross' => round($gross, 2), 'net' => round($net, 2), 'count' => $count];
};
$salesMtd = $sumSales($salesGeneral, $mtdFrom, $asOf);
$salesYtd = $sumSales($salesGeneral, $ytdFrom, $asOf);

// --- Service paid (payments on invoices, by paid_at) ---
$invoiceById = [];
foreach ($invoices as $inv) {
    $invoiceById[(int) $inv['id']] = $inv;
}
$openInvoiceStatuses = ['paid', 'paid_in_full', 'cancelled', 'declined', 'closed', 'write_off'];
$servicePayments = [];
foreach ($payments as $p) {
    $inv = $invoiceById[(int) ($p['invoice_id'] ?? 0)] ?? null;
    if ($inv === null) {
        continue;
    }
    if (strtolower((string) ($inv['type'] ?? 'invoice')) !== 'invoice') {
        continue;
    }
    $status = strtolower((string) ($inv['status'] ?? ''));
    if (in_array($status, ['cancelled', 'declined', 'closed'], true)) {
        continue;
    }
    $servicePayments[] = $p;
}
$sumPayments = static function (array $rows, string $from, string $to): array {
    $total = 0.0;
    $count = 0;
    foreach ($rows as $r) {
        $d = substr((string) ($r['paid_at'] ?? $r['created_at'] ?? ''), 0, 10);
        if (!inRange($d, $from, $to)) {
            continue;
        }
        $count++;
        $total += (float) ($r['amount'] ?? 0);
    }
    return ['gross' => round($total, 2), 'count' => $count];
};
$serviceMtd = $sumPayments($servicePayments, $mtdFrom, $asOf);
$serviceYtd = $sumPayments($servicePayments, $ytdFrom, $asOf);

// --- Expenses ---
$sumExpenses = static function (array $rows, string $from, string $to, ?bool $jobOnly = null): float {
    $total = 0.0;
    foreach ($rows as $r) {
        $d = substr((string) ($r['expense_date'] ?? ''), 0, 10);
        if (!inRange($d, $from, $to)) {
            continue;
        }
        $hasJob = !empty($r['job_id']);
        if ($jobOnly === true && !$hasJob) {
            continue;
        }
        if ($jobOnly === false && $hasJob) {
            continue;
        }
        $total += (float) ($r['amount'] ?? 0);
    }
    return round($total, 2);
};
$expensesMtd = $sumExpenses($expenses, $mtdFrom, $asOf);
$expensesYtd = $sumExpenses($expenses, $ytdFrom, $asOf);
$generalExpensesMtd = $sumExpenses($expenses, $mtdFrom, $asOf, false);
$generalExpensesYtd = $sumExpenses($expenses, $ytdFrom, $asOf, false);
$jobExpensesMtd = $sumExpenses($expenses, $mtdFrom, $asOf, true);
$jobExpensesYtd = $sumExpenses($expenses, $ytdFrom, $asOf, true);

// --- Purchases ---
$sumPurchases = static function (array $rows, string $from, string $to): array {
    $total = 0.0;
    $count = 0;
    foreach ($rows as $r) {
        $d = substr((string) ($r['purchase_date'] ?? $r['created_at'] ?? ''), 0, 10);
        if (!inRange($d, $from, $to)) {
            continue;
        }
        $count++;
        $total += (float) ($r['purchase_price'] ?? 0);
    }
    return ['total' => round($total, 2), 'count' => $count];
};
$purchasesMtd = $sumPurchases($purchases, $mtdFrom, $asOf);
$purchasesYtd = $sumPurchases($purchases, $ytdFrom, $asOf);

// --- Estate sales (on-site transactions with estate_sale_id) ---
$estateSaleById = [];
foreach ($estateSales as $es) {
    $estateSaleById[(int) $es['id']] = $es;
}
$estateTx = array_values(array_filter($sales, static fn (array $r): bool => !empty($r['estate_sale_id'])));
$estateGrossMtd = 0.0;
$estateGrossYtd = 0.0;
$estateNetMtd = 0.0;
$estateNetYtd = 0.0;
$estateCountMtd = 0;
$estateCountYtd = 0;
foreach ($estateTx as $r) {
    $d = substr((string) ($r['sale_date'] ?? ''), 0, 10);
    $gross = (float) ($r['gross_amount'] ?? 0);
    $esId = (int) ($r['estate_sale_id'] ?? 0);
    $es = $estateSaleById[$esId] ?? [];
    $clientPct = (float) ($es['client_percentage'] ?? 0);
    $ourSharePct = max(0.0, 100.0 - $clientPct) / 100.0;
    $netShare = round($gross * $ourSharePct, 2);

    if (inRange($d, $mtdFrom, $asOf)) {
        $estateGrossMtd += $gross;
        $estateNetMtd += $netShare;
        $estateCountMtd++;
    }
    if (inRange($d, $ytdFrom, $asOf)) {
        $estateGrossYtd += $gross;
        $estateNetYtd += $netShare;
        $estateCountYtd++;
    }
}
$estateGrossMtd = round($estateGrossMtd, 2);
$estateGrossYtd = round($estateGrossYtd, 2);
$estateNetMtd = round($estateNetMtd, 2);
$estateNetYtd = round($estateNetYtd, 2);

// --- Receivables ---
$paidByInvoice = [];
foreach ($payments as $p) {
    $iid = (int) ($p['invoice_id'] ?? 0);
    $paidByInvoice[$iid] = ($paidByInvoice[$iid] ?? 0.0) + (float) ($p['amount'] ?? 0);
}
$paymentsDue = 0.0;
$pastDue = 0.0;
$openCount = 0;
$openDetails = [];
$pastDueDetails = [];
foreach ($invoices as $inv) {
    if (strtolower((string) ($inv['type'] ?? 'invoice')) !== 'invoice') {
        continue;
    }
    $status = strtolower((string) ($inv['status'] ?? ''));
    if (in_array($status, $openInvoiceStatuses, true)) {
        continue;
    }
    $total = (float) ($inv['total'] ?? 0);
    $paid = (float) ($paidByInvoice[(int) $inv['id']] ?? 0);
    $balance = round(max($total - $paid, 0), 2);
    if ($balance <= 0.009) {
        continue;
    }
    $openCount++;
    $paymentsDue += $balance;
    $due = substr((string) ($inv['due_date'] ?? ''), 0, 10);
    $issue = substr((string) ($inv['issue_date'] ?? ''), 0, 10);
    $openDetails[] = [
        'id' => (int) $inv['id'],
        'number' => (string) ($inv['invoice_number'] ?? $inv['id']),
        'issue_date' => $issue,
        'due_date' => $due,
        'balance' => $balance,
        'status' => $status,
    ];
    if ($due !== '' && $due < $asOf) {
        $pastDue += $balance;
        $pastDueDetails[] = end($openDetails);
    }
}
$paymentsDue = round($paymentsDue, 2);
$pastDue = round($pastDue, 2);

// --- ReportSummary-style net income ---
$sumInvoiceGross = static function (array $rows, string $from, string $to): float {
    $total = 0.0;
    foreach ($rows as $inv) {
        if (strtolower((string) ($inv['type'] ?? 'invoice')) !== 'invoice') {
            continue;
        }
        $issue = substr((string) ($inv['issue_date'] ?? ''), 0, 10);
        if (!inRange($issue, $from, $to)) {
            continue;
        }
        $total += (float) ($inv['total'] ?? 0);
    }
    return round($total, 2);
};
$serviceInvoiceMtd = $sumInvoiceGross($invoices, $mtdFrom, $asOf);
$serviceInvoiceYtd = $sumInvoiceGross($invoices, $ytdFrom, $asOf);
$serviceNetMtd = round($serviceInvoiceMtd - $jobExpensesMtd, 2);
$serviceNetYtd = round($serviceInvoiceYtd - $jobExpensesYtd, 2);
$reportMtdNet = round($salesMtd['net'] + $estateNetMtd + $serviceNetMtd - $generalExpensesMtd, 2);
$reportYtdNet = round($salesYtd['net'] + $estateNetYtd + $serviceNetYtd - $generalExpensesYtd, 2);
$profitAfterPurchases = round($reportYtdNet - $purchasesYtd['total'], 2);

$expected = [
    'Sales MTD gross' => $salesMtd['gross'],
    'Sales YTD gross' => $salesYtd['gross'],
    'Sales MTD net' => $salesMtd['net'],
    'Sales YTD net' => $salesYtd['net'],
    'Service MTD paid' => $serviceMtd['gross'],
    'Service YTD paid' => $serviceYtd['gross'],
    'Total income MTD gross' => round($salesMtd['gross'] + $estateGrossMtd + $serviceMtd['gross'], 2),
    'Total income YTD gross' => round($salesYtd['gross'] + $estateGrossYtd + $serviceYtd['gross'], 2),
    'Total income MTD net' => $reportMtdNet,
    'Total income YTD net' => $reportYtdNet,
    'Service invoice MTD (issue date)' => $serviceInvoiceMtd,
    'Service invoice YTD (issue date)' => $serviceInvoiceYtd,
    'Job expenses MTD' => $jobExpensesMtd,
    'General expenses MTD' => $generalExpensesMtd,
    'Purchases MTD' => $purchasesMtd['total'],
    'Purchases YTD' => $purchasesYtd['total'],
    'Purchases MTD count' => $purchasesMtd['count'],
    'Purchases YTD count' => $purchasesYtd['count'],
    'Expenses MTD' => $expensesMtd,
    'Expenses YTD' => $expensesYtd,
    'Estate sales MTD gross' => $estateGrossMtd,
    'Estate sales YTD gross' => $estateGrossYtd,
    'Estate sales MTD net' => $estateNetMtd,
    'Estate sales YTD net' => $estateNetYtd,
    'Payments due' => $paymentsDue,
    'Past due' => $pastDue,
    'Open invoices' => $openCount,
    'Profit YTD' => $reportYtdNet,
    'Profit YTD after purchases' => $profitAfterPurchases,
];

$screenshot = [
    'Sales MTD gross' => 3935.00,
    'Sales YTD gross' => 70143.30,
    'Sales MTD net' => 3935.00,
    'Sales YTD net' => 57323.61,
    'Service MTD paid' => 13975.00,
    'Service YTD paid' => 48785.00,
    'Total income MTD gross' => 23843.00,
    'Total income YTD gross' => 124861.30,
    'Total income MTD net' => 10503.20,
    'Total income YTD net' => 54208.75,
    'Purchases MTD' => 300.00,
    'Purchases YTD' => 4450.00,
    'Purchases MTD count' => 1,
    'Purchases YTD count' => 12,
    'Expenses MTD' => 9458.55,
    'Expenses YTD' => 53546.61,
    'Estate sales MTD gross' => 5933.00,
    'Estate sales YTD gross' => 5933.00,
    'Estate sales MTD net' => 1626.75,
    'Estate sales YTD net' => 1626.75,
    'Payments due' => 9850.00,
    'Past due' => 0.00,
    'Open invoices' => 5,
    'Profit YTD' => 54208.75,
    'Profit YTD after purchases' => 49758.75,
];

echo "Dashboard KPI verification as of {$asOf}\n";
echo str_repeat('=', 72) . "\n";
printf("%-32s %14s %14s %8s\n", 'Metric', 'Calculated', 'Screenshot', 'Match?');
echo str_repeat('-', 72) . "\n";
foreach ($expected as $label => $calc) {
    $shot = $screenshot[$label] ?? null;
    $match = $shot !== null && abs((float) $calc - (float) $shot) < 0.02 ? 'OK' : ($shot === null ? '—' : 'DIFF');
    printf("%-32s %14s %14s %8s\n", $label, money((float) $calc), $shot !== null ? money((float) $shot) : '—', $match);
}

echo "\nOpen invoices (balance by due date):\n";
foreach ($openDetails as $row) {
    $past = ($row['due_date'] !== '' && $row['due_date'] < $asOf) ? 'PAST DUE' : 'current';
    printf(
        "  #%s id=%d issue=%s due=%s balance=%s status=%s [%s]\n",
        $row['number'],
        $row['id'],
        $row['issue_date'],
        $row['due_date'] !== '' ? $row['due_date'] : '—',
        money($row['balance']),
        $row['status'],
        $past
    );
}

if ($pastDueDetails !== []) {
    echo "\nPast due breakdown:\n";
    foreach ($pastDueDetails as $row) {
        printf("  #%s due=%s balance=%s\n", $row['number'], $row['due_date'], money($row['balance']));
    }
} else {
    echo "\nNo past-due invoices as of {$asOf} (all open balances have due_date >= {$asOf}).\n";
}
