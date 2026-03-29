<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/sales'));
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$feeDefaults = is_array($feeDefaults ?? null) ? $feeDefaults : [];

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Sale' : 'Add Sale') ?></h1>
        <p class="muted">Sale amount, inventory, and proceeds</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/sales')) ?>">Back to Sales</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-plus me-2"></i><?= e($mode === 'edit' ? 'Update Sale' : 'Create Sale') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sale-name">Name</label>
                <input
                    id="sale-name"
                    name="name"
                    class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['name'] ?? '')) ?>"
                    maxlength="190"
                    placeholder="Quick description"
                />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sale-gross">Gross</label>
                <input
                    id="sale-gross"
                    type="number"
                    step="0.01"
                    min="0"
                    name="gross_amount"
                    class="form-control <?= $hasError('gross_amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['gross_amount'] ?? '')) ?>"
                />
                <?php if ($hasError('gross_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('gross_amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sale-type">Type</label>
                <select id="sale-type" name="sale_type" class="form-select <?= $hasError('sale_type') ? 'is-invalid' : '' ?>">
                    <option value="">Choose type...</option>
                    <?php foreach ($typeOptions as $option): ?>
                        <option value="<?= e((string) $option) ?>" <?= ((string) ($form['sale_type'] ?? '')) === (string) $option ? 'selected' : '' ?>><?= e(ucfirst((string) $option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('sale_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="sale-date">Date</label>
                <input
                    id="sale-date"
                    type="date"
                    name="sale_date"
                    class="form-control <?= $hasError('sale_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['sale_date'] ?? '')) ?>"
                />
                <?php if ($hasError('sale_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <hr class="my-1">
                <div class="small text-muted text-uppercase fw-semibold mb-2">Fees (deducted from gross)</div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sale-fee-mode">Fee on this sale</label>
                <select id="sale-fee-mode" name="sale_fee_mode" class="form-select <?= $hasError('sale_fee_mode') ? 'is-invalid' : '' ?>">
                    <option value="default" <?= ((string) ($form['sale_fee_mode'] ?? 'default')) === 'default' ? 'selected' : '' ?>>Use default for sale type</option>
                    <option value="none" <?= ((string) ($form['sale_fee_mode'] ?? '')) === 'none' ? 'selected' : '' ?>>No fee</option>
                    <option value="percent" <?= ((string) ($form['sale_fee_mode'] ?? '')) === 'percent' ? 'selected' : '' ?>>Custom percentage</option>
                    <option value="amount" <?= ((string) ($form['sale_fee_mode'] ?? '')) === 'amount' ? 'selected' : '' ?>>Custom fixed amount</option>
                </select>
                <?php if ($hasError('sale_fee_mode')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_fee_mode')) ?></div><?php endif; ?>
                <div class="form-text">Use <a href="<?= e(url('/admin/sale-fee-defaults')) ?>">Sales default fees</a> to set defaults per type.</div>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="sale-fee-value">Fee value</label>
                <input
                    id="sale-fee-value"
                    type="number"
                    step="0.01"
                    min="0"
                    name="sale_fee_value"
                    class="form-control <?= $hasError('sale_fee_value') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['sale_fee_value'] ?? '')) ?>"
                    placeholder="% or $"
                />
                <?php if ($hasError('sale_fee_value')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sale_fee_value')) ?></div><?php endif; ?>
                <div class="form-text" id="sale-fee-value-hint">Enter a percentage or dollar amount when using a custom fee—not both.</div>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="sale-net-display">Net (after fee)</label>
                <input type="text" class="form-control" id="sale-net-display" readonly tabindex="-1" value="" />
                <div class="form-text">Calculated when you save (gross − fee).</div>
            </div>

            <div class="w-100"></div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="sale-client-search">Client (Optional)</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="sale-client-id" name="client_id" value="<?= e((string) ($form['client_id'] ?? '')) ?>" />
                    <input type="hidden" id="sale-client-name" name="client_name" value="<?= e((string) ($form['client_name'] ?? '')) ?>" />
                    <input
                        id="sale-client-search"
                        class="form-control <?= $hasError('client_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['client_name'] ?? '')) ?>"
                        placeholder="Search client by name, phone, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/sales/client-search')) ?>"
                    />
                    <div id="sale-client-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Client suggestions"></div>
                </div>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sale-job-search">Link to Job (Optional)</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="sale-job-id" name="job_id" value="<?= e((string) ($form['job_id'] ?? '')) ?>" />
                    <input type="hidden" id="sale-job-title" name="job_title" value="<?= e((string) ($form['job_title'] ?? '')) ?>" />
                    <input
                        id="sale-job-search"
                        class="form-control <?= $hasError('job_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['job_title'] ?? '')) ?>"
                        placeholder="Search job by title, id, city..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/sales/job-search')) ?>"
                    />
                    <div id="sale-job-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Job suggestions"></div>
                </div>
                <?php if ($hasError('job_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('job_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sale-purchase-search">Link to Purchase (Optional)</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input type="hidden" id="sale-purchase-id" name="purchase_id" value="<?= e((string) ($form['purchase_id'] ?? '')) ?>" />
                    <input type="hidden" id="sale-purchase-title" name="purchase_title" value="<?= e((string) ($form['purchase_title'] ?? '')) ?>" />
                    <input
                        id="sale-purchase-search"
                        class="form-control <?= $hasError('purchase_id') ? 'is-invalid' : '' ?>"
                        value="<?= e((string) ($form['purchase_title'] ?? '')) ?>"
                        placeholder="Search purchase by title, id, status..."
                        autocomplete="off"
                        data-search-url="<?= e(url('/sales/purchase-search')) ?>"
                    />
                    <div id="sale-purchase-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Purchase suggestions"></div>
                </div>
                <div class="form-text">You can link a sale to either a job or a purchase.</div>
                <?php if ($hasError('purchase_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('purchase_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="sale-notes">Note</label>
                <textarea
                    id="sale-notes"
                    name="notes"
                    class="form-control"
                    rows="3"
                    placeholder="Internal note (shown on sale view only)"
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Add Sale') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/sales')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const feeDefaults = <?= json_encode($feeDefaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    const grossInput = document.getElementById('sale-gross');
    const typeSelect = document.getElementById('sale-type');
    const feeMode = document.getElementById('sale-fee-mode');
    const feeValue = document.getElementById('sale-fee-value');
    const feeHint = document.getElementById('sale-fee-value-hint');
    const netDisplay = document.getElementById('sale-net-display');

    const parseMoney = (raw) => {
        const n = parseFloat(String(raw || '').replace(/,/g, ''));
        return Number.isFinite(n) ? n : 0;
    };

    const computeFee = (gross, saleType, mode, customRaw) => {
        const g = Math.max(0, parseMoney(gross));
        const m = String(mode || 'default').toLowerCase();
        const st = String(saleType || '').toLowerCase().trim();
        if (m === 'none') {
            return 0;
        }
        if (m === 'percent') {
            const p = Math.max(0, parseMoney(customRaw));
            return Math.round(g * (p / 100) * 100) / 100;
        }
        if (m === 'amount') {
            const a = Math.max(0, parseMoney(customRaw));
            return Math.round(Math.min(a, g) * 100) / 100;
        }
        if (m === 'default') {
            const d = feeDefaults && st ? feeDefaults[st] : null;
            if (!d || !d.fee_kind) {
                return 0;
            }
            if (d.fee_kind === 'percent') {
                const p = Math.max(0, Math.min(100, parseFloat(d.fee_value)));
                return Math.round(g * (p / 100) * 100) / 100;
            }
            const a = Math.max(0, parseFloat(d.fee_value));
            return Math.round(Math.min(a, g) * 100) / 100;
        }
        return 0;
    };

    const refreshNetPreview = () => {
        if (!grossInput || !feeMode || !netDisplay) {
            return;
        }
        const gross = parseMoney(grossInput.value);
        const st = typeSelect ? String(typeSelect.value || '').toLowerCase() : '';
        const mode = String(feeMode.value || 'default');
        const custom = feeValue ? String(feeValue.value || '') : '';
        const fee = computeFee(gross, st, mode, custom);
        const net = Math.max(0, Math.round((gross - fee) * 100) / 100);
        netDisplay.value = '$' + net.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (feeHint) {
            if (mode === 'percent') {
                feeHint.textContent = 'Enter a percentage of gross (e.g. 15 for 15%).';
            } else if (mode === 'amount') {
                feeHint.textContent = 'Enter a fixed dollar fee deducted from gross.';
            } else if (mode === 'default') {
                feeHint.textContent = 'Uses the current default for this sale type from Business Admin.';
            } else {
                feeHint.textContent = 'No fee deducted.';
            }
        }
        if (feeValue) {
            const customOn = mode === 'percent' || mode === 'amount';
            feeValue.disabled = !customOn;
        }
    };

    if (grossInput && feeMode && netDisplay) {
        grossInput.addEventListener('input', refreshNetPreview);
        if (typeSelect) {
            typeSelect.addEventListener('change', refreshNetPreview);
        }
        feeMode.addEventListener('change', () => {
            const m = String(feeMode.value || '');
            if (feeValue && (m === 'default' || m === 'none')) {
                feeValue.value = '';
            }
            refreshNetPreview();
        });
        if (feeValue) {
            feeValue.addEventListener('input', refreshNetPreview);
        }
        refreshNetPreview();
    }

    const initSuggest = ({
        input,
        hiddenId,
        hiddenLabel,
        suggestions,
        searchUrl,
        emptyMessage,
        metaFromItem,
        labelFromItem,
        onSelected,
    }) => {
        if (!input || !hiddenId || !hiddenLabel || !suggestions || !searchUrl) {
            return { clear: () => {}, containsTarget: () => false, hide: () => {} };
        }

        let debounce = null;

        const hide = () => {
            suggestions.innerHTML = '';
            suggestions.classList.add('d-none');
        };

        const clear = () => {
            hiddenId.value = '';
            hiddenLabel.value = '';
            input.value = '';
            hide();
        };

        const containsTarget = (target) => suggestions.contains(target) || target === input;

        const render = (items) => {
            suggestions.innerHTML = '';

            if (!Array.isArray(items) || items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'client-suggestion-item';
                empty.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
                empty.querySelector('.client-suggestion-name').textContent = emptyMessage;
                empty.querySelector('.client-suggestion-meta').textContent = '';
                empty.setAttribute('aria-disabled', 'true');
                suggestions.appendChild(empty);
                suggestions.classList.remove('d-none');
                return;
            }

            items.forEach((item) => {
                const id = Number(item && item.id ? item.id : 0);
                const label = String(labelFromItem(item) || '').trim();
                if (!id || label === '') {
                    return;
                }

                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'client-suggestion-item';
                row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
                row.querySelector('.client-suggestion-name').textContent = label;
                row.querySelector('.client-suggestion-meta').textContent = String(metaFromItem(item) || '').trim();
                row.addEventListener('click', () => {
                    hiddenId.value = String(id);
                    hiddenLabel.value = label;
                    input.value = label;
                    hide();
                    if (typeof onSelected === 'function') {
                        onSelected(id, label, item);
                    }
                });
                suggestions.appendChild(row);
            });

            if (suggestions.children.length > 0) {
                suggestions.classList.remove('d-none');
            } else {
                hide();
            }
        };

        const fetchResults = (query) => {
            if (query.length < 2) {
                hide();
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
                    render(Array.isArray(payload && payload.results) ? payload.results : []);
                })
                .catch(() => hide());
        };

        input.addEventListener('input', () => {
            const query = String(input.value || '').trim();
            hiddenId.value = '';
            hiddenLabel.value = query;
            if (debounce) {
                clearTimeout(debounce);
            }
            debounce = setTimeout(() => fetchResults(query), 160);
        });

        input.addEventListener('focus', () => {
            const query = String(input.value || '').trim();
            if (query.length >= 2) {
                fetchResults(query);
            }
        });

        return { clear, containsTarget, hide };
    };

    const clientField = initSuggest({
        input: document.getElementById('sale-client-search'),
        hiddenId: document.getElementById('sale-client-id'),
        hiddenLabel: document.getElementById('sale-client-name'),
        suggestions: document.getElementById('sale-client-suggestions'),
        searchUrl: String((document.getElementById('sale-client-search') || {}).dataset?.searchUrl || ''),
        emptyMessage: 'No clients found',
        labelFromItem: (item) => String(item && item.name ? item.name : ''),
        metaFromItem: (item) => {
            const phone = String(item && item.phone ? item.phone : '').trim();
            const city = String(item && item.city ? item.city : '').trim();
            return [phone, city].filter(Boolean).join(' · ');
        },
    });

    const purchaseField = initSuggest({
        input: document.getElementById('sale-purchase-search'),
        hiddenId: document.getElementById('sale-purchase-id'),
        hiddenLabel: document.getElementById('sale-purchase-title'),
        suggestions: document.getElementById('sale-purchase-suggestions'),
        searchUrl: String((document.getElementById('sale-purchase-search') || {}).dataset?.searchUrl || ''),
        emptyMessage: 'No purchases found',
        labelFromItem: (item) => String(item && item.title ? item.title : ''),
        metaFromItem: (item) => {
            const status = String(item && item.status ? item.status : '').trim();
            const client = String(item && item.client_name ? item.client_name : '').trim();
            return [status, client].filter(Boolean).join(' · ');
        },
    });

    const jobField = initSuggest({
        input: document.getElementById('sale-job-search'),
        hiddenId: document.getElementById('sale-job-id'),
        hiddenLabel: document.getElementById('sale-job-title'),
        suggestions: document.getElementById('sale-job-suggestions'),
        searchUrl: String((document.getElementById('sale-job-search') || {}).dataset?.searchUrl || ''),
        emptyMessage: 'No jobs found',
        labelFromItem: (item) => String(item && item.title ? item.title : ''),
        metaFromItem: (item) => String(item && item.city ? item.city : ''),
        onSelected: () => purchaseField.clear(),
    });

    const jobInput = document.getElementById('sale-job-search');
    if (jobInput) {
        jobInput.addEventListener('input', () => {
            const jobId = document.getElementById('sale-job-id');
            const query = String(jobInput.value || '').trim();
            if (jobId && jobId.value === '' && query !== '') {
                purchaseField.clear();
            }
        });
    }

    const purchaseInput = document.getElementById('sale-purchase-search');
    if (purchaseInput) {
        purchaseInput.addEventListener('input', () => {
            const purchaseId = document.getElementById('sale-purchase-id');
            const query = String(purchaseInput.value || '').trim();
            if (purchaseId && purchaseId.value === '' && query !== '') {
                jobField.clear();
            }
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!clientField.containsTarget(target)) {
            clientField.hide();
        }
        if (!jobField.containsTarget(target)) {
            jobField.hide();
        }
        if (!purchaseField.containsTarget(target)) {
            purchaseField.hide();
        }
    });
});
</script>
