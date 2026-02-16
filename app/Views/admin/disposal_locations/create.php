<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Add Disposal Location</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin/disposal-locations') ?>">Disposal Locations</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/admin/disposal-locations') ?>">Back to Locations</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus-circle me-1"></i>
            New Location
        </div>
        <div class="card-body">
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
