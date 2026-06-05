<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/events/create'));
$cancelUrl = (string) ($cancelUrl ?? url('/events'));
$typeOptions = [
    'appointment' => 'Appointment',
    'personal' => 'Personal time (blocks appointments)',
    'reminder' => 'Reminder',
    'note' => 'Note',
    'other' => 'Other',
];
$selectedType = strtolower(trim((string) ($form['type'] ?? 'appointment')));
if (!array_key_exists($selectedType, $typeOptions)) {
    $typeOptions[$selectedType] = ucwords(str_replace('_', ' ', $selectedType));
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$toInputDatetime = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime(str_replace('T', ' ', $value));
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Event' : 'Add Event') ?></h1>
        <p class="muted"><?= $selectedType === 'personal' ? 'Block time on your calendar so appointments cannot be booked' : 'Calendar appointment or reminder' ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/events')) ?>">Back to Calendar</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-calendar-plus me-2"></i><?= e($mode === 'edit' ? 'Update Event' : 'Create Event') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="event-title">Title</label>
                <input id="event-title" name="title" class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="190" required />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="event-type">Type</label>
                <select id="event-type" name="type" class="form-select">
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold d-block" for="event-all-day">All day</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="event-all-day" name="all_day" value="1" <?= ((string) ($form['all_day'] ?? '0')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label" for="event-all-day">All day event</label>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="event-start">Start</label>
                <input id="event-start" type="datetime-local" name="start_at" class="form-control <?= $hasError('start_at') ? 'is-invalid' : '' ?>" value="<?= e($toInputDatetime((string) ($form['start_at'] ?? ''))) ?>" required />
                <?php if ($hasError('start_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('start_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="event-end">End (optional)</label>
                <input id="event-end" type="datetime-local" name="end_at" class="form-control" value="<?= e($toInputDatetime((string) ($form['end_at'] ?? ''))) ?>" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="event-notes">Notes</label>
                <textarea id="event-notes" name="notes" class="form-control" rows="3"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Event') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
