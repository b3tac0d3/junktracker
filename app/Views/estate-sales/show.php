<?php
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$customers = is_array($customers ?? null) ? $customers : [];
$estateSaleId = (int) ($estateSale['id'] ?? 0);
$title = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
$status = strtolower(trim((string) ($estateSale['status'] ?? '')));
$customerCount = (int) ($estateSale['customer_count'] ?? count($customers));

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};

$formatDt = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y g:i A', $ts);
};

$line1 = trim((string) ($estateSale['address_line1'] ?? ''));
$line2 = trim((string) ($estateSale['address_line2'] ?? ''));
$addrParts = array_filter([
    $line1,
    $line2,
    trim((string) ($estateSale['city'] ?? '')),
    trim((string) ($estateSale['state'] ?? '')),
    trim((string) ($estateSale['postal_code'] ?? '')),
], static fn (string $v): bool => $v !== '');
$addrDisplay = $addrParts !== [] ? implode(', ', $addrParts) : '—';
$mapsAddressUrl = maps_directions_url_from_parts([
    (string) ($estateSale['address_line1'] ?? ''),
    (string) ($estateSale['address_line2'] ?? ''),
    (string) ($estateSale['city'] ?? ''),
    (string) ($estateSale['state'] ?? ''),
    (string) ($estateSale['postal_code'] ?? ''),
]);
$notes = trim((string) ($estateSale['notes'] ?? ''));
$csrfToken = csrf_token();
$stateOptions = us_state_options();
$canRemoveCustomers = (bool) ($canRemoveCustomers ?? false);
$expenses = is_array($expenses ?? null) ? $expenses : [];
$expenseCategoryOptions = is_array($expenseCategoryOptions ?? null) ? $expenseCategoryOptions : [];
$financialSummary = is_array($financialSummary ?? null) ? $financialSummary : [];
$sales = is_array($sales ?? null) ? $sales : [];
$salesCount = (int) ($salesCount ?? count($sales));
$salesTotal = (float) ($salesTotal ?? 0);
$customersPagination = is_array($customersPagination ?? null) ? $customersPagination : pagination_meta(1, 25, $customerCount, count($customers));
$customerPresence = is_array($customerPresence ?? null) ? $customerPresence : ['inside' => 0, 'waiting' => $customerCount, 'left' => 0, 'total_seen' => 0, 'total' => $customerCount];
$customersInsideCount = (int) ($customerPresence['inside'] ?? 0);
$customersWaitingCount = (int) ($customerPresence['waiting'] ?? 0);
$customersLeftCount = (int) ($customerPresence['left'] ?? 0);
$customersSeenCount = (int) ($customerPresence['total_seen'] ?? 0);
$customersTotalCount = (int) ($customerPresence['total'] ?? $customerCount);
$customersStatusFilter = \App\Models\EstateSale::normalizeCustomersStatusFilter($customersStatusFilter ?? null);

$customerFilterUrl = static function (string $status) use ($estateSaleId): string {
    $params = ['tab' => 'customers'];
    if ($status !== 'all') {
        $params['customers_status'] = $status;
    }

    return url('/estate-sales/' . (string) $estateSaleId . '?' . http_build_query($params));
};
$salesPagination = is_array($salesPagination ?? null) ? $salesPagination : pagination_meta(1, 25, $salesCount, count($sales));
$showBasePath = '/estate-sales/' . (string) $estateSaleId;
$expenseCount = count($expenses);
$timeSummary = is_array($timeSummary ?? null) ? $timeSummary : ['entries' => 0, 'open_entries' => 0, 'hours' => 0.0];
$timeLogs = is_array($timeLogs ?? null) ? $timeLogs : [];
$laborCost = (float) ($laborCost ?? 0);
$assignedEmployees = is_array($assignedEmployees ?? null) ? $assignedEmployees : [];
$assignedEmployeeCount = count($assignedEmployees);
$employeeAddUrl = url('/estate-sales/' . (string) $estateSaleId . '/employees/add');
$bulkPunchUrl = url('/estate-sales/' . (string) $estateSaleId . '/employees/bulk-punch');
$timeEntryCreateUrl = url('/time-tracking/create') . '?estate_sale_id=' . (string) $estateSaleId . '&return_to=' . rawurlencode('/estate-sales/' . (string) $estateSaleId . '?tab=labor');
$activeTab = strtolower(trim((string) ($activeTab ?? 'details')));
if (!in_array($activeTab, ['details', 'customers', 'sales', 'expenses', 'labor'], true)) {
    $activeTab = 'details';
}
$detailsTabActive = $activeTab === 'details';
$customersTabActive = $activeTab === 'customers';
$salesTabActive = $activeTab === 'sales';
$expensesTabActive = $activeTab === 'expenses';
$laborTabActive = $activeTab === 'labor';

$formatDuration = static function (int $minutes): string {
    if ($minutes <= 0) {
        return '0h 00m';
    }
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%dh %02dm', $hours, $mins);
};

$formatMoney = static fn (float $amount): string => '$' . number_format($amount, 2);
$clientPctRaw = trim((string) ($estateSale['client_percentage'] ?? ''));
$clientPctDisplay = $clientPctRaw !== '' && is_numeric($clientPctRaw)
    ? rtrim(rtrim(number_format((float) $clientPctRaw, 2, '.', ''), '0'), '.') . '%'
    : '—';
$clientSplitType = \App\Models\EstateSale::normalizeClientSplitType($estateSale['client_split_type'] ?? ($financialSummary['client_split_type'] ?? null));
$clientSplitTypeLabel = trim((string) ($financialSummary['client_split_type_label'] ?? ''));
if ($clientSplitTypeLabel === '') {
    $clientSplitTypeLabel = \App\Models\EstateSale::clientSplitTypeLabel($clientSplitType);
}
$clientSplitHelpText = trim((string) ($financialSummary['split_help_text'] ?? ''));
if ($clientSplitHelpText === '') {
    $clientSplitHelpText = \App\Models\EstateSale::clientSplitTypeHelpText($clientSplitType);
}
$estateGross = (float) ($financialSummary['gross'] ?? $financialSummary['total_sales'] ?? 0);
$estateNet = $financialSummary['net'] ?? $financialSummary['our_share'] ?? null;
$estateNetDisplay = $estateNet !== null ? $formatMoney((float) $estateNet) : '—';
$formatExpenseDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y', $ts);
};

$formatSaleDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y', $ts);
};

$formatVisitDuration = static function (?string $checkedInAt, ?string $checkedOutAt): string {
    return \App\Models\EstateSale::formatVisitDuration(
        \App\Models\EstateSale::customerVisitDurationMinutes(
            trim((string) ($checkedInAt ?? '')) !== '' ? trim((string) $checkedInAt) : null,
            trim((string) ($checkedOutAt ?? '')) !== '' ? trim((string) $checkedOutAt) : null
        )
    );
};

$customerStatusBadgeClass = static function (string $status): string {
    return match ($status) {
        'inside' => 'success',
        'left' => 'secondary',
        default => 'light text-dark border',
    };
};

