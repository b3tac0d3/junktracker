<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/sales'));
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Sale' : 'Add Sale') ?></h1>
        <p class="muted">Simple sales form</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/sales')) ?>">Back to Sales</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-plus me-2"></i><?= e($mode === 'edit' ? 'Update Sale' : 'Create Sale') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sale-name">Name</label>
                <input
                    id="sale-name"
                    name="name"
                    class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['name'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="Quick description"
                />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sale-gross">Gross</label>
                <input
                    id="sale-gross"
                    type="number"
                    step="0.01"
                    min="0"
                    name="gross_amount"
                    class="form-control <?= $hasError('gross_amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['gross_amount'] ?? '')) ?>"
                />
                <?php if ($hasError('gross_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('gross_amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sale-net">Net</label>
                <input
                    id="sale-net"
                    type="number"
                    step="0.01"
                    min="0"
                    name="net_amount"
                    class="form-control <?= $hasError('net_amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['net_amount'] ?? '')) ?>"
                />
                <?php if ($hasError('net_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('net_amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sale-type">Type</label>
                <select id="sale-type" name="sale_type" class="form-select <?= $hasError('sale_type') ? 'is-invalid' : '' ?>">
                    <option value="">Choose type...</option>
                    <?php foreach ($typeOptions as $option): ?>
                        <option value="<?= e((string) $option) ?>" <?= ((string) ($form['sale_type'] ?? '')) === (string) $option ? 'selected' : '' ?>><?= e(ucfirst((string) $option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('sale_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="sale-date">Date</label>
                <input
                    id="sale-date"
                    type="date"
                    name="sale_date"
                    class="form-control <?= $hasError('sale_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['sale_date'] ?? '')) ?>"
                />
                <?php if ($hasError('sale_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-9">
                <label class="form-label fw-semibold" for="sale-client-search">Client (Optional)</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="sale-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="sale-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="sale-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/sales/client-search')) ?>"
                    />
                    <div id="sale-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="sale-notes">Note</label>
                <textarea
                    id="sale-notes"
                    name="notes"
                    class="form-control"
                    rows="3"
                    placeholder="Internal note (shown on sale view only)"
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Add Sale') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/sales')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const grossInput = document.getElementById('sale-gross');
    const netInput = document.getElementById('sale-net');
    if (grossInput && netInput) {
        const grossRaw = String(grossInput.value || '').trim();
        const netRaw = String(netInput.value || '').trim();
        let netManuallyChanged = netRaw !== '' && netRaw !== grossRaw;

        if (netRaw === '') {
            netInput.value = grossInput.value;
            netManuallyChanged = false;
        }

        grossInput.addEventListener('input', () => {
            const currentNet = String(netInput.value || '').trim();
            if (!netManuallyChanged || currentNet === '') {
                netInput.value = grossInput.value;
            }
        });

        netInput.addEventListener('input', (event) => {
            const currentNet = String(netInput.value || '').trim();
            const currentGross = String(grossInput.value || '').trim();

            // Clearing net re-enables defaulting to gross.
            if (currentNet === '') {
                netManuallyChanged = false;
                return;
            }

            // Mark as manual only for user-generated edits.
            if (event.isTrusted) {
                netManuallyChanged = currentNet !== currentGross;
            }
        });
    }

    const searchInput = document.getElementById('sale-client-search');
    const hiddenClientId = document.getElementById('sale-client-id');
    const hiddenClientName = document.getElementById('sale-client-name');
    const suggestions = document.getElementById('sale-client-suggestions');
    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    let debounce = null;

    const hideSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const setSelected = (id, name) => {
        hiddenClientId.value = id > 0 ? String(id) : '';
        hiddenClientName.value = name || '';
        searchInput.value = name || '';
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'client-suggestion-item';
            empty.textContent = 'No clients found';
            empty.setAttribute('aria-disabled', 'true');
            suggestions.appendChild(empty);
            suggestions.classList.remove('d-none');
            return;
        }

        items.forEach((item) => {
            const id = Number(item && item.id ? item.id : 0);
            const name = String(item && item.name ? item.name : '').trim();
            if (!id || name === '') {
                return;
            }

            const phone = String(item && item.phone ? item.phone : '').trim();
            const city = String(item && item.city ? item.city : '').trim();
            const meta = [phone, city].filter(Boolean).join(' · ');

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            row.dataset.clientId = String(id);
            row.dataset.clientName = name;
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = meta;
            row.addEventListener('click', () => {
                setSelected(id, name);
                hideSuggestions();
            });
            suggestions.appendChild(row);
        });

        suggestions.classList.remove('d-none');
    };

    const fetchSuggestions = (query) => {
        if (!searchUrl || query.length < 2) {
            hideSuggestions();
            return;
        }

        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
            .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
            .then((payload) => {
                renderSuggestions(Array.isArray(payload && payload.results) ? payload.results : []);
            })
            .catch(() => {
                hideSuggestions();
            });
    };

    searchInput.addEventListener('input', () => {
        const query = String(searchInput.value || '').trim();
        hiddenClientId.value = '';
        hiddenClientName.value = query;

        if (debounce) {
            clearTimeout(debounce);
        }
        debounce = setTimeout(() => fetchSuggestions(query), 180);
    });

    searchInput.addEventListener('focus', () => {
        const query = String(searchInput.value || '').trim();
        if (query.length >= 2) {
            fetchSuggestions(query);
        }
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== searchInput) {
            hideSuggestions();
        }
    });
});
</script>
