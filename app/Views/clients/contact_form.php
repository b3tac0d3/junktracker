<?php
$client = is_array($client ?? null) ? $client : [];
$clientId = (int) ($clientId ?? ($client['id'] ?? 0));
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/clients/' . (string) $clientId . '/contacts'));

$displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = trim((string) ($client['company_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'Client #' . (string) max(0, $clientId);
}

$primaryPhone = trim((string) ($client['phone'] ?? ''));
$secondaryPhone = trim((string) ($client['secondary_phone'] ?? ''));
$email = trim((string) ($client['email'] ?? ''));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$typeOptions = [
    'call' => 'Call',
    'text' => 'Text',
    'email' => 'Email',
    'in_person' => 'In Person',
    'other' => 'Other',
];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Add Contact</h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId)) ?>">Back to Client</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-phone-volume me-2"></i>Contact Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3 mb-3">
            <div class="record-field">
                <span class="record-label">Phone</span>
                <span class="record-value"><?= e(format_phone($primaryPhone)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Secondary Phone</span>
                <span class="record-value"><?= e(format_phone($secondaryPhone)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Email</span>
                <span class="record-value"><?= e($email !== '' ? $email : '—') ?></span>
            </div>
        </div>

        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="client-contact-contacted-at">Contacted At</label>
                <input
                    id="client-contact-contacted-at"
                    type="datetime-local"
                    step="3600"
                    name="contacted_at"
                    class="form-control <?= $hasError('contacted_at') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['contacted_at'] ?? '')) ?>"
                />
                <?php if ($hasError('contacted_at')): ?><div class="invalid-feedback d-block"><?= e($fieldError('contacted_at')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="client-contact-type">Type</label>
                <select id="client-contact-type" name="contact_type" class="form-select <?= $hasError('contact_type') ? 'is-invalid' : '' ?>">
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['contact_type'] ?? 'call')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('contact_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('contact_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="client-contact-note">Note</label>
                <textarea id="client-contact-note" name="note" rows="5" class="form-control"><?= e((string) ($form['note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save Contact</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

