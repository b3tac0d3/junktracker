<?php
    $groups = is_array($groups ?? null) ? $groups : [];
    $selectedGroup = (string) ($selectedGroup ?? 'job_status');
    $rows = is_array($rows ?? null) ? $rows : [];
    $isReady = !empty($isReady);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2 mobile-two-col-buttons">
        <div>
            <h1 class="mb-1">Lookups</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Lookups</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
            <a class="btn btn-primary" href="<?= url('/admin/lookups/new?group=' . urlencode($selectedGroup)) ?>">
                <i class="fas fa-plus me-1"></i>Add Option
            </a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning">`app_lookups` table is not available yet. Run migrations to enable lookup management.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= url('/admin/lookups') ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="group">Lookup Group</label>
                    <select class="form-select" id="group" name="group">
                        <?php foreach ($groups as $groupKey => $groupLabel): ?>
                            <option value="<?= e((string) $groupKey) ?>" <?= $selectedGroup === (string) $groupKey ? 'selected' : '' ?>>
                                <?= e((string) $groupLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" type="submit">Load Group</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-list me-1"></i><?= e((string) ($groups[$selectedGroup] ?? $selectedGroup)) ?></div>
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-muted">No options found for this group.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Value</th>
                                <th>Label</th>
                                <th>Sort</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $rowId = (int) ($row['id'] ?? 0);
                                    $deleted = !empty($row['deleted_at']);
                                    $active = !$deleted && (int) ($row['active'] ?? 0) === 1;
                                ?>
                                <tr>
                                    <td><?= e((string) $rowId) ?></td>
                                    <td><?= e((string) ($row['value_key'] ?? '')) ?></td>
                                    <td><?= e((string) ($row['label'] ?? '')) ?></td>
                                    <td><?= e((string) ($row['sort_order'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($deleted): ?>
                                            <span class="badge bg-secondary">Deleted</span>
                                        <?php elseif ($active): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(format_datetime($row['updated_at'] ?? null)) ?></td>
                                    <td class="d-flex gap-2 mobile-two-col-buttons">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/lookups/' . $rowId . '/edit') ?>">Edit</a>
                                        <?php if (!$deleted): ?>
                                            <form method="post" action="<?= url('/admin/lookups/' . $rowId . '/delete') ?>" onsubmit="return confirm('Delete this lookup option?');">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

