<?php
    $row = is_array($row ?? null) ? $row : [];
    $selectedGroup = (string) ($row['group_key'] ?? 'job_status');
    $rowId = (int) ($row['id'] ?? 0);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2 mobile-two-col-buttons">
        <div>
            <h1 class="mb-1">Edit Lookup Option</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin/lookups?group=' . urlencode($selectedGroup)) ?>">Lookups</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/admin/lookups?group=' . urlencode($selectedGroup)) ?>">Back to Lookups</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/lookups/' . $rowId . '/edit') ?>" class="card border-0 shadow-sm">
        <?= csrf_field() ?>
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
        <div class="card-footer d-flex gap-2 mobile-two-col-buttons">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-outline-secondary" href="<?= url('/admin/lookups?group=' . urlencode($selectedGroup)) ?>">Cancel</a>
        </div>
    </form>
</div>

