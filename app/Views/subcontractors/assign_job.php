<?php
$subcontractor = is_array($subcontractor ?? null) ? $subcontractor : [];
$availableJobs = is_array($availableJobs ?? null) ? $availableJobs : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? '');
$subcontractorId = (int) ($subcontractor['id'] ?? 0);
$subName = trim((string) ($subcontractor['display_name'] ?? '')) ?: ('Sub #' . (string) $subcontractorId);
$selectedJobId = (int) ($form['job_id'] ?? 0);
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Add Job</h1>
        <p class="muted">Sub out work to <?= e($subName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/subs/' . (string) $subcontractorId . '?tab=jobs')) ?>">Back to Sub-Contractor</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-briefcase me-2"></i>Choose Job</strong>
    </div>
    <div class="card-body">
        <?php if ($availableJobs === []): ?>
            <div class="record-empty mb-3">No jobs are available to sub out. Every active job may already be assigned, or there are no jobs yet.</div>
            <a class="btn btn-primary" href="<?= e(url('/jobs/create')) ?>"><i class="fas fa-plus me-2"></i>Create Job</a>
        <?php else: ?>
            <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
                <?= csrf_field() ?>

                <div class="col-12 col-lg-8">
                    <label class="form-label fw-semibold" for="sub-assign-job">Job</label>
                    <select id="sub-assign-job" name="job_id" class="form-select <?= $hasError('job_id') ? 'is-invalid' : '' ?>">
                        <option value="">Choose a job...</option>
                        <?php foreach ($availableJobs as $jobRow): ?>
                            <?php if (!is_array($jobRow)) continue; ?>
                            <?php
                            $jobId = (int) ($jobRow['job_id'] ?? 0);
                            $jobTitle = trim((string) ($jobRow['job_title'] ?? '')) ?: ('Job #' . (string) $jobId);
                            $jobCity = trim((string) ($jobRow['job_city'] ?? ''));
                            $label = $jobCity !== '' ? $jobTitle . ' — ' . $jobCity : $jobTitle;
                            ?>
                            <option value="<?= (string) $jobId ?>" <?= $selectedJobId === $jobId ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('job_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('job_id')) ?></div><?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="sub-assign-notes">Notes / instructions</label>
                    <textarea id="sub-assign-notes" name="notes" class="form-control" rows="4" placeholder="What to tell the sub about this job..."><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-share-square me-2"></i>Assign Job</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/subs/' . (string) $subcontractorId . '?tab=jobs')) ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
