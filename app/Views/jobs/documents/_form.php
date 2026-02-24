<?php
$document = is_array($document ?? null) ? $document : [];
$types = is_array($types ?? null) ? $types : ['estimate', 'invoice'];
$statuses = is_array($statuses ?? null) ? $statuses : \App\Models\JobDocument::STATUSES;
$itemTypes = is_array($itemTypes ?? null) ? $itemTypes : [];
$isEditDocument = !empty($document['id']);
$lineItems = old('line_items', $lineItems ?? []);
if (!is_array($lineItems) || $lineItems === []) {
    $lineItems = [[
        'item_type_id' => '',
        'item_description' => '',
        'line_note' => '',
        'quantity' => '1',
        'unit_price' => '',
        'is_taxable' => '1',
    ]];
}

$fieldValue = static function (string $key, mixed $default = '') use ($document): mixed {
    $oldValue = old($key, null);
    if ($oldValue !== null) {
        return $oldValue;
    }

    return array_key_exists($key, $document) ? $document[$key] : $default;
};

$computedSubtotal = 0.0;
$computedTaxableSubtotal = 0.0;
foreach ($lineItems as $lineItem) {
    $qty = is_numeric((string) ($lineItem['quantity'] ?? '')) ? (float) $lineItem['quantity'] : 0.0;
    $unit = is_numeric((string) ($lineItem['unit_price'] ?? '')) ? (float) $lineItem['unit_price'] : 0.0;
    $isTaxable = !array_key_exists('is_taxable', $lineItem) || (int) $lineItem['is_taxable'] === 1;
    if ($qty > 0 && $unit >= 0) {
        $lineTotal = ($qty * $unit);
        $computedSubtotal += $lineTotal;
        if ($isTaxable) {
            $computedTaxableSubtotal += $lineTotal;
        }
    }
}
$computedTaxRate = is_numeric((string) $fieldValue('tax_rate', $document['tax_rate'] ?? 0)) ? (float) $fieldValue('tax_rate', $document['tax_rate'] ?? 0) : 0.0;
if ($computedTaxRate < 0) {
    $computedTaxRate = 0.0;
} elseif ($computedTaxRate > 100) {
    $computedTaxRate = 100.0;
}
$computedTaxAmount = round($computedTaxableSubtotal * ($computedTaxRate / 100), 2);
$computedAmount = round($computedSubtotal + $computedTaxAmount, 2);
?>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-invoice-dollar me-1"></i>
        Document Details
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="document_type">Type</label>
                <select class="form-select" id="document_type" name="document_type" <?= $isEditDocument ? 'disabled' : '' ?> required>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e((string) $type) ?>" <?= (string) $fieldValue('document_type', 'estimate') === (string) $type ? 'selected' : '' ?>>
                            <?= e(\App\Models\JobDocument::typeLabel((string) $type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEditDocument): ?>
                    <input type="hidden" name="document_type" value="<?= e((string) $fieldValue('document_type', 'estimate')) ?>" />
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e((string) $status) ?>" <?= (string) $fieldValue('status', 'draft') === (string) $status ? 'selected' : '' ?>>
                            <?= e(\App\Models\JobDocument::statusLabel((string) $status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" id="title" name="title" type="text" maxlength="190" required value="<?= e((string) $fieldValue('title', '')) ?>" placeholder="Estimate #, Invoice #, or descriptive title" />
            </div>

            <div class="col-md-3">
                <label class="form-label" for="issued_at">Issued At</label>
                <input class="form-control" id="issued_at" name="issued_at" type="datetime-local" value="<?= e(format_datetime_local((string) $fieldValue('issued_at', ''))) ?>" />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="due_at">Due At</label>
                <input class="form-control" id="due_at" name="due_at" type="datetime-local" value="<?= e(format_datetime_local((string) $fieldValue('due_at', ''))) ?>" />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sent_at">Sent At</label>
                <input class="form-control" id="sent_at" name="sent_at" type="datetime-local" value="<?= e(format_datetime_local((string) $fieldValue('sent_at', ''))) ?>" />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="approved_at">Approved At</label>
                <input class="form-control" id="approved_at" name="approved_at" type="datetime-local" value="<?= e(format_datetime_local((string) $fieldValue('approved_at', ''))) ?>" />
            </div>

            <div class="col-md-3">
                <label class="form-label" for="paid_at">Paid At</label>
                <input class="form-control" id="paid_at" name="paid_at" type="datetime-local" value="<?= e(format_datetime_local((string) $fieldValue('paid_at', ''))) ?>" />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-check me-1"></i>Line Items</span>
        <button type="button" class="btn btn-sm btn-primary" id="add-line-item-btn"><i class="fas fa-plus me-1"></i>Add Item</button>
    </div>
    <div class="card-body">
        <div id="line-items-wrap" class="d-flex flex-column gap-3">
            <?php foreach ($lineItems as $index => $lineItem): ?>
                <?php
                $itemTypeId = (string) ($lineItem['item_type_id'] ?? '');
                $description = (string) ($lineItem['item_description'] ?? '');
                $lineNote = (string) ($lineItem['line_note'] ?? '');
                $quantity = (string) ($lineItem['quantity'] ?? '1');
                $unitPrice = (string) ($lineItem['unit_price'] ?? '');
                $isTaxable = !array_key_exists('is_taxable', $lineItem) || (int) $lineItem['is_taxable'] === 1;
                ?>
                <div class="line-item-row border rounded p-3" data-line-item-row>
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label">Item Type</label>
                            <select class="form-select" name="line_items[<?= e((string) $index) ?>][item_type_id]" data-item-type-select>
                                <option value="">Select type...</option>
                                <?php foreach ($itemTypes as $itemType): ?>
                                    <?php $id = (int) ($itemType['id'] ?? 0); ?>
                                    <option value="<?= e((string) $id) ?>" data-item-type-label="<?= e((string) ($itemType['item_label'] ?? '')) ?>" <?= $itemTypeId === (string) $id ? 'selected' : '' ?>>
                                        <?= e((string) ($itemType['item_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="line_items[<?= e((string) $index) ?>][item_type_label]" data-item-type-label-input value="" />
                        </div>
                        <div class="col-lg-4 col-md-8">
                            <label class="form-label">Description</label>
                            <input class="form-control" type="text" maxlength="255" name="line_items[<?= e((string) $index) ?>][item_description]" value="<?= e($description) ?>" placeholder="Service or charge" required />
                        </div>
                        <div class="col-lg-1 col-md-3 col-6">
                            <label class="form-label">Qty</label>
                            <input class="form-control" type="number" min="0.01" step="0.01" name="line_items[<?= e((string) $index) ?>][quantity]" value="<?= e($quantity) ?>" data-line-qty />
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label">Unit Price</label>
                            <input class="form-control" type="number" min="0" step="0.01" name="line_items[<?= e((string) $index) ?>][unit_price]" value="<?= e($unitPrice) ?>" data-line-unit-price />
                        </div>
                        <div class="col-lg-1 col-md-3 col-6">
                            <label class="form-label d-block">Taxable</label>
                            <input type="hidden" name="line_items[<?= e((string) $index) ?>][is_taxable]" value="0" data-field="is_taxable" />
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch" name="line_items[<?= e((string) $index) ?>][is_taxable]" value="1" data-field="is_taxable" data-line-taxable <?= $isTaxable ? 'checked' : '' ?> />
                            </div>
                        </div>
                        <div class="col-lg-1 col-md-3 col-6 d-grid">
                            <button type="button" class="btn btn-outline-danger" data-remove-line-item title="Remove item"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Note</label>
                            <input class="form-control" type="text" maxlength="255" name="line_items[<?= e((string) $index) ?>][line_note]" value="<?= e($lineNote) ?>" placeholder="Short note for this line item" />
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="border rounded p-3 mt-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label" for="subtotal_amount">Net Subtotal</label>
                    <input class="form-control" id="subtotal_amount" name="subtotal_amount" type="number" step="0.01" readonly value="<?= e(number_format($computedSubtotal, 2, '.', '')) ?>" />
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                    <input class="form-control" id="tax_rate" name="tax_rate" type="number" min="0" max="100" step="0.01" value="<?= e(number_format($computedTaxRate, 2, '.', '')) ?>" />
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label" for="tax_amount">Tax</label>
                    <input class="form-control" id="tax_amount" name="tax_amount" type="number" step="0.01" readonly value="<?= e(number_format($computedTaxAmount, 2, '.', '')) ?>" />
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label" for="amount">Gross Total</label>
                    <input class="form-control" id="amount" name="amount" type="number" step="0.01" readonly value="<?= e(number_format($computedAmount, 2, '.', '')) ?>" />
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-comment me-1"></i>Notes</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="customer_note">Customer Note</label>
                <textarea class="form-control" id="customer_note" name="customer_note" rows="4" placeholder="This appears on the estimate/invoice."><?= e((string) $fieldValue('customer_note', '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="note">Internal Note</label>
                <textarea class="form-control" id="note" name="note" rows="3" placeholder="Internal-only notes for team visibility."><?= e((string) $fieldValue('note', '')) ?></textarea>
            </div>
        </div>
    </div>
</div>

<template id="line-item-template">
    <div class="line-item-row border rounded p-3" data-line-item-row>
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Item Type</label>
                <select class="form-select" data-field="item_type_id" data-item-type-select>
                    <option value="">Select type...</option>
                    <?php foreach ($itemTypes as $itemType): ?>
                        <?php $id = (int) ($itemType['id'] ?? 0); ?>
                        <option value="<?= e((string) $id) ?>" data-item-type-label="<?= e((string) ($itemType['item_label'] ?? '')) ?>"><?= e((string) ($itemType['item_label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" data-field="item_type_label" data-item-type-label-input value="" />
            </div>
            <div class="col-lg-4 col-md-8">
                <label class="form-label">Description</label>
                <input class="form-control" type="text" maxlength="255" data-field="item_description" placeholder="Service or charge" required />
            </div>
            <div class="col-lg-1 col-md-3 col-6">
                <label class="form-label">Qty</label>
                <input class="form-control" type="number" min="0.01" step="0.01" data-field="quantity" value="1" data-line-qty />
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label">Unit Price</label>
                <input class="form-control" type="number" min="0" step="0.01" data-field="unit_price" value="" data-line-unit-price />
            </div>
            <div class="col-lg-1 col-md-3 col-6">
                <label class="form-label d-block">Taxable</label>
                <input type="hidden" data-field="is_taxable" value="0" />
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" role="switch" data-field="is_taxable" data-line-taxable value="1" checked />
                </div>
            </div>
            <div class="col-lg-1 col-md-3 col-6 d-grid">
                <button type="button" class="btn btn-outline-danger" data-remove-line-item title="Remove item"><i class="fas fa-trash"></i></button>
            </div>
            <div class="col-12">
                <label class="form-label">Short Note</label>
                <input class="form-control" type="text" maxlength="255" data-field="line_note" placeholder="Short note for this line item" />
            </div>
        </div>
    </div>
</template>

<script>
(() => {
    const wrap = document.getElementById('line-items-wrap');
    const addBtn = document.getElementById('add-line-item-btn');
    const template = document.getElementById('line-item-template');
    const amountInput = document.getElementById('amount');
    const subtotalInput = document.getElementById('subtotal_amount');
    const taxRateInput = document.getElementById('tax_rate');
    const taxAmountInput = document.getElementById('tax_amount');
    if (!wrap || !addBtn || !template || !amountInput || !subtotalInput || !taxRateInput || !taxAmountInput) {
        return;
    }

    function syncTypeLabel(row) {
        const select = row.querySelector('[data-item-type-select]');
        const labelInput = row.querySelector('[data-item-type-label-input]');
        if (!select || !labelInput) {
            return;
        }
        const selected = select.options[select.selectedIndex];
        labelInput.value = selected ? (selected.getAttribute('data-item-type-label') || '') : '';
    }

    function recalcTotal() {
        let subtotal = 0;
        let taxableSubtotal = 0;
        wrap.querySelectorAll('[data-line-item-row]').forEach((row) => {
            const qty = parseFloat((row.querySelector('[data-line-qty]') || {}).value || '0');
            const unit = parseFloat((row.querySelector('[data-line-unit-price]') || {}).value || '0');
            const taxableInput = row.querySelector('[data-line-taxable]');
            const isTaxable = taxableInput ? taxableInput.checked : true;
            if (!Number.isNaN(qty) && !Number.isNaN(unit) && qty > 0 && unit >= 0) {
                const lineTotal = qty * unit;
                subtotal += lineTotal;
                if (isTaxable) {
                    taxableSubtotal += lineTotal;
                }
            }
        });
        let taxRate = parseFloat(taxRateInput.value || '0');
        if (Number.isNaN(taxRate) || taxRate < 0) {
            taxRate = 0;
        } else if (taxRate > 100) {
            taxRate = 100;
        }

        const taxAmount = taxableSubtotal * (taxRate / 100);
        const gross = subtotal + taxAmount;

        subtotalInput.value = subtotal.toFixed(2);
        taxAmountInput.value = taxAmount.toFixed(2);
        amountInput.value = gross.toFixed(2);
    }

    function refreshIndexes() {
        wrap.querySelectorAll('[data-line-item-row]').forEach((row, index) => {
            row.querySelectorAll('input, select, textarea').forEach((input) => {
                const field = input.getAttribute('data-field');
                if (field) {
                    input.name = `line_items[${index}][${field}]`;
                    return;
                }
                if (input.name) {
                    input.name = input.name.replace(/line_items\[\d+\]/, `line_items[${index}]`);
                }
            });
            syncTypeLabel(row);
        });
        recalcTotal();
    }

    function bindRow(row) {
        row.querySelectorAll('[data-line-qty], [data-line-unit-price], [data-line-taxable]').forEach((input) => {
            input.addEventListener('input', recalcTotal);
            input.addEventListener('change', recalcTotal);
        });
        const removeButton = row.querySelector('[data-remove-line-item]');
        if (removeButton) {
            removeButton.addEventListener('click', () => {
                if (wrap.querySelectorAll('[data-line-item-row]').length <= 1) {
                    return;
                }
                row.remove();
                refreshIndexes();
            });
        }
        const typeSelect = row.querySelector('[data-item-type-select]');
        if (typeSelect) {
            typeSelect.addEventListener('change', () => syncTypeLabel(row));
        }
    }

    addBtn.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-line-item-row]');
        if (!row) {
            return;
        }
        wrap.appendChild(row);
        bindRow(row);
        refreshIndexes();
    });

    taxRateInput.addEventListener('input', recalcTotal);
    taxRateInput.addEventListener('change', recalcTotal);

    wrap.querySelectorAll('[data-line-item-row]').forEach(bindRow);
    refreshIndexes();
})();
</script>
