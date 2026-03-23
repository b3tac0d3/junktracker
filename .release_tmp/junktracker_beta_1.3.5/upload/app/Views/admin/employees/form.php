<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$userOptions = is_array($userOptions ?? null) ? $userOptions : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/admin/employees'));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$userAutosuggest = [];
$userNameMap = [];
foreach ($userOptions as $user) {
    if (!is_array($user)) {
        continue;
    }
    $id = (int) ($user['id'] ?? 0);
    $name = trim((string) ($user['name'] ?? ''));
    if ($id <= 0 || $name === '') {
        continue;
    }

    $email = trim((string) ($user['email'] ?? ''));
    $role = trim((string) ($user['role'] ?? ''));
    $meta = $email !== '' ? $email : ('User #' . (string) $id);
    if ($role !== '') {
        $meta .= ' · ' . str_replace('_', ' ', $role);
    }

    $userNameMap[$id] = $name;
    $userAutosuggest[] = [
        'id' => $id,
        'name' => $name,
        'meta' => $meta,
    ];
}

$linkedUserId = (int) ($form['user_id'] ?? 0);
$linkedUserName = trim((string) ($form['user_name'] ?? ''));
if ($linkedUserName === '' && $linkedUserId > 0 && isset($userNameMap[$linkedUserId])) {
    $linkedUserName = $userNameMap[$linkedUserId];
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Employee' : 'Add Employee') ?></h1>
        <p class="muted">Simple employee form</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/employees')) ?>">Back to Employees</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-pen me-2"></i><?= e($mode === 'edit' ? 'Update Employee' : 'Create Employee') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="employee-first-name">First Name</label>
                <input id="employee-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="employee-last-name">Last Name</label>
                <input id="employee-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="employee-suffix">Suffix</label>
                <input id="employee-suffix" name="suffix" class="form-control <?= $hasError('suffix') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['suffix'] ?? '')) ?>" maxlength="20" placeholder="Jr, Sr, III..." />
                <?php if ($hasError('suffix')): ?><div class="invalid-feedback d-block"><?= e($fieldError('suffix')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="employee-hourly-rate">Hourly Rate</label>
                <input id="employee-hourly-rate" name="hourly_rate" class="form-control <?= $hasError('hourly_rate') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['hourly_rate'] ?? '')) ?>" inputmode="decimal" placeholder="0.00" />
                <?php if ($hasError('hourly_rate')): ?><div class="invalid-feedback d-block"><?= e($fieldError('hourly_rate')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="employee-phone">Phone Number</label>
                <input id="employee-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="employee-email">Email</label>
                <input id="employee-email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="employee-user-search">Linked User (for punch access)</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input
                        id="employee-user-search"
                        name="user_name"
                        class="form-control<?= $hasError('user_id') ? ' is-invalid' : '' ?>"
                        value="<?= e($linkedUserName) ?>"
                        autocomplete="off"
                        placeholder="Search user by name or email..."
                    />
                    <input type="hidden" id="employee-user-id" name="user_id" value="<?= e((string) $linkedUserId) ?>">
                    <div id="employee-user-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Linked user suggestions"></div>
                </div>
                <div class="small muted mt-1">If linked, user profile data is treated as the primary identity in time tracking.</div>
                <?php if ($hasError('user_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('user_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="employee-note">Note</label>
                <textarea id="employee-note" name="note" rows="4" class="form-control"><?= e((string) ($form['note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Employee') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($employeeId) ? url('/admin/employees/' . (string) ((int) $employeeId)) : url('/admin/employees')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('employee-user-search');
    const hiddenId = document.getElementById('employee-user-id');
    const suggestions = document.getElementById('employee-user-suggestions');
    if (!input || !hiddenId || !suggestions) {
        return;
    }

    const options = <?= json_encode($userAutosuggest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const clearSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.add('d-none');
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            suggestions.classList.add('d-none');
            return;
        }

        items.forEach((item) => {
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
                hiddenId.value = String(id);
                input.value = name;
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

    const findExactMatch = (value) => {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === '') {
            return '';
        }

        const match = options.find((item) => String(item.name || '').trim().toLowerCase() === normalized);
        return match ? String(Number(match.id || 0)) : '';
    };

    const filterOptions = () => {
        const query = String(input.value || '').trim().toLowerCase();
        const items = options
            .filter((item) => {
                const name = String(item.name || '').toLowerCase();
                const meta = String(item.meta || '').toLowerCase();
                return query === '' || name.includes(query) || meta.includes(query);
            })
            .slice(0, 8);

        renderSuggestions(items);
    };

    input.addEventListener('input', () => {
        hiddenId.value = '';
        filterOptions();
    });

    input.addEventListener('focus', filterOptions);

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            hiddenId.value = findExactMatch(input.value);
            if (hiddenId.value === '') {
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
