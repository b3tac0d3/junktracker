<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$followUpOptions = is_array($followUpOptions ?? null) ? $followUpOptions : [];
$actionUrl = (string) ($actionUrl ?? url('/clients/quick-add'));
$selectedFollowUps = is_array($form['follow_up_reminders'] ?? null) ? $form['follow_up_reminders'] : [];

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$contactTypeOptions = [
    'call' => 'Call',
    'text' => 'Text',
    'email' => 'Email',
    'in_person' => 'In Person',
    'other' => 'Other',
];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Quick Add Client</h1>
        <p class="muted mb-0">Capture a lead fast — name, phone, and note. A contact log entry is created automatically.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients/create')) ?>">Full client form</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients')) ?>">Client list</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-plus me-2"></i>Client details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="quick-add-name">Name</label>
                <input
                    id="quick-add-name"
                    type="text"
                    name="name"
                    class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['name'] ?? '')) ?>"
                    placeholder="First Last or company"
                    autocomplete="name"
                    required
                />
                <?php if ($hasError('name')): ?>
                    <div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="quick-add-phone">Phone</label>
                <input
                    id="quick-add-phone"
                    type="tel"
                    name="phone"
                    class="form-control"
                    value="<?= e((string) ($form['phone'] ?? '')) ?>"
                    placeholder="(555) 555-5555"
                    autocomplete="tel"
                />
                <div class="form-check mt-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="quick-add-can-text"
                        name="can_text"
                        value="1"
                        <?= ((string) ($form['can_text'] ?? '')) === '1' ? 'checked' : '' ?>
                    />
                    <label class="form-check-label" for="quick-add-can-text">Can text</label>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="quick-add-contact-type">Contact type</label>
                <select id="quick-add-contact-type" name="contact_type" class="form-select">
                    <?php foreach ($contactTypeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['contact_type'] ?? 'call')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Saved as the initial contact log entry for this client.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="quick-add-note">Note</label>
                <textarea
                    id="quick-add-note"
                    name="note"
                    rows="4"
                    class="form-control"
                    placeholder="What did they need? Any scheduling details?"
                ><?= e((string) ($form['note'] ?? '')) ?></textarea>
            </div>

            <?php if ($followUpOptions !== []): ?>
                <div class="col-12">
                    <fieldset class="border rounded p-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Follow-up reminders</legend>
                        <p class="small text-muted mb-3">Optional — checked items appear first on your dashboard until marked done.</p>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($followUpOptions as $value => $label): ?>
                                <?php $checked = in_array($value, $selectedFollowUps, true); ?>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="follow_up_reminders[]"
                                        id="quick-add-follow-<?= e($value) ?>"
                                        value="<?= e($value) ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                    />
                                    <label class="form-check-label" for="quick-add-follow-<?= e($value) ?>"><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
            <?php endif; ?>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i>Save client</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
