<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/quotes'));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\Quote::statusOptions();
$clientOptions = is_array($clientOptions ?? null) ? $clientOptions : [];
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$selectedClientId = (int) ($form['client_id'] ?? 0);
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Quote' : 'Add Quote') ?></h1>
        <p class="muted">Simple quote intake that can convert into a job.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/quotes')) ?>">Back to Quotes</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Quote' : 'Create Quote') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="quote-title">Quote Name</label>
                <input id="quote-title" name="title" class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="quote-status">Status</label>
                <select id="quote-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <?php $statusValue = strtolower(trim((string) $statusOption)); ?>
                        <?php if ($statusValue === '') continue; ?>
                        <option value="<?= e($statusValue) ?>" <?= strtolower(trim((string) ($form['status'] ?? 'new'))) === $statusValue ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $statusValue))) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-client-id">Client</label>
                <select id="quote-client-id" name="client_id" class="form-select <?= $hasError('client_id') ? 'is-invalid' : '' ?>">
                    <option value="">Select client...</option>
                    <?php foreach ($clientOptions as $clientOption): ?>
                        <?php if (!is_array($clientOption)) continue; ?>
                        <?php $cid = (int) ($clientOption['id'] ?? 0); ?>
                        <?php if ($cid <= 0) continue; ?>
                        <option value="<?= e((string) $cid) ?>" <?= $selectedClientId === $cid ? 'selected' : '' ?>>
                            <?= e(trim((string) ($clientOption['name'] ?? ('Client #' . (string) $cid)))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('client_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-service-type">Service Type</label>
                <input id="quote-service-type" name="service_type" class="form-control" value="<?= e((string) ($form['service_type'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-amount">Quoted Amount</label>
                <input id="quote-amount" type="number" step="0.01" min="0" name="quoted_amount" class="form-control" value="<?= e((string) ($form['quoted_amount'] ?? '')) ?>" />
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-follow-up">Next Follow Up</label>
                <input id="quote-follow-up" type="datetime-local" name="next_follow_up_at" class="form-control <?= $hasError('next_follow_up_at') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['next_follow_up_at'] ?? '')) ?>" />
                <?php if ($hasError('next_follow_up_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('next_follow_up_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-source">Source</label>
                <input id="quote-source" name="source" class="form-control" value="<?= e((string) ($form['source'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="quote-priority">Priority</label>
                <input id="quote-priority" name="priority" class="form-control" value="<?= e((string) ($form['priority'] ?? '')) ?>" maxlength="80" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="quote-notes">Scope / Notes</label>
                <textarea id="quote-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-address-line1">Address Line 1</label>
                <input id="quote-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-address-line2">Address Line 2</label>
                <input id="quote-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="quote-city">City</label>
                <input id="quote-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="quote-state">State</label>
                <select id="quote-state" name="state" class="form-select">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-4">
                <label class="form-label fw-semibold" for="quote-postal">Postal Code</label>
                <input id="quote-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="quote-lost-reason">Lost Reason (if lost)</label>
                <input id="quote-lost-reason" name="lost_reason" class="form-control" value="<?= e((string) ($form['lost_reason'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Quote') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/quotes')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

