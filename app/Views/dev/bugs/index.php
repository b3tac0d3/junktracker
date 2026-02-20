<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $bugs = is_array($bugs ?? null) ? $bugs : [];
    $users = is_array($users ?? null) ? $users : [];
    $statusFilters = is_array($statusFilters ?? null) ? $statusFilters : ['open', 'all'];
    $statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
    $environmentOptions = is_array($environmentOptions ?? null) ? $environmentOptions : ['all', 'local', 'live', 'both'];
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $returnTo = '/dev/bugs';
    if ($queryString !== '') {
        $returnTo .= '?' . $queryString;
    }
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Bug Board</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/dev') ?>">Dev</a></li>
                <li class="breadcrumb-item active">Bugs</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/dev') ?>">
            <i class="fas fa-arrow-left me-1"></i>
            Dev Center
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus me-1"></i>
            Log New Bug
        </div>
        <div class="card-body">
            <form method="post" action="<?= url('/dev/bugs/new') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <label class="form-label">Title</label>
                        <input class="form-control" type="text" name="title" maxlength="255" value="<?= e((string) old('title')) ?>" required />
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label">Severity</label>
                        <select class="form-select" name="severity">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= e((string) $i) ?>" <?= (string) old('severity', '3') === (string) $i ? 'selected' : '' ?>>P<?= e((string) $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label">Environment</label>
                        <select class="form-select" name="environment">
                            <?php foreach (['local', 'live', 'both'] as $env): ?>
                                <option value="<?= e($env) ?>" <?= (string) old('environment', 'local') === $env ? 'selected' : '' ?>><?= e(ucfirst($env)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-xl-2">
                        <label class="form-label">Assign</label>
                        <select class="form-select" name="assigned_user_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= e((string) ($user['id'] ?? '')) ?>" <?= (string) old('assigned_user_id') === (string) ($user['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string) ($user['name'] ?? ('User #' . (string) ($user['id'] ?? '')))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-xl-4">
                        <label class="form-label">Module</label>
                        <input class="form-control" type="text" name="module_key" maxlength="80" value="<?= e((string) old('module_key')) ?>" placeholder="jobs, tasks, dashboard..." />
                    </div>
                    <div class="col-12 col-xl-8">
                        <label class="form-label">Route / Path</label>
                        <input class="form-control" type="text" name="route_path" maxlength="255" value="<?= e((string) old('route_path')) ?>" placeholder="/jobs/12 or specific URL path" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Details</label>
                        <textarea class="form-control" name="details" rows="4" placeholder="Steps to reproduce, expected vs actual..."><?= e((string) old('details')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save me-1"></i>
                            Add Bug
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/dev/bugs') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-xl-4">
                        <label class="form-label">Search</label>
                        <input class="form-control" type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Title, details, path..." />
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statusFilters as $status): ?>
                                <option value="<?= e($status) ?>" <?= (string) ($filters['status'] ?? 'open') === $status ? 'selected' : '' ?>>
                                    <?= e($status === 'open' ? 'Open (New/In Progress)' : ucwords(str_replace('_', ' ', $status))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-1">
                        <label class="form-label">Severity</label>
                        <select class="form-select" name="severity">
                            <option value="">All</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= e((string) $i) ?>" <?= (string) ($filters['severity'] ?? '') === (string) $i ? 'selected' : '' ?>>P<?= e((string) $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label">Environment</label>
                        <select class="form-select" name="environment">
                            <?php foreach ($environmentOptions as $env): ?>
                                <option value="<?= e($env) ?>" <?= (string) ($filters['environment'] ?? 'all') === $env ? 'selected' : '' ?>>
                                    <?= e($env === 'all' ? 'All' : ucfirst($env)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label">Assigned</label>
                        <select class="form-select" name="assigned_user_id">
                            <option value="">All</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= e((string) ($user['id'] ?? '')) ?>" <?= (string) ($filters['assigned_user_id'] ?? '') === (string) ($user['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string) ($user['name'] ?? ('User #' . (string) ($user['id'] ?? '')))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-xl-1 d-grid">
                        <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-bug me-1"></i>
            Bugs
        </div>
        <div class="card-body">
            <?php if (empty($bugs)): ?>
                <div class="text-muted">No bugs found.</div>
            <?php else: ?>
                <table id="devBugsTable" class="js-card-list-source">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Severity</th>
                            <th>Environment</th>
                            <th>Module</th>
                            <th>Assigned</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bugs as $bug): ?>
                            <?php
                                $bugId = (int) ($bug['id'] ?? 0);
                                $status = (string) ($bug['status'] ?? 'new');
                                $statusClass = match ($status) {
                                    'fixed' => 'bg-success',
                                    'in_progress' => 'bg-warning text-dark',
                                    'wont_fix' => 'bg-secondary',
                                    default => 'bg-danger',
                                };
                            ?>
                            <tr>
                                <td>#<?= e((string) $bugId) ?></td>
                                <td>
                                    <a class="text-decoration-none fw-semibold" href="<?= url('/dev/bugs/' . $bugId) ?>">
                                        <?= e((string) ($bug['title'] ?? 'Bug')) ?>
                                    </a>
                                </td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span></td>
                                <td>P<?= e((string) ((int) ($bug['severity'] ?? 0))) ?></td>
                                <td><?= e(ucfirst((string) ($bug['environment'] ?? 'local'))) ?></td>
                                <td><?= e((string) (($bug['module_key'] ?? '') !== '' ? $bug['module_key'] : '—')) ?></td>
                                <td><?= e((string) (($bug['assigned_user_name'] ?? '') !== '' ? $bug['assigned_user_name'] : 'Unassigned')) ?></td>
                                <td><?= e(format_datetime($bug['updated_at'] ?? null)) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('/dev/bugs/' . $bugId) ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($status !== 'fixed'): ?>
                                            <form method="post" action="<?= url('/dev/bugs/' . $bugId . '/status') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                                                <input type="hidden" name="status" value="fixed" />
                                                <button class="btn btn-sm btn-success" type="submit" title="Mark Fixed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="<?= url('/dev/bugs/' . $bugId . '/status') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                                                <input type="hidden" name="status" value="in_progress" />
                                                <button class="btn btn-sm btn-warning" type="submit" title="Reopen">
                                                    <i class="fas fa-rotate-left"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
