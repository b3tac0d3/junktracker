<?php
    $disposal = $disposal ?? [];
    $locations = $locations ?? [];
    $isEdit = !empty($disposal['id']);

    $eventDate = (string) old('event_date', $disposal['event_date'] ?? '');
    $typeValue = (string) old('type', $disposal['type'] ?? 'dump');
    $amount = (string) old('amount', isset($disposal['amount']) ? (string) $disposal['amount'] : '');
    $note = (string) old('note', $disposal['note'] ?? '');
    $locationValue = (string) old('disposal_location_id', isset($disposal['disposal_location_id']) ? (string) $disposal['disposal_location_id'] : '');
?>
<form method="post" action="<?= url($isEdit ? '/jobs/' . ($job['id'] ?? '') . '/disposals/' . ($disposal['id'] ?? '') . '/edit' : '/jobs/' . ($job['id'] ?? '') . '/disposals/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="event_date">Event Date</label>
            <input class="form-control" id="event_date" name="event_date" type="date" value="<?= e($eventDate) ?>" required />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="disposal_location_id">Location</label>
            <select class="form-select" id="disposal_location_id" name="disposal_location_id" required>
                <option value="">Select location</option>
                <?php foreach ($locations as $location): ?>
                    <?php $locId = (string) ($location['id'] ?? ''); ?>
                    <option value="<?= e($locId) ?>" <?= $locationValue === $locId ? 'selected' : '' ?>><?= e((string) ($location['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="dump" <?= $typeValue === 'dump' ? 'selected' : '' ?>>Dump</option>
                <option value="transfer_station" <?= $typeValue === 'transfer_station' ? 'selected' : '' ?>>Transfer Station</option>
                <option value="landfill" <?= $typeValue === 'landfill' ? 'selected' : '' ?>>Landfill</option>
                <option value="other" <?= $typeValue === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="amount">Amount</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" value="<?= e($amount) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Note</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Disposal' : 'Save Disposal' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">Cancel</a>
    </div>
</form>
