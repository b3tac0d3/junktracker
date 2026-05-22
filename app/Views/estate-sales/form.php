<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/estate-sales'));
$estateSaleId = (int) ($estateSaleId ?? 0);
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\EstateSale::statusOptions(current_business_id());

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};

$clientSplitTypeOptions = \App\Models\EstateSale::clientSplitTypeOptions();
$selectedClientSplitType = \App\Models\EstateSale::normalizeClientSplitType($form['client_split_type'] ?? null);
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit estate sale' : 'Add estate sale') ?></h1>
        <p class="muted">Sale location, schedule, and status</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $estateSaleId > 0 ? url('/estate-sales/' . (string) $estateSaleId) : url('/estate-sales')) ?>">Back</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-store me-2"></i><?= e($mode === 'edit' ? 'Update estate sale' : 'Create estate sale') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="estate-sale-title">Title</label>
                <input
                    id="estate-sale-title"
                    name="title"
                    class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['title'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="Smith Estate — Warwick"
                    required
                />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-status">Status</label>
                <select id="estate-sale-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptionsRaw as $opt): ?>
                        <?php $opt = strtolower(trim((string) $opt)); ?>
                        <option value="<?= e($opt) ?>" <?= ((string) ($form['status'] ?? 'scheduled')) === $opt ? 'selected' : '' ?>><?= e($statusLabel($opt)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="estate-sale-start">Start</label>
                <input
                    id="estate-sale-start"
                    type="datetime-local"
                    name="start_at"
                    class="form-control <?= $hasError('start_at') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['start_at'] ?? '')) ?>"
                />
                <?php if ($hasError('start_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('start_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="estate-sale-end">End</label>
                <input
                    id="estate-sale-end"
                    type="datetime-local"
                    name="end_at"
                    class="form-control <?= $hasError('end_at') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['end_at'] ?? '')) ?>"
                />
                <?php if ($hasError('end_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('end_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="estate-sale-client-search">Client</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="estate-sale-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="estate-sale-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="estate-sale-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/jobs/client-search')) ?>"
                    />
                    <div id="estate-sale-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <div class="form-text">Estate owner or client receiving the sale share.</div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="estate-sale-address-1">Address line 1</label>
                <input
                    id="estate-sale-address-1"
                    name="address_line1"
                    class="form-control"
                    value="<?= e((string) ($form['address_line1'] ?? '')) ?>"
                    maxlength="190"
                />
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="estate-sale-address-2">Address line 2</label>
                <input
                    id="estate-sale-address-2"
                    name="address_line2"
                    class="form-control"
                    value="<?= e((string) ($form['address_line2'] ?? '')) ?>"
                    maxlength="190"
                />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-city">City</label>
                <input id="estate-sale-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-state">State</label>
                <select id="estate-sale-state" name="state" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($stateOptions as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $selectedState === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sale-postal">Postal code</label>
                <input id="estate-sale-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12 col-lg-8">
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold" for="estate-sale-client-percentage">Client percentage</label>
                        <div class="input-group">
                            <input
                                id="estate-sale-client-percentage"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                name="client_percentage"
                                class="form-control <?= $hasError('client_percentage') ? 'is-invalid' : '' ?>"
                                value="<?= e((string) ($form['client_percentage'] ?? '')) ?>"
                                placeholder="e.g. 60"
                            />
                            <span class="input-group-text">%</span>
                        </div>
                        <?php if ($hasError('client_percentage')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_percentage')) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold" for="estate-sale-client-split-type">Split basis</label>
                        <select
                            id="estate-sale-client-split-type"
                            name="client_split_type"
                            class="form-select <?= $hasError('client_split_type') ? 'is-invalid' : '' ?>"
                        >
                            <?php foreach ($clientSplitTypeOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $selectedClientSplitType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($hasError('client_split_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_split_type')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="form-text" id="estate-sale-split-help"><?= e(\App\Models\EstateSale::clientSplitTypeHelpText($selectedClientSplitType)) ?></div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="estate-sale-notes">Notes</label>
                <textarea id="estate-sale-notes" name="notes" class="form-control" rows="3"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save changes' : 'Create estate sale') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $estateSaleId > 0 ? url('/estate-sales/' . (string) $estateSaleId) : url('/estate-sales')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('estate-sale-client-search');
    const hiddenClientId = document.getElementById('estate-sale-client-id');
    const hiddenClientName = document.getElementById('estate-sale-client-name');
    const suggestions = document.getElementById('estate-sale-client-suggestions');
    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions) {
        return;
    }

    const searchUrl = String(searchInput.dataset.searchUrl || '').trim();
    let debounce = null;

    const hideSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.innerHTML = '';
    };

    const setSelected = (clientId, clientName) => {
        hiddenClientId.value = clientId > 0 ? String(clientId) : '';
        hiddenClientName.value = clientName;
        searchInput.value = clientName;
    };

    const renderSuggestions = (results, query) => {
        suggestions.innerHTML = '';
        if (!Array.isArray(results) || results.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'client-suggestion-item';
            empty.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            empty.querySelector('.client-suggestion-name').textContent = query.length >= 2 ? 'No clients found' : 'Type to search';
            suggestions.appendChild(empty);
            suggestions.classList.remove('d-none');
            return;
        }

        results.forEach((row) => {
            if (!row || typeof row !== 'object') {
                return;
            }
            const id = Number(row.id || 0);
            const name = String(row.name || ('Client #' + id)).trim();
            const meta = String(row.meta || '').trim();
            if (id <= 0) {
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'client-suggestion-item';
            button.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            button.querySelector('.client-suggestion-name').textContent = name;
            button.querySelector('.client-suggestion-meta').textContent = meta;
            button.addEventListener('click', () => {
                setSelected(id, name);
                hideSuggestions();
            });
            suggestions.appendChild(button);
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
            },
        })
            .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
            .then((payload) => {
                renderSuggestions(Array.isArray(payload && payload.results) ? payload.results : [], query);
            })
            .catch(() => hideSuggestions());
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

    const splitTypeSelect = document.getElementById('estate-sale-client-split-type');
    const splitHelpEl = document.getElementById('estate-sale-split-help');
    const splitHelpByType = <?= json_encode(array_combine(
        array_keys(\App\Models\EstateSale::clientSplitTypeOptions()),
        array_map(
            static fn (string $type): string => \App\Models\EstateSale::clientSplitTypeHelpText($type),
            array_keys(\App\Models\EstateSale::clientSplitTypeOptions())
        )
    )) ?>;
    if (splitTypeSelect && splitHelpEl) {
        splitTypeSelect.addEventListener('change', () => {
            const selected = String(splitTypeSelect.value || '');
            if (splitHelpByType[selected]) {
                splitHelpEl.textContent = splitHelpByType[selected];
            }
        });
    }
});
</script>
