<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $actions = is_array($actions ?? null) ? $actions : [];
    $entityOptions = is_array($entityOptions ?? null) ? $entityOptions : [];
    $actionOptions = is_array($actionOptions ?? null) ? $actionOptions : [];
    $userOptions = is_array($userOptions ?? null) ? $userOptions : [];
    $isReady = !empty($isReady);
    $preset = (string) ($preset ?? 'all');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2 mobile-two-col-buttons">
        <div>
            <h1 class="mb-1">Audit Log</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Audit Log</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
        </div>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning">`user_actions` table is not available yet. Run migrations to enable audit log.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header"><i class="fas fa-filter me-1"></i>Filters</div>
        <div class="card-body">
            <form method="get" action="<?= url('/admin/audit') ?>" class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e((string) ($filters['q'] ?? '')) ?>" />
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="user_id">User</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="0">All</option>
                        <?php foreach ($userOptions as $user): ?>
                            <option value="<?= e((string) ($user['id'] ?? 0)) ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) ($user['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e((string) ($user['label'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="entity_table">Entity</label>
                    <select class="form-select" id="entity_table" name="entity_table">
                        <option value="">All</option>
                        <?php foreach ($entityOptions as $entity): ?>
                            <option value="<?= e((string) $entity) ?>" <?= (string) ($filters['entity_table'] ?? '') === (string) $entity ? 'selected' : '' ?>>
                                <?= e((string) $entity) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="action_key">Action</label>
                    <select class="form-select" id="action_key" name="action_key">
                        <option value="">All</option>
                        <?php foreach ($actionOptions as $action): ?>
                            <option value="<?= e((string) $action) ?>" <?= (string) ($filters['action_key'] ?? '') === (string) $action ? 'selected' : '' ?>>
                                <?= e((string) $action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label" for="date_from">From</label>
                    <input class="form-control" id="date_from" name="date_from" type="date" value="<?= e((string) ($filters['date_from'] ?? '')) ?>" />
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label" for="date_to">To</label>
                    <input class="form-control" id="date_to" name="date_to" type="date" value="<?= e((string) ($filters['date_to'] ?? '')) ?>" />
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label" for="preset">Preset</label>
                    <select class="form-select" id="preset" name="preset">
                        <option value="all" <?= $preset === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="security" <?= $preset === 'security' ? 'selected' : '' ?>>Security</option>
                        <option value="financial" <?= $preset === 'financial' ? 'selected' : '' ?>>Financial</option>
                        <option value="data_changes" <?= $preset === 'data_changes' ? 'selected' : '' ?>>Changes</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-scroll me-1"></i>Audit Results</div>
        <div class="card-body">
            <?php if (empty($actions)): ?>
                <div class="text-muted">No matching actions found.</div>
            <?php else: ?>
                <table id="userActivityTable" class="js-card-list-source">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Summary</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actions as $action): ?>
                            <tr>
                                <td><?= e(format_datetime($action['created_at'] ?? null)) ?></td>
                                <td>
                                    <?php
                                        $actorName = trim((string) ($action['actor_name'] ?? ''));
                                        $actorEmail = trim((string) ($action['actor_email'] ?? ''));
                                        echo e($actorName !== '' ? $actorName : ($actorEmail !== '' ? $actorEmail : 'User #' . (string) ($action['user_id'] ?? '')));
                                    ?>
                                </td>
                                <td><?= e((string) ($action['action_key'] ?? '')) ?></td>
                                <td><?= e((string) (($action['entity_table'] ?? '') !== '' ? $action['entity_table'] . '#' . ($action['entity_id'] ?? '') : '—')) ?></td>
                                <td>
                                    <div><?= e((string) ($action['summary'] ?? '')) ?></div>
                                    <?php if (trim((string) ($action['details'] ?? '')) !== ''): ?>
                                        <div class="text-muted small mt-1"><?= e((string) ($action['details'] ?? '')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) ($action['ip_address'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
