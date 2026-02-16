<?php
    $action = $action ?? [];
    $isEdit = !empty($action['id']);
    $actionType = (string) old('action_type', $action['action_type'] ?? 'job_created');
    $actionAt = (string) old('action_at', format_datetime_local($action['action_at'] ?? null));
    $amount = (string) old('amount', isset($action['amount']) ? (string) $action['amount'] : '');
    $refTable = (string) old('ref_table', $action['ref_table'] ?? '');
    $refId = (string) old('ref_id', isset($action['ref_id']) ? (string) $action['ref_id'] : '');
    $note = (string) old('note', $action['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/jobs/' . ($job['id'] ?? '') . '/actions/' . ($action['id'] ?? '') . '/edit' : '/jobs/' . ($job['id'] ?? '') . '/actions/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="action_type">Action Type</label>
            <input class="form-control" id="action_type" name="action_type" type="text" value="<?= e($actionType) ?>" placeholder="job_created, payment, update" required />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="action_at">Date/Time</label>
            <input class="form-control" id="action_at" name="action_at" type="datetime-local" value="<?= e($actionAt) ?>" required />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="amount">Amount</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" value="<?= e($amount) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="ref_table">Reference Table</label>
            <input class="form-control" id="ref_table" name="ref_table" type="text" value="<?= e($refTable) ?>" placeholder="expenses, job_payments" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="ref_id">Reference ID</label>
            <input class="form-control" id="ref_id" name="ref_id" type="number" value="<?= e($refId) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Note</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Action' : 'Save Action' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">Cancel</a>
    </div>
</form>
