<?php
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$errors = is_array($errors ?? null) ? $errors : [];
$form = is_array($form ?? null) ? $form : [];
$assignedEmployees = is_array($assignedEmployees ?? null) ? $assignedEmployees : [];
$availableEmployees = is_array($availableEmployees ?? null) ? $availableEmployees : [];
$actionUrl = (string) ($actionUrl ?? '');
$searchUrl = (string) ($searchUrl ?? '');

$estateSaleId = (int) ($estateSale['id'] ?? 0);
$estateSaleTitle = trim((string) ($estateSale['title'] ?? ''));
if ($estateSaleTitle === '') {
    $estateSaleTitle = 'Estate Sale #' . (string) $estateSaleId;
}

$employeeName = trim((string) ($form['employee_name'] ?? ''));
$employeeId = (int) ($form['employee_id'] ?? 0);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Add Employee</h1>
        <p class="muted">Assign employees to <?= e($estateSaleTitle) ?> for quick punch in and out.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/estate-sales/' . (string) $estateSaleId . '?tab=labor')) ?>">Back to Estate Sale</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible mb-3">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-users me-2"></i>Add employees</strong>
        <?php if ($availableEmployees !== []): ?>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" id="estate-sale-emp-select-all">Select all</button>
                <button type="button" class="btn btn-outline-secondary" id="estate-sale-emp-clear-all">Clear</button>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($availableEmployees === []): ?>
            <div class="record-empty mb-0">Every active employee is already assigned to this estate sale, or there are no active employees.</div>
        <?php else: ?>
            <form method="post" action="<?= e($actionUrl) ?>" id="estate-sale-add-employees-bulk">
                <?= csrf_field() ?>
                <div class="border rounded p-2 mb-3" style="max-height: min(360px, 55vh); overflow-y: auto;">
                    <?php foreach ($availableEmployees as $row): ?>
                        <?php
                        if (!is_array($row)) {
                            continue;
                        }
                        $eid = (int) ($row['id'] ?? 0);
                        if ($eid <= 0) {
                            continue;
                        }
                        $label = trim((string) ($row['name'] ?? ''));
                        if ($label === '') {
                            $label = 'Employee #' . (string) $eid;
                        }
                        $metaParts = [];
                        $linkedUserName = trim((string) ($row['linked_user_name'] ?? ''));
                        $employeeNameRow = trim((string) ($row['employee_name'] ?? ''));
                        $linkedUserEmail = trim((string) ($row['linked_user_email'] ?? ''));
                        if ($linkedUserName !== '') {
                            $metaParts[] = 'Linked user: ' . $linkedUserName;
                        }
                        if ($employeeNameRow !== '' && strcasecmp($employeeNameRow, $label) !== 0) {
                            $metaParts[] = 'Employee: ' . $employeeNameRow;
                        }
                        if ($linkedUserEmail !== '') {
                            $metaParts[] = $linkedUserEmail;
                        }
                        $meta = implode(' · ', $metaParts);
                        $chkId = 'estate-sale-emp-add-' . (string) $eid;
                        ?>
                        <div class="form-check py-1 border-bottom">
                            <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (string) $eid ?>" id="<?= e($chkId) ?>" />
                            <label class="form-check-label w-100" for="<?= e($chkId) ?>">
                                <span class="fw-semibold"><?= e($label) ?></span>
                                <?php if ($meta !== ''): ?>
                                    <span class="d-block small text-muted"><?= e($meta) ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['employee_ids'])): ?><div class="text-danger small mb-2"><?= e((string) $errors['employee_ids']) ?></div><?php endif; ?>
                <button class="btn btn-primary" type="submit"><i class="fas fa-plus me-2"></i>Add selected employees</button>
            </form>
        <?php endif; ?>

        <hr class="my-4" />
        <p class="small text-muted mb-2">Or add one by search</p>
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-lg-8">
                <label class="form-label" for="estate-sale-employee-name">Employee</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input
                        id="estate-sale-employee-name"
                        type="text"
                        name="employee_name"
                        class="form-control<?= isset($errors['employee_id']) ? ' is-invalid' : '' ?>"
                        value="<?= e($employeeName) ?>"
                        placeholder="Search active employees by user, name, or email..."
                        autocomplete="off"
                        data-search-url="<?= e($searchUrl) ?>"
                    />
                    <input id="estate-sale-employee-id" type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                    <div id="estate-sale-employee-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Employee suggestions"></div>
                </div>
                <?php if (isset($errors['employee_id'])): ?><div class="invalid-feedback d-block"><?= e((string) $errors['employee_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end">
                <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus me-2"></i>Add employee</button>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-users me-2"></i>Assigned Employees</strong>
    </div>
    <div class="card-body">
        <?php if ($assignedEmployees === []): ?>
            <div class="record-empty mb-0">No employees assigned to this estate sale yet.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($assignedEmployees as $assigned): ?>
                    <?php
                    if (!is_array($assigned)) {
                        continue;
                    }
                    $name = trim((string) ($assigned['display_name'] ?? ''));
                    if ($name === '') {
                        $name = 'Employee #' . (string) ((int) ($assigned['employee_id'] ?? 0));
                    }
                    $linkedEmail = trim((string) ($assigned['linked_user_email'] ?? ''));
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-main mb-0">
                            <h3 class="record-title-simple mb-0"><?= e($name) ?></h3>
                            <?php if ($linkedEmail !== ''): ?>
                                <div class="record-subline small muted mt-1"><?= e($linkedEmail) ?></div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const selectAllBtn = document.getElementById('estate-sale-emp-select-all');
    const clearAllBtn = document.getElementById('estate-sale-emp-clear-all');
    const bulkForm = document.getElementById('estate-sale-add-employees-bulk');
    const setAllCheckboxes = (checked) => {
        if (!bulkForm) {
            return;
        }
        bulkForm.querySelectorAll('input[type="checkbox"][name="employee_ids[]"]').forEach((el) => {
            el.checked = checked;
        });
    };
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => setAllCheckboxes(true));
    }
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', () => setAllCheckboxes(false));
    }

    const input = document.getElementById('estate-sale-employee-name');
    const hidden = document.getElementById('estate-sale-employee-id');
    const suggestions = document.getElementById('estate-sale-employee-suggestions');
    if (!input || !hidden || !suggestions) {
        return;
    }

    const searchUrl = String(input.dataset.searchUrl || '').trim();
    if (searchUrl === '') {
        return;
    }

    let debounce = null;
    let lastQuery = '';

    const clearSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';
        const rows = Array.isArray(items) ? items : [];
        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'client-suggestion-item';
            empty.innerHTML = '<span class="client-suggestion-name">No available employees found</span><span class="client-suggestion-meta">All matching employees may already be assigned.</span>';
            suggestions.appendChild(empty);
            suggestions.classList.remove('d-none');
            return;
        }

        rows.forEach((item) => {
            const id = Number(item.id || 0);
            const name = String(item.name || '').trim();
            if (!Number.isFinite(id) || id <= 0 || name === '') {
                return;
            }

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = name;
            row.querySelector('.client-suggestion-meta').textContent = String(item.meta || '');
            row.addEventListener('click', () => {
                input.value = name;
                hidden.value = String(id);
                clearSuggestions();
            });
            suggestions.appendChild(row);
        });

        if (suggestions.children.length > 0) {
            suggestions.classList.remove('d-none');
        } else {
            suggestions.classList.add('d-none');
        }
    };

    const fetchSuggestions = (query) => {
        const endpoint = new URL(searchUrl, window.location.origin);
        endpoint.searchParams.set('q', query);

        fetch(endpoint.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (!payload || !payload.ok) {
                    renderSuggestions([]);
                    return;
                }
                renderSuggestions(payload.results || []);
            })
            .catch(() => renderSuggestions([]));
    };

    const search = () => {
        const query = String(input.value || '').trim();
        if (query === '') {
            clearSuggestions();
            return;
        }

        lastQuery = query;
        if (debounce) {
            clearTimeout(debounce);
        }

        debounce = setTimeout(() => {
            fetchSuggestions(lastQuery);
        }, 180);
    };

    input.addEventListener('input', () => {
        hidden.value = '';
        search();
    });

    input.addEventListener('focus', search);

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (hidden.value === '') {
                input.value = '';
            }
            clearSuggestions();
        }, 120);
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== input) {
            clearSuggestions();
        }
    });
});
</script>
