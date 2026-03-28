<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/deliveries'));
$deliveryId = (int) ($deliveryId ?? 0);
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$statusOptions = \App\Models\ClientDelivery::statusOptions();
$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit delivery' : 'Add delivery') ?></h1>
        <p class="muted">Schedule a client delivery or drop-off</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $deliveryId > 0 ? url('/deliveries/' . (string) $deliveryId) : url('/deliveries')) ?>">Back</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-truck me-2"></i><?= e($mode === 'edit' ? 'Update delivery' : 'Create delivery') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3" id="delivery-form">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="delivery-client-search">Client</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="delivery-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="delivery-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="delivery-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/jobs/client-search')) ?>"
                        data-create-url="<?= e(url('/jobs/quick-create-client')) ?>"
                    />
                    <div id="delivery-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="delivery-status">Status</label>
                <select id="delivery-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= ((string) ($form['status'] ?? 'scheduled')) === $opt ? 'selected' : '' ?>><?= e($statusLabel($opt)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="delivery-scheduled-at">Scheduled start</label>
                <input
                    id="delivery-scheduled-at"
                    type="datetime-local"
                    name="scheduled_at"
                    class="form-control <?= $hasError('scheduled_at') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['scheduled_at'] ?? '')) ?>"
                />
                <?php if ($hasError('scheduled_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('scheduled_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="delivery-end-at">End time <span class="text-muted fw-normal">(optional)</span></label>
                <input
                    id="delivery-end-at"
                    type="datetime-local"
                    name="end_at"
                    class="form-control <?= $hasError('end_at') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['end_at'] ?? '')) ?>"
                />
                <?php if ($hasError('end_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('end_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <h6 class="text-muted text-uppercase small mb-2">Delivery location</h6>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="delivery-address-1">Address line 1</label>
                <input
                    id="delivery-address-1"
                    name="address_line1"
                    class="form-control <?= $hasError('address_line1') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['address_line1'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="Street address (optional if same as client)"
                />
                <?php if ($hasError('address_line1')): ?><div class="invalid-feedback d-block"><?= e($fieldError('address_line1')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="delivery-city">City</label>
                <input id="delivery-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="delivery-state">State</label>
                <input id="delivery-state" name="state" class="form-control" value="<?= e((string) ($form['state'] ?? '')) ?>" maxlength="60" />
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="delivery-postal">Postal code</label>
                <input id="delivery-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="delivery-notes">Notes</label>
                <textarea id="delivery-notes" name="notes" class="form-control" rows="3" placeholder="Access codes, dock, special instructions..."><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save changes' : 'Schedule delivery') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $deliveryId > 0 ? url('/deliveries/' . (string) $deliveryId) : url('/deliveries')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<div class="modal fade" id="quickClientModalDelivery" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quick-client-error-delivery" class="alert alert-danger d-none mb-3"></div>
                <form id="quick-client-form-delivery" class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-first-name-delivery">First name</label>
                        <input id="quick-client-first-name-delivery" name="first_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-last-name-delivery">Last name</label>
                        <input id="quick-client-last-name-delivery" name="last_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-company-name-delivery">Company name</label>
                        <input id="quick-client-company-name-delivery" name="company_name" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-type-delivery">Client type</label>
                        <select id="quick-client-type-delivery" name="client_type" class="form-select">
                            <?php foreach ($clientTypeOptions as $optionRaw): ?>
                                <?php
                                $option = strtolower(trim((string) $optionRaw));
                                if ($option === '') {
                                    continue;
                                }
                                $label = ucwords(str_replace('_', ' ', $option));
                                ?>
                                <option value="<?= e($option) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-phone-delivery">Phone</label>
                        <input id="quick-client-phone-delivery" name="phone" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-12 col-lg-8">
                        <label class="form-label fw-semibold" for="quick-client-address-1-delivery">Address line 1</label>
                        <input id="quick-client-address-1-delivery" name="address_line1" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-city-delivery">City</label>
                        <input id="quick-client-city-delivery" name="city" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-state-delivery">State</label>
                        <input id="quick-client-state-delivery" name="state" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-postal-delivery">Postal code</label>
                        <input id="quick-client-postal-delivery" name="postal_code" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="quick-client-note-delivery">Primary note</label>
                        <textarea id="quick-client-note-delivery" name="primary_note" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quick-client-save-delivery">Save client</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('delivery-client-search');
    const hiddenClientId = document.getElementById('delivery-client-id');
    const hiddenClientName = document.getElementById('delivery-client-name');
    const suggestions = document.getElementById('delivery-client-suggestions');
    const quickClientModalEl = document.getElementById('quickClientModalDelivery');
    const saveQuickClientBtn = document.getElementById('quick-client-save-delivery');
    const quickClientForm = document.getElementById('quick-client-form-delivery');
    const quickClientError = document.getElementById('quick-client-error-delivery');
    const deliveryForm = document.getElementById('delivery-form');

    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions || !quickClientModalEl || !saveQuickClientBtn || !quickClientForm || !quickClientError || !deliveryForm) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    const createUrl = searchInput.dataset.createUrl || '';
    const csrfInput = deliveryForm.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const modal = bootstrap.Modal.getOrCreateInstance(quickClientModalEl);
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

    const openQuickClientModal = (queryText) => {
        const q = String(queryText || '').trim();
        const firstNameInput = document.getElementById('quick-client-first-name-delivery');
        const lastNameInput = document.getElementById('quick-client-last-name-delivery');
        const companyNameInput = document.getElementById('quick-client-company-name-delivery');

        if (q !== '') {
            const parts = q.split(/\s+/);
            if (firstNameInput) {
                firstNameInput.value = parts[0] || '';
            }
            if (lastNameInput && parts.length > 1) {
                lastNameInput.value = parts.slice(1).join(' ');
            }
            if (companyNameInput && parts.length <= 1) {
                companyNameInput.value = q;
            }
        }

        quickClientError.classList.add('d-none');
        quickClientError.textContent = '';
        modal.show();
        hideSuggestions();
    };

    const renderSuggestions = (items, queryText) => {
        suggestions.innerHTML = '';

        const addNewRow = () => {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'client-suggestion-item client-suggestion-add';
            addBtn.textContent = 'Add new client';
            addBtn.addEventListener('click', () => openQuickClientModal(queryText));
            suggestions.appendChild(addBtn);
        };

        if (Array.isArray(items) && items.length > 0) {
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
        }

        addNewRow();
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
                renderSuggestions(Array.isArray(payload && payload.results) ? payload.results : [], query);
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

    saveQuickClientBtn.addEventListener('click', async () => {
        if (createUrl === '') {
            return;
        }

        quickClientError.classList.add('d-none');
        quickClientError.textContent = '';
        saveQuickClientBtn.disabled = true;

        try {
            const data = new FormData(quickClientForm);
            data.set('csrf_token', csrfToken);
            const response = await fetch(createUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                const errorMessage = payload.error || (payload.errors && Object.values(payload.errors).join(' ')) || 'Unable to save client.';
                quickClientError.textContent = errorMessage;
                quickClientError.classList.remove('d-none');
                saveQuickClientBtn.disabled = false;
                return;
            }

            const client = payload.client || {};
            const clientId = Number(client.id || 0);
            if (clientId > 0) {
                setSelected(clientId, (client.name || ('Client #' + clientId)).toString());
            }

            quickClientForm.reset();
            modal.hide();
        } catch (error) {
            quickClientError.textContent = 'Unable to save client. Please try again.';
            quickClientError.classList.remove('d-none');
            console.error(error);
        } finally {
            saveQuickClientBtn.disabled = false;
        }
    });
});
</script>
