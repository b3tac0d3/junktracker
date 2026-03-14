<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$clientOptions = is_array($clientOptions ?? null) ? $clientOptions : [];
$jobOptions = is_array($jobOptions ?? null) ? $jobOptions : [];
$invoiceItemTypes = is_array($invoiceItemTypes ?? null) ? $invoiceItemTypes : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/billing'));
$documentType = strtolower(trim((string) ($documentType ?? ($form['type'] ?? 'invoice'))));
if (!in_array($documentType, ['estimate', 'invoice'], true)) {
    $documentType = 'invoice';
}

$estimateStatusOptions = is_array($estimateStatusOptions ?? null) ? $estimateStatusOptions : [];
if ($estimateStatusOptions === []) {
    $estimateStatusOptions = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'approved' => 'Approved',
        'declined' => 'Declined',
    ];
}
$invoiceStatusOptions = is_array($invoiceStatusOptions ?? null) ? $invoiceStatusOptions : [];
if ($invoiceStatusOptions === []) {
    $invoiceStatusOptions = [
        'unsent' => 'Unsent',
        'sent' => 'Sent',
        'partially_paid' => 'Partially Paid',
        'paid_in_full' => 'Paid in Full',
    ];
}
$paymentCategoryOptions = is_array($paymentCategoryOptions ?? null) ? $paymentCategoryOptions : [];
if ($paymentCategoryOptions === []) {
    $paymentCategoryOptions = [
        'deposit' => 'Deposit',
        'payment' => 'Payment',
    ];
}
$paymentTypeOptions = is_array($paymentTypeOptions ?? null) ? $paymentTypeOptions : [];
if ($paymentTypeOptions === []) {
    $paymentTypeOptions = [
        'check' => 'Check',
        'cc' => 'CC',
        'cash' => 'Cash',
        'venmo' => 'Venmo',
        'cashapp' => 'Cashapp',
        'other' => 'Other',
    ];
}

