<div class="container-fluid px-4">
    <div class="mt-4 mb-3">
        <h1 class="mb-1">Add Consignor</h1>
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/consignors') ?>">Consignors</a></li>
            <li class="breadcrumb-item active">Add</li>
        </ol>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-handshake me-1"></i>
            Consignor Details
        </div>
        <div class="card-body">
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
