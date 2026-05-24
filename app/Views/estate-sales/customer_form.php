<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$customer = is_array($customer ?? null) ? $customer : [];
$contactMethodOptions = is_array($contactMethodOptions ?? null) ? $contactMethodOptions : \App\Models\EstateSale::futureSalesContactMethodOptions();

$estateSaleId = (int) ($estateSale['id'] ?? 0);
$customerId = (int) ($customer['id'] ?? 0);
$estateSaleTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
$customerName = \App\Models\EstateSale::customerDisplayName($customer);
$backUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId);
$actionUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/update');
$subscribesChecked = !empty($form['subscribes_to_future_sales']);
$selectedContactMethod = \App\Models\EstateSale::normalizeFutureSalesContactMethod($form['future_sales_contact_method'] ?? null) ?? '';

$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
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
        <h1>Edit Customer</h1>
        <p class="muted mb-0"><?= e($customerName) ?> · <?= e($estateSaleTitle) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Back to Customer</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-pen me-2"></i>Customer Details</strong>
    </div>
    <div class="card-body">
        <div id="estate-sale-customer-duplicate-alert" class="alert alert-warning alert-persistent alert-dismissible d-none mb-3" role="status" aria-live="polite"></div>
        <form
            method="post"
            action="<?= e($actionUrl) ?>"
            class="row g-3"
            id="estate-sale-customer-form"
            data-check-url="<?= e(url('/estate-customers/check-duplicates')) ?>"
            data-estate-sale-id="<?= e((string) $estateSaleId) ?>"
            data-customer-id="<?= e((string) $customerId) ?>"
        >
            <?= csrf_field() ?>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-first-name">First name</label>
                <input
                    id="estate-sale-customer-edit-first-name"
                    name="first_name"
                    class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>"
                    maxlength="90"
                    value="<?= e((string) ($form['first_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-last-name">Last name</label>
                <input
                    id="estate-sale-customer-edit-last-name"
                    name="last_name"
                    class="form-control <?= $hasError('last_name') ? 'is-invalid' : '' ?>"
                    maxlength="90"
                    value="<?= e((string) ($form['last_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('last_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('last_name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-email">Email</label>
                <input
                    id="estate-sale-customer-edit-email"
                    name="email"
                    type="email"
                    class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>"
                    maxlength="190"
                    value="<?= e((string) ($form['email'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-phone">Phone</label>
                <input
                    id="estate-sale-customer-edit-phone"
                    name="phone"
                    class="form-control"
                    maxlength="40"
                    value="<?= e((string) ($form['phone'] ?? '')) ?>"
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-city">City</label>
                <input
                    id="estate-sale-customer-edit-city"
                    name="city"
                    class="form-control"
                    maxlength="120"
                    value="<?= e((string) ($form['city'] ?? '')) ?>"
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-state">State</label>
                <select id="estate-sale-customer-edit-state" name="state" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($stateOptions as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $selectedState === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="estate-sale-customer-edit-subscribes"
                        name="subscribes_to_future_sales"
                        value="1"
                        <?= $subscribesChecked ? 'checked' : '' ?>
                    />
                    <label class="form-check-label" for="estate-sale-customer-edit-subscribes">Subscriber to future sales</label>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-contact-method">Preferred contact for future sales</label>
                <select
                    id="estate-sale-customer-edit-contact-method"
                    name="future_sales_contact_method"
                    class="form-select <?= $hasError('future_sales_contact_method') ? 'is-invalid' : '' ?>"
                    <?= $subscribesChecked ? '' : 'disabled' ?>
                >
                    <option value="">Choose...</option>
                    <?php foreach ($contactMethodOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedContactMethod === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('future_sales_contact_method')): ?><div class="invalid-feedback d-block"><?= e($fieldError('future_sales_contact_method')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('estate-sale-customer-form');
    const alertEl = document.getElementById('estate-sale-customer-duplicate-alert');
    const subscribesEl = document.getElementById('estate-sale-customer-edit-subscribes');
    const contactMethodEl = document.getElementById('estate-sale-customer-edit-contact-method');
    if (!form) {
        return;
    }

    const syncContactMethodState = () => {
        if (!contactMethodEl || !subscribesEl) {
            return;
        }
        const enabled = subscribesEl.checked;
        contactMethodEl.disabled = !enabled;
        if (!enabled) {
            contactMethodEl.value = '';
        }
    };

    subscribesEl?.addEventListener('change', syncContactMethodState);
    syncContactMethodState();

    if (!alertEl || !form.dataset.checkUrl) {
        return;
    }

    const checkUrl = form.dataset.checkUrl;
    const estateSaleId = form.dataset.estateSaleId || '0';
    const excludeId = form.dataset.customerId || '0';
    const fieldNames = ['first_name', 'last_name', 'email', 'phone'];
    const inputs = {};
    fieldNames.forEach((name) => {
        const el = form.elements.namedItem(name);
        if (el && el instanceof HTMLElement) {
            inputs[name] = el;
        }
    });

    const reasonLabels = {
        name: 'Same first and last name',
        phone: 'Same phone number',
        email: 'Same email address',
    };

    const digitsOnly = (value) => String(value || '').replace(/\D/g, '');

    const shouldQueryDuplicates = () => {
        const fn = String(inputs.first_name?.value || '').trim();
        const ln = String(inputs.last_name?.value || '').trim();
        const phoneDigits = digitsOnly(inputs.phone?.value);
        const em = String(inputs.email?.value || '').trim();
        if (fn !== '' && ln !== '') {
            return true;
        }
        if (phoneDigits.length >= 7) {
            return true;
        }
        return em !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em);
    };

    const buildQuery = () => {
        const p = new URLSearchParams();
        fieldNames.forEach((name) => {
            const el = inputs[name];
            if (el && 'value' in el) {
                p.set(name, String(el.value || ''));
            }
        });
        if (excludeId !== '0') {
            p.set('exclude_id', excludeId);
        }
        if (estateSaleId !== '0') {
            p.set('estate_sale_id', estateSaleId);
        }
        return p.toString();
    };

    let debounceTimer = null;

    const renderAlert = (matches) => {
        alertEl.classList.add('d-none');
        alertEl.innerHTML = '';
        if (!matches || matches.length === 0) {
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Possible duplicate customer(s) already in the system:';
        alertEl.appendChild(title);

        const list = document.createElement('ul');
        list.className = 'mb-0 mt-2 ps-3';
        matches.forEach((match) => {
            const item = document.createElement('li');
            const reasons = (match.reasons || []).map((r) => reasonLabels[r] || r).join(', ');
            const saleNote = match.same_sale ? ' (already on this sale)' : (' · ' + (match.estate_sale_title || 'Estate sale'));
            item.textContent = `#${match.id} ${match.display_name}${saleNote}${reasons ? ' — ' + reasons : ''}`;
            list.appendChild(item);
        });
        alertEl.appendChild(list);
        alertEl.classList.remove('d-none');
    };

    const runDuplicateCheck = async () => {
        if (!shouldQueryDuplicates()) {
            renderAlert([]);
            return;
        }
        try {
            const res = await fetch(`${checkUrl}?${buildQuery()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            renderAlert(data.matches || []);
        } catch (_err) {
            // ignore
        }
    };

    const scheduleCheck = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            void runDuplicateCheck();
        }, 400);
    };

    fieldNames.forEach((name) => {
        const el = inputs[name];
        if (!el) {
            return;
        }
        el.addEventListener('input', scheduleCheck);
        el.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            void runDuplicateCheck();
        });
    });

    form.addEventListener('submit', async (e) => {
        if (form.dataset.duplicateBypass === '1') {
            form.dataset.duplicateBypass = '';
            return;
        }
        e.preventDefault();
        try {
            const res = await fetch(`${checkUrl}?${buildQuery()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                window.alert('Could not verify duplicates. Please try again.');
                return;
            }
            const data = await res.json();
            const matches = data.matches || [];
            if (matches.length === 0) {
                form.dataset.duplicateBypass = '1';
                form.requestSubmit();
                return;
            }
            const summary = matches
                .map((m) => {
                    const saleNote = m.same_sale ? ' (this sale)' : (' @ ' + (m.estate_sale_title || 'sale'));
                    return `#${m.id} ${m.display_name}${saleNote}`;
                })
                .join('; ');
            const ok = window.confirm(
                `Possible duplicate customer(s) already in the system:\n${summary}\n\nSave anyway?`
            );
            if (ok) {
                form.dataset.duplicateBypass = '1';
                form.requestSubmit();
            }
        } catch (_err) {
            window.alert('Could not verify duplicates. Please try again.');
        }
    });
})();
</script>