$customerActionsMenu = static function (
    int $customerId,
    int $estateSaleId,
    string $csrfToken,
    bool $canRemoveCustomers
): string {
    $saleCreateUrl = url('/estate-sales/' . (string) $estateSaleId . '/sales/create?customer_id=' . (string) $customerId);
    $customerShowUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId);
    $removeUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/remove');

    ob_start();
    ?>
    <div class="dropdown">
        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h me-1"></i>Actions
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="<?= e($customerShowUrl) ?>">
                    <i class="fas fa-user me-2"></i>View customer
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="<?= e($saleCreateUrl) ?>">
                    <i class="fas fa-cash-register me-2"></i>Add sale
                </a>
            </li>
            <?php if ($canRemoveCustomers): ?>
                <li>
                    <form method="post" action="<?= e($removeUrl) ?>" class="m-0" onsubmit="return confirm('Remove this customer from the estate sale list?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fas fa-user-minus me-2"></i>Remove customer
                        </button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= e($statusLabel($status)) ?> · <?= e((string) $customerCount) ?> customer(s) · Gross <?= e($formatMoney($estateGross)) ?> · Net <?= e($estateNetDisplay) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button type="button" class="dropdown-item estate-sale-add-customer-trigger">
                        <i class="fas fa-user-plus me-2"></i>Add customer
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item estate-sale-add-expense-trigger">
                        <i class="fas fa-receipt me-2"></i>Add expense
                    </button>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/sales/create')) ?>">
                        <i class="fas fa-cash-register me-2"></i>Add sale
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e($employeeAddUrl) ?>">
                        <i class="fas fa-user-plus me-2"></i>Add employee
                    </a>
                </li>
                <li><a class="dropdown-item" href="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <li>
                    <form method="post" action="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Remove this estate sale? Customer records for this sale will also be removed.');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/estate-sales')) ?>">All estate sales</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs" id="estate-sale-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
                    id="estate-sale-details-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#estate-sale-tab-details"
                    data-tab="details"
                    aria-controls="estate-sale-tab-details"
                    aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                    <span class="estate-sale-tab-label">Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $customersTabActive ? ' active' : '' ?>"
                    id="estate-sale-customers-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#estate-sale-tab-customers"
                    data-tab="customers"
                    aria-controls="estate-sale-tab-customers"
                    aria-selected="<?= $customersTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                    <span class="estate-sale-tab-label">Customers</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $customerCount) ?>"><?= e((string) $customerCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $salesTabActive ? ' active' : '' ?>"
                    id="estate-sale-sales-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#estate-sale-tab-sales"
                    data-tab="sales"
                    aria-controls="estate-sale-tab-sales"
                    aria-selected="<?= $salesTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                    <span class="estate-sale-tab-label">Sales</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $salesCount) ?>"><?= e((string) $salesCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $expensesTabActive ? ' active' : '' ?>"
                    id="estate-sale-expenses-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#estate-sale-tab-expenses"
                    data-tab="expenses"
                    aria-controls="estate-sale-tab-expenses"
                    aria-selected="<?= $expensesTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
                    <span class="estate-sale-tab-label">Expenses</span>
                    <span class="estate-sale-tab-badge" id="estate-sale-expense-count" data-count="<?= e((string) $expenseCount) ?>"><?= e((string) $expenseCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $laborTabActive ? ' active' : '' ?>"
                    id="estate-sale-labor-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#estate-sale-tab-labor"
                    data-tab="labor"
                    aria-controls="estate-sale-tab-labor"
                    aria-selected="<?= $laborTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                    <span class="estate-sale-tab-label">Labor</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $assignedEmployeeCount) ?>"><?= e((string) $assignedEmployeeCount) ?></span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body tab-content" id="estate-sale-tab-content">
        <div
            class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>"
            id="estate-sale-tab-details"
            role="tabpanel"
            aria-labelledby="estate-sale-details-tab"
            tabindex="0"
        >
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="record-label">Status</div>
                    <span class="badge text-bg-secondary"><?= e($statusLabel($status)) ?></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="record-label">Start</div>
                    <div><?= e($formatDt((string) ($estateSale['start_at'] ?? ''))) ?></div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="record-label">End</div>
                    <div><?= e($formatDt((string) ($estateSale['end_at'] ?? ''))) ?></div>
                </div>
                <?php
                $clientId = (int) ($estateSale['client_id'] ?? 0);
                $clientName = trim((string) ($estateSale['client_name'] ?? ''));
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="record-label">Client</div>
                    <?php if ($clientId > 0): ?>
                        <a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/clients/' . (string) $clientId)) ?>"><?= e($clientName !== '' ? $clientName : ('Client #' . (string) $clientId)) ?></a>
                    <?php else: ?>
                        <div>—</div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <div class="record-label">Location</div>
                    <?php if ($mapsAddressUrl !== '' && $addrDisplay !== '—'): ?>
                        <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($addrDisplay) ?></a>
                    <?php else: ?>
                        <div><?= e($addrDisplay) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($notes !== ''): ?>
                    <div class="col-12">
                        <div class="record-label">Notes</div>
                        <div><?= nl2br(e($notes)) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div id="estate-sale-financial-summary">
                <h2 class="h6 mb-3"><i class="fas fa-calculator me-2"></i>Financial summary</h2>
                <div class="record-row-fields record-row-fields-2 mb-3">
                    <div class="record-field">
                        <span class="record-label">Gross</span>
                        <span class="record-value fw-semibold fs-5" id="estate-sale-gross-total"><?= e($formatMoney($estateGross)) ?></span>
                        <div class="form-text">Total on-site sales for this estate sale</div>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Net</span>
                        <span class="record-value fw-semibold fs-5 text-success" id="estate-sale-net-total"><?= e($estateNetDisplay) ?></span>
                        <div class="form-text">Our share after the agreed client split</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Client percentage</div>
                        <div id="estate-sale-client-percentage-display"><?= e($clientPctDisplay) ?></div>
                        <?php if ($clientPctDisplay === '—'): ?>
                            <div class="form-text">Set on <a href="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/edit')) ?>">Edit estate sale</a>.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Split basis</div>
                        <div id="estate-sale-client-split-type-display"><?= e($clientSplitTypeLabel) ?></div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">On-site sales (gross)</div>
                        <div id="estate-sale-total-sales"><?= e($formatMoney($estateGross)) ?></div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Total expenses</div>
                        <div id="estate-sale-total-expenses"><?= e($formatMoney((float) ($financialSummary['total_expenses'] ?? 0))) ?></div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Total labor</div>
                        <div id="estate-sale-total-labor"><?= e($formatMoney((float) ($financialSummary['total_labor'] ?? 0))) ?></div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Client share</div>
                        <div id="estate-sale-client-share">
                            <?= ($financialSummary['client_share'] ?? null) !== null ? e($formatMoney((float) $financialSummary['client_share'])) : '—' ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="record-label">Our share (net)</div>
                        <div id="estate-sale-our-share" class="fw-semibold">
                            <?= e($estateNetDisplay) ?>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-2" id="estate-sale-split-help"><?= e($clientSplitHelpText) ?></div>
            </div>
        </div>

        <div
            class="tab-pane fade<?= $customersTabActive ? ' show active' : '' ?>"
            id="estate-sale-tab-customers"
            role="tabpanel"
            aria-labelledby="estate-sale-customers-tab"
            tabindex="0"
        >
            <div class="mb-3">
                <label class="form-label fw-semibold mb-1" for="estate-sale-customer-search">Quick find customer</label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-search"></i></span>
                    <input
                        id="estate-sale-customer-search"
                        type="search"
                        class="form-control"
                        placeholder="Search by name, email, phone, or city..."
                        autocomplete="off"
                        <?= $customerCount === 0 ? 'disabled' : '' ?>
                    />
                </div>
                <div class="form-text">Filter the list to quickly open Actions and add a sale.</div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="record-row-fields record-row-fields-3 mb-0" id="estate-sale-customer-presence-summary">
                    <div class="record-field">
                        <span class="record-label">Inside now</span>
                        <span class="record-value fw-semibold text-success" id="estate-sale-customers-inside-count"><?= e((string) $customersInsideCount) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Waiting</span>
                        <span class="record-value fw-semibold" id="estate-sale-customers-waiting-count"><?= e((string) $customersWaitingCount) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Total seen</span>
                        <span class="record-value fw-semibold" id="estate-sale-customers-seen-count"><?= e((string) $customersSeenCount) ?></span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm estate-sale-add-customer-trigger" id="estate-sale-add-customer-btn">
                    <i class="fas fa-user-plus me-1"></i>Add customer
                </button>
            </div>

            <div class="small muted mb-3">Customers registered at this estate sale. Queue numbers are assigned in registration order.</div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" role="group" aria-label="Filter customers by status" id="estate-sale-customer-status-filters">
                <a
                    class="btn btn-sm <?= $customersStatusFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> estate-sale-customer-status-filter"
                    href="<?= e($customerFilterUrl('all')) ?>"
                    data-status="all"
                >All <span class="badge bg-light text-dark ms-1" id="estate-sale-filter-count-all"><?= e((string) $customersTotalCount) ?></span></a>
                <a
                    class="btn btn-sm <?= $customersStatusFilter === 'waiting' ? 'btn-primary' : 'btn-outline-secondary' ?> estate-sale-customer-status-filter"
                    href="<?= e($customerFilterUrl('waiting')) ?>"
                    data-status="waiting"
                >Waiting <span class="badge bg-light text-dark ms-1" id="estate-sale-filter-count-waiting"><?= e((string) $customersWaitingCount) ?></span></a>
                <a
                    class="btn btn-sm <?= $customersStatusFilter === 'inside' ? 'btn-primary' : 'btn-outline-secondary' ?> estate-sale-customer-status-filter"
                    href="<?= e($customerFilterUrl('inside')) ?>"
                    data-status="inside"
                >Checked in <span class="badge bg-light text-dark ms-1" id="estate-sale-filter-count-inside"><?= e((string) $customersInsideCount) ?></span></a>
                <a
                    class="btn btn-sm <?= $customersStatusFilter === 'left' ? 'btn-primary' : 'btn-outline-secondary' ?> estate-sale-customer-status-filter"
                    href="<?= e($customerFilterUrl('left')) ?>"
                    data-status="left"
                >Checked out <span class="badge bg-light text-dark ms-1" id="estate-sale-filter-count-left"><?= e((string) $customersLeftCount) ?></span></a>
            </div>

            <div id="estate-sale-customer-alert" class="alert d-none mb-3" role="alert"></div>
            <div class="record-empty mb-0 d-none" id="estate-sale-customers-no-match">No customers match your search.</div>

            <?php if ($customerCount > 0): ?>
                <?php
                $pagination = $customersPagination;
                $basePath = $showBasePath;
                $pageParam = 'customers_page';
                $perPageParam = 'customers_per_page';
                $fixedQueryParams = ['tab' => 'customers'];
                if ($customersStatusFilter !== 'all') {
                    $fixedQueryParams['customers_status'] = $customersStatusFilter;
                }
                require base_path('app/Views/components/index_pagination.php');
                ?>
            <?php endif; ?>

            <?php if ($customers === []): ?>
                <div class="record-empty mb-0" id="estate-sale-customers-empty">No customers added to this sale yet.</div>
            <?php else: ?>
                <div class="record-list-simple" id="estate-sale-customers-list">
                    <?php foreach ($customers as $customer): ?>
                        <?php
                        if (!is_array($customer)) {
                            continue;
                        }
                        $customerId = (int) ($customer['id'] ?? 0);
                        $queueNumber = (int) ($customer['queue_number'] ?? 0);
                        $customerName = \App\Models\EstateSale::customerDisplayName($customer);
                        $customerShowUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId);
                        $checkInStatus = \App\Models\EstateSale::customerCheckInStatus($customer);
                        $checkInStatusLabel = \App\Models\EstateSale::customerCheckInStatusLabel($checkInStatus);
                        $statusBadgeClass = $customerStatusBadgeClass($checkInStatus);
                        $canCheckIn = $checkInStatus !== 'inside';
                        $canCheckOut = $checkInStatus === 'inside';
                        $email = trim((string) ($customer['email'] ?? ''));
                        $phone = trim((string) ($customer['phone'] ?? ''));
                        $city = trim((string) ($customer['city'] ?? ''));
                        $state = trim((string) ($customer['state'] ?? ''));
                        $cityState = trim(implode(', ', array_filter([$city, $state], static fn (string $v): bool => $v !== '')));
                        $addedAt = $formatDt((string) ($customer['added_at'] ?? ''));
                        $checkedInAt = $formatDt((string) ($customer['checked_in_at'] ?? ''));
                        $checkedOutAt = $formatDt((string) ($customer['checked_out_at'] ?? ''));
                        $visitDuration = $formatVisitDuration(
                            (string) ($customer['checked_in_at'] ?? ''),
                            (string) ($customer['checked_out_at'] ?? '')
                        );
                        $searchText = strtolower(implode(' ', array_filter([
                            $queueNumber > 0 ? ('#' . (string) $queueNumber) : '',
                            $customerName,
                            $email,
                            $phone,
                            $city,
                            $state,
                            $cityState,
                        ], static fn (string $v): bool => $v !== '')));
                        ?>
                        <article
                            class="record-row-simple estate-sale-customer-row"
                            data-customer-id="<?= e((string) $customerId) ?>"
                            data-search-text="<?= e($searchText) ?>"
                            data-check-in-status="<?= e($checkInStatus) ?>"
                        >
                            <div class="record-row-main d-flex flex-wrap align-items-start justify-content-between gap-2 w-100">
                                <div>
                                    <h3 class="record-title-simple mb-1 d-flex flex-wrap align-items-center gap-2">
                                        <?php if ($queueNumber > 0): ?>
                                            <span class="badge rounded-pill bg-primary estate-sale-customer-queue">#<?= e((string) $queueNumber) ?></span>
                                        <?php endif; ?>
                                        <a class="text-decoration-none estate-sale-customer-name-link" href="<?= e($customerShowUrl) ?>"><?= e($customerName) ?></a>
                                        <span class="badge bg-<?= e($statusBadgeClass) ?> estate-sale-customer-status"><?= e($checkInStatusLabel) ?></span>
                                    </h3>
                                    <div class="small muted">
                                        <?= e($email !== '' ? $email : '—') ?>
                                        <?php if ($phone !== ''): ?> · <?= e($phone) ?><?php endif; ?>
                                        <?php if ($cityState !== ''): ?> · <?= e($cityState) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 estate-sale-customer-actions">
                                    <?php if ($canCheckIn): ?>
                                        <button
                                            type="button"
                                            class="btn btn-success btn-sm estate-sale-customer-check-in"
                                            data-url="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/check-in')) ?>"
                                        >
                                            <i class="fas fa-door-open me-1"></i>Check in
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canCheckOut): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-warning btn-sm estate-sale-customer-check-out"
                                            data-url="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/check-out')) ?>"
                                        >
                                            <i class="fas fa-door-closed me-1"></i>Check out
                                        </button>
                                    <?php endif; ?>
                                    <?= $customerActionsMenu($customerId, $estateSaleId, $csrfToken, $canRemoveCustomers) ?>
                                </div>
                            </div>
                            <div class="record-row-fields record-row-fields-4 mt-2">
                                <div class="record-field">
                                    <span class="record-label">Created</span>
                                    <span class="record-value estate-sale-customer-created-at"><?= e($addedAt) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Entry</span>
                                    <span class="record-value estate-sale-customer-entry-at"><?= e($checkedInAt) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Exit</span>
                                    <span class="record-value estate-sale-customer-exit-at"><?= e($checkedOutAt) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Visit</span>
                                    <span class="record-value estate-sale-customer-visit-duration"><?= e($checkInStatus === 'inside' ? 'In progress' : $visitDuration) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="tab-pane fade<?= $salesTabActive ? ' show active' : '' ?>"
            id="estate-sale-tab-sales"
            role="tabpanel"
            aria-labelledby="estate-sale-sales-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="record-row-fields record-row-fields-3 mb-0">
                    <div class="record-field">
                        <span class="record-label">Gross</span>
                        <span class="record-value fw-semibold"><?= e($formatMoney($estateGross)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Net</span>
                        <span class="record-value fw-semibold"><?= e($estateNetDisplay) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Transactions</span>
                        <span class="record-value"><?= e((string) $salesCount) ?></span>
                    </div>
                </div>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/sales/create')) ?>">
                    <i class="fas fa-plus me-1"></i>Add sale
                </a>
            </div>

            <?php if ($salesCount > 0): ?>
                <?php
                $pagination = $salesPagination;
                $basePath = $showBasePath;
                $pageParam = 'sales_page';
                $perPageParam = 'sales_per_page';
                $fixedQueryParams = ['tab' => 'sales'];
                require base_path('app/Views/components/index_pagination.php');
                ?>
            <?php endif; ?>

            <?php if ($sales === []): ?>
                <div class="record-empty mb-0">No sales recorded for this estate sale yet.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($sales as $sale): ?>
                        <?php
                        if (!is_array($sale)) {
                            continue;
                        }
                        $saleId = (int) ($sale['id'] ?? 0);
                        $saleName = trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $saleId);
                        $saleDate = $formatSaleDate((string) ($sale['sale_date'] ?? ''));
                        $saleAmount = (float) ($sale['gross_amount'] ?? 0);
                        $customerName = trim((string) ($sale['customer_name'] ?? ''));
                        ?>
                        <article class="record-row-simple">
                            <a class="record-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                                <div class="record-row-main">
                                    <h3 class="record-title-simple mb-1"><?= e($saleName) ?></h3>
                                    <?php if ($customerName !== ''): ?>
                                        <div class="small muted"><?= e($customerName) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="record-row-fields record-row-fields-3 mt-2">
                                    <div class="record-field">
                                        <span class="record-label">Date</span>
                                        <span class="record-value"><?= e($saleDate) ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Gross</span>
                                        <span class="record-value"><?= e($formatMoney($saleAmount)) ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Type</span>
                                        <span class="record-value"><?= e(trim((string) ($sale['sale_type'] ?? '')) ?: '—') ?></span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="tab-pane fade<?= $expensesTabActive ? ' show active' : '' ?>"
            id="estate-sale-tab-expenses"
            role="tabpanel"
            aria-labelledby="estate-sale-expenses-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="small muted">Track advertising, setup, and other sale costs.</div>
                <button type="button" class="btn btn-primary btn-sm estate-sale-add-expense-trigger" id="estate-sale-add-expense-btn">
                    <i class="fas fa-plus me-1"></i>Add expense
                </button>
            </div>

            <div id="estate-sale-expense-alert" class="alert d-none mb-3" role="alert"></div>

            <?php if ($expenses === []): ?>
                <div class="record-empty mb-0" id="estate-sale-expenses-empty">No expenses recorded yet.</div>
            <?php else: ?>
                <div class="record-list-simple" id="estate-sale-expenses-list">
                    <?php foreach ($expenses as $expense): ?>
                        <?php
                        if (!is_array($expense)) {
                            continue;
                        }
                        $expenseId = (int) ($expense['id'] ?? 0);
                        $expenseCategory = trim((string) ($expense['category'] ?? ''));
                        $expenseNote = trim((string) ($expense['note'] ?? ''));
                        $expenseTitle = $expenseCategory !== '' ? $expenseCategory : ('Expense #' . (string) $expenseId);
                        $expenseDate = $formatExpenseDate((string) ($expense['expense_date'] ?? ''));
                        $expenseAmount = (float) ($expense['amount'] ?? 0);
                        ?>
                        <article class="record-row-simple" data-expense-id="<?= e((string) $expenseId) ?>">
                            <div class="record-row-main d-flex flex-wrap align-items-start justify-content-between gap-2 w-100">
                                <div>
                                    <h3 class="record-title-simple mb-1"><?= e($expenseTitle) ?></h3>
                                    <div class="small muted">
                                        <?= e($expenseDate) ?> · <?= e($formatMoney($expenseAmount)) ?>
                                        <?php if ($expenseNote !== ''): ?> · <?= e($expenseNote) ?><?php endif; ?>
                                    </div>
                                </div>
                                <form method="post" action="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/expenses/' . (string) $expenseId . '/remove')) ?>" onsubmit="return confirm('Remove this expense?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="tab-pane fade<?= $laborTabActive ? ' show active' : '' ?>"
            id="estate-sale-tab-labor"
            role="tabpanel"
            aria-labelledby="estate-sale-labor-tab"
            tabindex="0"
        >
            <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2 mb-4">
                <div class="record-field">
                    <span class="record-label">Entries</span>
                    <span class="record-value"><?= e((string) ((int) ($timeSummary['entries'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Open Entries</span>
                    <span class="record-value"><?= e((string) ((int) ($timeSummary['open_entries'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Total Hours</span>
                    <span class="record-value"><?= e(number_format((float) ($timeSummary['hours'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Labor Cost</span>
                    <span class="record-value"><?= e($formatMoney($laborCost)) ?></span>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="record-label mb-0">Employee Time Logs</span>
            </div>
            <?php if ($timeLogs === []): ?>
                <div class="record-empty mb-4">No time logs for this estate sale yet.</div>
            <?php else: ?>
                <ul class="list-unstyled mb-4">
                    <?php foreach ($timeLogs as $entry): ?>
                        <?php
                        if (!is_array($entry)) {
                            continue;
                        }
                        $entryId = (int) ($entry['id'] ?? 0);
                        $minutes = (int) ($entry['duration_minutes'] ?? 0);
                        $hourlyRate = (float) ($entry['hourly_rate'] ?? 0);
                        $entryLaborCost = (float) ($entry['labor_cost'] ?? 0);
                        $clockOutAt = trim((string) ($entry['clock_out_at'] ?? ''));
                        $entryUrl = $entryId > 0
                            ? url('/time-tracking/' . (string) $entryId) . '?return_to=' . rawurlencode('/estate-sales/' . (string) $estateSaleId . '?tab=labor')
                            : '#';
                        ?>
                        <li class="mb-2">
                            <a class="fw-bold text-decoration-none" href="<?= e($entryUrl) ?>"><?= e(trim((string) ($entry['employee_name'] ?? '')) ?: ('Employee #' . (string) ((int) ($entry['employee_id'] ?? 0)))) ?></a>
                            <ul class="time-log-meta-list small muted">
                                <li>In <?= e(format_datetime((string) ($entry['clock_in_at'] ?? null))) ?></li>
                                <li>Out <?= e(format_datetime($clockOutAt !== '' ? $clockOutAt : null)) ?></li>
                                <li><?= e($formatDuration($minutes)) ?></li>
                                <li>Rate $<?= e(number_format($hourlyRate, 2)) ?></li>
                                <li>Cost $<?= e(number_format($entryLaborCost, 2)) ?></li>
                                <?php if ($clockOutAt === ''): ?><li><span class="badge text-bg-warning">Open</span></li><?php endif; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                    <?php if ($assignedEmployees !== []): ?>
                        <div class="form-check flex-shrink-0 mb-0">
                            <input class="form-check-input" type="checkbox" id="es-bulk-select-all" title="Select all" aria-label="Select all employees for mass punch">
                        </div>
                    <?php endif; ?>
                    <strong class="mb-0"><i class="fas fa-users me-2"></i>Assigned Employees</strong>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <a class="btn btn-outline-primary btn-sm" href="<?= e($employeeAddUrl) ?>"><i class="fas fa-user-plus me-1"></i>Add Employee</a>
                    <?php if ($assignedEmployees !== []): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                                Mass punch
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button type="button" class="dropdown-item" id="es-bulk-punch-in">Punch in selected</button></li>
                                <li><button type="button" class="dropdown-item" id="es-bulk-punch-out">Punch out selected</button></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($assignedEmployees === []): ?>
                <div class="record-empty mb-0">No employees assigned to this estate sale yet.</div>
            <?php else: ?>
                <form id="es-bulk-punch-form" method="post" action="<?= e($bulkPunchUrl) ?>" class="d-none" aria-hidden="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bulk_action" id="es-bulk-action" value="">
                </form>
                <div class="record-list-simple">
                    <?php foreach ($assignedEmployees as $employee): ?>
                        <?php
                        if (!is_array($employee)) {
                            continue;
                        }
                        $employeeId = (int) ($employee['employee_id'] ?? 0);
                        if ($employeeId <= 0) {
                            continue;
                        }
                        $displayName = trim((string) ($employee['display_name'] ?? ''));
                        if ($displayName === '') {
                            $displayName = 'Employee #' . (string) $employeeId;
                        }
                        $openEntryId = (int) ($employee['open_entry_id'] ?? 0);
                        $isOpen = $openEntryId > 0;
                        $isOpenForThisEstateSale = (bool) ($employee['is_open_for_this_estate_sale'] ?? false);
                        $openJobTitle = trim((string) ($employee['open_job_title'] ?? ''));
                        if ($openJobTitle === '') {
                            $openJobTitle = 'Non-Job Time';
                        }
                        $openClockInAt = trim((string) ($employee['open_clock_in_at'] ?? ''));
                        $linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
                        $canManageEmployeeTime = is_site_admin() || workspace_role() === 'admin';
                        $addTimeEntryUrl = url('/time-tracking/create?estate_sale_id=' . rawurlencode((string) $estateSaleId) . '&employee_id=' . rawurlencode((string) $employeeId) . '&return_to=' . rawurlencode('/estate-sales/' . (string) $estateSaleId . '?tab=labor'));
                        ?>
                        <article class="record-row-simple">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">
                                    <div class="form-check flex-shrink-0 pt-1">
                                        <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (string) $employeeId ?>" form="es-bulk-punch-form" id="es-bulk-emp-<?= (string) $employeeId ?>" aria-label="<?= e('Select ' . $displayName) ?>">
                                    </div>
                                    <div class="record-row-main mb-0 min-w-0">
                                        <h3 class="record-title-simple mb-0"><?= e($displayName) ?></h3>
                                        <div class="record-subline small muted mt-1">
                                            <?php if ($isOpen): ?>
                                                <?php if ($isOpenForThisEstateSale): ?>
                                                    <span class="badge text-bg-success">Punched In</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-warning">Open Elsewhere</span>
                                                <?php endif; ?>
                                                <span>· <?= e($openJobTitle) ?></span>
                                                <span>· <?= e(format_datetime($openClockInAt !== '' ? $openClockInAt : null)) ?></span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Punched Out</span>
                                            <?php endif; ?>
                                            <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($canManageEmployeeTime): ?>
                                        <a class="btn btn-outline-primary btn-sm" href="<?= e($addTimeEntryUrl) ?>"><i class="fas fa-plus me-1"></i>Add Time Entry</a>
                                    <?php endif; ?>
                                    <?php if ($isOpen): ?>
                                        <form method="post" action="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/employees/' . (string) $employeeId . '/punch-out')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fas fa-stop me-1"></i>Punch Out</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/estate-sales/' . (string) $estateSaleId . '/employees/' . (string) $employeeId . '/punch-in')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-play me-1"></i>Punch In</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <script>
                    (function () {
                        var form = document.getElementById('es-bulk-punch-form');
                        var actionInput = document.getElementById('es-bulk-action');
                        var selectAll = document.getElementById('es-bulk-select-all');
                        if (!form || !actionInput) {
                            return;
                        }
                        function getEmployeeBoxes() {
                            return document.querySelectorAll('input[form="es-bulk-punch-form"][name="employee_ids[]"]');
                        }
                        function syncSelectAll() {
                            if (!selectAll) {
                                return;
                            }
                            var boxes = getEmployeeBoxes();
                            var n = boxes.length;
                            var checked = 0;
                            for (var i = 0; i < n; i++) {
                                if (boxes[i].checked) {
                                    checked++;
                                }
                            }
                            selectAll.indeterminate = checked > 0 && checked < n;
                            selectAll.checked = n > 0 && checked === n;
                        }
                        if (selectAll) {
                            selectAll.addEventListener('change', function () {
                                var boxes = getEmployeeBoxes();
                                var on = selectAll.checked;
                                for (var i = 0; i < boxes.length; i++) {
                                    boxes[i].checked = on;
                                }
                                selectAll.indeterminate = false;
                            });
                            var boxesInit = getEmployeeBoxes();
                            for (var j = 0; j < boxesInit.length; j++) {
                                boxesInit[j].addEventListener('change', syncSelectAll);
                            }
                            syncSelectAll();
                        }
                        function submitBulk(action) {
                            var boxes = getEmployeeBoxes();
                            var any = false;
                            for (var i = 0; i < boxes.length; i++) {
                                if (boxes[i].checked) {
                                    any = true;
                                    break;
                                }
                            }
                            if (!any) {
                                window.alert('Select at least one employee.');
                                return;
                            }
                            actionInput.value = action;
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        }
                        var btnIn = document.getElementById('es-bulk-punch-in');
                        var btnOut = document.getElementById('es-bulk-punch-out');
                        if (btnIn) {
                            btnIn.addEventListener('click', function () { submitBulk('in'); });
                        }
                        if (btnOut) {
                            btnOut.addEventListener('click', function () { submitBulk('out'); });
                        }
                    })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="modal fade" id="estate-sale-add-customer-modal" tabindex="-1" aria-labelledby="estate-sale-add-customer-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="estate-sale-add-customer-modal-label">Add customer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="estate-sale-add-customer-error" class="alert alert-danger d-none"></div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-customer-first-name">First name</label>
                        <input id="estate-sale-customer-first-name" class="form-control" maxlength="90" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-customer-last-name">Last name</label>
                        <input id="estate-sale-customer-last-name" class="form-control" maxlength="90" autocomplete="off" />
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="estate-sale-customer-email">Email</label>
                        <input id="estate-sale-customer-email" type="email" class="form-control" maxlength="190" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-customer-phone">Phone</label>
                        <input id="estate-sale-customer-phone" class="form-control" maxlength="40" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-customer-city">City</label>
                        <input id="estate-sale-customer-city" class="form-control" maxlength="120" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-customer-state">State</label>
                        <select id="estate-sale-customer-state" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($stateOptions as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="estate-sale-add-customer-save">Save &amp; add</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="estate-sale-add-expense-modal" tabindex="-1" aria-labelledby="estate-sale-add-expense-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="estate-sale-add-expense-modal-label">Add expense</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="estate-sale-add-expense-error" class="alert alert-danger d-none"></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="estate-sale-expense-category">Category</label>
                        <select id="estate-sale-expense-category" class="form-select">
                            <option value="">Choose category...</option>
                            <?php foreach ($expenseCategoryOptions as $option): ?>
                                <option value="<?= e((string) $option) ?>"><?= e((string) $option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-expense-amount">Amount</label>
                        <input id="estate-sale-expense-amount" type="number" step="0.01" min="0" class="form-control" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="estate-sale-expense-date">Date</label>
                        <input id="estate-sale-expense-date" type="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" />
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="estate-sale-expense-note">Note</label>
                        <input id="estate-sale-expense-note" class="form-control" maxlength="255" autocomplete="off" placeholder="Optional details" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="estate-sale-add-expense-save">Save &amp; add</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tabList = document.getElementById('estate-sale-tabs');
    const syncTabUrl = (tabName) => {
        if (!tabName) {
            return;
        }
        const url = new URL(window.location.href);
        if (tabName === 'details') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tabName);
        }
        const next = url.pathname + url.search + url.hash;
        const current = window.location.pathname + window.location.search + window.location.hash;
        if (next !== current) {
            window.history.replaceState(null, '', next);
        }
    };

    if (tabList) {
        tabList.addEventListener('shown.bs.tab', (event) => {
            const trigger = event.target;
            if (!(trigger instanceof HTMLElement)) {
                return;
            }
            syncTabUrl(String(trigger.dataset.tab || '').trim());
        });

        const urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab) {
            const normalizedTab = urlTab.toLowerCase();
            const trigger = tabList.querySelector('[data-tab="' + normalizedTab + '"]');
            if (trigger instanceof HTMLElement && !trigger.classList.contains('active') && window.bootstrap) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    }

    const csrfToken = <?= json_encode($csrfToken) ?>;
    const quickCreateUrl = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/quick-create-customer')) ?>;
    const removeCustomerBase = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/customers/')) ?>;
    const saleCreateBase = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/sales/create')) ?>;
    const estateSaleId = <?= json_encode($estateSaleId) ?>;
    const canRemoveCustomers = <?= json_encode($canRemoveCustomers) ?>;

    const addCustomerTriggers = document.querySelectorAll('.estate-sale-add-customer-trigger');
    const modalEl = document.getElementById('estate-sale-add-customer-modal');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const saveBtn = document.getElementById('estate-sale-add-customer-save');
    const errorBox = document.getElementById('estate-sale-add-customer-error');
    const alertBox = document.getElementById('estate-sale-customer-alert');
    const customersList = document.getElementById('estate-sale-customers-list');
    const customersEmpty = document.getElementById('estate-sale-customers-empty');
    const customersNoMatch = document.getElementById('estate-sale-customers-no-match');
    const customerSearchInput = document.getElementById('estate-sale-customer-search');
    const customersTabActiveOnLoad = <?= json_encode($customersTabActive) ?>;
    const customersStatusFilter = <?= json_encode($customersStatusFilter) ?>;

    const customerCheckInBase = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/customers/')) ?>;

    const formatCustomerDt = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return '—';
        }
        const ts = Date.parse(raw.replace(' ', 'T'));
        if (Number.isNaN(ts)) {
            return '—';
        }
        return new Intl.DateTimeFormat(undefined, {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(ts));
    };

    const customerStatusBadgeClass = (status) => {
        if (status === 'inside') {
            return 'success';
        }
        if (status === 'left') {
            return 'secondary';
        }
        return 'light text-dark border';
    };

    const buildCustomerSearchText = (customer) => {
        const queueNumber = Number(customer.queue_number || 0);
        const queueLabel = queueNumber > 0 ? ('#' + String(queueNumber)) : '';
        const name = String(customer.name || '').trim();
        const email = String(customer.email || '').trim();
        const phone = String(customer.phone || '').trim();
        const city = String(customer.city || '').trim();
        const state = String(customer.state || '').trim();
        return [queueLabel, name, email, phone, city, state].filter(Boolean).join(' ').toLowerCase();
    };

    const buildCustomerCheckButtonsHtml = (customer) => {
        const customerId = Number(customer.id || 0);
        const status = String(customer.check_in_status || 'waiting');
        let html = '';
        if (status !== 'inside') {
            html += `<button type="button" class="btn btn-success btn-sm estate-sale-customer-check-in" data-url="${customerCheckInBase}${customerId}/check-in"><i class="fas fa-door-open me-1"></i>Check in</button>`;
        }
        if (status === 'inside') {
            html += `<button type="button" class="btn btn-outline-warning btn-sm estate-sale-customer-check-out" data-url="${customerCheckInBase}${customerId}/check-out"><i class="fas fa-door-closed me-1"></i>Check out</button>`;
        }
        return html;
    };

    const updateCustomerRow = (row, customer) => {
        if (!(row instanceof HTMLElement)) {
            return;
        }

        const customerId = Number(customer.id || 0);
        const queueNumber = Number(customer.queue_number || 0);
        const name = String(customer.name || ('Customer #' + customerId));
        const status = String(customer.check_in_status || 'waiting');
        const statusLabel = String(customer.check_in_status_label || 'Waiting');
        const showUrl = String(customer.show_url || `${customerCheckInBase}${customerId}`);
        const email = String(customer.email || '—');
        const phone = String(customer.phone || '').trim();
        const city = String(customer.city || '').trim();
        const state = String(customer.state || '').trim();
        const cityState = [city, state].filter(Boolean).join(', ');
        const metaParts = [email];
        if (phone) {
            metaParts.push(phone);
        }
        if (cityState) {
            metaParts.push(cityState);
        }

        row.dataset.customerId = String(customerId);
        row.dataset.searchText = buildCustomerSearchText(customer);
        row.dataset.checkInStatus = status;
        row.classList.add('estate-sale-customer-row');

        const queueEl = row.querySelector('.estate-sale-customer-queue');
        if (queueEl) {
            if (queueNumber > 0) {
                queueEl.textContent = '#' + String(queueNumber);
                queueEl.classList.remove('d-none');
            } else {
                queueEl.classList.add('d-none');
            }
        }

        const nameEl = row.querySelector('.estate-sale-customer-name-link');
        if (nameEl) {
            nameEl.textContent = name;
            nameEl.setAttribute('href', showUrl);
        }

        const statusEl = row.querySelector('.estate-sale-customer-status');
        if (statusEl) {
            statusEl.textContent = statusLabel;
            statusEl.className = 'badge bg-' + customerStatusBadgeClass(status) + ' estate-sale-customer-status';
        }

        const metaEl = row.querySelector('.customer-meta');
        if (metaEl) {
            metaEl.textContent = metaParts.join(' · ');
        }

        const createdEl = row.querySelector('.estate-sale-customer-created-at');
        if (createdEl) {
            createdEl.textContent = formatCustomerDt(customer.added_at) === '—' ? 'Just now' : formatCustomerDt(customer.added_at);
        }

        const entryEl = row.querySelector('.estate-sale-customer-entry-at');
        if (entryEl) {
            entryEl.textContent = formatCustomerDt(customer.checked_in_at);
        }

        const exitEl = row.querySelector('.estate-sale-customer-exit-at');
        if (exitEl) {
            exitEl.textContent = formatCustomerDt(customer.checked_out_at);
        }

        const visitEl = row.querySelector('.estate-sale-customer-visit-duration');
        if (visitEl) {
            visitEl.textContent = status === 'inside'
                ? 'In progress'
                : String(customer.visit_duration || '—');
        }

        const actionsWrap = row.querySelector('.estate-sale-customer-actions');
        if (actionsWrap) {
            actionsWrap.innerHTML = buildCustomerCheckButtonsHtml(customer) + buildCustomerActionsHtml(customerId);
        }
    };

    const postCustomerAction = async (url) => {
        const body = new URLSearchParams();
        body.set('csrf_token', csrfToken);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error(String(data.error || 'Request failed.'));
        }

        return data;
    };

    const updatePresenceSummary = (presence) => {
        if (!presence || typeof presence !== 'object') {
            return;
        }

        const insideEl = document.getElementById('estate-sale-customers-inside-count');
        const waitingEl = document.getElementById('estate-sale-customers-waiting-count');
        const seenEl = document.getElementById('estate-sale-customers-seen-count');
        const filterAllEl = document.getElementById('estate-sale-filter-count-all');
        const filterWaitingEl = document.getElementById('estate-sale-filter-count-waiting');
        const filterInsideEl = document.getElementById('estate-sale-filter-count-inside');
        const filterLeftEl = document.getElementById('estate-sale-filter-count-left');

        if (insideEl && presence.inside !== undefined) {
            insideEl.textContent = String(presence.inside);
        }
        if (waitingEl && presence.waiting !== undefined) {
            waitingEl.textContent = String(presence.waiting);
        }
        if (seenEl && presence.total_seen !== undefined) {
            seenEl.textContent = String(presence.total_seen);
        }
        if (filterAllEl && presence.total !== undefined) {
            filterAllEl.textContent = String(presence.total);
        }
        if (filterWaitingEl && presence.waiting !== undefined) {
            filterWaitingEl.textContent = String(presence.waiting);
        }
        if (filterInsideEl && presence.inside !== undefined) {
            filterInsideEl.textContent = String(presence.inside);
        }
        if (filterLeftEl && presence.left !== undefined) {
            filterLeftEl.textContent = String(presence.left);
        }
    };

    const customerMatchesStatusFilter = (status) => {
        const normalizedStatus = String(status || 'waiting');
        if (customersStatusFilter === 'all') {
            return true;
        }

        return normalizedStatus === customersStatusFilter;
    };

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const checkInBtn = target.closest('.estate-sale-customer-check-in');
        const checkOutBtn = target.closest('.estate-sale-customer-check-out');
        const button = checkInBtn || checkOutBtn;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const url = String(button.dataset.url || '').trim();
        if (url === '') {
            return;
        }

        event.preventDefault();
        button.disabled = true;

        try {
            const data = await postCustomerAction(url);
            showAlert(String(data.message || 'Saved.'), 'success');
            if (data.presence) {
                updatePresenceSummary(data.presence);
            }
            const row = button.closest('.estate-sale-customer-row');
            if (row && data.customer) {
                updateCustomerRow(row, data.customer);
                filterCustomers();
            }
        } catch (error) {
            showAlert(error instanceof Error ? error.message : 'Request failed.', 'danger');
            button.disabled = false;
        }
    });

    const filterCustomers = () => {
        const query = String(customerSearchInput?.value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('#estate-sale-customers-list .estate-sale-customer-row');
        let visibleCount = 0;

        rows.forEach((row) => {
            const haystack = String(row.dataset.searchText || row.textContent || '').toLowerCase();
            const searchMatch = query === '' || haystack.includes(query);
            const statusMatch = customerMatchesStatusFilter(String(row.dataset.checkInStatus || 'waiting'));
            const match = searchMatch && statusMatch;
            row.classList.toggle('d-none', !match);
            if (match) {
                visibleCount += 1;
            }
        });

        if (customersNoMatch) {
            customersNoMatch.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
        }
    };

    const enableCustomerSearch = () => {
        if (customerSearchInput) {
            customerSearchInput.disabled = false;
        }
    };

    const fieldIds = [
        'estate-sale-customer-first-name',
        'estate-sale-customer-last-name',
        'estate-sale-customer-email',
        'estate-sale-customer-phone',
        'estate-sale-customer-city',
        'estate-sale-customer-state',
    ];

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }
        alertBox.textContent = message;
        alertBox.className = 'alert alert-' + type + ' mb-3';
        alertBox.classList.remove('d-none');
    };

    const clearModal = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            el.value = '';
        });
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }
    };

    const ensureCustomersList = () => {
        if (customersList) {
            return customersList;
        }
        if (customersEmpty) {
            customersEmpty.remove();
        }
        enableCustomerSearch();
        const list = document.createElement('div');
        list.id = 'estate-sale-customers-list';
        list.className = 'record-list-simple';
        const tabPane = document.getElementById('estate-sale-tab-customers');
        if (tabPane) {
            tabPane.appendChild(list);
        }
        return list;
    };

    const buildCustomerActionsHtml = (customerId) => {
        const saleCreateUrl = `${saleCreateBase}?customer_id=${encodeURIComponent(String(customerId))}`;
        const customerShowUrl = `${customerCheckInBase}${customerId}`;
        let removeItem = '';
        if (canRemoveCustomers) {
            removeItem = `
                <li>
                    <form method="post" action="${removeCustomerBase}${customerId}/remove" class="m-0" onsubmit="return confirm('Remove this customer from the estate sale list?');">
                        <input type="hidden" name="csrf_token" value="${csrfToken.replace(/"/g, '&quot;')}" />
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fas fa-user-minus me-2"></i>Remove customer
                        </button>
                    </form>
                </li>`;
        }

        return `
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h me-1"></i>Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="${customerShowUrl}">
                            <i class="fas fa-user me-2"></i>View customer
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="${saleCreateUrl}">
                            <i class="fas fa-cash-register me-2"></i>Add sale
                        </a>
                    </li>
                    ${removeItem}
                </ul>
            </div>`;
    };

    const appendCustomerRow = (customer) => {
        const customerId = Number(customer && customer.id ? customer.id : 0);
        if (customerId <= 0) {
            return;
        }

        const list = ensureCustomersList();
        const queueNumber = Number(customer.queue_number || 0);
        const name = String(customer.name || ('Customer #' + customerId));
        const status = String(customer.check_in_status || 'waiting');
        const statusLabel = String(customer.check_in_status_label || 'Waiting');
        const showUrl = String(customer.show_url || `${customerCheckInBase}${customerId}`);
        const email = String(customer.email || '—');
        const phone = String(customer.phone || '').trim();
        const city = String(customer.city || '').trim();
        const state = String(customer.state || '').trim();
        const cityState = [city, state].filter(Boolean).join(', ');
        const metaParts = [email];
        if (phone) {
            metaParts.push(phone);
        }
        if (cityState) {
            metaParts.push(cityState);
        }

        const article = document.createElement('article');
        article.className = 'record-row-simple estate-sale-customer-row';
        article.dataset.customerId = String(customerId);
        article.dataset.searchText = buildCustomerSearchText(customer);
        article.dataset.checkInStatus = status;
        article.innerHTML = `
            <div class="record-row-main d-flex flex-wrap align-items-start justify-content-between gap-2 w-100">
                <div>
                    <h3 class="record-title-simple mb-1 d-flex flex-wrap align-items-center gap-2">
                        ${queueNumber > 0 ? `<span class="badge rounded-pill bg-primary estate-sale-customer-queue">#${queueNumber}</span>` : ''}
                        <a class="text-decoration-none estate-sale-customer-name-link" href="${showUrl.replace(/"/g, '&quot;')}"></a>
                        <span class="badge bg-${customerStatusBadgeClass(status)} estate-sale-customer-status"></span>
                    </h3>
                    <div class="small muted customer-meta"></div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 estate-sale-customer-actions">
                    ${buildCustomerCheckButtonsHtml(customer)}
                    ${buildCustomerActionsHtml(customerId)}
                </div>
            </div>
            <div class="record-row-fields record-row-fields-4 mt-2">
                <div class="record-field">
                    <span class="record-label">Created</span>
                    <span class="record-value estate-sale-customer-created-at">Just now</span>
                </div>
                <div class="record-field">
                    <span class="record-label">Entry</span>
                    <span class="record-value estate-sale-customer-entry-at">—</span>
                </div>
                <div class="record-field">
                    <span class="record-label">Exit</span>
                    <span class="record-value estate-sale-customer-exit-at">—</span>
                </div>
                <div class="record-field">
                    <span class="record-label">Visit</span>
                    <span class="record-value estate-sale-customer-visit-duration">—</span>
                </div>
            </div>
        `;
        article.querySelector('.estate-sale-customer-name-link').textContent = name;
        article.querySelector('.estate-sale-customer-status').textContent = statusLabel;
        article.querySelector('.customer-meta').textContent = metaParts.join(' · ');
        updateCustomerRow(article, customer);
        list.appendChild(article);
        filterCustomers();
    };

    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', filterCustomers);
    }

    const customersTabTrigger = document.getElementById('estate-sale-customers-tab');
    if (customersTabTrigger) {
        customersTabTrigger.addEventListener('shown.bs.tab', () => {
            customerSearchInput?.focus();
        });
    }

    if (customersTabActiveOnLoad) {
        customerSearchInput?.focus();
    }

    const openAddCustomerModal = () => {
        if (!modal) {
            return;
        }
        clearModal();
        const customersTabTrigger = document.getElementById('estate-sale-customers-tab');
        if (customersTabTrigger && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(customersTabTrigger).show();
        }
        modal.show();
        const firstField = document.getElementById('estate-sale-customer-first-name');
        if (firstField) {
            firstField.focus();
        }
    };

    if (addCustomerTriggers.length > 0 && modal) {
        addCustomerTriggers.forEach((trigger) => {
            trigger.addEventListener('click', openAddCustomerModal);
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (errorBox) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
            }

            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            body.set('first_name', String(document.getElementById('estate-sale-customer-first-name')?.value || '').trim());
            body.set('last_name', String(document.getElementById('estate-sale-customer-last-name')?.value || '').trim());
            body.set('email', String(document.getElementById('estate-sale-customer-email')?.value || '').trim());
            body.set('phone', String(document.getElementById('estate-sale-customer-phone')?.value || '').trim());
            body.set('city', String(document.getElementById('estate-sale-customer-city')?.value || '').trim());
            body.set('state', String(document.getElementById('estate-sale-customer-state')?.value || '').trim());

            fetch(quickCreateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: body.toString(),
            })
                .then((response) => response.json().then((payload) => ({ ok: response.ok, payload })))
                .then(({ ok, payload }) => {
                    if (!ok || !payload || !payload.ok) {
                        if (payload && payload.errors) {
                            const firstError = Object.values(payload.errors)[0];
                            throw new Error(String(firstError || 'Could not save customer.'));
                        }
                        throw new Error((payload && payload.error) ? payload.error : 'Could not save customer.');
                    }

                    appendCustomerRow(payload.customer || {});
                    if (payload.presence) {
                        updatePresenceSummary(payload.presence);
                    }
                    const customersTabTrigger = document.getElementById('estate-sale-customers-tab');
                    if (customersTabTrigger && window.bootstrap) {
                        bootstrap.Tab.getOrCreateInstance(customersTabTrigger).show();
                    }
                    if (modal) {
                        modal.hide();
                    }
                    clearModal();
                    showAlert(payload.message || 'Customer added.');
                })
                .catch((error) => {
                    if (errorBox) {
                        errorBox.textContent = error.message || 'Could not save customer.';
                        errorBox.classList.remove('d-none');
                    }
                });
        });
    }

    const quickCreateExpenseUrl = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/quick-create-expense')) ?>;
    const removeExpenseBase = <?= json_encode(url('/estate-sales/' . (string) $estateSaleId . '/expenses/')) ?>;
    const addExpenseTriggers = document.querySelectorAll('.estate-sale-add-expense-trigger');
    const expenseModalEl = document.getElementById('estate-sale-add-expense-modal');
    const expenseModal = expenseModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(expenseModalEl) : null;
    const expenseSaveBtn = document.getElementById('estate-sale-add-expense-save');
    const expenseErrorBox = document.getElementById('estate-sale-add-expense-error');
    const expenseAlertBox = document.getElementById('estate-sale-expense-alert');
    const expensesList = document.getElementById('estate-sale-expenses-list');
    const expensesEmpty = document.getElementById('estate-sale-expenses-empty');
    const expenseCountBadge = document.getElementById('estate-sale-expense-count');

    const formatMoney = (amount) => {
        const value = Number(amount || 0);
        return '$' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const showExpenseAlert = (message, type = 'success') => {
        if (!expenseAlertBox) {
            return;
        }
        expenseAlertBox.textContent = message;
        expenseAlertBox.className = 'alert alert-' + type + ' mb-3';
        expenseAlertBox.classList.remove('d-none');
    };

    const updateFinancialSummary = (summary) => {
        if (!summary || typeof summary !== 'object') {
            return;
        }
        const totalSalesEl = document.getElementById('estate-sale-total-sales');
        const grossTotalEl = document.getElementById('estate-sale-gross-total');
        const netTotalEl = document.getElementById('estate-sale-net-total');
        const totalExpensesEl = document.getElementById('estate-sale-total-expenses');
        const totalLaborEl = document.getElementById('estate-sale-total-labor');
        const clientShareEl = document.getElementById('estate-sale-client-share');
        const ourShareEl = document.getElementById('estate-sale-our-share');
        const grossValue = formatMoney(summary.gross ?? summary.total_sales);
        const netValue = summary.net ?? summary.our_share;
        if (totalSalesEl) {
            totalSalesEl.textContent = grossValue;
        }
        if (grossTotalEl) {
            grossTotalEl.textContent = grossValue;
        }
        if (netTotalEl) {
            netTotalEl.textContent = netValue === null || netValue === undefined
                ? '—'
                : formatMoney(netValue);
        }
        if (totalExpensesEl) {
            totalExpensesEl.textContent = formatMoney(summary.total_expenses);
        }
        if (totalLaborEl) {
            totalLaborEl.textContent = formatMoney(summary.total_labor);
        }
        if (clientShareEl) {
            clientShareEl.textContent = summary.client_share === null || summary.client_share === undefined
                ? '—'
                : formatMoney(summary.client_share);
        }
        if (ourShareEl) {
            ourShareEl.textContent = summary.our_share === null || summary.our_share === undefined
                ? '—'
                : formatMoney(summary.our_share);
        }
        const splitHelpEl = document.getElementById('estate-sale-split-help');
        if (splitHelpEl && summary.split_help_text) {
            splitHelpEl.textContent = summary.split_help_text;
        }
    };

    const ensureExpensesList = () => {
        if (expensesList) {
            return expensesList;
        }
        if (expensesEmpty) {
            expensesEmpty.remove();
        }
        const list = document.createElement('div');
        list.id = 'estate-sale-expenses-list';
        list.className = 'record-list-simple';
        const tabPane = document.getElementById('estate-sale-tab-expenses');
        if (tabPane) {
            tabPane.appendChild(list);
        }
        return list;
    };

    const updateExpenseCount = (delta = 0) => {
        if (!expenseCountBadge) {
            return;
        }
        const current = Number(expenseCountBadge.textContent || '0');
        const next = Math.max(0, current + delta);
        expenseCountBadge.textContent = String(next);
        expenseCountBadge.setAttribute('data-count', String(next));
    };

    const appendExpenseRow = (expense) => {
        const expenseId = Number(expense && expense.id ? expense.id : 0);
        if (expenseId <= 0) {
            return;
        }

        const list = ensureExpensesList();
        const category = String(expense.category || ('Expense #' + expenseId));
        const amount = formatMoney(expense.amount || 0);
        const expenseDate = String(expense.expense_date || '—');
        const note = String(expense.note || '').trim();
        const metaParts = [expenseDate, amount];
        if (note) {
            metaParts.push(note);
        }

        const article = document.createElement('article');
        article.className = 'record-row-simple';
        article.dataset.expenseId = String(expenseId);
        article.innerHTML = `
            <div class="record-row-main d-flex flex-wrap align-items-start justify-content-between gap-2 w-100">
                <div>
                    <h3 class="record-title-simple mb-1 expense-title"></h3>
                    <div class="small muted expense-meta"></div>
                </div>
                <form method="post" action="${removeExpenseBase}${expenseId}/remove" onsubmit="return confirm('Remove this expense?');">
                    <input type="hidden" name="csrf_token" value="${csrfToken.replace(/"/g, '&quot;')}" />
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
            </div>
        `;
        article.querySelector('.expense-title').textContent = category;
        article.querySelector('.expense-meta').textContent = metaParts.join(' · ');
        list.prepend(article);
        updateExpenseCount(1);
    };

    const clearExpenseModal = () => {
        const categoryEl = document.getElementById('estate-sale-expense-category');
        const amountEl = document.getElementById('estate-sale-expense-amount');
        const dateEl = document.getElementById('estate-sale-expense-date');
        const noteEl = document.getElementById('estate-sale-expense-note');
        if (categoryEl) {
            categoryEl.value = '';
        }
        if (amountEl) {
            amountEl.value = '';
        }
        if (dateEl) {
            dateEl.value = new Date().toISOString().slice(0, 10);
        }
        if (noteEl) {
            noteEl.value = '';
        }
        if (expenseErrorBox) {
            expenseErrorBox.classList.add('d-none');
            expenseErrorBox.textContent = '';
        }
    };

    const openAddExpenseModal = () => {
        if (!expenseModal) {
            return;
        }
        clearExpenseModal();
        const expensesTabTrigger = document.getElementById('estate-sale-expenses-tab');
        if (expensesTabTrigger && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(expensesTabTrigger).show();
        }
        expenseModal.show();
        document.getElementById('estate-sale-expense-category')?.focus();
    };

    if (addExpenseTriggers.length > 0 && expenseModal) {
        addExpenseTriggers.forEach((trigger) => {
            trigger.addEventListener('click', openAddExpenseModal);
        });
    }

    if (expenseSaveBtn) {
        expenseSaveBtn.addEventListener('click', () => {
            if (expenseErrorBox) {
                expenseErrorBox.classList.add('d-none');
                expenseErrorBox.textContent = '';
            }

            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            body.set('category', String(document.getElementById('estate-sale-expense-category')?.value || '').trim());
            body.set('amount', String(document.getElementById('estate-sale-expense-amount')?.value || '').trim());
            body.set('expense_date', String(document.getElementById('estate-sale-expense-date')?.value || '').trim());
            body.set('note', String(document.getElementById('estate-sale-expense-note')?.value || '').trim());

            fetch(quickCreateExpenseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: body.toString(),
            })
                .then((response) => response.json().then((payload) => ({ ok: response.ok, payload })))
                .then(({ ok, payload }) => {
                    if (!ok || !payload || !payload.ok) {
                        if (payload && payload.errors) {
                            const firstError = Object.values(payload.errors)[0];
                            throw new Error(String(firstError || 'Could not save expense.'));
                        }
                        throw new Error((payload && payload.error) ? payload.error : 'Could not save expense.');
                    }

                    appendExpenseRow(payload.expense || {});
                    updateFinancialSummary(payload.financialSummary || null);
                    if (expenseModal) {
                        expenseModal.hide();
                    }
                    clearExpenseModal();
                    showExpenseAlert(payload.message || 'Expense added.');
                })
                .catch((error) => {
                    if (expenseErrorBox) {
                        expenseErrorBox.textContent = error.message || 'Could not save expense.';
                        expenseErrorBox.classList.remove('d-none');
                    }
                });
        });
    }
});
</script>
