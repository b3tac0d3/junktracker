<?php
    $entities = is_array($entities ?? null) ? $entities : [];
    $selectedEntity = (string) ($selectedEntity ?? 'jobs');
    $rows = is_array($rows ?? null) ? $rows : [];
    $query = (string) ($query ?? '');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2 mobile-two-col-buttons">
        <div>
            <h1 class="mb-1">Recovery</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Recovery</li>
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

    <div class="card mb-3">
        <div class="card-header"><i class="fas fa-filter me-1"></i>Find Deleted Records</div>
        <div class="card-body">
            <form method="get" action="<?= url('/admin/recovery') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="entity">Entity</label>
                    <select class="form-select" id="entity" name="entity">
                        <?php foreach ($entities as $entityKey => $entityLabel): ?>
                            <option value="<?= e((string) $entityKey) ?>" <?= $selectedEntity === (string) $entityKey ? 'selected' : '' ?>>
                                <?= e((string) $entityLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e($query) ?>" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-trash-arrow-up me-1"></i>Deleted <?= e((string) ($entities[$selectedEntity] ?? 'Records')) ?></div>
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-muted">No deleted records found for this selection.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Record</th>
                                <th>Deleted</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e((string) ($row['id'] ?? '')) ?></td>
                                    <td><?= e((string) (($row['item_title'] ?? '') !== '' ? $row['item_title'] : '—')) ?></td>
                                    <td><?= e(format_datetime($row['deleted_at'] ?? null)) ?></td>
                                    <td><?= e(format_datetime($row['updated_at'] ?? null)) ?></td>
                                    <td>
                                        <form method="post" action="<?= url('/admin/recovery/' . $selectedEntity . '/' . (string) ($row['id'] ?? '') . '/restore') ?>" onsubmit="return confirm('Restore this record?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-success" type="submit">
                                                <i class="fas fa-rotate-left me-1"></i>Restore
                                            </button>
                                        </form>
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

