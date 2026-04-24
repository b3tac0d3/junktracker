<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/clients'));
$hasClientType = (bool) ($hasClientType ?? false);
$hasNewsletter = (bool) ($hasNewsletter ?? false);
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$clientId = (int) ($clientId ?? 0);
$referralsSent = is_array($referralsSent ?? null) ? $referralsSent : [];

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
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Client' : 'Add Client') ?></h1>
        <p class="muted">Contact details and client classification</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients')) ?>">Back to Clients</a>
    </div>
</div>

<?php if ($mode === 'edit' && $referralsSent !== []): ?>
    <section class="card index-card mb-3">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-user-friends me-2"></i>Referrals sent</strong>
        </div>
        <div class="card-body py-3">
            <div class="simple-list-table">
                <?php foreach ($referralsSent as $ref): ?>
                    <?php
                    if (!is_array($ref)) {
                        continue;
                    }
                    $refId = (int) ($ref['id'] ?? 0);
                    if ($refId <= 0) {
                        continue;
                    }
                    $refName = trim(((string) ($ref['first_name'] ?? '')) . ' ' . ((string) ($ref['last_name'] ?? '')));
                    if ($refName === '') {
                        $refName = trim((string) ($ref['company_name'] ?? ''));
                    }
                    if ($refName === '') {
                        $refName = 'Client #' . (string) $refId;
                    }
                    $refCity = trim((string) ($ref['city'] ?? ''));
                    $refPhone = trim((string) ($ref['phone'] ?? ''));
                    ?>
                    <a class="simple-list-row simple-list-row-link" href="<?= e(url('/clients/' . (string) $refId)) ?>">
                        <div class="simple-list-title"><?= e($refName) ?></div>
                        <div class="simple-list-meta">
                            <?php if ($refPhone !== ''): ?>
                                <span><?= e(format_phone($refPhone)) ?></span>
                            <?php endif; ?>
                            <?php if ($refCity !== ''): ?>
                                <span><?= e($refCity) ?></span>
                            <?php endif; ?>
                            <span>ID #<?= e((string) $refId) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-plus me-2"></i><?= e($mode === 'edit' ? 'Update Client' : 'Create Client') ?></strong>
    </div>
    <div class="card-body">
        <div id="client-duplicate-alert" class="alert alert-warning alert-persistent alert-dismissible d-none" role="status" aria-live="polite"></div>
        <form
            id="client-form"
            method="post"
            action="<?= e($actionUrl) ?>"
            class="row g-3"
            data-check-url="<?= e(url('/clients/check-duplicates')) ?>"
            data-clients-base="<?= e(url('/clients')) ?>"
            data-client-id="<?= (string) $clientId ?>"
        >
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-first-name">First Name</label>
                <input id="client-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-last-name">Last Name</label>
                <input id="client-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <?php if ($hasClientType): ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="client-type">Client Type</label>
                    <select id="client-type" name="client_type" class="form-select <?= $hasError('client_type') ? 'is-invalid' : '' ?>">
                        <?php foreach ($clientTypeOptions as $optionRaw): ?>
                            <?php
                            $option = strtolower(trim((string) $optionRaw));
                            if ($option === '') {
                                continue;
                            }
                            $label = ucwords(str_replace('_', ' ', $option));
                            ?>
                            <option value="<?= e($option) ?>" <?= ((string) ($form['client_type'] ?? 'client')) === $option ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('client_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_type')) ?></div><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="client-company-name">Company Name</label>
                    <input id="client-company-name" name="company_name" class="form-control" value="<?= e((string) ($form['company_name'] ?? '')) ?>" maxlength="150" />
                </div>
            <?php endif; ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-email">Email</label>
                <input id="client-email" name="email" type="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <?php if ($hasClientType): ?>
                <div class="col-12 col-lg-8">
                    <label class="form-label fw-semibold" for="client-company-name">Company Name</label>
                    <input id="client-company-name" name="company_name" class="form-control" value="<?= e((string) ($form['company_name'] ?? '')) ?>" maxlength="150" />
                </div>
            <?php endif; ?>

            <?php if ($hasNewsletter): ?>
                <div class="col-12 col-lg-8">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="client-newsletter" name="newsletter_subscribed" value="1" <?= ((string) ($form['newsletter_subscribed'] ?? '0')) === '1' ? 'checked' : '' ?> />
                        <label class="form-check-label fw-semibold" for="client-newsletter">Subscribe to newsletter</label>
                    </div>
                    <div class="form-text">When you send email campaigns, include clients who opted in here. A unique unsubscribe token is stored for each subscriber.</div>
                </div>
            <?php endif; ?>

            <?php if (!$hasClientType): ?>
                <input type="hidden" name="client_type" value="<?= e((string) ($form['client_type'] ?? 'client')) ?>" />
            <?php endif; ?>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-phone">Primary Phone</label>
                <input id="client-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="client-can-text" name="can_text" <?= ((string) ($form['can_text'] ?? '0')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label fw-semibold text-nowrap" for="client-can-text">Can Text</label>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-secondary-phone">Secondary Phone</label>
                <input id="client-secondary-phone" name="secondary_phone" class="form-control" value="<?= e((string) ($form['secondary_phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="client-secondary-can-text" name="secondary_can_text" <?= ((string) ($form['secondary_can_text'] ?? '0')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label fw-semibold text-nowrap" for="client-secondary-can-text">Can Text</label>
                </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-address-line1">Address Line 1</label>
                <input id="client-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-address-line2">Address Line 2</label>
                <input id="client-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-city">City</label>
                <input id="client-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="client-state">State</label>
                <select id="client-state" name="state" class="form-select">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="client-postal-code">Postal Code</label>
                <input id="client-postal-code" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="referrer-search">Referred by</label>
                <input type="hidden" id="referrer-client-id" name="referred_by_client_id" value="<?= e((string) ($form['referred_by_client_id'] ?? '')) ?>" />
                <div class="position-relative client-autosuggest-wrap">
                    <input
                        id="referrer-search"
                        type="text"
                        class="form-control <?= $hasError('referred_by_client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['referrer_display_name'] ?? '')) ?>"
                        placeholder="Search for the client who referred this person…"
                        autocomplete="off"
                        data-search-url="<?= e(url('/clients/referrer-search')) ?>"
                        data-exclude-id="<?= (string) $clientId ?>"
                    />
                    <div id="referrer-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Referrer suggestions"></div>
                </div>
                <?php if ($hasError('referred_by_client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('referred_by_client_id')) ?></div><?php endif; ?>
                <div class="form-text">Optional. Links this record to an existing client who sent you this lead.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="client-primary-note">Primary Note</label>
                <textarea id="client-primary-note" name="primary_note" class="form-control" rows="4"><?= e((string) ($form['primary_note'] ?? '')) ?></textarea>
            </div>

            <?php if ($mode === 'create'): ?>
                <?php $nextAction = strtolower(trim((string) ($form['next_action'] ?? ''))); ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="client-next-action">Next Step</label>
                    <select id="client-next-action" name="next_action" class="form-select">
                        <option value="" <?= $nextAction === '' ? 'selected' : '' ?>>No action</option>
                        <option value="job" <?= $nextAction === 'job' ? 'selected' : '' ?>>Go to Add Job</option>
                        <option value="quote" <?= $nextAction === 'quote' ? 'selected' : '' ?>>Go to Add Quote</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Client') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($clientId) ? url('/clients/' . (string) ((int) $clientId)) : url('/clients')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('referrer-search');
    const hiddenId = document.getElementById('referrer-client-id');
    const suggestions = document.getElementById('referrer-suggestions');
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
