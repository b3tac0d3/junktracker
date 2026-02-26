<?php
    $task = $task ?? [];
    $users = $users ?? [];
    $statusOptions = $statusOptions ?? ['open', 'in_progress', 'closed'];
    $linkTypes = $linkTypes ?? ['general'];
    $linkTypeLabels = $linkTypeLabels ?? [];
    $importanceOptions = $importanceOptions ?? [1, 2, 3, 4, 5];
    $isEdit = !empty($task['id']);
    $entryMode = (string) ($entryMode ?? '');

    $title = (string) old('title', $task['title'] ?? '');
    $body = (string) old('body', $task['body'] ?? '');
    $status = (string) old('status', $task['status'] ?? 'open');
    if (!in_array($status, $statusOptions, true)) {
        $status = 'open';
    }

    $importance = (string) old('importance', isset($task['importance']) ? (string) $task['importance'] : '3');
    $allowedImportance = array_map(static fn (int $i): string => (string) $i, $importanceOptions);
    if (!in_array($importance, $allowedImportance, true)) {
        $importance = '3';
    }

    $assignedUserId = (string) old('assigned_user_id', isset($task['assigned_user_id']) ? (string) $task['assigned_user_id'] : '');
    $currentUserId = (int) (auth_user_id() ?? 0);
    $assignedToOther = $assignedUserId !== '' && (int) $assignedUserId !== $currentUserId;
    $linkType = (string) old('link_type', $task['link_type'] ?? 'general');
    if (!in_array($linkType, $linkTypes, true)) {
        $linkType = 'general';
    }
    $linkId = (string) old('link_id', isset($task['link_id']) ? (string) $task['link_id'] : '');
    $linkSearch = (string) old('link_search', $task['link_label'] ?? '');
    $dueAt = (string) old('due_at', format_datetime_local($task['due_at'] ?? null));
    $completedAt = (string) old('completed_at', format_datetime_local($task['completed_at'] ?? null));
    $outcome = (string) old('outcome', $task['outcome'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/tasks/' . ($task['id'] ?? '') . '/edit' : '/tasks/new') ?>">
    <?= csrf_field() ?>
    <?php if (!$isEdit && $entryMode !== ''): ?>
        <input type="hidden" name="entry_mode" value="<?= e($entryMode) ?>" />
    <?php endif; ?>
    <input id="task_link_lookup_url" type="hidden" value="<?= e(url('/tasks/lookup/links')) ?>" />

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="title">Title</label>
            <input class="form-control" id="title" name="title" type="text" value="<?= e($title) ?>" required />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach ($statusOptions as $value): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                        <?= e(ucwords(str_replace('_', ' ', $value))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="importance">Priority</label>
            <select class="form-select" id="importance" name="importance">
                <?php foreach ($importanceOptions as $value): ?>
                    <?php $valueString = (string) $value; ?>
                    <option value="<?= e($valueString) ?>" <?= $importance === $valueString ? 'selected' : '' ?>>
                        <?= e($valueString) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="assigned_user_id">Assigned To</label>
            <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($users as $user): ?>
                    <?php $id = (string) ((int) ($user['id'] ?? 0)); ?>
                    <option value="<?= e($id) ?>" <?= $assignedUserId === $id ? 'selected' : '' ?>>
                        <?= e((string) ($user['name'] ?? ('User #' . $id))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <?php if ($assignedToOther): ?>
                    Assignment will be marked pending until that user accepts.
                <?php else: ?>
                    Assign to yourself for immediate acceptance.
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="link_type">Link Type</label>
            <select class="form-select" id="link_type" name="link_type">
                <?php foreach ($linkTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= $linkType === $type ? 'selected' : '' ?>>
                        <?= e((string) ($linkTypeLabels[$type] ?? ucwords(str_replace('_', ' ', $type)))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5 position-relative">
            <label class="form-label" for="link_search">Linked Record</label>
            <input id="link_id" name="link_id" type="hidden" value="<?= e($linkId) ?>" />
            <input
                class="form-control"
                id="link_search"
                name="link_search"
                type="text"
                autocomplete="off"
                value="<?= e($linkSearch) ?>"
                placeholder="Search selected type..."
                <?= $linkType === 'general' ? 'disabled' : '' ?>
            />
            <div id="task_link_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="due_at">Due At</label>
            <input class="form-control" id="due_at" name="due_at" type="datetime-local" value="<?= e($dueAt) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="completed_at">Completed At</label>
            <input class="form-control" id="completed_at" name="completed_at" type="datetime-local" value="<?= e($completedAt) ?>" />
        </div>

        <div class="col-12">
            <label class="form-label" for="body">Task Notes</label>
            <textarea class="form-control" id="body" name="body" rows="4"><?= e($body) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="outcome">Outcome</label>
            <textarea class="form-control" id="outcome" name="outcome" rows="3"><?= e($outcome) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Task' : 'Save Task' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/tasks/' . ($task['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