$normalizeStatusMap = static function (array $options): array {
    $normalized = [];
    foreach ($options as $value => $label) {
        $key = strtolower(trim((string) $value));
        if ($key === '' || array_key_exists($key, $normalized)) {
            continue;
        }
        $normalized[$key] = (string) $label;
    }
    return $normalized;
};
$estimateStatusOptions = $normalizeStatusMap($estimateStatusOptions);
$invoiceStatusOptions = $normalizeStatusMap($invoiceStatusOptions);
$paymentCategoryOptions = $normalizeStatusMap($paymentCategoryOptions);
$paymentTypeOptions = $normalizeStatusMap($paymentTypeOptions);
$statusOptions = $documentType === 'estimate' ? $estimateStatusOptions : $invoiceStatusOptions;
$selectedStatus = strtolower(trim((string) ($form['status'] ?? ($documentType === 'estimate' ? 'draft' : 'unsent'))));
if ($documentType === 'invoice') {
    if ($selectedStatus === 'draft') {
        $selectedStatus = 'unsent';
    } elseif ($selectedStatus === 'partial') {
        $selectedStatus = 'partially_paid';
    } elseif ($selectedStatus === 'paid') {
        $selectedStatus = 'paid_in_full';
    }
}
if (!array_key_exists($selectedStatus, $statusOptions)) {
    $selectedStatus = array_key_first($statusOptions) ?? ($documentType === 'estimate' ? 'draft' : 'unsent');
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$clientId = (int) ($form['client_id'] ?? 0);
$jobId = (int) ($form['job_id'] ?? 0);
$clientName = '—';
$jobTitle = '—';
foreach ($clientOptions as $client) {
    if ((int) ($client['id'] ?? 0) === $clientId) {
        $clientName = trim((string) ($client['name'] ?? '')) ?: ('Client #' . (string) $clientId);
        break;
    }
}
foreach ($jobOptions as $job) {
    if ((int) ($job['id'] ?? 0) === $jobId) {
        $jobTitle = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
        break;
    }
}

$dateLabel = $documentType === 'estimate' ? 'Estimate Date' : 'Invoice Date';
$dueLabel = $documentType === 'estimate' ? 'Expire Date' : 'Due Date';
$inlinePaymentDate = trim((string) ($form['payment_paid_date'] ?? date('Y-m-d')));
$inlinePaymentCategory = strtolower(trim((string) ($form['payment_type'] ?? 'payment')));
$inlinePaymentMethod = strtolower(trim((string) ($form['payment_method'] ?? 'cash')));
$inlinePaymentReference = trim((string) ($form['payment_reference_number'] ?? ''));
$inlinePaymentAmount = trim((string) ($form['payment_amount'] ?? ''));
$inlinePaymentNote = trim((string) ($form['payment_note'] ?? ''));
if (!array_key_exists($inlinePaymentCategory, $paymentCategoryOptions)) {
    $inlinePaymentCategory = (string) (array_key_first($paymentCategoryOptions) ?? 'payment');
}
if (!array_key_exists($inlinePaymentMethod, $paymentTypeOptions)) {
    $inlinePaymentMethod = (string) (array_key_first($paymentTypeOptions) ?? 'cash');
}
$items = is_array($form['items'] ?? null) ? $form['items'] : [];
if ($items === []) {
    $items = [[
        'name' => '',
        'note' => '',
        'quantity' => '1.00',
        'rate' => '0.00',
        'taxable' => '1',
    ]];
}

$itemTypeDefaults = [];
foreach ($invoiceItemTypes as $typeRow) {
    if (!is_array($typeRow)) {
        continue;
    }
    $typeName = trim((string) ($typeRow['name'] ?? ''));
    if ($typeName === '') {
        continue;
    }
    $itemTypeDefaults[$typeName] = [
        'rate' => number_format((float) ($typeRow['default_unit_price'] ?? 0), 2, '.', ''),
        'taxable' => ((int) ($typeRow['default_taxable'] ?? 0)) === 1 ? '1' : '0',
        'note' => trim((string) ($typeRow['default_note'] ?? '')),
    ];
}
$itemTypeNames = array_keys($itemTypeDefaults);
natcasesort($itemTypeNames);
$itemTypeNames = array_values($itemTypeNames);

$from = strtolower(trim((string) ($_GET['from'] ?? ($_POST['from'] ?? ''))));
$jobBackId = (int) ($_GET['job_id'] ?? ($_POST['job_id'] ?? $jobId));
$fromJob = $from === 'job' && $jobBackId > 0;
$headerBackUrl = $fromJob
    ? url('/jobs/' . (string) $jobBackId)
    : url('/billing');
$headerBackLabel = $fromJob ? 'Back to Job' : 'Back to Billing';
$cancelUrl = $fromJob
    ? url('/jobs/' . (string) $jobBackId)
    : ($mode === 'edit' && isset($invoiceId)
        ? url('/billing/' . (string) ((int) $invoiceId))
        : url('/billing'));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e(($mode === 'edit' ? 'Edit ' : 'Add ') . ($documentType === 'estimate' ? 'Estimate' : 'Invoice')) ?></h1>
        <p class="muted"><?= e($documentType === 'estimate' ? 'Estimate form' : 'Invoice form') ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($headerBackUrl) ?>"><?= e($headerBackLabel) ?></a>
    </div>
</div>

<form method="post" action="<?= e($actionUrl) ?>" id="financial-doc-form">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($documentType) ?>">
    <input type="hidden" name="client_id" value="<?= e((string) $clientId) ?>">
    <input type="hidden" name="job_id" value="<?= e((string) $jobId) ?>">
    <?php if ($fromJob): ?>
        <input type="hidden" name="from" value="job">
    <?php endif; ?>
    <section class="card index-card index-card-overflow-visible mb-3">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-file-lines me-2"></i><?= e($documentType === 'estimate' ? 'Estimate Details' : 'Invoice Details') ?></strong>
        </div>
        <div class="card-body">
            <?php if ($clientId <= 0 || $jobId <= 0): ?>
                <div class="alert alert-warning mb-3">This document must be created from a job so client and job are locked correctly.</div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label fw-semibold">Client</label>
                    <div class="fw-semibold py-2"><?= e($clientName) ?></div>
                    <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
                </div>

                <div class="col-12 col-lg-6">
                    <label class="form-label fw-semibold">Job</label>
                    <div class="fw-semibold py-2"><?= e($jobTitle) ?></div>
                    <?php if ($hasError('job_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('job_id')) ?></div><?php endif; ?>
                </div>

                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="billing-status">Status</label>
                    <select id="billing-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
                </div>

                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="billing-issue-date"><?= e($dateLabel) ?></label>
                    <input id="billing-issue-date" type="date" name="issue_date" class="form-control <?= $hasError('issue_date') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['issue_date'] ?? '')) ?>" />
                    <?php if ($hasError('issue_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('issue_date')) ?></div><?php endif; ?>
                </div>

                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="billing-due-date"><?= e($dueLabel) ?></label>
                    <input id="billing-due-date" type="date" name="due_date" class="form-control <?= $hasError('due_date') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['due_date'] ?? '')) ?>" />
                    <?php if ($hasError('due_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('due_date')) ?></div><?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="billing-customer-note">Note</label>
                    <textarea id="billing-customer-note" name="customer_note" rows="2" class="form-control"><?= e((string) ($form['customer_note'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card mb-3">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-list-ul me-2"></i>Line Items</strong>
            <button type="button" class="btn btn-primary btn-sm" id="line-item-add"><i class="fas fa-plus me-1"></i>Add Line Item</button>
        </div>
        <div class="card-body">
            <?php if ($hasError('items')): ?><div class="alert alert-danger py-2"><?= e($fieldError('items')) ?></div><?php endif; ?>
            <?php if ($invoiceItemTypes !== []): ?>
                <p class="small muted mb-2">Line item names must be selected from your saved invoice item types.</p>
            <?php else: ?>
                <div class="alert alert-warning py-2 mb-2">No invoice item types are configured. Add them in Admin before creating estimates or invoices.</div>
            <?php endif; ?>
            <div class="line-item-grid line-item-grid-header d-none d-md-flex mb-2">
                <div class="line-item-cell line-item-cell-handle"></div>
                <div class="line-item-cell line-item-cell-name">Name</div>
                <div class="line-item-cell line-item-cell-note">Note</div>
                <div class="line-item-cell line-item-cell-qty text-center">Qty</div>
                <div class="line-item-cell line-item-cell-rate text-center">Rate</div>
                <div class="line-item-cell line-item-cell-amount text-center">Amount</div>
                <div class="line-item-cell line-item-cell-taxable text-center">Taxable</div>
                <div class="line-item-cell line-item-cell-remove"></div>
            </div>
            <div id="line-items-wrap" class="d-grid gap-2">
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $idx = (int) $index;
                    $name = trim((string) ($item['name'] ?? ''));
                    $note = trim((string) ($item['note'] ?? ''));
                    $quantity = trim((string) ($item['quantity'] ?? '1.00'));
                    $rate = trim((string) ($item['rate'] ?? '0.00'));
                    $taxable = ((string) ($item['taxable'] ?? '0')) === '1';
                    ?>
                    <div class="line-item-row border rounded p-2">
                        <div class="line-item-grid">
                            <div class="line-item-cell line-item-cell-handle">
                                <span class="line-item-drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
                            </div>
                            <div class="line-item-cell line-item-cell-name">
                                <div class="line-item-name-wrap">
                                    <input class="form-control line-item-name" aria-label="Line item name" placeholder="Name" autocomplete="off" name="items[<?= e((string) $idx) ?>][name]" value="<?= e($name) ?>" maxlength="80" />
                                    <div class="line-item-suggestions d-none"></div>
                                </div>
                            </div>
                            <div class="line-item-cell line-item-cell-note">
                                <input class="form-control line-item-note" aria-label="Line item note" placeholder="Note" name="items[<?= e((string) $idx) ?>][note]" value="<?= e($note) ?>" maxlength="255" />
                            </div>
                            <div class="line-item-cell line-item-cell-qty">
                                <input class="form-control line-item-qty" aria-label="Quantity" placeholder="Qty" name="items[<?= e((string) $idx) ?>][quantity]" value="<?= e($quantity) ?>" inputmode="decimal" />
                            </div>
                            <div class="line-item-cell line-item-cell-rate">
                                <input class="form-control line-item-rate" aria-label="Rate" placeholder="Rate" name="items[<?= e((string) $idx) ?>][rate]" value="<?= e($rate) ?>" inputmode="decimal" />
                            </div>
                            <div class="line-item-cell line-item-cell-amount">
                                <div class="line-item-amount fw-bold py-2 text-center">$0.00</div>
                            </div>
                            <div class="line-item-cell line-item-cell-taxable">
                                <div class="form-check form-switch line-item-taxable-switch mt-1 mb-0 d-flex justify-content-center">
                                    <input class="form-check-input line-item-taxable" aria-label="Taxable" type="checkbox" name="items[<?= e((string) $idx) ?>][taxable]" value="1" <?= $taxable ? 'checked' : '' ?> />
                                </div>
                            </div>
                            <div class="line-item-cell line-item-cell-remove">
                                <button type="button" class="btn btn-outline-danger btn-sm line-item-remove"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="card index-card mb-3">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-calculator me-2"></i>Totals</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-tax-rate">Tax Rate (%)</label>
                    <input id="billing-tax-rate" name="tax_rate" class="form-control <?= $hasError('tax_rate') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['tax_rate'] ?? '0')) ?>" inputmode="decimal" />
                    <?php if ($hasError('tax_rate')): ?><div class="invalid-feedback d-block"><?= e($fieldError('tax_rate')) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label fw-semibold">Sub-total</label>
                    <div id="billing-subtotal" class="fw-bold py-2">$<?= e(number_format((float) ($form['subtotal'] ?? '0'), 2)) ?></div>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label fw-semibold">Tax</label>
                    <div id="billing-tax-amount" class="fw-bold py-2">$<?= e(number_format((float) ($form['tax_amount'] ?? '0'), 2)) ?></div>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label fw-semibold">Total</label>
                    <div id="billing-total" class="fw-bold py-2">$<?= e(number_format((float) ($form['total'] ?? '0'), 2)) ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($documentType === 'invoice'): ?>
        <section class="card index-card mb-3">
            <div class="card-header index-card-header">
                <strong><i class="fas fa-money-check-dollar me-2"></i>Optional Payment / Deposit</strong>
            </div>
            <div class="card-body">
                <p class="small muted mb-3">If this invoice already has money received, add it here and it will post as a payment when you save.</p>
                <div class="row g-3">
                    <div class="col-12 col-lg-3">
                        <label class="form-label fw-semibold" for="inline-payment-date">Date</label>
                        <input id="inline-payment-date" type="date" name="payment_paid_date" class="form-control <?= $hasError('payment_paid_date') ? 'is-invalid' : '' ?>" value="<?= e($inlinePaymentDate) ?>" />
                        <?php if ($hasError('payment_paid_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_paid_date')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label fw-semibold" for="inline-payment-category">Type</label>
                        <select id="inline-payment-category" name="payment_type" class="form-select <?= $hasError('payment_type') ? 'is-invalid' : '' ?>">
                            <?php foreach ($paymentCategoryOptions as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= $inlinePaymentCategory === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($hasError('payment_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_type')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label fw-semibold" for="inline-payment-method">Method</label>
                        <select id="inline-payment-method" name="payment_method" class="form-select <?= $hasError('payment_method') ? 'is-invalid' : '' ?>">
                            <?php foreach ($paymentTypeOptions as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= $inlinePaymentMethod === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($hasError('payment_method')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_method')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label fw-semibold" for="inline-payment-amount">Amount</label>
                        <input id="inline-payment-amount" type="number" step="0.01" min="0" name="payment_amount" class="form-control <?= $hasError('payment_amount') ? 'is-invalid' : '' ?>" value="<?= e($inlinePaymentAmount) ?>" placeholder="Optional" />
                        <?php if ($hasError('payment_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_amount')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="inline-payment-reference">Reference Number</label>
                        <input id="inline-payment-reference" type="text" name="payment_reference_number" class="form-control <?= $hasError('payment_reference_number') ? 'is-invalid' : '' ?>" maxlength="120" value="<?= e($inlinePaymentReference) ?>" placeholder="Check #, Venmo ID, etc" />
                        <?php if ($hasError('payment_reference_number')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_reference_number')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="inline-payment-note">Payment Note</label>
                        <input id="inline-payment-note" type="text" name="payment_note" class="form-control <?= $hasError('payment_note') ? 'is-invalid' : '' ?>" maxlength="255" value="<?= e($inlinePaymentNote) ?>" placeholder="Optional note" />
                        <?php if ($hasError('payment_note')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_note')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit" <?= ($clientId <= 0 || $jobId <= 0) ? 'disabled' : '' ?>><?= e($mode === 'edit' ? 'Save Changes' : 'Create ' . ucfirst($documentType)) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
    </div>
</form>

<template id="line-item-template">
    <div class="line-item-row border rounded p-2">
        <div class="line-item-grid">
            <div class="line-item-cell line-item-cell-handle">
                <span class="line-item-drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
            </div>
            <div class="line-item-cell line-item-cell-name">
                <div class="line-item-name-wrap">
                    <input class="form-control line-item-name" aria-label="Line item name" placeholder="Name" autocomplete="off" maxlength="80" />
                    <div class="line-item-suggestions d-none"></div>
                </div>
            </div>
            <div class="line-item-cell line-item-cell-note">
                <input class="form-control line-item-note" aria-label="Line item note" placeholder="Note" maxlength="255" />
            </div>
            <div class="line-item-cell line-item-cell-qty">
                <input class="form-control line-item-qty" aria-label="Quantity" placeholder="Qty" value="1.00" inputmode="decimal" />
            </div>
            <div class="line-item-cell line-item-cell-rate">
                <input class="form-control line-item-rate" aria-label="Rate" placeholder="Rate" value="0.00" inputmode="decimal" />
            </div>
            <div class="line-item-cell line-item-cell-amount">
                <div class="line-item-amount fw-bold py-2 text-center">$0.00</div>
            </div>
            <div class="line-item-cell line-item-cell-taxable">
                <div class="form-check form-switch line-item-taxable-switch mt-1 mb-0 d-flex justify-content-center">
                    <input class="form-check-input line-item-taxable" aria-label="Taxable" type="checkbox" value="1" checked />
                </div>
            </div>
            <div class="line-item-cell line-item-cell-remove">
                <button type="button" class="btn btn-outline-danger btn-sm line-item-remove"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    </div>
</template>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const itemTypeDefaults = <?= json_encode($itemTypeDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const itemTypeNames = <?= json_encode($itemTypeNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const itemTypeMap = {};
    Object.keys(itemTypeDefaults || {}).forEach((key) => {
        const normalized = String(key || '').trim().toLowerCase();
        if (normalized !== '') {
            itemTypeMap[normalized] = itemTypeDefaults[key];
        }
    });

    const wrap = document.getElementById('line-items-wrap');
    const addBtn = document.getElementById('line-item-add');
    const tpl = document.getElementById('line-item-template');
    const formEl = document.getElementById('financial-doc-form');
    const subtotalInput = document.getElementById('billing-subtotal');
    const taxRateInput = document.getElementById('billing-tax-rate');
    const taxAmountInput = document.getElementById('billing-tax-amount');
    const totalInput = document.getElementById('billing-total');
    if (!formEl || !wrap || !addBtn || !tpl || !subtotalInput || !taxRateInput || !taxAmountInput || !totalInput) {
        return;
    }

    const toNumber = (value) => {
        const v = parseFloat(String(value || '0').replace(/,/g, '').trim());
        return Number.isFinite(v) ? v : 0;
    };

    const money = (value) => {
        return (Math.round((value + Number.EPSILON) * 100) / 100).toFixed(2);
    };
    let activeSuggestionRow = null;
    const getSuggestionListEl = (row) => {
        if (row.__lineItemSuggestions instanceof HTMLElement) {
            return row.__lineItemSuggestions;
        }
        const el = row.querySelector('.line-item-suggestions');
        if (el instanceof HTMLElement) {
            row.__lineItemSuggestions = el;
            return el;
        }
        return null;
    };

    const positionSuggestionList = (row) => {
        const inputEl = row.querySelector('.line-item-name');
        const listEl = getSuggestionListEl(row);
        if (!inputEl || !listEl) {
            return;
        }
        const rect = inputEl.getBoundingClientRect();
        listEl.style.position = 'fixed';
        listEl.style.left = `${Math.max(8, rect.left)}px`;
        listEl.style.top = `${rect.bottom + 4}px`;
        listEl.style.width = `${Math.max(180, rect.width)}px`;
        listEl.style.zIndex = '6000';
    };

    const normalizeItemName = (value) => String(value || '').trim().toLowerCase();
    const hasConfiguredItemTypes = Array.isArray(itemTypeNames) && itemTypeNames.length > 0;
    const isKnownItemType = (value) => {
        const normalized = normalizeItemName(value);
        return normalized !== '' && Object.prototype.hasOwnProperty.call(itemTypeMap, normalized);
    };

    const setItemNameValidity = (row, report = false) => {
        const inputEl = row.querySelector('.line-item-name');
        if (!inputEl) {
            return true;
        }

        const value = String(inputEl.value || '').trim();
        if (value === '') {
            inputEl.setCustomValidity('');
            return true;
        }

        if (!hasConfiguredItemTypes) {
            inputEl.setCustomValidity('Add invoice item types in Admin before creating estimates or invoices.');
            if (report) {
                inputEl.reportValidity();
            }
            return false;
        }

        if (!isKnownItemType(value)) {
            inputEl.setCustomValidity('Select an item from your saved invoice item type list.');
            if (report) {
                inputEl.reportValidity();
            }
            return false;
        }

        inputEl.setCustomValidity('');
        return true;
    };

    const reindexRows = () => {
        const rows = Array.from(wrap.querySelectorAll('.line-item-row'));
        rows.forEach((row, index) => {
            const name = row.querySelector('.line-item-name');
            const note = row.querySelector('.line-item-note');
            const qty = row.querySelector('.line-item-qty');
            const rate = row.querySelector('.line-item-rate');
            const taxable = row.querySelector('.line-item-taxable');
            if (name) name.setAttribute('name', `items[${index}][name]`);
            if (note) note.setAttribute('name', `items[${index}][note]`);
            if (qty) qty.setAttribute('name', `items[${index}][quantity]`);
            if (rate) rate.setAttribute('name', `items[${index}][rate]`);
            if (taxable) taxable.setAttribute('name', `items[${index}][taxable]`);
        });
    };

    const recalcTotals = () => {
        let subtotal = 0;
        let taxableSubtotal = 0;
        const rows = Array.from(wrap.querySelectorAll('.line-item-row'));
        rows.forEach((row) => {
            const qtyInput = row.querySelector('.line-item-qty');
            const rateInput = row.querySelector('.line-item-rate');
            const amountDisplay = row.querySelector('.line-item-amount');
            const taxableInput = row.querySelector('.line-item-taxable');
            const qty = Math.max(0, toNumber(qtyInput ? qtyInput.value : 0));
            const rate = Math.max(0, toNumber(rateInput ? rateInput.value : 0));
            const amount = qty * rate;
            subtotal += amount;
            if (taxableInput && taxableInput.checked) {
                taxableSubtotal += amount;
            }
            if (amountDisplay) {
                amountDisplay.textContent = '$' + money(amount);
            }
        });

        const taxRate = Math.max(0, toNumber(taxRateInput.value));
        const taxAmount = taxableSubtotal * (taxRate / 100);
        const total = subtotal + taxAmount;
        subtotalInput.textContent = '$' + money(subtotal);
        taxAmountInput.textContent = '$' + money(taxAmount);
        totalInput.textContent = '$' + money(total);
    };

    const applyItemTypeDefaults = (row) => {
        const nameInput = row.querySelector('.line-item-name');
        const noteInput = row.querySelector('.line-item-note');
        const rateInput = row.querySelector('.line-item-rate');
        const taxableInput = row.querySelector('.line-item-taxable');
        if (!nameInput || !noteInput || !rateInput || !taxableInput) {
            return;
        }

        const normalized = String(nameInput.value || '').trim().toLowerCase();
        if (normalized === '' || !Object.prototype.hasOwnProperty.call(itemTypeMap, normalized)) {
            return;
        }

        const defaults = itemTypeMap[normalized] || {};
        const defaultRate = String(defaults.rate || '').trim();
        const defaultNote = String(defaults.note || '');
        const defaultTaxable = String(defaults.taxable || '0') === '1';

        if (defaultRate !== '') {
            rateInput.value = defaultRate;
        }
        noteInput.value = defaultNote;
        taxableInput.checked = defaultTaxable;
        recalcTotals();
    };

    const hideNameSuggestions = (row) => {
        const listEl = getSuggestionListEl(row);
        const wrapEl = row.querySelector('.line-item-name-wrap');
        if (!listEl) {
            return;
        }
        listEl.classList.add('d-none');
        listEl.innerHTML = '';
        listEl.style.left = '';
        listEl.style.top = '';
        listEl.style.width = '';
        listEl.style.position = '';
        listEl.style.zIndex = '';
        if (wrapEl && listEl.parentElement !== wrapEl) {
            wrapEl.appendChild(listEl);
        }
        row.classList.remove('line-item-row-suggest-open');
        if (wrapEl) {
            wrapEl.classList.remove('suggest-open');
        }
        if (activeSuggestionRow === row) {
            activeSuggestionRow = null;
        }
    };

    const renderNameSuggestions = (row, forceOpen = false) => {
        const inputEl = row.querySelector('.line-item-name');
        const listEl = getSuggestionListEl(row);
        if (!inputEl || !listEl) {
            return;
        }
        if (!Array.isArray(itemTypeNames) || itemTypeNames.length === 0) {
            hideNameSuggestions(row);
            return;
        }

        const query = String(inputEl.value || '').trim().toLowerCase();
        if (query === '' && !forceOpen) {
            hideNameSuggestions(row);
            return;
        }

        const matches = itemTypeNames
            .filter((name) => {
                const label = String(name || '').trim();
                if (label === '') {
                    return false;
                }
                if (query === '') {
                    return true;
                }
                return label.toLowerCase().includes(query);
            })
            .slice(0, 8);

        if (matches.length === 0) {
            hideNameSuggestions(row);
            return;
        }

        listEl.innerHTML = '';
        if (listEl.parentElement !== document.body) {
            document.body.appendChild(listEl);
        }
        matches.forEach((match) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'line-item-suggestion-item';
            btn.textContent = match;
            btn.addEventListener('mousedown', (event) => {
                event.preventDefault();
                inputEl.value = match;
                hideNameSuggestions(row);
                applyItemTypeDefaults(row);
                setItemNameValidity(row);
            });
            listEl.appendChild(btn);
        });

        listEl.classList.remove('d-none');
        positionSuggestionList(row);
        activeSuggestionRow = row;
        row.classList.add('line-item-row-suggest-open');
        const wrapEl = row.querySelector('.line-item-name-wrap');
        if (wrapEl) {
            wrapEl.classList.add('suggest-open');
        }
    };

    const attachDragEvents = (row) => {
        row.setAttribute('draggable', 'true');

        row.addEventListener('dragstart', (event) => {
            row.classList.add('line-item-row-dragging');
            row.dataset.dragging = '1';
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(Date.now()));
            }
        });

        row.addEventListener('dragend', () => {
            row.classList.remove('line-item-row-dragging');
            row.dataset.dragging = '0';
        });

        row.addEventListener('dragover', (event) => {
            const draggingRow = wrap.querySelector('.line-item-row[data-dragging="1"]');
            if (!draggingRow || draggingRow === row) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
        });

        row.addEventListener('drop', (event) => {
            const draggingRow = wrap.querySelector('.line-item-row[data-dragging="1"]');
            if (!draggingRow || draggingRow === row) {
                return;
            }
            event.preventDefault();

            const bounds = row.getBoundingClientRect();
            const placeBefore = (event.clientY - bounds.top) < (bounds.height / 2);
            if (placeBefore) {
                wrap.insertBefore(draggingRow, row);
            } else {
                wrap.insertBefore(draggingRow, row.nextElementSibling);
            }

            reindexRows();
            recalcTotals();
        });
    };

    const attachRowEvents = (row) => {
        const name = row.querySelector('.line-item-name');
        const qty = row.querySelector('.line-item-qty');
        const rate = row.querySelector('.line-item-rate');
        const taxable = row.querySelector('.line-item-taxable');
        const remove = row.querySelector('.line-item-remove');

        [qty, rate].forEach((input) => {
            if (input) {
                input.addEventListener('input', recalcTotals);
            }
        });
        if (name) {
            name.addEventListener('input', () => {
                setItemNameValidity(row);
                renderNameSuggestions(row);
            });
            name.addEventListener('focus', () => renderNameSuggestions(row, true));
            name.addEventListener('change', () => {
                applyItemTypeDefaults(row);
                setItemNameValidity(row);
            });
            name.addEventListener('blur', () => {
                window.setTimeout(() => hideNameSuggestions(row), 120);
                applyItemTypeDefaults(row);
                setItemNameValidity(row);
            });
            name.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }
                const listEl = getSuggestionListEl(row);
                if (!listEl || listEl.classList.contains('d-none')) {
                    return;
                }
                const first = listEl.querySelector('.line-item-suggestion-item');
                if (!first) {
                    return;
                }
                event.preventDefault();
                first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            });
        }
        if (taxable) {
            taxable.addEventListener('change', recalcTotals);
        }
        if (remove) {
            remove.addEventListener('click', () => {
                if (wrap.querySelectorAll('.line-item-row').length <= 1) {
                    const name = row.querySelector('.line-item-name');
                    const note = row.querySelector('.line-item-note');
                    const qtyInput = row.querySelector('.line-item-qty');
                    const rateInput = row.querySelector('.line-item-rate');
                    const taxableInput = row.querySelector('.line-item-taxable');
                    if (name) name.value = '';
                    if (note) note.value = '';
                    if (qtyInput) qtyInput.value = '1.00';
                    if (rateInput) rateInput.value = '0.00';
                    if (taxableInput) taxableInput.checked = true;
                } else {
                    hideNameSuggestions(row);
                    row.remove();
                    reindexRows();
                }
                recalcTotals();
            });
        }

        attachDragEvents(row);
        setItemNameValidity(row);
    };

    addBtn.addEventListener('click', () => {
        const clone = tpl.content.firstElementChild.cloneNode(true);
        wrap.appendChild(clone);
        reindexRows();
        attachRowEvents(clone);
        recalcTotals();
    });

    taxRateInput.addEventListener('input', recalcTotals);
    window.addEventListener('resize', () => {
        if (activeSuggestionRow) {
            positionSuggestionList(activeSuggestionRow);
        }
    });
    window.addEventListener('scroll', () => {
        if (activeSuggestionRow) {
            positionSuggestionList(activeSuggestionRow);
        }
    }, true);
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !activeSuggestionRow) {
            return;
        }
        const listEl = getSuggestionListEl(activeSuggestionRow);
        const inputEl = activeSuggestionRow.querySelector('.line-item-name');
        if ((listEl && listEl.contains(target)) || (inputEl && inputEl.contains(target))) {
            return;
        }
        hideNameSuggestions(activeSuggestionRow);
    });
    formEl.addEventListener('submit', (event) => {
        let hasInvalidName = false;
        Array.from(wrap.querySelectorAll('.line-item-row')).forEach((row) => {
            if (!setItemNameValidity(row, !hasInvalidName)) {
                hasInvalidName = true;
            }
        });
        if (hasInvalidName) {
            event.preventDefault();
        }
    });
    Array.from(wrap.querySelectorAll('.line-item-row')).forEach((row) => attachRowEvents(row));
    reindexRows();
    recalcTotals();
});
</script>
