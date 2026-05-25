<?php
$purchase = is_array($purchase ?? null) ? $purchase : [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$linkedSales = is_array($linkedSales ?? null) ? $linkedSales : [];
$salesTotals = is_array($salesTotals ?? null) ? $salesTotals : [];
$purchaseProfit = (float) ($purchaseProfit ?? 0);
$purchaseId = (int) ($purchase['id'] ?? 0);
$salesCount = (int) ($salesTotals['count'] ?? count($linkedSales));
$taskCount = count($tasks);

$activeTab = strtolower(trim((string) ($activeTab ?? 'details')));
$allowedTabs = ['details', 'financial', 'sales', 'tasks'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'details';
}

$detailsTabActive = $activeTab === 'details';
$financialTabActive = $activeTab === 'financial';
$salesTabActive = $activeTab === 'sales';
$tasksTabActive = $activeTab === 'tasks';

$displayTitle = trim((string) ($purchase['title'] ?? ''));
if ($displayTitle === '') {
    $displayTitle = 'Purchase #' . (string) $purchaseId;
}

$statusLabel = static function (string $value): string {
    if ($value === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', $value));
};

$formatDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return '—';
    }

    return date('m/d/Y', $ts);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($displayTitle) ?></h1>
        <p class="muted">Purchase Order</p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/purchases/' . (string) $purchaseId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Purchase</a></li>
                <li>
                    <form method="post" action="<?= e(url('/purchases/' . (string) $purchaseId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Delete this purchase order?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/purchases')) ?>">Back to Purchases</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs client-tabs" id="purchase-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
                    id="purchase-details-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#purchase-tab-details"
                    data-tab="details"
                    aria-controls="purchase-tab-details"
                    aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-address-card"></i></span>
                    <span class="estate-sale-tab-label">Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $financialTabActive ? ' active' : '' ?>"
                    id="purchase-financial-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#purchase-tab-financial"
                    data-tab="financial"
                    aria-controls="purchase-tab-financial"
                    aria-selected="<?= $financialTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                    <span class="estate-sale-tab-label">Financial</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $salesTabActive ? ' active' : '' ?>"
                    id="purchase-sales-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#purchase-tab-sales"
                    data-tab="sales"
                    aria-controls="purchase-tab-sales"
                    aria-selected="<?= $salesTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-sack-dollar"></i></span>
                    <span class="estate-sale-tab-label">Sales</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $salesCount) ?>"><?= e((string) $salesCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $tasksTabActive ? ' active' : '' ?>"
                    id="purchase-tasks-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#purchase-tab-tasks"
                    data-tab="tasks"
                    aria-controls="purchase-tab-tasks"
                    aria-selected="<?= $tasksTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-list-check"></i></span>
                    <span class="estate-sale-tab-label">Tasks</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $taskCount) ?>"><?= e((string) $taskCount) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body tab-content" id="purchase-tab-content">
        <div
            class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>"
            id="purchase-tab-details"
            role="tabpanel"
            aria-labelledby="purchase-details-tab"
            tabindex="0"
        >
            <div class="record-row-fields record-row-fields-5">
                <div class="record-field">
                    <span class="record-label">Purchase ID</span>
                    <span class="record-value"><?= e((string) $purchaseId) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Status</span>
                    <span class="record-value"><?= e($statusLabel((string) ($purchase['status'] ?? ''))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Contact Date</span>
                    <span class="record-value"><?= e($formatDate((string) ($purchase['contact_date'] ?? ''))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Purchase Date</span>
                    <span class="record-value"><?= e($formatDate((string) ($purchase['purchase_date'] ?? ''))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Client</span>
                    <?php if (((int) ($purchase['client_id'] ?? 0)) > 0): ?>
                        <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/clients/' . (string) ((int) ($purchase['client_id'] ?? 0)))) ?>"><?= e(trim((string) ($purchase['client_name'] ?? '')) ?: ('Client #' . (string) ((int) ($purchase['client_id'] ?? 0)))) ?></a></span>
                    <?php else: ?>
                        <span class="record-value">—</span>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-4">

            <h2 class="h6 mb-3"><i class="fas fa-note-sticky me-2"></i>Note</h2>
            <div class="record-value"><?= e(trim((string) ($purchase['notes'] ?? '')) ?: '—') ?></div>
        </div>

        <div
            class="tab-pane fade<?= $financialTabActive ? ' show active' : '' ?>"
            id="purchase-tab-financial"
            role="tabpanel"
            aria-labelledby="purchase-financial-tab"
            tabindex="0"
        >
            <h2 class="h6 mb-3"><i class="fas fa-chart-line me-2"></i>Purchase Financial Snapshot</h2>
            <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2">
                <div class="record-field">
                    <span class="record-label">Purchase Price</span>
                    <span class="record-value">$<?= e(number_format((float) ($purchase['purchase_price'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Linked Sales Gross</span>
                    <span class="record-value">$<?= e(number_format((float) ($salesTotals['gross'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Linked Sales Net</span>
                    <span class="record-value">$<?= e(number_format((float) ($salesTotals['net'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Profit (Net - Purchase Price)</span>
                    <span class="record-value">$<?= e(number_format($purchaseProfit, 2)) ?></span>
                </div>
            </div>
        </div>

        <div
            class="tab-pane fade<?= $salesTabActive ? ' show active' : '' ?>"
            id="purchase-tab-sales"
            role="tabpanel"
            aria-labelledby="purchase-sales-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-sack-dollar me-2"></i>Linked Sales</h2>
                <span class="small muted"><?= e((string) $salesCount) ?> linked sale(s)</span>
            </div>
            <?php if ($linkedSales === []): ?>
                <div class="record-empty">No sales linked to this purchase yet.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($linkedSales as $sale): ?>
                        <?php
                        if (!is_array($sale)) {
                            continue;
                        }
                        $saleId = (int) ($sale['id'] ?? 0);
                        $saleTitle = trim((string) ($sale['name'] ?? ''));
                        if ($saleTitle === '') {
                            $saleTitle = 'Sale #' . (string) $saleId;
                        }
                        ?>
                        <article class="record-row-simple">
                            <a class="record-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                                <div class="record-row-main">
                                    <h3 class="record-title-simple"><?= e($saleTitle) ?></h3>
                                </div>
                                <div class="record-row-fields record-row-fields-4">
                                    <div class="record-field">
                                        <span class="record-label">Date</span>
                                        <span class="record-value"><?= e($formatDate((string) ($sale['sale_date'] ?? null))) ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Type</span>
                                        <span class="record-value"><?= e($statusLabel((string) ($sale['sale_type'] ?? ''))) ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Gross</span>
                                        <span class="record-value">$<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Net</span>
                                        <span class="record-value">$<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="tab-pane fade<?= $tasksTabActive ? ' show active' : '' ?>"
            id="purchase-tab-tasks"
            role="tabpanel"
            aria-labelledby="purchase-tasks-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-list-check me-2"></i>Follow-Up Tasks</h2>
                <a class="small fw-semibold text-decoration-none" href="<?= e(url('/tasks?q=' . rawurlencode('purchase #' . (string) $purchaseId))) ?>">Open Tasks</a>
            </div>
            <?php if ($tasks === []): ?>
                <div class="record-empty">No follow-up tasks linked to this purchase.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $taskId = (int) ($task['id'] ?? 0);
                        $taskStatus = strtolower(trim((string) ($task['status'] ?? 'open')));
                        $isClosed = $taskStatus === 'closed';
                        ?>
                        <article class="record-row-simple">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <a class="record-row-link flex-grow-1" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                                    <div class="record-row-main">
                                        <h3 class="record-title-simple<?= $isClosed ? ' text-decoration-line-through text-muted' : '' ?>"><?= e(trim((string) ($task['title'] ?? '')) !== '' ? (string) $task['title'] : ('Task #' . (string) $taskId)) ?></h3>
                                    </div>
                                    <div class="record-row-fields record-row-fields-3">
                                        <div class="record-field">
                                            <span class="record-label">Status</span>
                                            <span class="record-value"><?= e($statusLabel((string) ($task['status'] ?? ''))) ?></span>
                                        </div>
                                        <div class="record-field">
                                            <span class="record-label">Due</span>
                                            <span class="record-value"><?= e(format_datetime((string) ($task['due_at'] ?? ''))) ?></span>
                                        </div>
                                        <div class="record-field">
                                            <span class="record-label">Owner</span>
                                            <span class="record-value"><?= e(trim((string) ($task['owner_name'] ?? '')) ?: '—') ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tabList = document.getElementById('purchase-tabs');
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
});
</script>
