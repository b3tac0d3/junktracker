<div class="container-fluid px-4">
    <div class="mt-4 mb-3">
        <h1 class="mb-1">Edit Consignor</h1>
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/consignors') ?>">Consignors</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0))) ?>">#<?= e((string) ($consignor['id'] ?? '')) ?></a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-pen me-1"></i>
            Update Consignor
        </div>
        <div class="card-body">
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
