<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Edit Task</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/tasks') ?>">Tasks</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/tasks/' . ($task['id'] ?? '')) ?>">#<?= e((string) ($task['id'] ?? '')) ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/tasks/' . ($task['id'] ?? '')) ?>">Back to Task</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pen me-1"></i>
            Task Details
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
