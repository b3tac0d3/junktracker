<?php
    $selectedRole = isset($selectedRole) ? (int) $selectedRole : 3;
    $roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
    $matrix = is_array($matrix ?? null) ? $matrix : [];
    $isReady = !empty($isReady);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2">
        <div>
            <h1 class="mb-1">Permission Matrix</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Permissions</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning">`role_permissions` table is not available yet. Run migrations to enable this feature.</div>
    <?php endif; ?>

    <form method="get" action="<?= url('/admin/permissions') ?>" class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label mb-1" for="role">Role</label>
                <select class="form-select" id="role" name="role">
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= (int) $value === $selectedRole ? 'selected' : '' ?>>
                            <?= e((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button class="btn btn-outline-primary" type="submit">Load Role</button>
            </div>
        </div>
    </form>

    <form method="post" action="<?= url('/admin/permissions') ?>" class="card border-0 shadow-sm">
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="<?= e((string) $selectedRole) ?>" />
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-lock me-1"></i>Role Permissions</span>
            <button class="btn btn-sm btn-primary" type="submit" <?= !$isReady ? 'disabled' : '' ?>>Save Permissions</button>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th class="text-center">View</th>
                        <th class="text-center">Create</th>
                        <th class="text-center">Edit</th>
                        <th class="text-center">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matrix as $module => $row): ?>
                        <tr>
                            <td><?= e((string) ($row['label'] ?? $module)) ?></td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="matrix[<?= e((string) $module) ?>][view]" value="1" <?= !empty($row['view']) ? 'checked' : '' ?> />
                            </td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="matrix[<?= e((string) $module) ?>][create]" value="1" <?= !empty($row['create']) ? 'checked' : '' ?> />
                            </td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="matrix[<?= e((string) $module) ?>][edit]" value="1" <?= !empty($row['edit']) ? 'checked' : '' ?> />
                            </td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="matrix[<?= e((string) $module) ?>][delete]" value="1" <?= !empty($row['delete']) ? 'checked' : '' ?> />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

