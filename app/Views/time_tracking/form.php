<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$employeeOptions = is_array($employeeOptions ?? null) ? $employeeOptions : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/time-tracking'));
$canManageEmployees = (bool) ($canManageEmployees ?? false);
$punchBoardJobs = is_array($punchBoardJobs ?? null) ? $punchBoardJobs : [];
$returnTo = (string) ($returnTo ?? '');
$backPath = $returnTo !== '' ? $returnTo : '/time-tracking';
$backLabel = $returnTo !== '' ? 'Back to Job' : 'Back to Time Tracking';

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$employeeMap = [];
$employeeAutosuggest = [];
foreach ($employeeOptions as $employee) {
    if (!is_array($employee)) {
        continue;
    }

    $id = (int) ($employee['id'] ?? 0);
    $name = trim((string) ($employee['name'] ?? ''));
    if ($id <= 0 || $name === '') {
        continue;
    }

    $meta = trim((string) ($employee['meta'] ?? ''));
    $employeeMap[$id] = $name;
    $employeeAutosuggest[] = [
        'id' => $id,
        'name' => $name,
        'meta' => $meta,
    ];
}

$selectedEmployeeId = (int) ($form['employee_id'] ?? 0);
$selectedEmployeeName = trim((string) ($form['employee_name'] ?? ''));
if ($selectedEmployeeName === '' && $selectedEmployeeId > 0 && isset($employeeMap[$selectedEmployeeId])) {
    $selectedEmployeeName = $employeeMap[$selectedEmployeeId];
}

$selectedJobId = (int) ($form['job_id'] ?? 0);
$selectedEstateSaleId = (int) ($form['estate_sale_id'] ?? 0);
$selectedEstateSaleTitle = trim((string) ($form['estate_sale_title'] ?? ''));
$jobSelRaw = trim((string) ($form['job_selection'] ?? ''));
if ($jobSelRaw !== 'shop_time' && $jobSelRaw !== 'general_labor' && $jobSelRaw !== '') {
    $parsedFromSelection = (int) $jobSelRaw;
    if ($parsedFromSelection > 0 && (string) $parsedFromSelection === $jobSelRaw) {
        $selectedJobId = $parsedFromSelection;
    }
}
$selectedJobTitle = trim((string) ($form['job_title'] ?? ''));
$jobSelectOptions = [
    ['value' => '', 'label' => 'Select active job or non-job type'],
    ['value' => 'shop_time', 'label' => 'Shop Time'],
    ['value' => 'general_labor', 'label' => 'General Labor'],
];
foreach ($punchBoardJobs as $jobOption) {
    if (!is_array($jobOption)) {
        continue;
    }
    $jobOptionId = (int) ($jobOption['id'] ?? 0);
    if ($jobOptionId <= 0) {
        continue;
    }
    $jobOptionTitle = trim((string) ($jobOption['title'] ?? ''));
    $jobOptionCity = trim((string) ($jobOption['city'] ?? ''));
    $label = $jobOptionTitle !== '' ? $jobOptionTitle : ('Job #' . (string) $jobOptionId);
    if ($jobOptionCity !== '') {
        $label .= ' - ' . $jobOptionCity;
    }
    $jobSelectOptions[] = [
        'value' => (string) $jobOptionId,
        'label' => $label,
    ];
}

$openPunch = (string) ($form['open_punch'] ?? '0') === '1';

