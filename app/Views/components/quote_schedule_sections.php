<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$entityLabel = trim((string) ($entityLabel ?? 'Quote'));
$scheduleType = strtolower(trim((string) ($form['schedule_type'] ?? 'none')));
$followUpTaskDueAt = (string) ($form['follow_up_task_due_at'] ?? '');
$followUpTaskTitle = (string) ($form['follow_up_task_title'] ?? '');
$meetingAt = (string) ($form['meeting_at'] ?? '');

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$sectionId = static fn (string $suffix): string => 'quote-schedule-' . preg_replace('/[^a-z0-9-]/', '', strtolower($entityLabel)) . '-' . $suffix;
?>

<div class="col-12">
    <div class="form-label fw-semibold mb-2">Scheduling</div>
    <p class="small text-muted mb-3">Choose one path — a soft follow-up on the task list, or a hard meeting on the calendar. Leave both unselected to keep the <?= e(strtolower($entityLabel)) ?> date open-ended.</p>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card index-card border h-100 quote-schedule-card<?= $scheduleType === 'follow_up_task' ? ' quote-schedule-card--active' : '' ?>">
                <div class="card-header index-card-header py-2 d-flex align-items-center gap-2">
                    <input
                        class="form-check-input mt-0"
                        type="radio"
                        name="schedule_type"
                        id="<?= e($sectionId('follow-up')) ?>"
                        value="follow_up_task"
                        <?= $scheduleType === 'follow_up_task' ? 'checked' : '' ?>
                    />
                    <label class="form-check-label fw-semibold mb-0" for="<?= e($sectionId('follow-up')) ?>">
                        <i class="fas fa-list-check me-1"></i>Follow-Up Task
                    </label>
                </div>
                <div class="card-body quote-schedule-panel" data-schedule-panel="follow_up_task">
                    <p class="small text-muted">Adds an item to the task list. The <?= e(strtolower($entityLabel)) ?> itself stays open-ended — nothing is placed on the calendar from this record.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="<?= e($sectionId('task-due')) ?>">Task Due Date &amp; Time</label>
                            <input
                                id="<?= e($sectionId('task-due')) ?>"
                                type="datetime-local"
                                name="follow_up_task_due_at"
                                class="form-control <?= $hasError('follow_up_task_due_at') ? 'is-invalid' : '' ?>"
                                value="<?= e($followUpTaskDueAt) ?>"
                            />
                            <?php if ($hasError('follow_up_task_due_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('follow_up_task_due_at')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="<?= e($sectionId('task-title')) ?>">Task Title (optional)</label>
                            <input
                                id="<?= e($sectionId('task-title')) ?>"
                                type="text"
                                name="follow_up_task_title"
                                class="form-control"
                                value="<?= e($followUpTaskTitle) ?>"
                                placeholder="Default: <?= e($entityLabel) ?> Follow-Up"
                                maxlength="190"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card index-card border h-100 quote-schedule-card<?= $scheduleType === 'meeting' ? ' quote-schedule-card--active' : '' ?>">
                <div class="card-header index-card-header py-2 d-flex align-items-center gap-2">
                    <input
                        class="form-check-input mt-0"
                        type="radio"
                        name="schedule_type"
                        id="<?= e($sectionId('meeting')) ?>"
                        value="meeting"
                        <?= $scheduleType === 'meeting' ? 'checked' : '' ?>
                    />
                    <label class="form-check-label fw-semibold mb-0" for="<?= e($sectionId('meeting')) ?>">
                        <i class="fas fa-calendar-day me-1"></i>Meeting
                    </label>
                </div>
                <div class="card-body quote-schedule-panel" data-schedule-panel="meeting">
                    <p class="small text-muted">Schedules a hard date on the calendar for this <?= e(strtolower($entityLabel)) ?>. No follow-up task is created.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="<?= e($sectionId('meeting-at')) ?>">Meeting Date &amp; Time</label>
                            <input
                                id="<?= e($sectionId('meeting-at')) ?>"
                                type="datetime-local"
                                name="meeting_at"
                                class="form-control <?= $hasError('meeting_at') ? 'is-invalid' : '' ?>"
                                value="<?= e($meetingAt) ?>"
                            />
                            <?php if ($hasError('meeting_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('meeting_at')) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-check mt-3">
        <input
            class="form-check-input"
            type="radio"
            name="schedule_type"
            id="<?= e($sectionId('none')) ?>"
            value="none"
            <?= $scheduleType === 'none' ? 'checked' : '' ?>
        />
        <label class="form-check-label" for="<?= e($sectionId('none')) ?>">No scheduling yet — keep open-ended</label>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const radios = Array.from(document.querySelectorAll('input[name="schedule_type"]'));
    const cards = Array.from(document.querySelectorAll('.quote-schedule-card'));
    const panels = Array.from(document.querySelectorAll('.quote-schedule-panel'));
    if (radios.length === 0) {
        return;
    }

    const selectedType = () => {
        const checked = radios.find((radio) => radio.checked);
        return checked ? String(checked.value || 'none') : 'none';
    };

    const syncPanels = () => {
        const type = selectedType();
        cards.forEach((card) => {
            const panel = card.querySelector('.quote-schedule-panel');
            const panelType = panel ? String(panel.dataset.schedulePanel || '') : '';
            const active = panelType !== '' && panelType === type;
            card.classList.toggle('quote-schedule-card--active', active);
        });

        panels.forEach((panel) => {
            const panelType = String(panel.dataset.schedulePanel || '');
            const enabled = panelType === type;
            panel.classList.toggle('opacity-50', !enabled);
            panel.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !enabled;
            });
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', syncPanels));
    syncPanels();
});
</script>
