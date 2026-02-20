<?php
    $billing = $billing ?? [];
    $isEdit = !empty($billing['id']);
    $entryType = (string) old('entry_type', $billing['action_type'] ?? 'deposit');
    $entryDate = (string) old('entry_date', format_datetime_local($billing['action_at'] ?? null));
    $amount = (string) old('amount', isset($billing['amount']) ? (string) $billing['amount'] : '');

    $existingNote = (string) ($billing['note'] ?? '');
    $methodDefault = '';
    if (str_starts_with($existingNote, 'Method: ')) {
        $parts = explode('|', substr($existingNote, 8), 2);
        $methodDefault = trim((string) ($parts[0] ?? ''));
        $existingNote = trim((string) ($parts[1] ?? ''));
    }
    $method = (string) old('method', $methodDefault);
    $note = (string) old('note', $existingNote);
?>
<form method="post" action="<?= url($isEdit ? '/jobs/' . ($job['id'] ?? '') . '/billing/' . ($billing['id'] ?? '') . '/edit' : '/jobs/' . ($job['id'] ?? '') . '/billing/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="entry_type">Entry Type</label>
            <select class="form-select" id="entry_type" name="entry_type">
                <option value="deposit" <?= $entryType === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                <option value="bill_sent" <?= $entryType === 'bill_sent' ? 'selected' : '' ?>>Bill Sent</option>
                <option value="payment" <?= $entryType === 'payment' ? 'selected' : '' ?>>Payment</option>
                <option value="adjustment" <?= $entryType === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                <option value="other" <?= $entryType === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="entry_date">Date</label>
            <input class="form-control" id="entry_date" name="entry_date" type="datetime-local" value="<?= e($entryDate) ?>" required />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="amount">Amount</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" value="<?= e($amount) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="method">Method</label>
            <input class="form-control" id="method" name="method" type="text" placeholder="Check, Cash, Card, Venmo" value="<?= e($method) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Entry' : 'Save Entry' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">Cancel</a>
    </div>
</form>