$selectedJobSelection = $selectedJobId > 0 ? (string) $selectedJobId : $jobSelRaw;
$hasJobOption = false;
foreach ($jobSelectOptions as $jobOpt) {
    if ((string) ($jobOpt['value'] ?? '') === $selectedJobSelection) {
        $hasJobOption = true;
        break;
    }
}
if (!$hasJobOption && $selectedJobId > 0) {
    $fallbackLabel = $selectedJobTitle !== '' ? $selectedJobTitle : ('Job #' . (string) $selectedJobId);
    $jobSelectOptions[] = [
        'value' => (string) $selectedJobId,
        'label' => $fallbackLabel,
    ];
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Time Entry' : 'Add Time Entry') ?></h1>
        <p class="muted">Track manual entries or punch workflow</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url($backPath)) ?>"><?= e($backLabel) ?></a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Entry' : 'Create Entry') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3" id="time-entry-form">
            <?= csrf_field() ?>
            <?php if ($returnTo !== ''): ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
            <?php endif; ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="entry-employee-search">Employee</label>
                <?php if ($canManageEmployees): ?>
                    <div class="position-relative client-autosuggest-wrap">
                        <input
                            id="entry-employee-search"
                            name="employee_name"
                            class="form-control<?= $hasError('employee_id') ? ' is-invalid' : '' ?>"
                            value="<?= e($selectedEmployeeName) ?>"
                            autocomplete="off"
                            placeholder="Search employee by linked user, name, or email..."
                        />
                        <input type="hidden" id="entry-employee-id" name="employee_id" value="<?= e((string) $selectedEmployeeId) ?>" />
                        <div id="entry-employee-suggestions" class="client-suggestions d-none" role="listbox" aria-label="Employee suggestions"></div>
                    </div>
                    <div class="small muted mt-1">Linked user names are prioritized where available.</div>
                <?php else: ?>
                    <input id="entry-employee-search" class="form-control" value="<?= e($selectedEmployeeName) ?>" readonly />
                    <input type="hidden" id="entry-employee-id" name="employee_id" value="<?= e((string) $selectedEmployeeId) ?>" />
                    <input type="hidden" name="employee_name" value="<?= e($selectedEmployeeName) ?>" />
                    <div class="small muted mt-1">Time entries are limited to your linked employee profile.</div>
                <?php endif; ?>
                <?php if ($hasError('employee_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('employee_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="entry-job-selection">Job</label>
                <?php if ($selectedEstateSaleId > 0): ?>
                    <input type="hidden" name="estate_sale_id" value="<?= e((string) $selectedEstateSaleId) ?>" />
                    <input type="hidden" name="estate_sale_title" value="<?= e($selectedEstateSaleTitle) ?>" />
                    <input id="entry-estate-sale-title" class="form-control" value="<?= e($selectedEstateSaleTitle !== '' ? $selectedEstateSaleTitle : ('Estate Sale #' . (string) $selectedEstateSaleId)) ?>" readonly />
                    <div class="small muted mt-1">Time will be linked to this estate sale.</div>
                <?php else: ?>
                <select id="entry-job-selection" name="job_selection" class="form-select<?= $hasError('job_id') ? ' is-invalid' : '' ?>">
                    <?php foreach ($jobSelectOptions as $option): ?>
                        <option value="<?= e((string) ($option['value'] ?? '')) ?>"<?= (string) ($option['value'] ?? '') === $selectedJobSelection ? ' selected' : '' ?>><?= e((string) ($option['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="small muted mt-1">Choose an active job or one of the built-in non-job types.</div>
                <?php if ($hasError('job_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('job_id')) ?></div><?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="entry-clock-in">Clock In</label>
                <input id="entry-clock-in" type="datetime-local" name="clock_in_at" class="form-control <?= $hasError('clock_in_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['clock_in_at'] ?? '')) ?>" />
                <?php if ($hasError('clock_in_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('clock_in_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="entry-clock-out">Clock Out</label>
                <input id="entry-clock-out" type="datetime-local" name="clock_out_at" class="form-control <?= $hasError('clock_out_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['clock_out_at'] ?? '')) ?>" />
                <?php if ($hasError('clock_out_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('clock_out_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input
                        type="checkbox"
                        name="open_punch"
                        value="1"
                        id="entry-open-punch"
                        class="form-check-input"
                        <?= $openPunch ? 'checked' : '' ?>
                    />
                    <label class="form-check-label" for="entry-open-punch">Open punch (still clocked in — no clock out)</label>
                </div>
                <div class="form-text">Backdate clock-in only and leave the entry open. Uncheck to enter a clock-out; changing clock-in then updates clock-out to the same day as usual.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="entry-notes">Note</label>
                <textarea id="entry-notes" name="notes" rows="4" class="form-control"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Entry') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url($backPath)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const canManageEmployees = <?= $canManageEmployees ? 'true' : 'false' ?>;

    const employeeInput = document.getElementById('entry-employee-search');
    const employeeIdInput = document.getElementById('entry-employee-id');
    const employeeSuggestions = document.getElementById('entry-employee-suggestions');
    const employeeOptions = <?= json_encode($employeeAutosuggest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const clockInInput = document.getElementById('entry-clock-in');
    const clockOutInput = document.getElementById('entry-clock-out');
    const openPunchCheckbox = document.getElementById('entry-open-punch');

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

    /** Keep clock-out on the same calendar day as clock-in; default +1h if out was empty. */
    const syncClockOutWithClockIn = () => {
        if (!clockInInput || !clockOutInput) {
            return;
        }
        if (openPunchCheckbox && openPunchCheckbox.checked) {
            return;
        }
        const start = parseLocalDatetimeValue(clockInInput.value);
        if (!start) {
            return;
        }
        const endExisting = parseLocalDatetimeValue(clockOutInput.value);
        if (!endExisting) {
            clockOutInput.value = toLocalDatetimeValue(new Date(start.getTime() + 60 * 60 * 1000));
            return;
        }
        const merged = new Date(
            start.getFullYear(),
            start.getMonth(),
            start.getDate(),
            endExisting.getHours(),
            endExisting.getMinutes(),
            0,
            0
        );
        if (merged.getTime() <= start.getTime()) {
            merged.setTime(start.getTime() + 60 * 60 * 1000);
        }
        clockOutInput.value = toLocalDatetimeValue(merged);
    };

    if (clockInInput && String(clockInInput.value || '').trim() === '') {
        const now = new Date();
        clockInInput.value = toLocalDatetimeValue(now);
    }
    if (clockOutInput && clockInInput && String(clockOutInput.value || '').trim() === '') {
        if (!openPunchCheckbox || !openPunchCheckbox.checked) {
            syncClockOutWithClockIn();
        }
    }

    if (openPunchCheckbox && clockOutInput) {
        const syncOpenPunchDisabledState = () => {
            const open = openPunchCheckbox.checked;
            clockOutInput.disabled = open;
            if (open) {
                clockOutInput.value = '';
            }
        };
        syncOpenPunchDisabledState();
        openPunchCheckbox.addEventListener('change', () => {
            const open = openPunchCheckbox.checked;
            clockOutInput.disabled = open;
            if (open) {
                clockOutInput.value = '';
            } else if (clockInInput) {
                syncClockOutWithClockIn();
            }
        });
    }

    if (clockInInput) {
        clockInInput.addEventListener('change', syncClockOutWithClockIn);
    }

    const clearSuggestions = (el) => {
        if (!el) {
            return;
        }
        el.innerHTML = '';
        el.classList.add('d-none');
    };

    const exactMatchId = (options, value) => {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === '') {
            return '';
        }
        const match = options.find((item) => String(item.name || '').trim().toLowerCase() === normalized);
        return match ? String(Number(match.id || 0)) : '';
    };

    if (canManageEmployees && employeeInput && employeeIdInput && employeeSuggestions) {
        const renderEmployeeSuggestions = (items) => {
            employeeSuggestions.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                employeeSuggestions.classList.add('d-none');
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
                    employeeInput.value = name;
                    employeeIdInput.value = String(id);
                    clearSuggestions(employeeSuggestions);
                });
                employeeSuggestions.appendChild(row);
            });

            if (employeeSuggestions.children.length > 0) {
                employeeSuggestions.classList.remove('d-none');
            } else {
                employeeSuggestions.classList.add('d-none');
            }
        };

        const filterEmployeeSuggestions = () => {
            const query = String(employeeInput.value || '').trim().toLowerCase();
            const filtered = employeeOptions
                .filter((item) => {
                    const name = String(item.name || '').toLowerCase();
                    const meta = String(item.meta || '').toLowerCase();
                    return query === '' || name.includes(query) || meta.includes(query);
                })
                .slice(0, 8);
            renderEmployeeSuggestions(filtered);
        };

        employeeInput.addEventListener('input', () => {
            employeeIdInput.value = '';
            filterEmployeeSuggestions();
        });

        employeeInput.addEventListener('focus', filterEmployeeSuggestions);

        employeeInput.addEventListener('blur', () => {
            window.setTimeout(() => {
                employeeIdInput.value = exactMatchId(employeeOptions, employeeInput.value);
                if (employeeIdInput.value === '') {
                    employeeInput.value = '';
                }
                clearSuggestions(employeeSuggestions);
            }, 120);
        });

        document.addEventListener('click', (event) => {
            if (!employeeSuggestions.contains(event.target) && event.target !== employeeInput) {
                clearSuggestions(employeeSuggestions);
            }
        });
    }

    if (!jobInput || !jobIdInput || !jobSuggestions) {
        return;
    }

    let jobDebounce = null;
    let jobLastQuery = '';

    const renderJobSuggestions = (results) => {
        jobSuggestions.innerHTML = '';
        const rows = Array.isArray(results) ? results : [];

        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'client-suggestion-item';
            empty.innerHTML = '<span class="client-suggestion-name">No jobs found</span><span class="client-suggestion-meta">Try a different search.</span>';
            jobSuggestions.appendChild(empty);
            jobSuggestions.classList.remove('d-none');
            return;
        }

        rows.forEach((item) => {
            const id = Number(item.id || 0);
            const title = String(item.title || '').trim();
            if (!Number.isFinite(id) || id <= 0 || title === '') {
                return;
            }

            const city = String(item.city || '').trim();
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'client-suggestion-item';
            row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
            row.querySelector('.client-suggestion-name').textContent = title;
            row.querySelector('.client-suggestion-meta').textContent = city !== '' ? city : ('Job #' + String(id));
            row.addEventListener('click', () => {
                jobIdInput.value = String(id);
                jobInput.value = title;
                clearSuggestions(jobSuggestions);
            });
            jobSuggestions.appendChild(row);
        });

        if (jobSuggestions.children.length > 0) {
            jobSuggestions.classList.remove('d-none');
        } else {
            jobSuggestions.classList.add('d-none');
        }
    };

    const fetchJobs = (query) => {
        const endpoint = String(jobInput.dataset.searchUrl || '').trim();
        if (endpoint === '') {
            return;
        }

        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('q', query);

        fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (!payload || !payload.ok) {
                    renderJobSuggestions([]);
                    return;
                }
                renderJobSuggestions(payload.results || []);
            })
            .catch(() => {
                renderJobSuggestions([]);
            });
    };

    const searchJobs = () => {
        const query = String(jobInput.value || '').trim();
        if (query === '') {
            clearSuggestions(jobSuggestions);
            return;
        }

        jobLastQuery = query;
        if (jobDebounce) {
            clearTimeout(jobDebounce);
        }

        jobDebounce = setTimeout(() => {
            fetchJobs(jobLastQuery);
        }, 180);
    };

    jobInput.addEventListener('input', () => {
        jobIdInput.value = '';
        searchJobs();
    });

    jobInput.addEventListener('focus', searchJobs);

    jobInput.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (jobIdInput.value === '') {
                jobInput.value = '';
            }
            clearSuggestions(jobSuggestions);
        }, 120);
    });

    document.addEventListener('click', (event) => {
        if (!jobSuggestions.contains(event.target) && event.target !== jobInput) {
            clearSuggestions(jobSuggestions);
        }
    });
});
</script>
