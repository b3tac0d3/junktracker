<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Add Business</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                <li class="breadcrumb-item active">Add Business</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Back to Site Admin</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-building me-1"></i>
            Business Profile Setup
        </div>
        <div class="card-body">
            <?php
                $action = url('/site-admin/businesses');
                $formValues = is_array($formValues ?? null) ? $formValues : [];
                $business = [];
                require __DIR__ . '/_form.php';
            ?>
        </div>
    </div>
</div>
