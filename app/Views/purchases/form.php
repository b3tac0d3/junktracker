<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/purchases'));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$searchUrl = (string) ($searchUrl ?? url('/purchases/client-search'));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Purchase Order' : 'Add Purchase Order') ?></h1>
        <p class="muted">Simple purchase intake workflow.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($purchaseId) ? url('/purchases/' . (string) ((int) $purchaseId)) : url('/purchases')) ?>">Back to Purchases</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-cart-arrow-down me-2"></i><?= e($mode === 'edit' ? 'Update Purchase Order' : 'Create Purchase Order') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-5">
                <label class="form-label fw-semibold" for="purchase-title">Name</label>
                <input
                    id="purchase-title"
                    name="title"
                    class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['title'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="What are you buying?"
                />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="purchase-status">Status</label>
                <select id="purchase-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $option): ?>
                        <option value="<?= e((string) $option) ?>" <?= ((string) ($form['status'] ?? 'prospect')) === (string) $option ? 'selected' : '' ?>><?= e($statusLabel((string) $option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="purchase-client-search">Client</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="purchase-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="purchase-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="purchase-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e($searchUrl) ?>"
                        data-create-url="<?= e(url('/purchases/quick-create-client')) ?>"
                    />
                    <div id="purchase-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="purchase-contact-date">Contact Date</label>
                <input
                    id="purchase-contact-date"
                    type="date"
                    name="contact_date"
                    class="form-control <?= $hasError('contact_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['contact_date'] ?? '')) ?>"
                />
                <?php if ($hasError('contact_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('contact_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="purchase-purchase-date">Purchase Date</label>
                <input
                    id="purchase-purchase-date"
                    type="date"
                    name="purchase_date"
                    class="form-control <?= $hasError('purchase_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['purchase_date'] ?? '')) ?>"
                />
                <?php if ($hasError('purchase_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('purchase_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="purchase-price">Purchase Price</label>
                <input
                    id="purchase-price"
                    type="number"
                    step="0.01"
                    min="0"
                    name="purchase_price"
                    class="form-control <?= $hasError('purchase_price') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['purchase_price'] ?? '')) ?>"
                    placeholder="0.00"
                />
                <?php if ($hasError('purchase_price')): ?><div class="invalid-feedback d-block"><?= e($fieldError('purchase_price')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="purchase-notes">Note</label>
                <textarea
                    id="purchase-notes"
                    name="notes"
                    class="form-control"
                    rows="4"
                    placeholder="Basic notes"
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12">
                <div class="card index-card border">
                    <div class="card-header index-card-header py-2">
                        <strong><i class="fas fa-list-check me-2"></i>Follow-Up Task</strong>
                    </div>
                    <div class="card-body row g-3 align-items-end">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create-follow-up-task" name="create_follow_up_task" value="1" <?= ((string) ($form['create_follow_up_task'] ?? '')) === '1' ? 'checked' : '' ?> />
                                <label class="form-check-label" for="create-follow-up-task">Create follow-up task when saving</label>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8 follow-up-task-fields">
                            <label class="form-label fw-semibold" for="follow-up-title">Task Title (Optional)</label>
                            <input id="follow-up-title" name="follow_up_title" class="form-control" value="<?= e((string) ($form['follow_up_title'] ?? '')) ?>" placeholder="Default: Purchase Follow-Up" />
                        </div>
                        <div class="col-12 col-lg-4 follow-up-task-fields">
                            <label class="form-label fw-semibold" for="follow-up-due-date">Task Due Date (Optional)</label>
                            <input id="follow-up-due-date" type="date" name="follow_up_due_date" class="form-control <?= $hasError('follow_up_due_date') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['follow_up_due_date'] ?? '')) ?>" />
                            <?php if ($hasError('follow_up_due_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('follow_up_due_date')) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Purchase Order') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($purchaseId) ? url('/purchases/' . (string) ((int) $purchaseId)) : url('/purchases')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<div class="modal fade" id="quickClientModalPurchase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quick-client-error-purchase" class="alert alert-danger d-none mb-3"></div>
                <form id="quick-client-form-purchase" class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-first-name-purchase">First Name</label>
                        <input id="quick-client-first-name-purchase" name="first_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-last-name-purchase">Last Name</label>
                        <input id="quick-client-last-name-purchase" name="last_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-company-name-purchase">Company Name</label>
                        <input id="quick-client-company-name-purchase" name="company_name" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-type-purchase">Client Type</label>
                        <select id="quick-client-type-purchase" name="client_type" class="form-select">
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
                        <label class="form-label fw-semibold" for="quick-client-phone-purchase">Phone</label>
                        <input id="quick-client-phone-purchase" name="phone" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-12 col-lg-8">
                        <label class="form-label fw-semibold" for="quick-client-address-1-purchase">Address Line 1</label>
                        <input id="quick-client-address-1-purchase" name="address_line1" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-city-purchase">City</label>
                        <input id="quick-client-city-purchase" name="city" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-state-purchase">State</label>
                        <input id="quick-client-state-purchase" name="state" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-postal-purchase">Postal Code</label>
                        <input id="quick-client-postal-purchase" name="postal_code" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="quick-client-note-purchase">Primary Note</label>
                        <textarea id="quick-client-note-purchase" name="primary_note" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quick-client-save-purchase">Save Client</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('purchase-client-search');
    const hiddenClientId = document.getElementById('purchase-client-id');
    const hiddenClientName = document.getElementById('purchase-client-name');
    const suggestions = document.getElementById('purchase-client-suggestions');
    const quickClientModalEl = document.getElementById('quickClientModalPurchase');
    const saveQuickClientBtn = document.getElementById('quick-client-save-purchase');
    const quickClientForm = document.getElementById('quick-client-form-purchase');
    const quickClientError = document.getElementById('quick-client-error-purchase');
    const purchaseForm = document.querySelector('form[action="<?= e($actionUrl) ?>"]');
    const followUpToggle = document.getElementById('create-follow-up-task');
    const followUpFields = Array.from(document.querySelectorAll('.follow-up-task-fields'));

    const syncFollowUpState = () => {
        if (!followUpToggle) {
            return;
        }

        const enabled = followUpToggle.checked;
        followUpFields.forEach((container) => {
            container.classList.toggle('opacity-50', !enabled);
            container.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !enabled;
            });
        });
    };

    if (followUpToggle) {
        followUpToggle.addEventListener('change', syncFollowUpState);
        syncFollowUpState();
    }

    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions || !quickClientModalEl || !saveQuickClientBtn || !quickClientForm || !quickClientError || !purchaseForm) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    const createUrl = searchInput.dataset.createUrl || '';
    const csrfInput = purchaseForm.querySelector('input[name="csrf_token"]');
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
        const firstNameInput = document.getElementById('quick-client-first-name-purchase');
        const lastNameInput = document.getElementById('quick-client-last-name-purchase');
        const companyNameInput = document.getElementById('quick-client-company-name-purchase');

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
