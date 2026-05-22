<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$estateSaleId = (int) ($estateSale['id'] ?? 0);
$estateSaleTitle = trim((string) ($estateSaleTitle ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
$backUrl = (string) ($backUrl ?? url('/estate-sales/' . (string) $estateSaleId . '?tab=sales'));
$mode = strtolower(trim((string) ($mode ?? 'create')));
$isEdit = $mode === 'edit';
$saleId = (int) ($saleId ?? 0);
$pageHeading = trim((string) ($pageTitle ?? '')) ?: ($isEdit ? 'Edit Sale' : 'Add Sale');
$submitLabel = $isEdit ? 'Save Changes' : 'Add Sale';
$actionUrl = $isEdit
    ? url('/estate-sales/' . (string) $estateSaleId . '/sales/' . (string) $saleId . '/update')
    : url('/estate-sales/' . (string) $estateSaleId . '/sales');
$customerSearchUrl = url('/estate-sales/' . (string) $estateSaleId . '/customer-search');

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($pageHeading) ?></h1>
        <p class="muted"><?= e($estateSaleTitle) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Back to Estate Sale</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-cash-register me-2"></i>Estate Sale Transaction</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-store me-2"></i>
                    This sale will be recorded for <strong><?= e($estateSaleTitle) ?></strong>.
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="estate-sale-sale-name">Name</label>
                <input
                    id="estate-sale-sale-name"
                    name="name"
                    class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['name'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="Quick description"
                />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-sale-date">Date</label>
                <input
                    id="estate-sale-sale-date"
                    type="date"
                    name="sale_date"
                    class="form-control <?= $hasError('sale_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['sale_date'] ?? '')) ?>"
                />
                <?php if ($hasError('sale_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="estate-sale-customer-search">Customer <span class="text-muted fw-normal">(recommended)</span></label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="estate-sale-customer-id" name="estate_sale_customer_id" value="<?= e((string) ($form['estate_sale_customer_id'] ?? '')) ?>" />
                    <input type="hidden" id="estate-sale-customer-name" name="estate_sale_customer_name" value="<?= e((string) ($form['estate_sale_customer_name'] ?? '')) ?>" />
                    <input
                        id="estate-sale-customer-search"
                        class="form-control <?= $hasError('estate_sale_customer_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['estate_sale_customer_name'] ?? '')) ?>"
                        placeholder="Search customer by name, email, phone, or city..."
                        autocomplete="off"
                        data-search-url="<?= e($customerSearchUrl) ?>"
                        data-estate-sale-title="<?= e($estateSaleTitle) ?>"
                    />
                    <div id="estate-sale-customer-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Customer suggestions"></div>
                </div>
                <div class="form-text">Link a customer when you know who bought the item. Leave blank for walk-up or unknown buyers.</div>
                <?php if ($hasError('estate_sale_customer_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('estate_sale_customer_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-sale-gross">Sale price</label>
                <input
                    id="estate-sale-sale-gross"
                    type="number"
                    step="0.01"
                    min="0"
                    name="gross_amount"
                    class="form-control <?= $hasError('gross_amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['gross_amount'] ?? '')) ?>"
                />
                <?php if ($hasError('gross_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('gross_amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="estate-sale-sale-notes">Note</label>
                <textarea
                    id="estate-sale-sale-notes"
                    name="notes"
                    class="form-control"
                    rows="3"
                    placeholder="Internal note (shown on sale view only)"
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const customerInput = document.getElementById('estate-sale-customer-search');
    const customerIdInput = document.getElementById('estate-sale-customer-id');
    const customerNameInput = document.getElementById('estate-sale-customer-name');
    const suggestions = document.getElementById('estate-sale-customer-suggestions');
    const saleNameInput = document.getElementById('estate-sale-sale-name');
    const estateSaleTitle = String(customerInput?.dataset?.estateSaleTitle || '').trim();
    const searchUrl = String(customerInput?.dataset?.searchUrl || '');

    if (!customerInput || !customerIdInput || !customerNameInput || !suggestions || !searchUrl) {
        return;
    }

    let debounce = null;

    const hideSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const applyCustomerSelection = (id, name) => {
        customerIdInput.value = String(id);
        customerNameInput.value = name;
        customerInput.value = name;
        if (saleNameInput && String(saleNameInput.value || '').trim() === '') {
            saleNameInput.value = estateSaleTitle !== '' ? (name + ' — ' + estateSaleTitle) : name;
        }
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'client-suggestion-item';
            empty.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            empty.querySelector('.client-suggestion-name').textContent = 'No customers found';
            empty.setAttribute('aria-disabled', 'true');
            suggestions.appendChild(empty);
            suggestions.classList.remove('d-none');
            return;
        }

        items.forEach((item) => {
            const id = Number(item && item.id ? item.id : 0);
            const name = String(item && item.name ? item.name : '').trim();
            if (id <= 0 || name === '') {
                return;
            }

            const phone = String(item && item.phone ? item.phone : '').trim();
            const city = String(item && item.city ? item.city : '').trim();
            const state = String(item && item.state ? item.state : '').trim();
            const meta = [phone, [city, state].filter(Boolean).join(', ')].filter(Boolean).join(' · ');

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = meta;
            row.addEventListener('click', () => {
                applyCustomerSelection(id, name);
                hideSuggestions();
            });
            suggestions.appendChild(row);
        });

        if (suggestions.children.length > 0) {
            suggestions.classList.remove('d-none');
        } else {
            hideSuggestions();
        }
    };

    const fetchResults = (query) => {
        if (query.length < 2) {
            hideSuggestions();
            return;
        }

        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
            .then((payload) => {
                renderSuggestions(Array.isArray(payload && payload.results) ? payload.results : []);
            })
            .catch(() => hideSuggestions());
    };

    customerInput.addEventListener('input', () => {
        customerIdInput.value = '';
        customerNameInput.value = '';
        if (debounce) {
            clearTimeout(debounce);
        }
        debounce = setTimeout(() => fetchResults(String(customerInput.value || '').trim()), 160);
    });

    customerInput.addEventListener('focus', () => {
        const query = String(customerInput.value || '').trim();
        if (query.length >= 2) {
            fetchResults(query);
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!suggestions.contains(target) && target !== customerInput) {
            hideSuggestions();
        }
    });
});
</script>
