<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Edit Employee</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/employees') ?>">Employees</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/employees/' . ($employee['id'] ?? '')) ?>">#<?= e((string) ($employee['id'] ?? '')) ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/employees/' . ($employee['id'] ?? '')) ?>">Back to Employee</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pen me-1"></i>
            Employee Details
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
