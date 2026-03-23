<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$userOptions = is_array($userOptions ?? null) ? $userOptions : [];
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : ['open', 'in_progress', 'closed'];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/tasks'));

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
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'closed' => 'Closed',
    ];
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$ownerMap = [];
$ownerAutosuggestItems = [];
foreach ($userOptions as $user) {
    if (!is_array($user)) {
        continue;
    }
    $id = (int) ($user['id'] ?? 0);
    $name = trim((string) ($user['name'] ?? ''));
    if ($id > 0 && $name !== '') {
        $ownerMap[$id] = $name;
        $ownerAutosuggestItems[] = [
            'id' => $id,
            'name' => $name,
            'meta' => 'User #' . (string) $id,
        ];
    }
}
$ownerNameValue = trim((string) ($form['owner_user_name'] ?? ''));
if ($ownerNameValue === '') {
    $selectedOwnerId = (int) ($form['owner_user_id'] ?? 0);
    if ($selectedOwnerId > 0 && isset($ownerMap[$selectedOwnerId])) {
        $ownerNameValue = $ownerMap[$selectedOwnerId];
    }
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Task' : 'Add Task') ?></h1>
        <p class="muted">Simple task form</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/tasks')) ?>">Back to Tasks</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Task' : 'Create Task') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="link_type" value="<?= e((string) ($form['link_type'] ?? '')) ?>">
            <input type="hidden" name="link_id" value="<?= e((string) ($form['link_id'] ?? '')) ?>">

            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="task-title">Task Title</label>
                <input id="task-title" name="title" class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="255" />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="task-status">Status</label>
                <select id="task-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['status'] ?? 'open')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-4 col-lg-1">
                <label class="form-label fw-semibold" for="task-priority">Priority</label>
                <select id="task-priority" name="priority" class="form-select <?= $hasError('priority') ? 'is-invalid' : '' ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= e((string) $i) ?>" <?= ((string) ($form['priority'] ?? '3')) === (string) $i ? 'selected' : '' ?>><?= e((string) $i) ?></option>
                    <?php endfor; ?>
                </select>
                <?php if ($hasError('priority')): ?><div class="invalid-feedback d-block"><?= e($fieldError('priority')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="task-owner-autosuggest">Owner</label>
                <div class="position-relative client-autosuggest-wrap">
                    <input
                        id="task-owner-autosuggest"
                        name="owner_user_name"
                        class="form-control<?= $hasError('owner_user_id') ? ' is-invalid' : '' ?>"
                        value="<?= e($ownerNameValue) ?>"
                        autocomplete="off"
                        placeholder="Search user..."
                    />
                    <input type="hidden" id="task-owner-id" name="owner_user_id" value="<?= e((string) ($form['owner_user_id'] ?? '')) ?>">
                    <div id="task-owner-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Owner suggestions"></div>
                </div>
                <div class="small muted mt-1">Only active users in this business are shown.</div>
                <?php if ($hasError('owner_user_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('owner_user_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="task-due-at">Due Date</label>
                <input id="task-due-at" type="datetime-local" name="due_at" class="form-control <?= $hasError('due_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['due_at'] ?? '')) ?>" />
                <?php if ($hasError('due_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('due_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="task-body">Note</label>
                <textarea id="task-body" name="body" rows="4" class="form-control"><?= e((string) ($form['body'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Task') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($taskId) ? url('/tasks/' . (string) ((int) $taskId)) : url('/tasks')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const ownerInput = document.getElementById('task-owner-autosuggest');
    const ownerIdInput = document.getElementById('task-owner-id');
    const ownerSuggestions = document.getElementById('task-owner-suggestions');
    const dueAtInput = document.getElementById('task-due-at');
    if (!ownerInput || !ownerIdInput || !ownerSuggestions) {
        return;
    }

    const ownerOptions = <?= json_encode($ownerAutosuggestItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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

    const normalizeDatetimeInput = (input, fillIfEmpty) => {
        if (!input) {
            return;
        }
        const raw = (input.value || '').trim();
        let base = parseLocalDatetimeValue(raw);
        if (!base && fillIfEmpty) {
            base = new Date();
        }
        if (!base) {
            return;
        }
        input.value = toLocalDatetimeValue(roundToNearestHour(base));
    };

    if (dueAtInput) {
        normalizeDatetimeInput(dueAtInput, false);
        dueAtInput.addEventListener('focus', () => normalizeDatetimeInput(dueAtInput, true));
        dueAtInput.addEventListener('blur', () => normalizeDatetimeInput(dueAtInput, false));
    }

    const clearSuggestions = () => {
        ownerSuggestions.innerHTML = '';
        ownerSuggestions.classList.add('d-none');
    };

    const renderSuggestions = (items) => {
        ownerSuggestions.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            ownerSuggestions.classList.add('d-none');
            return;
        }

        items.forEach((item) => {
            const id = Number(item.id || 0);
            const name = String(item.name || '').trim();
            if (!Number.isFinite(id) || id <= 0 || name === '') {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'client-suggestion-item';
            button.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            button.querySelector('.client-suggestion-name').textContent = name;
            button.querySelector('.client-suggestion-meta').textContent = String(item.meta || '');
            button.addEventListener('click', () => {
                ownerInput.value = name;
                ownerIdInput.value = String(id);
                clearSuggestions();
            });
            ownerSuggestions.appendChild(button);
        });

        if (ownerSuggestions.children.length > 0) {
            ownerSuggestions.classList.remove('d-none');
        } else {
            ownerSuggestions.classList.add('d-none');
        }
    };

    const findMatchId = (inputValue) => {
        const value = String(inputValue || '').trim().toLowerCase();
        if (value === '') {
            return '';
        }
        const match = ownerOptions.find((item) => String(item.name || '').trim().toLowerCase() === value);
        return match ? String(Number(match.id || 0)) : '';
    };

    const filterSuggestions = () => {
        const query = String(ownerInput.value || '').trim().toLowerCase();
        const filtered = ownerOptions
            .filter((item) => {
                const name = String(item.name || '').toLowerCase();
                const meta = String(item.meta || '').toLowerCase();
                return query === '' || name.includes(query) || meta.includes(query);
            })
            .slice(0, 8);
        renderSuggestions(filtered);
    };

    ownerInput.addEventListener('input', () => {
        ownerIdInput.value = '';
        filterSuggestions();
    });
    ownerInput.addEventListener('focus', filterSuggestions);
    ownerInput.addEventListener('change', () => {
        ownerIdInput.value = findMatchId(ownerInput.value);
        if (ownerIdInput.value === '') {
            filterSuggestions();
        } else {
            clearSuggestions();
        }
    });
    ownerInput.addEventListener('blur', () => {
        window.setTimeout(() => {
            ownerIdInput.value = findMatchId(ownerInput.value);
            clearSuggestions();
        }, 120);
    });

    document.addEventListener('click', (event) => {
        if (!ownerSuggestions.contains(event.target) && event.target !== ownerInput) {
            clearSuggestions();
        }
    });
});
</script>
