window.addEventListener('DOMContentLoaded', () => {
    const taskTogglePattern = /\/tasks\/\d+\/toggle-complete(?:\?.*)?$/i;
    const jobPunchPattern = /\/jobs\/(\d+)\/time\/(punch-in|punch-out)(?:\?.*)?$/i;
    let noticeTimer = null;

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const showNotice = (message, type = 'success') => {
        if (!message) {
            return;
        }

        const container = document.querySelector('.container-fluid.px-4');
        if (!container) {
            return;
        }

        let notice = document.getElementById('ajaxActionNotice');
        if (!notice) {
            notice = document.createElement('div');
            notice.id = 'ajaxActionNotice';
            notice.className = 'alert';
            container.prepend(notice);
        }

        notice.className = `alert alert-${type}`;
        notice.textContent = message;

        if (noticeTimer) {
            clearTimeout(noticeTimer);
        }
        noticeTimer = window.setTimeout(() => {
            notice.remove();
        }, 3500);
    };

    const setSubmitting = (form, submitting) => {
        if (submitting) {
            form.dataset.ajaxPending = '1';
        } else {
            delete form.dataset.ajaxPending;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = submitting;
        }
    };

    const extractJobsBase = (action) => {
        const clean = String(action || '').split('?')[0];
        const match = clean.match(/^(.*)\/jobs\/\d+\/time\/punch-(?:in|out)$/i);
        if (match) {
            return match[1];
        }
        return '';
    };

    const ensureDashboardEmptyRow = (tbody) => {
        if (!tbody) {
            return;
        }
        const taskRows = tbody.querySelectorAll('tr');
        if (taskRows.length > 0) {
            return;
        }

        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="7" class="text-muted">No overdue or upcoming tasks.</td>';
        tbody.appendChild(row);
    };

    const updateTaskRow = (form, payload) => {
        const row = form.closest('tr');
        const cardItem = form.closest('.card-list-item');
        const cardList = cardItem ? cardItem.closest('.card-list-list') : null;
        const dashboardItem = form.closest('.dashboard-task-item');
        const dashboardList = dashboardItem ? dashboardItem.closest('.dashboard-task-list') : null;
        const buildEmptyCard = () => {
            if (!cardList) {
                return;
            }
            if (cardList.querySelector('.card-list-item')) {
                return;
            }

            const empty = document.createElement('div');
            empty.className = 'card-list-item card-list-item-empty text-muted';
            empty.textContent = 'No overdue or upcoming tasks.';
            cardList.appendChild(empty);
        };
        const buildEmptyDashboard = () => {
            if (!dashboardList) {
                return;
            }
            if (dashboardList.querySelector('.dashboard-task-item')) {
                return;
            }
            if (dashboardList.querySelector('.dashboard-task-empty')) {
                return;
            }

            const empty = document.createElement('li');
            empty.className = 'list-group-item text-muted dashboard-task-empty';
            empty.textContent = dashboardList.dataset.emptyText || 'No overdue or upcoming tasks.';
            dashboardList.appendChild(empty);
        };

        if (!row && !cardItem && !dashboardItem) {
            return;
        }

        const checkbox = form.querySelector('input[type="checkbox"][name="is_completed"]');
        const completedFromPayload = Boolean(payload?.task?.is_completed);
        const isCompleted = payload?.task?.is_completed === undefined
            ? Boolean(checkbox && checkbox.checked)
            : completedFromPayload;

        if (cardItem) {
            const title = cardItem.querySelector('.card-list-item-title');
            if (title) {
                title.classList.toggle('text-muted', isCompleted);
                title.classList.toggle('text-decoration-line-through', isCompleted);
            }

            if (isCompleted) {
                cardItem.remove();
                buildEmptyCard();
            }
            return;
        }

        if (dashboardItem) {
            const title = dashboardItem.querySelector('.js-task-title');
            if (title) {
                title.classList.toggle('text-muted', isCompleted);
                title.classList.toggle('text-decoration-line-through', isCompleted);
            }

            if (isCompleted) {
                dashboardItem.remove();
                buildEmptyDashboard();
            }
            return;
        }

        if (!row) {
            return;
        }
        const table = row.closest('table');
        const inTaskIndex = table && table.id === 'tasksTable';

        if (inTaskIndex) {
            const title = row.querySelector('.js-task-title');
            if (title) {
                title.classList.toggle('text-muted', isCompleted);
                title.classList.toggle('text-decoration-line-through', isCompleted);
            }

            const badge = row.querySelector('.js-task-status-badge');
            if (badge) {
                const status = String(payload?.task?.status || (isCompleted ? 'closed' : 'open'));
                const statusLabel = status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                badge.textContent = statusLabel;
                badge.className = 'badge js-task-status-badge';
                if (status === 'closed') {
                    badge.classList.add('bg-success');
                } else if (status === 'in_progress') {
                    badge.classList.add('bg-primary');
                } else {
                    badge.classList.add('bg-warning', 'text-dark');
                }
            }

            const params = new URLSearchParams(window.location.search);
            const statusFilter = params.get('status') || 'open';
            if (isCompleted && statusFilter !== 'all' && statusFilter !== 'closed') {
                row.remove();
            }
            return;
        }

        if (isCompleted) {
            row.remove();
            ensureDashboardEmptyRow(table ? table.querySelector('tbody') : null);
        }
    };

    const updateCrewRow = (form, payload) => {
        const row = form.closest('tr');
        if (!row) {
            window.location.reload();
            return;
        }

        const statusCell = row.querySelector('.js-crew-status');
        const elapsedCell = row.querySelector('.js-crew-elapsed');
        const actionsCell = row.querySelector('.js-crew-actions');
        const csrfToken = (form.querySelector('input[name="csrf_token"]') || {}).value || '';

        const jobId = Number(payload?.job_id || row.dataset.jobId || 0);
        const employeeId = Number(payload?.employee_id || row.dataset.employeeId || 0);
        const base = extractJobsBase(form.action);

        if (!statusCell || !elapsedCell || !actionsCell || !csrfToken || jobId <= 0 || employeeId <= 0) {
            window.location.reload();
            return;
        }

        if (payload?.action === 'punched_in') {
            const entryId = Number(payload?.entry_id || 0);
            if (entryId <= 0) {
                window.location.reload();
                return;
            }

            statusCell.innerHTML = `
                <span class="badge bg-success">On Clock</span>
                <span class="small text-muted ms-2">since ${escapeHtml(payload?.since_label || 'now')}</span>
            `;
            elapsedCell.textContent = String(payload?.elapsed_label || '0h 00m');
            actionsCell.innerHTML = `
                <form class="d-inline js-punch-geo-form" method="post" action="${escapeHtml(base)}/jobs/${jobId}/time/punch-out">
                    <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}" />
                    <input type="hidden" name="time_entry_id" value="${entryId}" />
                    <input type="hidden" name="geo_lat" value="" />
                    <input type="hidden" name="geo_lng" value="" />
                    <input type="hidden" name="geo_accuracy" value="" />
                    <input type="hidden" name="geo_captured_at" value="" />
                    <input type="hidden" name="geo_source" value="job_view_punch_out" />
                    <button class="btn btn-sm btn-danger" type="submit">
                        <i class="fas fa-stop-circle me-1"></i>
                        Punch Out
                    </button>
                </form>
            `;
            return;
        }

        if (payload?.action === 'punched_out') {
            statusCell.innerHTML = '<span class="badge bg-secondary">Punched Out</span>';
            elapsedCell.textContent = '—';
            actionsCell.innerHTML = `
                <form class="d-inline" method="post" action="${escapeHtml(base)}/jobs/${jobId}/crew/${employeeId}/remove">
                    <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}" />
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Remove from crew" aria-label="Remove from crew">
                        <i class="fas fa-user-minus"></i>
                    </button>
                </form>
                <form class="d-inline js-punch-geo-form" method="post" action="${escapeHtml(base)}/jobs/${jobId}/time/punch-in">
                    <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}" />
                    <input type="hidden" name="employee_id" value="${employeeId}" />
                    <input type="hidden" name="geo_lat" value="" />
                    <input type="hidden" name="geo_lng" value="" />
                    <input type="hidden" name="geo_accuracy" value="" />
                    <input type="hidden" name="geo_captured_at" value="" />
                    <input type="hidden" name="geo_source" value="job_view_punch_in" />
                    <button class="btn btn-sm btn-success" type="submit">
                        <i class="fas fa-play-circle me-1"></i>
                        Punch In
                    </button>
                </form>
            `;
            return;
        }

        window.location.reload();
    };

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.type !== 'checkbox' || target.name !== 'is_completed') {
            return;
        }

        const form = target.closest('form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const action = form.action || form.getAttribute('action') || '';
        if (!taskTogglePattern.test(action)) {
            return;
        }
        if (form.dataset.ajaxPending === '1') {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(submitEvent);
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if ((form.method || 'get').toLowerCase() !== 'post') {
            return;
        }

        const action = form.action || form.getAttribute('action') || '';
        const isTaskToggle = taskTogglePattern.test(action);
        const isJobPunch = jobPunchPattern.test(action);
        if (!isTaskToggle && !isJobPunch) {
            return;
        }
        if (form.dataset.ajaxPending === '1') {
            event.preventDefault();
            return;
        }
        if (!window.fetch || !window.FormData) {
            return;
        }

        event.preventDefault();
        setSubmitting(form, true);

        try {
            const response = await fetch(action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.toLowerCase().includes('application/json')) {
                window.location.href = response.url || window.location.href;
                return;
            }

            const payload = await response.json();
            if (!response.ok || !payload || payload.ok !== true) {
                const errorMessage = payload && payload.message ? payload.message : 'Action failed. Please try again.';
                throw new Error(errorMessage);
            }

            if (isTaskToggle) {
                updateTaskRow(form, payload);
            } else if (isJobPunch) {
                updateCrewRow(form, payload);
            }

            showNotice(payload.message || 'Saved.');
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Action failed. Please try again.';
            showNotice(message, 'danger');
        } finally {
            setSubmitting(form, false);
        }
    });
});
