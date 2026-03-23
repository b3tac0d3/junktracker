<?php
$event = is_array($event ?? null) ? $event : [];
$id = (int) ($event['id'] ?? 0);
$title = trim((string) ($event['title'] ?? ''));
$type = strtolower(trim((string) ($event['type'] ?? 'appointment')));
$status = strtolower(trim((string) ($event['status'] ?? 'scheduled')));
$startAt = (string) ($event['start_at'] ?? '');
$endAt = (string) ($event['end_at'] ?? '');
$notes = (string) ($event['notes'] ?? '');
$allDay = (int) ($event['all_day'] ?? 0) === 1;
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($title !== '' ? $title : ('Event #' . (string) $id)) ?></h1>
        <p class="muted"><?= e(ucfirst($type)) ?> · <?= e($status === 'cancelled' ? 'Cancelled' : 'Scheduled') ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/events')) ?>">Back to Events</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-calendar-days me-2"></i>Event Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Type</span>
                <span class="record-value"><?= e(ucfirst($type)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Start</span>
                <span class="record-value"><?= e(format_datetime($startAt)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">End</span>
                <span class="record-value"><?= e($endAt !== '' ? format_datetime($endAt) : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">All Day</span>
                <span class="record-value"><?= e($allDay ? 'Yes' : 'No') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value"><?= e($status === 'cancelled' ? 'Cancelled' : 'Scheduled') ?></span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Notes</span>
                <span class="record-value"><?= e($notes !== '' ? $notes : '—') ?></span>
            </div>
        </div>
    </div>
</section>

