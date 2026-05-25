<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/purchase-quotes'));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\PurchaseQuote::statusOptions();
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$searchUrl = url('/purchases/client-search');
$stateOptions = us_state_options();
$purchaseQuoteId = (int) ($purchaseQuoteId ?? 0);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Purchase Quote' : 'Add Purchase Quote') ?></h1>
        <p class="muted">Track a buy opportunity before it becomes a purchase order.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $purchaseQuoteId > 0 ? url('/purchase-quotes/' . (string) $purchaseQuoteId) : url('/purchase-quotes')) ?>">Back</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Purchase Quote' : 'Create Purchase Quote') ?></strong>
    </div>
    <div class="card-body">
        <form id="pq-form" method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="pq-title">Title</label>
                <input id="pq-title" name="title" class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="190" placeholder="What are you looking to buy?" />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="pq-status">Status</label>
                <select id="pq-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <?php $statusValue = strtolower(trim((string) $statusOption)); ?>
                        <?php if ($statusValue === '') continue; ?>
                        <option value="<?= e($statusValue) ?>" <?= strtolower(trim((string) ($form['status'] ?? 'new'))) === $statusValue ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $statusValue))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="pq-contact-date">First Contact</label>
                <input id="pq-contact-date" type="date" name="contact_date" class="form-control <?= $hasError('contact_date') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['contact_date'] ?? '')) ?>" />
                <?php if ($hasError('contact_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('contact_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="pq-client-search">Client / Seller</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="pq-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="pq-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="pq-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e($searchUrl) ?>"
                        data-create-url="<?= e(url('/purchases/quick-create-client')) ?>"
                    />
                    <div id="pq-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="pq-follow-up">Follow-up Date &amp; Time</label>
                <input id="pq-follow-up" type="datetime-local" name="next_follow_up_at" class="form-control <?= $hasError('next_follow_up_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['next_follow_up_at'] ?? '')) ?>" />
                <?php if ($hasError('next_follow_up_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('next_follow_up_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="pq-notes">Notes</label>
                <textarea id="pq-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <?php if ($mode === 'edit'): ?>
            <div class="col-12">
                <label class="form-label fw-semibold" for="pq-lost-reason">Lost Reason</label>
                <input id="pq-lost-reason" name="lost_reason" class="form-control" value="<?= e((string) ($form['lost_reason'] ?? '')) ?>" maxlength="190" placeholder="If status is lost, why?" />
            </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Purchase Quote') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && $purchaseQuoteId > 0 ? url('/purchase-quotes/' . (string) $purchaseQuoteId) : url('/purchase-quotes')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<div class="modal fade" id="quickClientModalPq" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quick-client-error-pq" class="alert alert-danger d-none mb-3"></div>
                <form id="quick-client-form-pq" class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-first-name-pq">First Name</label>
                        <input id="quick-client-first-name-pq" name="first_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-last-name-pq">Last Name</label>
                        <input id="quick-client-last-name-pq" name="last_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-company-name-pq">Company Name</label>
                        <input id="quick-client-company-name-pq" name="company_name" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-type-pq">Client Type</label>
                        <select id="quick-client-type-pq" name="client_type" class="form-select">
                            <?php foreach ($clientTypeOptions as $optionRaw): ?>
                                <?php
                                $option = strtolower(trim((string) $optionRaw));
                                if ($option === '') continue;
                                ?>
                                <option value="<?= e($option) ?>"><?= e(ucwords(str_replace('_', ' ', $option))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-phone-pq">Phone</label>
                        <input id="quick-client-phone-pq" name="phone" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="quick-client-address-1-pq">Address Line 1</label>
                        <input id="quick-client-address-1-pq" name="address_line1" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="quick-client-address-2-pq">Address Line 2</label>
                        <input id="quick-client-address-2-pq" name="address_line2" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-city-pq">City</label>
                        <input id="quick-client-city-pq" name="city" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-state-pq">State</label>
                        <select id="quick-client-state-pq" name="state" class="form-select">
                            <?php foreach ($stateOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-postal-pq">Postal Code</label>
                        <input id="quick-client-postal-pq" name="postal_code" class="form-control" maxlength="30" />
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quick-client-save-pq">Save Client</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('pq-client-search');
    const hiddenClientId = document.getElementById('pq-client-id');
    const hiddenClientName = document.getElementById('pq-client-name');
    const suggestions = document.getElementById('pq-client-suggestions');
    const quickClientModalEl = document.getElementById('quickClientModalPq');
    const saveQuickClientBtn = document.getElementById('quick-client-save-pq');
    const quickClientForm = document.getElementById('quick-client-form-pq');
    const quickClientError = document.getElementById('quick-client-error-pq');
    const pqForm = document.getElementById('pq-form');

    if (!searchInput || !hiddenClientId || !hiddenClientName || !suggestions || !quickClientModalEl || !saveQuickClientBtn || !quickClientForm || !quickClientError || !pqForm) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    const createUrl = searchInput.dataset.createUrl || '';
    const csrfInput = pqForm.querySelector('input[name="csrf_token"]');
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
        const firstNameInput = document.getElementById('quick-client-first-name-pq');
        const lastNameInput = document.getElementById('quick-client-last-name-pq');
        const companyNameInput = document.getElementById('quick-client-company-name-pq');
        if (q !== '') {
            const parts = q.split(/\s+/);
            if (firstNameInput) firstNameInput.value = parts[0] || '';
            if (lastNameInput && parts.length > 1) lastNameInput.value = parts.slice(1).join(' ');
            if (companyNameInput && parts.length <= 1) companyNameInput.value = q;
        }
        quickClientError.classList.add('d-none');
        quickClientError.textContent = '';
        modal.show();
    };

    const addNewRow = () => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'client-suggestion-item client-suggestion-item--new';
        row.innerHTML = '<span class="client-suggestion-name">Add new client</span>';
        row.addEventListener('click', () => {
            hideSuggestions();
            openQuickClientModal(searchInput.value);
        });
        suggestions.appendChild(row);
    };

    const renderSuggestions = (results, query) => {
        suggestions.innerHTML = '';
        results.forEach((item) => {
            const id = Number(item.id || 0);
            const name = String(item.name || '').trim();
            const meta = [item.phone, item.city].filter(Boolean).join(' · ');
            if (id <= 0 || name === '') return;
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = meta;
            row.addEventListener('click', () => { setSelected(id, name); hideSuggestions(); });
            suggestions.appendChild(row);
        });
        addNewRow();
        suggestions.classList.remove('d-none');
    };

    const fetchSuggestions = (query) => {
        if (!searchUrl || query.length < 2) { hideSuggestions(); return; }
        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then((r) => r.ok ? r.json() : Promise.reject())
            .then((payload) => renderSuggestions(Array.isArray(payload?.results) ? payload.results : [], query))
            .catch(() => hideSuggestions());
    };

    searchInput.addEventListener('input', () => {
        hiddenClientId.value = '';
        hiddenClientName.value = String(searchInput.value || '').trim();
        if (debounce) clearTimeout(debounce);
        debounce = setTimeout(() => fetchSuggestions(String(searchInput.value || '').trim()), 180);
    });

    searchInput.addEventListener('focus', () => {
        const query = String(searchInput.value || '').trim();
        if (query.length >= 2) fetchSuggestions(query);
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== searchInput) hideSuggestions();
    });

    saveQuickClientBtn.addEventListener('click', async () => {
        if (createUrl === '') return;
        quickClientError.classList.add('d-none');
        saveQuickClientBtn.disabled = true;
        try {
            const data = new FormData(quickClientForm);
            data.set('csrf_token', csrfToken);
            const response = await fetch(createUrl, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                quickClientError.textContent = payload.error || 'Unable to save client.';
                quickClientError.classList.remove('d-none');
                return;
            }
            const client = payload.client || {};
            const clientId = Number(client.id || 0);
            if (clientId > 0) setSelected(clientId, (client.name || ('Client #' + clientId)).toString());
            quickClientForm.reset();
            modal.hide();
        } catch (e) {
            quickClientError.textContent = 'Unable to save client.';
            quickClientError.classList.remove('d-none');
        } finally {
            saveQuickClientBtn.disabled = false;
        }
    });
});
</script>
