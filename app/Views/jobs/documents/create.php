<?php
    $jobId = (int) ($job['id'] ?? 0);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Add Estimate/Invoice</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs') ?>">Jobs</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs/' . $jobId) ?>">Job #<?= e((string) $jobId) ?></a></li>
                <li class="breadcrumb-item active">Add Document</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . $jobId . '#estimate-invoice') ?>">Back to Job</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('/jobs/' . $jobId . '/documents/new') ?>">
        <?= csrf_field() ?>
        <?php require __DIR__ . '/_form.php'; ?>

        <div class="d-flex flex-wrap gap-2 mb-4 mobile-two-col-buttons">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-save me-1"></i>
                Save Document
            </button>
            <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . $jobId . '#estimate-invoice') ?>">Cancel</a>
        </div>
    </form>
</div>
