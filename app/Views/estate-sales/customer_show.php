<?php
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$customer = is_array($customer ?? null) ? $customer : [];
$visits = is_array($visits ?? null) ? $visits : [];
$sales = is_array($sales ?? null) ? $sales : [];
$canRemoveCustomers = (bool) ($canRemoveCustomers ?? false);

$estateSaleId = (int) ($estateSale['id'] ?? 0);
$customerId = (int) ($customer['id'] ?? 0);
$queueNumber = (int) ($customer['queue_number'] ?? 0);
$estateSaleTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
$customerName = \App\Models\EstateSale::customerDisplayName($customer);
$checkInStatus = \App\Models\EstateSale::customerCheckInStatus($customer);
$statusLabel = \App\Models\EstateSale::customerCheckInStatusLabel($checkInStatus);
$statusBadge = match ($checkInStatus) {
    'inside' => 'success',
    'left' => 'secondary',
    default => 'light text-dark border',
};

$formatDt = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y g:i A', $ts);
};

$formatSaleDate = static function (?string $value): string {
    return format_datetime($value);
};

$formatMoney = static fn (float $amount): string => '$' . number_format($amount, 2);

$addedAt = $formatDt((string) ($customer['added_at'] ?? ''));
$checkedInAt = $formatDt((string) ($customer['checked_in_at'] ?? ''));
$checkedOutAt = $formatDt((string) ($customer['checked_out_at'] ?? ''));
$visitDuration = \App\Models\EstateSale::formatVisitDuration(
    \App\Models\EstateSale::customerVisitDurationMinutes(
        trim((string) ($customer['checked_in_at'] ?? '')) ?: null,
        trim((string) ($customer['checked_out_at'] ?? '')) ?: null
    )
);

$email = trim((string) ($customer['email'] ?? ''));
$phone = trim((string) ($customer['phone'] ?? ''));
$city = trim((string) ($customer['city'] ?? ''));
$state = trim((string) ($customer['state'] ?? ''));
$cityState = trim(implode(', ', array_filter([$city, $state], static fn (string $v): bool => $v !== '')));
$subscribesFutureSales = !empty($customer['subscribes_to_future_sales']);
$futureSalesContactLabel = \App\Models\EstateSale::futureSalesContactMethodLabel($customer['future_sales_contact_method'] ?? null);
$futureSalesLabel = $subscribesFutureSales
    ? ('Yes' . ($futureSalesContactLabel !== '' ? ' · ' . $futureSalesContactLabel : ''))
    : 'No';
