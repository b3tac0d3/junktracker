window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('taskQuickAddForm');
    const list = document.getElementById('taskQuickList');
    const titleInput = document.getElementById('taskQuickTitle');
    const searchInput = document.getElementById('taskQuickSearch');
    const statusSelect = document.getElementById('taskQuickStatus');
    const filterSearchInput = document.getElementById('tasksFilterQ');
    const presetSearchInput = document.getElementById('tasksPresetLoadQ');
    const alertBox = document.getElementById('taskQuickAlert');
    const addButton = document.getElementById('taskQuickAddBtn');
    const taskUrlTemplate = String(list?.dataset.taskUrlTemplate || '/tasks/__ID__');
    const toggleUrlTemplate = String(list?.dataset.toggleUrlTemplate || '/tasks/__ID__/toggle-complete');

    if (!form || !list || !titleInput || !searchInput || !statusSelect || !alertBox || !addButton) {
        return;
    }

    const csrfInput = form.querySelector('input[name="csrf_token"]');
    if (!csrfInput) {
        return;
    }

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    };

    const showAlert = (message, type = 'success') => {
        alertBox.className = 'alert';
        alertBox.classList.add(type === 'error' ? 'alert-danger' : 'alert-success');
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        window.setTimeout(() => {
            alertBox.classList.add('d-none');
        }, 3500);
    };

    const applyFilter = () => {
        const term = searchInput.value.trim().toLowerCase();
        const status = statusSelect.value;

        let visibleCount = 0;
        list.querySelectorAll('.task-quick-item').forEach((item) => {
            const itemTitle = (item.dataset.title || '').toLowerCase();
            const itemStatus = item.dataset.status || '';

            const matchesTerm = term === '' || itemTitle.includes(term);
            const matchesStatus = status === 'all' || status === 'overdue' || itemStatus === status;
            const visible = matchesTerm && matchesStatus;
            item.classList.toggle('d-none', !visible);
            if (visible) {
                visibleCount += 1;
            }
        });

        let emptyRow = list.querySelector('.task-quick-empty');
        if (visibleCount === 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('li');
                emptyRow.className = 'list-group-item text-muted task-quick-empty';
                emptyRow.textContent = list.dataset.emptyText || 'No tasks found.';
                list.appendChild(emptyRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }
    };

    const updateRowVisualState = (row, isCompleted, status) => {
        const title = row.querySelector('.task-quick-title');
        row.classList.toggle('is-complete', isCompleted);
        row.dataset.status = status;
        if (title) {
            title.classList.toggle('text-muted', isCompleted);
            title.classList.toggle('text-decoration-line-through', isCompleted);
        }

    };

    const buildTaskRow = (task) => {
        const id = Number(task.id || 0);
        const title = String(task.title || `Task #${id}`);
        const status = String(task.status || 'open');
        const isCompleted = !!task.is_completed || status === 'closed';
        const ownerName = String(task.assigned_user_name || 'Unassigned');
        const taskUrl = String(task.url || taskUrlTemplate.replace('__ID__', String(id)));
        const toggleUrl = toggleUrlTemplate.replace('__ID__', String(id));

        const row = document.createElement('li');
        row.className = `list-group-item task-quick-item${isCompleted ? ' is-complete' : ''}`;
        row.dataset.taskId = String(id);
        row.dataset.title = title.toLowerCase();
        row.dataset.status = status;
        row.innerHTML = `
            <div class="d-flex align-items-start gap-3">
                <input
                    class="form-check-input mt-1 task-quick-toggle"
                    type="checkbox"
                    ${isCompleted ? 'checked' : ''}
                    data-id="${id}"
                    data-toggle-url="${escapeHtml(toggleUrl)}"
                />
                <div class="min-w-0">
                    <a class="task-quick-title text-decoration-none${isCompleted ? ' text-muted text-decoration-line-through' : ''}" href="${escapeHtml(taskUrl)}">
                        ${escapeHtml(title)}
                    </a>
                    <div class="small text-muted mt-1">Owner: ${escapeHtml(ownerName)}</div>
                </div>
            </div>
        `;

        return row;
    };

    const onToggle = async (checkbox) => {
        const row = checkbox.closest('.task-quick-item');
        const url = checkbox.dataset.toggleUrl;
        if (!row || !url) {
            return;
        }

        checkbox.disabled = true;
        try {
            const body = new URLSearchParams();
            body.set('csrf_token', csrfInput.value);
            body.set('ajax', '1');
            body.set('is_completed', checkbox.checked ? '1' : '0');
            body.set('return_to', window.location.pathname);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Unable to update task.');
            }

            const status = String(payload.task?.status || (checkbox.checked ? 'closed' : 'open'));
            updateRowVisualState(row, checkbox.checked, status);
            applyFilter();
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            showAlert(error instanceof Error ? error.message : 'Unable to update task.', 'error');
        } finally {
            checkbox.disabled = false;
        }
    };

    list.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('task-quick-toggle')) {
            return;
        }
        void onToggle(target);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const title = titleInput.value.trim();
        if (title === '') {
            showAlert('Task title is required.', 'error');
            titleInput.focus();
            return;
        }

        addButton.disabled = true;
        const originalText = addButton.innerHTML;
        addButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving';

        try {
            const formData = new FormData(form);
            formData.set('title', title);
            formData.set('ajax', '1');

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Unable to save task.');
            }

            const task = payload.task || null;
            if (!task || !task.id) {
                throw new Error('Task saved, but response payload is invalid.');
            }

            const row = buildTaskRow(task);
            const emptyRow = list.querySelector('.task-quick-empty');
            if (emptyRow) {
                emptyRow.remove();
            }
            list.prepend(row);

            titleInput.value = '';
            titleInput.focus();
            showAlert(payload.message || 'Task added.');
            applyFilter();
        } catch (error) {
            showAlert(error instanceof Error ? error.message : 'Unable to save task.', 'error');
        } finally {
            addButton.disabled = false;
            addButton.innerHTML = originalText;
        }
    });

    searchInput.addEventListener('input', () => {
        const value = searchInput.value;
        if (filterSearchInput) {
            filterSearchInput.value = value;
        }
        if (presetSearchInput) {
            presetSearchInput.value = value;
        }
        applyFilter();
    });
    statusSelect.addEventListener('change', applyFilter);
    applyFilter();
});
