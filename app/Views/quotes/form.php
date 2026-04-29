<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/quotes'));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\Quote::statusOptions();
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$serviceTypeOptions = is_array($serviceTypeOptions ?? null) ? $serviceTypeOptions : ['removal', 'cleanout', 'demo', 'haul_away'];
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}
$selectedServiceType = strtolower(trim((string) ($form['service_type'] ?? '')));
$normalizedServiceTypeOptions = [];
foreach ($serviceTypeOptions as $optionRaw) {
    $option = strtolower(trim((string) $optionRaw));
    if ($option === '') {
        continue;
    }
    $normalizedServiceTypeOptions[$option] = ucwords(str_replace('_', ' ', $option));
}
if ($selectedServiceType !== '' && !array_key_exists($selectedServiceType, $normalizedServiceTypeOptions)) {
    $normalizedServiceTypeOptions[$selectedServiceType] = ucwords(str_replace('_', ' ', $selectedServiceType));
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Quote' : 'Add Quote') ?></h1>
        <p class="muted">Simple quote intake that can convert into a job.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/quotes')) ?>">Back to Quotes</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Quote' : 'Create Quote') ?></strong>
    </div>
    <div class="card-body">
        <form id="quote-form" method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-title">Quote Name</label>
                <input id="quote-title" name="title" class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-status">Status</label>
                <select id="quote-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <?php $statusValue = strtolower(trim((string) $statusOption)); ?>
                        <?php if ($statusValue === '') continue; ?>
                        <option value="<?= e($statusValue) ?>" <?= strtolower(trim((string) ($form['status'] ?? 'new'))) === $statusValue ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $statusValue))) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-service-type">Service Type</label>
                <select id="quote-service-type" name="service_type" class="form-select">
                    <option value="">Select service type...</option>
                    <?php foreach ($normalizedServiceTypeOptions as $serviceValue => $serviceLabel): ?>
                        <option value="<?= e($serviceValue) ?>" <?= $selectedServiceType === $serviceValue ? 'selected' : '' ?>><?= e($serviceLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-client-search">Client</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="quote-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="quote-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="quote-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/jobs/client-search')) ?>"
                        data-create-url="<?= e(url('/jobs/quick-create-client')) ?>"
                    />
                    <div id="quote-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-date">Quote Date</label>
                <input id="quote-date" type="date" name="next_follow_up_at" class="form-control <?= $hasError('next_follow_up_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['next_follow_up_at'] ?? '')) ?>" />
                <?php if ($hasError('next_follow_up_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('next_follow_up_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="quote-notes">Scope / Notes</label>
                <textarea id="quote-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-address-line1">Address Line 1</label>
                <input id="quote-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-address-line2">Address Line 2</label>
                <input id="quote-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="quote-city">City</label>
                <input id="quote-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="quote-state">State</label>
                <select id="quote-state" name="state" class="form-select">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="quote-postal">Postal Code</label>
                <input id="quote-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-lost-reason">Lost Reason (if lost)</label>
                <input id="quote-lost-reason" name="lost_reason" class="form-control" value="<?= e((string) ($form['lost_reason'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Quote') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/quotes')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<div class="modal fade" id="quickClientModalQuote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quick-client-error-quote" class="alert alert-danger d-none mb-3"></div>
                <form id="quick-client-form-quote" class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-first-name-quote">First name</label>
                        <input id="quick-client-first-name-quote" name="first_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-last-name-quote">Last name</label>
                        <input id="quick-client-last-name-quote" name="last_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-company-name-quote">Company name</label>
                        <input id="quick-client-company-name-quote" name="company_name" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-type-quote">Client type</label>
                        <select id="quick-client-type-quote" name="client_type" class="form-select">
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
                        <label class="form-label fw-semibold" for="quick-client-phone-quote">Phone</label>
                        <input id="quick-client-phone-quote" name="phone" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="quick-client-address-1-quote">Address Line 1</label>
                        <input id="quick-client-address-1-quote" name="address_line1" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="quick-client-address-2-quote">Address Line 2</label>
                        <input id="quick-client-address-2-quote" name="address_line2" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="quick-client-city-quote">City</label>
                        <input id="quick-client-city-quote" name="city" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label fw-semibold" for="quick-client-state-quote">State</label>
                        <select id="quick-client-state-quote" name="state" class="form-select">
                            <?php foreach ($stateOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label fw-semibold" for="quick-client-postal-quote">Postal Code</label>
                        <input id="quick-client-postal-quote" name="postal_code" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="quick-client-note-quote">Primary note</label>
                        <textarea id="quick-client-note-quote" name="primary_note" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quick-client-save-quote">Save client</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('quote-client-search');
    const hiddenClientId = document.getElementById('quote-client-id');
    const hiddenClientName = document.getElementById('quote-client-name');
    const suggestions = document.getElementById('quote-client-suggestions');
    const quickClientModalEl = document.getElementById('quickClientModalQuote');
    const saveQuickClientBtn = document.getElementById('quick-client-save-quote');
    const quickClientForm = document.getElementById('quick-client-form-quote');
    const quickClientError = document.getElementById('quick-client-error-quote');
    const quoteForm = document.getElementById('quote-form');
    const addressLine1Input = document.getElementById('quote-address-line1');
    const addressLine2Input = document.getElementById('quote-address-line2');
    const cityInput = document.getElementById('quote-city');
    const stateInput = document.getElementById('quote-state');
    const postalInput = document.getElementById('quote-postal');

    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions || !quickClientModalEl || !saveQuickClientBtn || !quickClientForm || !quickClientError || !quoteForm) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    const createUrl = searchInput.dataset.createUrl || '';
    const csrfInput = quoteForm.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const modal = bootstrap.Modal.getOrCreateInstance(quickClientModalEl);
    let debounce = null;

    const fillIfEmpty = (input, value) => {
        if (!input) {
            return;
        }
        const next = (value || '').toString().trim();
        if (input.tagName === 'SELECT') {
            if ((input.value || '').trim() !== '') {
                return;
            }
            if (next === '') {
                return;
            }
            const up = next.toUpperCase();
            const opts = Array.from(input.options || []);
            if (opts.some((o) => o.value === up)) {
                input.value = up;
            } else if (opts.some((o) => o.value === next)) {
                input.value = next;
            }
            return;
        }
        if ((input.value || '').trim() !== '') {
            return;
        }
        if (next !== '') {
            input.value = next;
        }
    };

    const maybeAutofillQuoteAddress = (client) => {
        if (!client || typeof client !== 'object') {
            return;
        }
        fillIfEmpty(addressLine1Input, client.address_line1);
        fillIfEmpty(addressLine2Input, client.address_line2);
        fillIfEmpty(cityInput, client.city);
        fillIfEmpty(stateInput, client.state);
        fillIfEmpty(postalInput, client.postal_code);
    };

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
        const firstNameInput = document.getElementById('quick-client-first-name-quote');
        const lastNameInput = document.getElementById('quick-client-last-name-quote');
        const companyNameInput = document.getElementById('quick-client-company-name-quote');

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
                    maybeAutofillQuoteAddress(item);
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
                maybeAutofillQuoteAddress(client);
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

