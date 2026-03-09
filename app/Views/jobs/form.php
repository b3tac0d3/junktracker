<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$clientOptions = is_array($clientOptions ?? null) ? $clientOptions : [];
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : ['prospect', 'pending', 'active', 'complete', 'cancelled'];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/jobs'));

$statusOptions = [];
foreach ($statusOptionsRaw as $statusOptionRaw) {
    $statusOption = strtolower(trim((string) $statusOptionRaw));
    if ($statusOption === '') {
        continue;
    }
    if (array_key_exists($statusOption, $statusOptions)) {
        continue;
    }
    $statusOptions[$statusOption] = ucwords(str_replace('_', ' ', $statusOption));
}
if ($statusOptions === []) {
    $statusOptions = [
        'prospect' => 'Prospect',
        'pending' => 'Pending',
        'active' => 'Active',
        'complete' => 'Complete',
        'cancelled' => 'Cancelled',
    ];
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$selectedClientId = (int) ($form['client_id'] ?? 0);
$selectedClientName = '';
foreach ($clientOptions as $clientOption) {
    if (!is_array($clientOption)) {
        continue;
    }
    $id = (int) ($clientOption['id'] ?? 0);
    if ($id !== $selectedClientId) {
        continue;
    }
    $selectedClientName = trim((string) ($clientOption['name'] ?? ''));
    break;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Job' : 'Add Job') ?></h1>
        <p class="muted">Simple job form</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/jobs')) ?>">Back to Jobs</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Job' : 'Create Job') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="job-title">Job Name</label>
                <input
                    id="job-title"
                    name="title"
                    class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['title'] ?? '')) ?>"
                    maxlength="190"
                />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="job-status">Status</label>
                <select id="job-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['status'] ?? 'pending')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="job-client-search">Client</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="job-client-id" name="client_id" value="<?= e((string) $selectedClientId) ?>" />
                    <input
                        id="job-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e($selectedClientName) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/jobs/client-search')) ?>"
                        data-create-url="<?= e(url('/jobs/quick-create-client')) ?>"
                    />
                    <div id="job-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="scheduled-start">Scheduled Start</label>
                <input id="scheduled-start" type="datetime-local" step="3600" name="scheduled_start_at" class="form-control <?= $hasError('scheduled_start_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['scheduled_start_at'] ?? '')) ?>" />
                <?php if ($hasError('scheduled_start_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('scheduled_start_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="scheduled-end">Scheduled End</label>
                <input id="scheduled-end" type="datetime-local" step="3600" name="scheduled_end_at" class="form-control <?= $hasError('scheduled_end_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['scheduled_end_at'] ?? '')) ?>" />
                <?php if ($hasError('scheduled_end_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('scheduled_end_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="actual-start">Actual Start</label>
                <input id="actual-start" type="datetime-local" step="3600" name="actual_start_at" class="form-control <?= $hasError('actual_start_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['actual_start_at'] ?? '')) ?>" />
                <?php if ($hasError('actual_start_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('actual_start_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="actual-end">Actual End</label>
                <input id="actual-end" type="datetime-local" step="3600" name="actual_end_at" class="form-control <?= $hasError('actual_end_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['actual_end_at'] ?? '')) ?>" />
                <?php if ($hasError('actual_end_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('actual_end_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="address-line1">Address Line 1</label>
                <input id="address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="address-line2">Address Line 2</label>
                <input id="address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="job-city">City</label>
                <input id="job-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="job-state">State</label>
                <input id="job-state" name="state" class="form-control" value="<?= e((string) ($form['state'] ?? '')) ?>" maxlength="60" />
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="job-postal">Postal Code</label>
                <input id="job-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="job-notes">Primary Note</label>
                <textarea id="job-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <?php if ($mode !== 'edit'): ?>
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
                                <input id="follow-up-title" name="follow_up_title" class="form-control" value="<?= e((string) ($form['follow_up_title'] ?? '')) ?>" placeholder="Default: Job Follow-Up" />
                            </div>
                            <div class="col-12 col-lg-4 follow-up-task-fields">
                                <label class="form-label fw-semibold" for="follow-up-due-date">Task Due Date (Optional)</label>
                                <input id="follow-up-due-date" type="date" name="follow_up_due_date" class="form-control <?= $hasError('follow_up_due_date') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['follow_up_due_date'] ?? '')) ?>" />
                                <?php if ($hasError('follow_up_due_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('follow_up_due_date')) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Job') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($jobId) ? url('/jobs/' . (string) ((int) $jobId)) : url('/jobs')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<div class="modal fade" id="quickClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="quick-client-error" class="alert alert-danger d-none mb-3"></div>
                <form id="quick-client-form" class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-first-name">First Name</label>
                        <input id="quick-client-first-name" name="first_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-last-name">Last Name</label>
                        <input id="quick-client-last-name" name="last_name" class="form-control" maxlength="90" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-company-name">Company Name</label>
                        <input id="quick-client-company-name" name="company_name" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-type">Client Type</label>
                        <select id="quick-client-type" name="client_type" class="form-select">
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
                        <label class="form-label fw-semibold" for="quick-client-phone">Phone</label>
                        <input id="quick-client-phone" name="phone" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-12 col-lg-8">
                        <label class="form-label fw-semibold" for="quick-client-address-1">Address Line 1</label>
                        <input id="quick-client-address-1" name="address_line1" class="form-control" maxlength="190" />
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-city">City</label>
                        <input id="quick-client-city" name="city" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-state">State</label>
                        <input id="quick-client-state" name="state" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label fw-semibold" for="quick-client-postal">Postal Code</label>
                        <input id="quick-client-postal" name="postal_code" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="quick-client-note">Primary Note</label>
                        <textarea id="quick-client-note" name="primary_note" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quick-client-save">Save Client</button>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('job-client-search');
    const hiddenClientId = document.getElementById('job-client-id');
    const suggestions = document.getElementById('job-client-suggestions');
    const scheduledStartInput = document.getElementById('scheduled-start');
    const scheduledEndInput = document.getElementById('scheduled-end');
    const addressLine1Input = document.getElementById('address-line1');
    const addressLine2Input = document.getElementById('address-line2');
    const cityInput = document.getElementById('job-city');
    const stateInput = document.getElementById('job-state');
    const postalInput = document.getElementById('job-postal');
    const quickClientModalEl = document.getElementById('quickClientModal');
    const saveQuickClientBtn = document.getElementById('quick-client-save');
    const quickClientForm = document.getElementById('quick-client-form');
    const quickClientError = document.getElementById('quick-client-error');
    const jobForm = document.querySelector('form[action="<?= e($actionUrl) ?>"]');
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

    if (!searchInput || !hiddenClientId || !suggestions || !quickClientModalEl || !saveQuickClientBtn || !quickClientForm || !quickClientError || !jobForm) {
        return;
    }

    const searchUrl = searchInput.dataset.searchUrl || '';
    const createUrl = searchInput.dataset.createUrl || '';
    const csrfInput = jobForm.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const modal = bootstrap.Modal.getOrCreateInstance(quickClientModalEl);

    let debounce = null;
    let lastQuery = '';

    const toLocalDatetimeValue = (dateObj) => {
        const pad = (v) => String(v).padStart(2, '0');
        return dateObj.getFullYear()
            + '-' + pad(dateObj.getMonth() + 1)
            + '-' + pad(dateObj.getDate())
            + 'T' + pad(dateObj.getHours())
            + ':' + pad(dateObj.getMinutes());
    };

    const parseLocalDatetimeValue = (rawValue) => {
        const raw = String(rawValue || '').trim();
        if (raw === '') {
            return null;
        }
        const parsed = new Date(raw);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const roundToNearestHour = (dateObj) => {
        const rounded = new Date(dateObj);
        rounded.setSeconds(0, 0);
        if (rounded.getMinutes() >= 30) {
            rounded.setHours(rounded.getHours() + 1);
        }
        rounded.setMinutes(0);
        return rounded;
    };

    const normalizeDatetimeInput = (input, options = {}) => {
        if (!input) {
            return false;
        }
        const { fillIfEmpty = false, baseDate = null } = options;
        const raw = (input.value || '').trim();

        let base = parseLocalDatetimeValue(raw);
        if (!base && fillIfEmpty) {
            base = baseDate instanceof Date ? new Date(baseDate.getTime()) : new Date();
        }
        if (!base) {
            return false;
        }

        const rounded = roundToNearestHour(base);
        const nextValue = toLocalDatetimeValue(rounded);
        if (input.value !== nextValue) {
            input.value = nextValue;
            return true;
        }
        return false;
    };

    const applyStartEndDefaults = () => {
        if (!scheduledStartInput || !scheduledEndInput) {
            return;
        }

        const startChanged = normalizeDatetimeInput(scheduledStartInput, { fillIfEmpty: false });
        const startDate = parseLocalDatetimeValue(scheduledStartInput.value);
        if (!startDate) {
            return;
        }

        const endRaw = (scheduledEndInput.value || '').trim();
        const canSetEnd = endRaw === '' || scheduledEndInput.dataset.autoDefaulted === '1';
        if (canSetEnd) {
            const endDate = new Date(startDate.getTime() + (60 * 60 * 1000));
            scheduledEndInput.value = toLocalDatetimeValue(roundToNearestHour(endDate));
            scheduledEndInput.dataset.autoDefaulted = '1';
        } else if (startChanged) {
            normalizeDatetimeInput(scheduledEndInput, { fillIfEmpty: false });
        }
    };

    const attachHourlyRounding = (input, options = {}) => {
        if (!input) {
            return;
        }

        normalizeDatetimeInput(input, { fillIfEmpty: false });

        input.addEventListener('focus', () => {
            let baseDate = null;
            if (typeof options.baseDateResolver === 'function') {
                baseDate = options.baseDateResolver();
            }
            const changed = normalizeDatetimeInput(input, { fillIfEmpty: true, baseDate });
            if (changed && typeof options.onValueSet === 'function') {
                options.onValueSet();
            }
        });

        input.addEventListener('blur', () => {
            const changed = normalizeDatetimeInput(input, { fillIfEmpty: false });
            if (changed && typeof options.onValueSet === 'function') {
                options.onValueSet();
            }
        });
    };

    attachHourlyRounding(scheduledStartInput, { onValueSet: applyStartEndDefaults });
    if (scheduledStartInput) {
        scheduledStartInput.addEventListener('change', applyStartEndDefaults);
    }

    attachHourlyRounding(scheduledEndInput, {
        baseDateResolver: () => {
            const startDate = parseLocalDatetimeValue(scheduledStartInput ? scheduledStartInput.value : '');
            if (startDate) {
                return new Date(startDate.getTime() + (60 * 60 * 1000));
            }
            return new Date();
        },
    });
    if (scheduledEndInput) {
        scheduledEndInput.addEventListener('input', () => {
            scheduledEndInput.dataset.autoDefaulted = '0';
        });
    }

    attachHourlyRounding(document.getElementById('actual-start'));
    attachHourlyRounding(document.getElementById('actual-end'));

    const fillIfEmpty = (input, value) => {
        if (!input) {
            return;
        }
        if ((input.value || '').trim() !== '') {
            return;
        }
        const next = (value || '').toString().trim();
        if (next !== '') {
            input.value = next;
        }
    };

    const maybeAutofillJobAddress = (client) => {
        if (!client || typeof client !== 'object') {
            return;
        }
        fillIfEmpty(addressLine1Input, client.address_line1);
        fillIfEmpty(addressLine2Input, client.address_line2);
        fillIfEmpty(cityInput, client.city);
        fillIfEmpty(stateInput, client.state);
        fillIfEmpty(postalInput, client.postal_code);
    };

    const clearSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const renderAddNew = (queryText) => {
        suggestions.innerHTML = '';
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'client-suggestion-item client-suggestion-add';
        addBtn.textContent = 'Add new client';
        addBtn.addEventListener('click', () => openQuickClientModal(queryText));
        suggestions.appendChild(addBtn);
        suggestions.classList.remove('d-none');
    };

    const renderResults = (items, queryText) => {
        suggestions.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            renderAddNew(queryText);
            return;
        }

        items.forEach((item) => {
            const id = Number(item.id || 0);
            if (id <= 0) return;

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            const name = (item.name || ('Client #' + id)).toString();
            const company = (item.company_name || '').toString().trim();
            const phone = (item.phone || '').toString().trim();
            const city = (item.city || '').toString().trim();
            const meta = [company, phone, city].filter(Boolean).join(' · ');
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = meta;
            row.addEventListener('click', () => {
                hiddenClientId.value = String(id);
                searchInput.value = name;
                maybeAutofillJobAddress(item);
                clearSuggestions();
            });
            suggestions.appendChild(row);
        });

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'client-suggestion-item client-suggestion-add';
        addBtn.textContent = 'Add new client';
        addBtn.addEventListener('click', () => openQuickClientModal(queryText));
        suggestions.appendChild(addBtn);

        suggestions.classList.remove('d-none');
    };

    const fetchSuggestions = async (queryText) => {
        if (searchUrl === '') return;
        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', queryText);
            const response = await fetch(url.toString(), { credentials: 'same-origin' });
            if (!response.ok) return;
            const payload = await response.json();
            if (queryText !== lastQuery) return;
            renderResults(payload.results || [], queryText);
        } catch (error) {
            console.error(error);
        }
    };

    const openQuickClientModal = (queryText) => {
        const q = (queryText || '').trim();
        const firstNameInput = document.getElementById('quick-client-first-name');
        const lastNameInput = document.getElementById('quick-client-last-name');
        const companyNameInput = document.getElementById('quick-client-company-name');
        if (firstNameInput && q !== '') {
            const parts = q.split(/\s+/);
            firstNameInput.value = parts[0] || '';
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
        clearSuggestions();
    };

    searchInput.addEventListener('input', () => {
        const queryText = searchInput.value.trim();
        hiddenClientId.value = '';
        lastQuery = queryText;
        if (debounce) {
            clearTimeout(debounce);
        }
        if (queryText.length < 2) {
            clearSuggestions();
            return;
        }
        debounce = setTimeout(() => fetchSuggestions(queryText), 180);
    });

    searchInput.addEventListener('focus', () => {
        const queryText = searchInput.value.trim();
        if (queryText.length >= 2) {
            lastQuery = queryText;
            fetchSuggestions(queryText);
        }
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== searchInput) {
            clearSuggestions();
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
                hiddenClientId.value = String(clientId);
                searchInput.value = (client.name || ('Client #' + clientId)).toString();
                maybeAutofillJobAddress(client);
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
