<?php
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $usersBasePath = trim((string) ($usersBasePath ?? '/users'));
    if ($usersBasePath === '') {
        $usersBasePath = '/users';
    }
    $isGlobalDirectory = !empty($isGlobalDirectory);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Edit User</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <?php if ($isGlobalDirectory): ?>
                    <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= url($usersBasePath) ?>">Global Users</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="<?= url($usersBasePath) ?>">Users</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= e($name !== '' ? $name : ('#' . ($user['id'] ?? ''))) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url($usersBasePath . '/' . ($user['id'] ?? '')) ?>">Back to User</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-pen me-1"></i>
            Edit User
        </div>
        <div class="card-body">
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
</div>
