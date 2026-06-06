<?php
$job = is_array($job ?? null) ? $job : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$subs = is_array($subs ?? null) ? $subs : [];
$actionUrl = (string) ($actionUrl ?? '');
$jobId = (int) ($job['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$selectedSubId = (int) ($form['subcontractor_id'] ?? 0);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Sub Out Job</h1>
        <p class="muted">Send <?= e($jobTitle) ?> to a sub-contractor.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Back to Job</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-share-square me-2"></i>Assign to Sub</strong>
    </div>
    <div class="card-body">
        <?php if ($subs === []): ?>
            <div class="record-empty mb-3">No active sub-contractors yet.</div>
            <a class="btn btn-primary" href="<?= e(url('/subs/create')) ?>"><i class="fas fa-plus me-2"></i>Add Sub-Contractor</a>
        <?php else: ?>
            <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
                <?= csrf_field() ?>

                <div class="col-12 col-lg-6">
                    <label class="form-label fw-semibold" for="sub-out-subcontractor">Sub-Contractor</label>
                    <select id="sub-out-subcontractor" name="subcontractor_id" class="form-select <?= $hasError('subcontractor_id') ? 'is-invalid' : '' ?>">
                        <option value="">Choose a sub...</option>
                        <?php foreach ($subs as $sub): ?>
                            <?php if (!is_array($sub)) continue; ?>
                            <?php $subId = (int) ($sub['id'] ?? 0); ?>
                            <?php $label = trim((string) ($sub['display_name'] ?? '')) ?: ('Sub #' . (string) $subId); ?>
                            <option value="<?= (string) $subId ?>" <?= $selectedSubId === $subId ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('subcontractor_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('subcontractor_id')) ?></div><?php endif; ?>
                    <div class="form-text"><a href="<?= e(url('/subs/create')) ?>">Add a new sub-contractor</a> if they are not listed.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="sub-out-notes">Notes / instructions</label>
                    <textarea id="sub-out-notes" name="notes" class="form-control" rows="4" placeholder="What to tell the sub about this job..."><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-share-square me-2"></i>Sub Out</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
