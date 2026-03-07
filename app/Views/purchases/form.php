<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/purchases'));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
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

            <div class="col-12 col-lg-6">
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

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('purchase-client-search');
    const hiddenClientId = document.getElementById('purchase-client-id');
    const hiddenClientName = document.getElementById('purchase-client-name');
    const suggestions = document.getElementById('purchase-client-suggestions');
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
