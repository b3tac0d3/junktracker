<?php
$client = is_array($client ?? null) ? $client : [];
$clientId = (int) ($clientId ?? ($client['id'] ?? 0));
$memberId = (int) ($memberId ?? 0);
$mode = (string) ($mode ?? 'create');
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$relationshipOptions = is_array($relationshipOptions ?? null) ? $relationshipOptions : \App\Models\ClientFamilyMember::relationshipOptions();
$actionUrl = (string) ($actionUrl ?? url('/clients/' . (string) $clientId . '/family'));
$returnTab = (string) ($returnTab ?? 'details');
$backUrl = url(detail_path_with_tab('/clients/' . (string) $clientId, $returnTab));
$isEdit = $mode === 'edit';

$displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = trim((string) ($client['company_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'Client #' . (string) max(0, $clientId);
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= $isEdit ? 'Edit Family Member' : 'Add Family Member' ?></h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Back to Client</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-people-roof me-2"></i>Family Member</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>
            <?= detail_tab_hidden_field($returnTab) ?>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="family-member-first-name">First Name</label>
                <input
                    id="family-member-first-name"
                    type="text"
                    name="first_name"
                    class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['first_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="family-member-last-name">Last Name</label>
                <input
                    id="family-member-last-name"
                    type="text"
                    name="last_name"
                    class="form-control <?= $hasError('last_name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['last_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('last_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('last_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="family-member-relationship">Relationship</label>
                <select id="family-member-relationship" name="relationship" class="form-select <?= $hasError('relationship') ? 'is-invalid' : '' ?>">
                    <option value="">Choose relationship…</option>
                    <?php foreach ($relationshipOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['relationship'] ?? '')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('relationship')): ?><div class="invalid-feedback d-block"><?= e($fieldError('relationship')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="family-member-phone">Phone</label>
                <input
                    id="family-member-phone"
                    type="text"
                    name="phone"
                    class="form-control <?= $hasError('phone') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['phone'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('phone')): ?><div class="invalid-feedback d-block"><?= e($fieldError('phone')) ?></div><?php endif; ?>
                <div class="form-text">Separate from the primary client phone.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="family-linked-client-search">Link to existing client</label>
                <input type="hidden" id="family-linked-client-id" name="linked_client_id" value="<?= e((string) ($form['linked_client_id'] ?? '')) ?>" />
                <input type="hidden" name="linked_client_display_name" value="<?= e((string) ($form['linked_client_display_name'] ?? '')) ?>" />
                <div class="position-relative client-autosuggest-wrap">
                    <input
                        id="family-linked-client-search"
                        type="text"
                        class="form-control <?= $hasError('linked_client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['linked_client_display_name'] ?? '')) ?>"
                        placeholder="Search by name to link an existing client record…"
                        autocomplete="off"
                        data-search-url="<?= e(url('/clients/referrer-search')) ?>"
                        data-exclude-id="<?= (string) $clientId ?>"
                    />
                    <div id="family-linked-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('linked_client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('linked_client_id')) ?></div><?php endif; ?>
                <div class="form-text">Optional. Use when this person is also a client in your system.</div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Add Family Member' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('family-linked-client-search');
    const hiddenId = document.getElementById('family-linked-client-id');
    const suggestions = document.getElementById('family-linked-client-suggestions');
    if (!searchInput || !hiddenId || !suggestions) {
        return;
    }
    const searchUrl = String(searchInput.dataset.searchUrl || '');
    const excludeId = String(searchInput.dataset.excludeId || '0');
    let debounce = null;

    const hide = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const render = (items) => {
        suggestions.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            hide();
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
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = meta;
            row.addEventListener('click', () => {
                hiddenId.value = String(id);
                searchInput.value = name;
                hide();
            });
            suggestions.appendChild(row);
        });
        if (suggestions.children.length > 0) {
            suggestions.classList.remove('d-none');
        }
    };

    const fetchResults = (query) => {
        if (query.length < 2 || searchUrl === '') {
            hide();
            return;
        }
        let url = searchUrl + '?q=' + encodeURIComponent(query);
        if (excludeId !== '0') {
            url += '&exclude_id=' + encodeURIComponent(excludeId);
        }
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then((r) => r.ok ? r.json() : Promise.reject())
            .then((payload) => {
                render(Array.isArray(payload && payload.results) ? payload.results : []);
            })
            .catch(() => hide());
    };

    searchInput.addEventListener('input', () => {
        hiddenId.value = '';
        if (debounce) {
            clearTimeout(debounce);
        }
        debounce = setTimeout(() => fetchResults(String(searchInput.value || '').trim()), 160);
    });

    searchInput.addEventListener('focus', () => {
        const q = String(searchInput.value || '').trim();
        if (q.length >= 2) {
            fetchResults(q);
        }
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== searchInput) {
            hide();
        }
    });
});
</script>
