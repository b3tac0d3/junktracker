<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Edit Time Entry</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/time-tracking') ?>">Time Tracking</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/time-tracking/' . ($entry['id'] ?? '')) ?>">Entry #<?= e((string) ($entry['id'] ?? '')) ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url($cancelUrl ?? ('/time-tracking/' . ($entry['id'] ?? ''))) ?>">Back</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pen me-1"></i>
            Time Details
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
