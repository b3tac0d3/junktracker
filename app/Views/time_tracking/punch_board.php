<?php
$punchEmployees = is_array($punchEmployees ?? null) ? $punchEmployees : [];
$canManageEmployees = (bool) ($canManageEmployees ?? false);
$recentEntries = is_array($recentEntries ?? null) ? $recentEntries : [];
$isPunchOnly = (bool) ($isPunchOnly ?? false);
$jobSearchUrl = e(url('/time-tracking/job-search'));

$employeeDisplayName = static function (array $row): string {
    $linked = trim((string) ($row['linked_user_name'] ?? ''));
    if ($linked !== '') {
        return $linked;
    }

    $employee = trim((string) ($row['employee_name'] ?? ''));
    if ($employee !== '') {
        return $employee;
    }

    return 'Employee #' . (string) ((int) ($row['id'] ?? 0));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($canManageEmployees ? 'Punch Board' : 'My Punch Clock') ?></h1>
        <p class="muted"><?= e($isPunchOnly ? 'Punch in, punch out, and review your recent time.' : 'Punch employees in or out with optional job selection.') ?></p>
    </div>
    <?php if (!$isPunchOnly): ?>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/time-tracking')) ?>"><i class="fas fa-arrow-left me-2"></i>Back to Time Tracking</a>
    </div>
    <?php endif; ?>
</div>

<section class="card index-card index-card-overflow-visible mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-clock me-2"></i><?= e($canManageEmployees ? 'Employee Punch Board' : 'My Punch Clock') ?></strong>
    </div>
    <div class="card-body">
        <?php if ($punchEmployees === []): ?>
            <div class="record-empty mb-0">No active employees available for punch tracking.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($punchEmployees as $employee): ?>
                    <?php
                    $employeeId = (int) ($employee['id'] ?? 0);
                    $openEntryId = (int) ($employee['open_entry_id'] ?? 0);
                    $isOpen = $openEntryId > 0;
                    $displayName = $employeeDisplayName($employee);
                    $employeeName = trim((string) ($employee['employee_name'] ?? ''));
                    $linkedUserName = trim((string) ($employee['linked_user_name'] ?? ''));
                    $linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
                    $openClockInAt = trim((string) ($employee['open_clock_in_at'] ?? ''));
                    $openJobTitle = trim((string) ($employee['open_job_title'] ?? ''));
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-main mb-2">
                            <h3 class="record-title-simple mb-0"><?= e($displayName) ?></h3>
                            <?php if ($linkedUserName !== '' && strcasecmp($linkedUserName, $employeeName) !== 0): ?>
                                <div class="record-subline small muted mt-1">
                                    <span>Employee: <?= e($employeeName !== '' ? $employeeName : ('#' . (string) $employeeId)) ?></span>
                                    <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isOpen): ?>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="record-row-fields record-row-fields-3 flex-grow-1">
                                    <div class="record-field">
                                        <span class="record-label">Status</span>
                                        <span class="record-value"><span class="badge text-bg-success">Punched In</span></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Job</span>
                                        <span class="record-value"><?= e($openJobTitle !== '' ? $openJobTitle : 'Non-Job Time') ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Clock In</span>
                                        <span class="record-value"><?= e(format_datetime($openClockInAt !== '' ? $openClockInAt : null)) ?></span>
                                    </div>
                                </div>
                                <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>" class="d-flex">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                    <button class="btn btn-outline-danger" type="submit"><i class="fas fa-stop me-2"></i>Punch Out</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/time-tracking/punch-in')) ?>" class="row g-2 align-items-end time-punch-in-form" data-search-url="<?= $jobSearchUrl ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                <input type="hidden" name="job_id" class="time-punch-job-id" value="" />

                                <div class="col-12 col-xl-6">
                                    <label class="form-label fw-semibold">Job</label>
                                    <div class="position-relative client-autosuggest-wrap">
                                        <input type="text" class="form-control time-punch-job-search" placeholder="Search job by title, id, or city... (leave blank for non-job)" autocomplete="off" />
                                        <div class="client-suggestions d-none time-punch-job-suggestions" role="listbox" aria-label="Punch job suggestions"></div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-4">
                                    <label class="form-label fw-semibold" for="time-punch-note-<?= e((string) $employeeId) ?>">Note</label>
                                    <input id="time-punch-note-<?= e((string) $employeeId) ?>" type="text" class="form-control" name="notes" placeholder="Optional" />
                                </div>

                                <div class="col-12 col-xl-2 d-grid">
                                    <button class="btn btn-success" type="submit"><i class="fas fa-play me-2"></i>Punch In</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($isPunchOnly): ?>
    <section class="card index-card">
        <div class="card-header index-card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-clock-rotate-left me-2"></i>Recent Time</strong>
            <span class="muted small">Last 25 entries</span>
        </div>
        <div class="card-body">
            <?php if ($recentEntries === []): ?>
                <div class="record-empty mb-0">No time entries recorded yet.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($recentEntries as $entry): ?>
                        <?php
                        $clockInAt = trim((string) ($entry['clock_in_at'] ?? ''));
                        $clockOutAt = trim((string) ($entry['clock_out_at'] ?? ''));
                        $durationMinutes = (int) ($entry['duration_minutes'] ?? 0);
                        $durationHours = $durationMinutes > 0 ? number_format($durationMinutes / 60, 2) . 'h' : 'Open';
                        ?>
                        <article class="record-row-simple">
                            <div class="record-row-main mb-2">
                                <h3 class="record-title-simple mb-0"><?= e(trim((string) ($entry['job_title'] ?? '')) !== '' ? (string) $entry['job_title'] : 'Non-Job Time') ?></h3>
                                <div class="record-subline small muted mt-1">
                                    <span>In <?= e(format_datetime($clockInAt !== '' ? $clockInAt : null)) ?></span>
                                    <span>· Out <?= e(format_datetime($clockOutAt !== '' ? $clockOutAt : null)) ?></span>
                                </div>
                            </div>
                            <div class="record-row-fields record-row-fields-2">
                                <div class="record-field">
                                    <span class="record-label">Hours</span>
                                    <span class="record-value"><?= e($durationHours) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($clockOutAt === '' ? 'Open' : 'Closed') ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const forms = Array.from(document.querySelectorAll('.time-punch-in-form'));
    if (forms.length === 0) {
        return;
    }

    forms.forEach((form) => {
        const searchUrl = String(form.dataset.searchUrl || '').trim();
        const jobSearchInput = form.querySelector('.time-punch-job-search');
        const jobIdInput = form.querySelector('.time-punch-job-id');
        const suggestions = form.querySelector('.time-punch-job-suggestions');

        if (!jobSearchInput || !jobIdInput || !suggestions) {
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
                empty.innerHTML = '<span class="client-suggestion-name">No jobs found</span><span class="client-suggestion-meta">Try a different search.</span>';
                suggestions.appendChild(empty);
                suggestions.classList.remove('d-none');
                return;
            }

            rows.forEach((item) => {
                const id = Number(item.id || 0);
                const title = String(item.title || '').trim();
                if (!Number.isFinite(id) || id <= 0 || title === '') {
                    return;
                }

                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'client-suggestion-item';
                row.innerHTML = '<span class="client-suggestion-name"></span><span class="client-suggestion-meta"></span>';
                row.querySelector('.client-suggestion-name').textContent = title;
                row.querySelector('.client-suggestion-meta').textContent = String(item.city || ('Job #' + String(id)));
                row.addEventListener('click', () => {
                    jobSearchInput.value = title;
                    jobIdInput.value = String(id);
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
            if (searchUrl === '') {
                return;
            }

            const url = new URL(searchUrl, window.location.origin);
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
                        renderSuggestions([]);
                        return;
                    }
                    renderSuggestions(payload.results || []);
                })
                .catch(() => renderSuggestions([]));
        };

        const search = () => {
            const query = String(jobSearchInput.value || '').trim();
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

        jobSearchInput.addEventListener('input', () => {
            jobIdInput.value = '';
            search();
        });

        jobSearchInput.addEventListener('focus', search);

        jobSearchInput.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (jobIdInput.value === '') {
                    jobSearchInput.value = '';
                }
                clearSuggestions();
            }, 120);
        });

        document.addEventListener('click', (event) => {
            if (!suggestions.contains(event.target) && event.target !== jobSearchInput) {
                clearSuggestions();
            }
        });
    });
});
</script>
