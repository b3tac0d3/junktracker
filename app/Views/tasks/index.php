<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? 'open')));
$tasks = is_array($tasks ?? null) ? $tasks : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($tasks), count($tasks));
$perPage = (int) ($pagination['per_page'] ?? 25);
$currentUserId = (int) (auth_user_id() ?? 0);
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : ['open', 'in_progress', 'closed'];
$statusOptions = ['' => 'All'];
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
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Tasks</h1>
        <p class="muted">Quick-add tasks, then click any task to add full details.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/tasks/create')) ?>"><i class="fas fa-plus me-2"></i>Add Task</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Open</span>
                <span id="task-summary-open" class="record-value"><?= e((string) ((int) ($summary['open'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">In Progress</span>
                <span id="task-summary-in-progress" class="record-value"><?= e((string) ((int) ($summary['in_progress'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Closed</span>
                <span id="task-summary-closed" class="record-value"><?= e((string) ((int) ($summary['closed'] ?? 0))) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/tasks')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="tasks-search">Search</label>
                <input id="tasks-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by task, owner, status, or id..." />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="tasks-status">Status</label>
                <select id="tasks-status" class="form-select" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/tasks')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-list-check me-2"></i>Task List</strong>
        <span id="task-record-count" class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($tasks)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <form id="task-quick-add-form" class="row g-2 align-items-center mb-3" action="<?= e(url('/tasks/quick-create')) ?>" method="post">
            <?= csrf_field() ?>
            <div class="col-12 col-lg-9">
                <input
                    id="task-quick-title"
                    name="title"
                    class="form-control"
                    placeholder="Add a task..."
                    maxlength="255"
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-lg-3 d-grid">
                <button class="btn btn-primary" type="submit"><i class="fas fa-plus me-2"></i>Add Task</button>
            </div>
            <div class="col-12">
                <div id="task-quick-add-error" class="small text-danger d-none"></div>
            </div>
        </form>

        <?php
        $basePath = '/tasks';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($tasks === []): ?>
            <div id="task-empty-state" class="record-empty">No tasks found for the current filter.</div>
        <?php else: ?>
            <div id="task-empty-state" class="record-empty d-none">No tasks found for the current filter.</div>
        <?php endif; ?>

        <div id="task-list-container" class="record-list-simple<?= $tasks === [] ? ' d-none' : '' ?>">
            <?php foreach ($tasks as $task): ?>
                <?php
                $taskId = (int) ($task['id'] ?? 0);
                $taskTitle = trim((string) ($task['title'] ?? '')) !== '' ? (string) $task['title'] : ('Task #' . (string) $taskId);
                $taskStatusRaw = strtolower(trim((string) ($task['status'] ?? 'open')));
                $taskStatus = str_replace('_', ' ', $taskStatusRaw);
                $isDone = $taskStatusRaw === 'closed';
                $ownerName = trim((string) ($task['owner_name'] ?? '')) ?: '—';
                $ownerUserId = (int) ($task['owner_user_id'] ?? 0);
                $dueText = format_datetime((string) ($task['due_at'] ?? null));
                $completedByName = trim((string) ($task['completed_by_name'] ?? ''));
                $completedAtText = format_datetime((string) ($task['completed_at'] ?? null));
                $statusActionTarget = $taskStatusRaw === 'in_progress' ? 'open' : 'in_progress';
                $statusActionLabel = $taskStatusRaw === 'in_progress' ? 'Change to Active' : 'Change to In Progress';
                ?>
                <article
                    class="record-row-simple task-row-item"
                    data-task-id="<?= e((string) $taskId) ?>"
                    data-owner-user-id="<?= e((string) $ownerUserId) ?>"
                    data-status="<?= e($taskStatusRaw) ?>"
                >
                    <div class="task-row-head">
                        <div class="task-row-check">
                            <input class="form-check-input task-done-checkbox" type="checkbox" value="1" data-task-id="<?= e((string) $taskId) ?>" <?= $isDone ? 'checked' : '' ?> aria-label="Mark task complete">
                        </div>
                        <a class="record-row-link flex-grow-1" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($taskTitle) ?></h3>
                                <div class="record-subline muted small">
                                    <span>#<?= e((string) $taskId) ?></span>
                                    <span>&middot;</span>
                                    <span>Status: <span class="task-status-text text-capitalize"><?= e($taskStatus) ?></span></span>
                                    <span>&middot;</span>
                                    <span>Owner: <span class="task-owner-name"><?= e($ownerName) ?></span></span>
                                    <span class="task-due-wrap<?= $dueText === '—' ? ' d-none' : '' ?>">
                                        <span>&middot;</span>
                                        <span>Due: <span class="task-due-text"><?= e($dueText) ?></span></span>
                                    </span>
                                </div>
                            </div>
                        </a>
                        <div class="dropdown task-row-menu">
                            <button
                                class="btn btn-link task-row-menu-toggle"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="Task actions"
                            >
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(url('/tasks/' . (string) $taskId . '/edit')) ?>">Edit</a></li>
                                <?php if ($currentUserId > 0): ?>
                                    <li>
                                        <button
                                            class="dropdown-item task-action-own<?= $ownerUserId === $currentUserId ? ' d-none' : '' ?>"
                                            type="button"
                                            data-task-id="<?= e((string) $taskId) ?>"
                                        >Take Ownership</button>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <button
                                        class="dropdown-item task-action-status"
                                        type="button"
                                        data-task-id="<?= e((string) $taskId) ?>"
                                        data-target-status="<?= e($statusActionTarget) ?>"
                                    ><?= e($statusActionLabel) ?></button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="task-complete-meta muted small ps-4 mt-1<?= $isDone ? '' : ' d-none' ?>">
                        Done<?= $completedByName !== '' ? ' by ' . e($completedByName) : '' ?><?= $completedAtText !== '—' ? ' on ' . e($completedAtText) : '' ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('task-quick-add-form');
    const titleInput = document.getElementById('task-quick-title');
    const errorBox = document.getElementById('task-quick-add-error');
    const listContainer = document.getElementById('task-list-container');
    const emptyState = document.getElementById('task-empty-state');
    const summaryOpen = document.getElementById('task-summary-open');
    const summaryInProgress = document.getElementById('task-summary-in-progress');
    const summaryClosed = document.getElementById('task-summary-closed');
    const recordCount = document.getElementById('task-record-count');
    const statusFilterSelect = document.getElementById('tasks-status');

    if (!form || !titleInput || !errorBox) {
        return;
    }

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const toStatusLabel = (status) => {
        return String(status || 'open').replace('_', ' ');
    };

    const statusActionFor = (status) => {
        const normalized = String(status || 'open').toLowerCase();
        if (normalized === 'in_progress') {
            return {
                target: 'open',
                label: 'Change to Active',
            };
        }
        return {
            target: 'in_progress',
            label: 'Change to In Progress',
        };
    };

    const currentStatusFilter = () => {
        return statusFilterSelect ? String(statusFilterSelect.value || 'open') : 'open';
    };

    const csrfToken = () => {
        const tokenInput = form.querySelector('input[name="csrf_token"]');
        return tokenInput instanceof HTMLInputElement ? tokenInput.value : '';
    };

    const updateSummary = (summary) => {
        if (!summary || typeof summary !== 'object') {
            return;
        }
        if (summaryOpen) {
            summaryOpen.textContent = String(summary.open ?? summaryOpen.textContent);
        }
        if (summaryInProgress) {
            summaryInProgress.textContent = String(summary.in_progress ?? summaryInProgress.textContent);
        }
        if (summaryClosed) {
            summaryClosed.textContent = String(summary.closed ?? summaryClosed.textContent);
        }
    };

    const updateRecordCount = (delta) => {
        if (!recordCount || !Number.isFinite(delta) || delta === 0) {
            return;
        }
        const current = parseInt(recordCount.textContent, 10) || 0;
        const next = Math.max(0, current + delta);
        recordCount.textContent = String(next) + ' record(s)';
    };

    const ensureVisibleListState = () => {
        if (!listContainer || !emptyState) {
            return;
        }
        const hasRows = listContainer.querySelector('.task-row-item') !== null;
        if (hasRows) {
            listContainer.classList.remove('d-none');
            emptyState.classList.add('d-none');
        } else {
            listContainer.classList.add('d-none');
            emptyState.classList.remove('d-none');
        }
    };

    const updateTaskActionLabel = (article, status) => {
        if (!article) {
            return;
        }
        const actionButton = article.querySelector('.task-action-status');
        if (!(actionButton instanceof HTMLButtonElement)) {
            return;
        }
        const nextAction = statusActionFor(status);
        actionButton.dataset.targetStatus = nextAction.target;
        actionButton.textContent = nextAction.label;
    };

    const updateTaskOwner = (article, ownerUserId, ownerName) => {
        if (!article) {
            return;
        }
        const ownerId = Number(ownerUserId || 0);
        article.dataset.ownerUserId = String(ownerId);

        const ownerNode = article.querySelector('.task-owner-name');
        if (ownerNode) {
            ownerNode.textContent = String(ownerName || '—').trim() || '—';
        }

        const ownButton = article.querySelector('.task-action-own');
        if (ownButton) {
            if (<?= e((string) $currentUserId) ?> > 0 && ownerId === <?= e((string) $currentUserId) ?>) {
                ownButton.classList.add('d-none');
            } else {
                ownButton.classList.remove('d-none');
            }
        }
    };

    const updateTaskDue = (article, dueDisplay) => {
        if (!article) {
            return;
        }
        const dueWrap = article.querySelector('.task-due-wrap');
        const dueTextNode = article.querySelector('.task-due-text');
        if (!dueWrap || !dueTextNode) {
            return;
        }
        const text = String(dueDisplay || '').trim();
        if (text === '' || text === '—') {
            dueWrap.classList.add('d-none');
            dueTextNode.textContent = '';
            return;
        }
        dueWrap.classList.remove('d-none');
        dueTextNode.textContent = text;
    };

    const applyCompletionMeta = (article, taskPayload) => {
        const meta = article ? article.querySelector('.task-complete-meta') : null;
        if (!meta) {
            return;
        }
        const statusKey = String((taskPayload && taskPayload.status) || 'open').toLowerCase();
        if (statusKey !== 'closed') {
            meta.classList.add('d-none');
            meta.textContent = '';
            return;
        }

        const by = String((taskPayload && taskPayload.completed_by_name) || '').trim();
        const at = String((taskPayload && taskPayload.completed_at_display) || '').trim();
        let text = 'Done';
        if (by !== '') {
            text += ' by ' + by;
        }
        if (at !== '' && at !== '—') {
            text += ' on ' + at;
        }
        meta.textContent = text;
        meta.classList.remove('d-none');
    };

    const applyTaskPayloadToRow = (article, taskPayload) => {
        if (!article || !taskPayload || typeof taskPayload !== 'object') {
            return;
        }
        const statusKey = String(taskPayload.status || 'open').toLowerCase();
        article.dataset.status = statusKey;

        const statusText = article.querySelector('.task-status-text');
        if (statusText) {
            statusText.textContent = toStatusLabel(statusKey);
        }

        const checkbox = article.querySelector('.task-done-checkbox');
        if (checkbox instanceof HTMLInputElement) {
            checkbox.checked = statusKey === 'closed';
        }

        if (Object.prototype.hasOwnProperty.call(taskPayload, 'owner_user_id')) {
            updateTaskOwner(article, Number(taskPayload.owner_user_id || 0), String(taskPayload.owner_name || '—'));
        }

        if (Object.prototype.hasOwnProperty.call(taskPayload, 'due_at_display')) {
            updateTaskDue(article, String(taskPayload.due_at_display || ''));
        } else if (Object.prototype.hasOwnProperty.call(taskPayload, 'due_at')) {
            updateTaskDue(article, String(taskPayload.due_at || ''));
        }

        updateTaskActionLabel(article, statusKey);
        applyCompletionMeta(article, taskPayload);
    };

    const buildTaskRow = (task) => {
        const id = Number(task.id || 0);
        const titleText = String(task.title || ('Task #' + id));
        const ownerName = String(task.owner_name || '—').trim() || '—';
        const ownerId = Number(task.owner_user_id || 0);
        const dueDisplay = String(task.due_at_display || task.due_at || '').trim();
        const href = String(task.url || '#');
        const statusKey = String(task.status || 'open').toLowerCase();
        const statusLabel = toStatusLabel(statusKey);
        const nextAction = statusActionFor(statusKey);
        const ownHidden = <?= e((string) $currentUserId) ?> > 0 && ownerId === <?= e((string) $currentUserId) ?>;

        const article = document.createElement('article');
        article.className = 'record-row-simple task-row-item';
        article.dataset.taskId = String(id);
        article.dataset.ownerUserId = String(ownerId);
        article.dataset.status = statusKey;
        article.innerHTML = `
            <div class="task-row-head">
                <div class="task-row-check">
                    <input class="form-check-input task-done-checkbox" type="checkbox" value="1" data-task-id="${escapeHtml(String(id))}" aria-label="Mark task complete">
                </div>
                <a class="record-row-link flex-grow-1" href="${escapeHtml(href)}">
                    <div class="record-row-main">
                        <h3 class="record-title-simple">${escapeHtml(titleText)}</h3>
                        <div class="record-subline muted small">
                            <span>#${escapeHtml(String(id))}</span>
                            <span>&middot;</span>
                            <span>Status: <span class="task-status-text text-capitalize">${escapeHtml(statusLabel)}</span></span>
                            <span>&middot;</span>
                            <span>Owner: <span class="task-owner-name">${escapeHtml(ownerName)}</span></span>
                            <span class="task-due-wrap${dueDisplay === '' ? ' d-none' : ''}">
                                <span>&middot;</span>
                                <span>Due: <span class="task-due-text">${escapeHtml(dueDisplay)}</span></span>
                            </span>
                        </div>
                    </div>
                </a>
                <div class="dropdown task-row-menu">
                    <button
                        class="btn btn-link task-row-menu-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Task actions"
                    >
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(url('/tasks')) ?>/${escapeHtml(String(id))}/edit">Edit</a></li>
                        <?php if ($currentUserId > 0): ?>
                            <li>
                                <button
                                    class="dropdown-item task-action-own${ownHidden ? ' d-none' : ''}"
                                    type="button"
                                    data-task-id="${escapeHtml(String(id))}"
                                >Take Ownership</button>
                            </li>
                        <?php endif; ?>
                        <li>
                            <button
                                class="dropdown-item task-action-status"
                                type="button"
                                data-task-id="${escapeHtml(String(id))}"
                                data-target-status="${escapeHtml(nextAction.target)}"
                            >${escapeHtml(nextAction.label)}</button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="task-complete-meta muted small ps-4 mt-1 d-none"></div>
        `;

        return article;
    };

    const handleTaskStatusAction = async (button) => {
        const taskId = Number(button.dataset.taskId || 0);
        const targetStatus = String(button.dataset.targetStatus || '').trim();
        if (!Number.isFinite(taskId) || taskId <= 0 || targetStatus === '') {
            return;
        }

        const token = csrfToken();
        if (token === '') {
            errorBox.textContent = 'Session token missing. Reload the page.';
            errorBox.classList.remove('d-none');
            return;
        }

        button.disabled = true;
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        try {
            const response = await fetch(`<?= e(url('/tasks')) ?>/${taskId}/quick-status`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({
                    csrf_token: token,
                    status: targetStatus,
                }).toString(),
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                errorBox.textContent = payload.error || 'Unable to update task status.';
                errorBox.classList.remove('d-none');
                return;
            }

            const article = button.closest('.task-row-item');
            const taskPayload = payload.task || {};
            applyTaskPayloadToRow(article, taskPayload);
            updateSummary(payload.summary || null);

            const statusKey = String(taskPayload.status || '').toLowerCase();
            const activeFilter = currentStatusFilter();
            const shouldKeepRow = activeFilter === '' || activeFilter === statusKey;
            if (!shouldKeepRow && article) {
                article.remove();
                updateRecordCount(-1);
            }
            ensureVisibleListState();
        } catch (error) {
            console.error(error);
            errorBox.textContent = 'Unable to update task status. Please try again.';
            errorBox.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    };

    const handleTaskOwnershipAction = async (button) => {
        const taskId = Number(button.dataset.taskId || 0);
        if (!Number.isFinite(taskId) || taskId <= 0) {
            return;
        }

        const token = csrfToken();
        if (token === '') {
            errorBox.textContent = 'Session token missing. Reload the page.';
            errorBox.classList.remove('d-none');
            return;
        }

        button.disabled = true;
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        try {
            const response = await fetch(`<?= e(url('/tasks')) ?>/${taskId}/quick-take-ownership`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({
                    csrf_token: token,
                }).toString(),
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                errorBox.textContent = payload.error || 'Unable to take ownership.';
                errorBox.classList.remove('d-none');
                return;
            }

            const article = button.closest('.task-row-item');
            const taskPayload = payload.task || {};
            updateTaskOwner(
                article,
                Number(taskPayload.owner_user_id || 0),
                String(taskPayload.owner_name || '—')
            );
        } catch (error) {
            console.error(error);
            errorBox.textContent = 'Unable to take ownership. Please try again.';
            errorBox.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const title = titleInput.value.trim();
        if (title === '') {
            errorBox.textContent = 'Task title is required.';
            errorBox.classList.remove('d-none');
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const data = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                errorBox.textContent = payload.error || 'Unable to create task.';
                errorBox.classList.remove('d-none');
                return;
            }

            const task = payload.task || {};
            const id = Number(task.id || 0);
            const statusKey = String(task.status || 'open').toLowerCase();
            const activeFilter = currentStatusFilter();

            if (listContainer && (activeFilter === '' || activeFilter === statusKey)) {
                const article = buildTaskRow(task);
                listContainer.prepend(article);
                updateRecordCount(1);
            }

            ensureVisibleListState();

            updateSummary(payload.summary || null);

            titleInput.value = '';
            titleInput.focus();
        } catch (error) {
            console.error(error);
            errorBox.textContent = 'Unable to create task. Please try again.';
            errorBox.classList.remove('d-none');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    if (listContainer) {
        listContainer.addEventListener('change', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || !target.classList.contains('task-done-checkbox')) {
                return;
            }

            const taskId = Number(target.dataset.taskId || 0);
            if (!Number.isFinite(taskId) || taskId <= 0) {
                return;
            }

            const isDone = target.checked;
            const token = csrfToken();
            if (token === '') {
                target.checked = !isDone;
                errorBox.textContent = 'Session token missing. Reload the page.';
                errorBox.classList.remove('d-none');
                return;
            }

            target.disabled = true;
            errorBox.classList.add('d-none');
            errorBox.textContent = '';

            try {
                const response = await fetch(`<?= e(url('/tasks')) ?>/${taskId}/quick-complete`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({
                        csrf_token: token,
                        done: isDone ? '1' : '0',
                    }).toString(),
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    target.checked = !isDone;
                    errorBox.textContent = payload.error || 'Unable to update task.';
                    errorBox.classList.remove('d-none');
                    return;
                }

                const article = target.closest('.task-row-item');
                const taskPayload = payload.task || {};
                const statusKey = String(taskPayload.status || (isDone ? 'closed' : 'open')).toLowerCase();
                applyTaskPayloadToRow(article, taskPayload);
                updateSummary(payload.summary || null);

                const activeFilter = currentStatusFilter();
                const shouldKeepRow =
                    activeFilter === '' ||
                    activeFilter === statusKey;

                if (!shouldKeepRow && article) {
                    article.remove();
                    updateRecordCount(-1);
                }

                ensureVisibleListState();
            } catch (error) {
                console.error(error);
                target.checked = !isDone;
                errorBox.textContent = 'Unable to update task. Please try again.';
                errorBox.classList.remove('d-none');
            } finally {
                target.disabled = false;
            }
        });

        listContainer.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const statusButton = target.closest('.task-action-status');
            if (statusButton instanceof HTMLButtonElement) {
                event.preventDefault();
                await handleTaskStatusAction(statusButton);
                return;
            }

            const ownButton = target.closest('.task-action-own');
            if (ownButton instanceof HTMLButtonElement) {
                event.preventDefault();
                await handleTaskOwnershipAction(ownButton);
            }
        });
    }
});
</script>
