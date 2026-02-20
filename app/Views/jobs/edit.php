<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Edit Job</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs') ?>">Jobs</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">#<?= e((string) ($job['id'] ?? '')) ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">Back to Job</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pen me-1"></i>
            Job Details
        </div>
        <div class="card-body">
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php require __DIR__ . '/_details_form.php'; ?>
        </div>
    </div>
</div>
