<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$labels = is_array($labels ?? null) ? $labels : [];
$routePrefix = trim((string) ($routePrefix ?? '/admin/bug-reports'));
$actionUrl = (string) ($actionUrl ?? url($routePrefix));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e((string) ($labels['create_title'] ?? 'Submit')) ?></h1>
        <p class="muted mb-0"><?= e((string) ($labels['create_desc'] ?? '')) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url($routePrefix)) ?>">Back</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-clipboard-list me-2"></i>Details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="submission-title">Title</label>
                <input
                    id="submission-title"
                    type="text"
                    name="title"
                    class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['title'] ?? '')) ?>"
                    maxlength="200"
                    required
                />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="submission-area">Area</label>
                <input
                    id="submission-area"
                    type="text"
                    name="area"
                    class="form-control"
                    value="<?= e((string) ($form['area'] ?? '')) ?>"
                    maxlength="80"
                    placeholder="jobs, billing, mobile..."
                />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="submission-notes"><?= e((string) ($labels['notes_label'] ?? 'Description')) ?></label>
                <textarea
                    id="submission-notes"
                    name="notes"
                    class="form-control <?= $hasError('notes') ? 'is-invalid' : '' ?>"
                    rows="8"
                    placeholder="<?= e((string) ($labels['notes_placeholder'] ?? '')) ?>"
                    required
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                <?php if ($hasError('notes')): ?><div class="invalid-feedback d-block"><?= e($fieldError('notes')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="submission-screenshot">Screenshot</label>
                <input id="submission-screenshot" type="file" name="screenshot" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" />
                <div class="form-text">Optional. PNG, JPG, GIF, or WebP up to 5 MB.</div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e((string) ($labels['create_button'] ?? 'Submit')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url($routePrefix)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