$csrfToken = csrf_token();
$backUrl = url('/estate-sales/' . (string) $estateSaleId . '?tab=customers');
$editUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/edit');
$saleCreateUrl = url('/estate-sales/' . (string) $estateSaleId . '/sales/create?customer_id=' . (string) $customerId);
$checkInUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/check-in');
$checkOutUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/check-out');
$removeUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/remove');
$canCheckIn = $checkInStatus !== 'inside';
$canCheckOut = $checkInStatus === 'inside';
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <?php if ($queueNumber > 0): ?>
                <span class="badge rounded-pill bg-primary fs-6">#<?= e((string) $queueNumber) ?></span>
            <?php endif; ?>
            <span><?= e($customerName) ?></span>
            <span class="badge bg-<?= e($statusBadge) ?>"><?= e($statusLabel) ?></span>
        </h1>
        <p class="muted mb-0"><?= e($estateSaleTitle) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canCheckIn): ?>
                    <li>
                        <button type="button" class="dropdown-item estate-sale-customer-check-in" data-url="<?= e($checkInUrl) ?>">
                            <i class="fas fa-door-open me-2"></i>Check in
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($canCheckOut): ?>
                    <li>
                        <button type="button" class="dropdown-item estate-sale-customer-check-out" data-url="<?= e($checkOutUrl) ?>">
                            <i class="fas fa-door-closed me-2"></i>Check out
                        </button>
                    </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item" href="<?= e($saleCreateUrl) ?>">
                        <i class="fas fa-cash-register me-2"></i>Add sale
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e($editUrl) ?>">
                        <i class="fas fa-pen me-2"></i>Edit customer
                    </a>
                </li>
                <?php if ($canRemoveCustomers): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= e($removeUrl) ?>" class="m-0" onsubmit="return confirm('Remove this customer from the estate sale list?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-user-minus me-2"></i>Remove from sale
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e($backUrl) ?>">Back to customers</a>
    </div>
</div>

<div id="estate-sale-customer-detail-alert" class="alert d-none mb-3" role="alert"></div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user me-2"></i>Customer details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Email</span>
                <span class="record-value"><?= e($email !== '' ? $email : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Phone</span>
                <span class="record-value"><?= e($phone !== '' ? $phone : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">City / State</span>
                <span class="record-value"><?= e($cityState !== '' ? $cityState : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Future sales</span>
                <span class="record-value"><?= e($futureSalesLabel) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Latest visit</span>
                <span class="record-value" id="estate-sale-customer-visit-duration"><?= e($visitDuration) ?></span>
            </div>
        </div>
        <div class="record-row-fields record-row-fields-4 mt-3">
            <div class="record-field">
                <span class="record-label">Created</span>
                <span class="record-value" id="estate-sale-customer-created-at"><?= e($addedAt) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Entry</span>
                <span class="record-value" id="estate-sale-customer-entry-at"><?= e($checkedInAt) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Exit</span>
                <span class="record-value" id="estate-sale-customer-exit-at"><?= e($checkedOutAt) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value" id="estate-sale-customer-status-label"><?= e($statusLabel) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-clock me-2"></i>Visit log</strong>
        <span class="small muted"><?= e((string) count($visits)) ?> visit(s)</span>
    </div>
    <div class="card-body">
        <?php if ($visits === []): ?>
            <div class="record-empty mb-0">No check-ins recorded yet.</div>
        <?php else: ?>
            <div class="record-list-simple" id="estate-sale-customer-visits-list">
                <?php foreach ($visits as $visit): ?>
                    <?php
                    if (!is_array($visit)) {
                        continue;
                    }
                    $visitIn = $formatDt((string) ($visit['checked_in_at'] ?? ''));
                    $visitOut = $formatDt((string) ($visit['checked_out_at'] ?? ''));
                    $visitDurationRow = \App\Models\EstateSale::formatVisitDuration(
                        \App\Models\EstateSale::customerVisitDurationMinutes(
                            trim((string) ($visit['checked_in_at'] ?? '')) ?: null,
                            trim((string) ($visit['checked_out_at'] ?? '')) ?: null
                        )
                    );
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-fields record-row-fields-3">
                            <div class="record-field">
                                <span class="record-label">Entry</span>
                                <span class="record-value"><?= e($visitIn) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Exit</span>
                                <span class="record-value"><?= e($visitOut) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Duration</span>
                                <span class="record-value"><?= e($visitOut !== '—' ? $visitDurationRow : 'In progress') ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-cash-register me-2"></i>Sales at this estate sale</strong>
        <a class="btn btn-primary btn-sm" href="<?= e($saleCreateUrl) ?>">
            <i class="fas fa-plus me-1"></i>Add sale
        </a>
    </div>
    <div class="card-body">
        <?php if ($sales === []): ?>
            <div class="record-empty mb-0">No sales linked to this customer yet.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($sales as $sale): ?>
                    <?php
                    if (!is_array($sale)) {
                        continue;
                    }
                    $saleId = (int) ($sale['id'] ?? 0);
                    $saleName = trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $saleId);
                    $canViewFinancials = can_view_financials();
                    $effectiveClientPct = $sale['effective_client_percentage'] ?? null;
                    $clientPctIsOverride = !empty($sale['client_percentage_is_override']);
                    $paymentMethodLabel = \App\Models\Sale::paymentMethodLabel($sale['payment_method'] ?? null);
                    $saleRowUrl = $canViewFinancials
                        ? sale_detail_url($saleId, '/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId)
                        : url('/estate-sales/' . (string) $estateSaleId . '/sales/' . (string) $saleId . '/edit');
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e($saleRowUrl) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple mb-1"><?= e($saleName) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-<?= $canViewFinancials ? '5' : '3' ?> mt-2">
                                <div class="record-field">
                                    <span class="record-label">Date & time</span>
                                    <span class="record-value"><?= e($formatSaleDate((string) ($sale['sale_date'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Payment</span>
                                    <span class="record-value"><?= e($paymentMethodLabel) ?></span>
                                </div>
                                <?php if ($canViewFinancials): ?>
                                <div class="record-field">
                                    <span class="record-label">Amount</span>
                                    <span class="record-value"><?= e($formatMoney((float) ($sale['gross_amount'] ?? 0))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Client split</span>
                                    <span class="record-value<?= $clientPctIsOverride ? ' fw-bold' : '' ?>"><?= e(format_client_percentage(is_numeric($effectiveClientPct) ? (float) $effectiveClientPct : null)) ?></span>
                                </div>
                                <?php endif; ?>
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
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const alertBox = document.getElementById('estate-sale-customer-detail-alert');

    const formatDt = (value) => {
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

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }
        alertBox.textContent = message;
        alertBox.className = 'alert alert-' + type + ' mb-3';
        alertBox.classList.remove('d-none');
    };

    const updateDetailFields = (customer) => {
        const entryEl = document.getElementById('estate-sale-customer-entry-at');
        const exitEl = document.getElementById('estate-sale-customer-exit-at');
        const createdEl = document.getElementById('estate-sale-customer-created-at');
        const statusEl = document.getElementById('estate-sale-customer-status-label');
        const durationEl = document.getElementById('estate-sale-customer-visit-duration');

        if (entryEl) {
            entryEl.textContent = formatDt(customer.checked_in_at);
        }
        if (exitEl) {
            exitEl.textContent = formatDt(customer.checked_out_at);
        }
        if (createdEl && customer.added_at) {
            createdEl.textContent = formatDt(customer.added_at);
        }
        if (statusEl) {
            statusEl.textContent = String(customer.check_in_status_label || '');
        }
        if (durationEl) {
            durationEl.textContent = String(customer.visit_duration || '—');
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

    document.querySelectorAll('.estate-sale-customer-check-in, .estate-sale-customer-check-out').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = String(button.dataset.url || '').trim();
            if (url === '') {
                return;
            }

            button.disabled = true;
            try {
                const data = await postCustomerAction(url);
                showAlert(String(data.message || 'Saved.'), 'success');
                if (data.customer) {
                    updateDetailFields(data.customer);
                }
                window.setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                showAlert(error instanceof Error ? error.message : 'Request failed.', 'danger');
                button.disabled = false;
            }
        });
    });
});
</script>
