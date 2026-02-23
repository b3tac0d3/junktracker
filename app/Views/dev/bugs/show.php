<?php
    $bug = is_array($bug ?? null) ? $bug : [];
    $notes = is_array($notes ?? null) ? $notes : [];
    $users = is_array($users ?? null) ? $users : [];
    $statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
    $environmentOptions = is_array($environmentOptions ?? null) ? $environmentOptions : [];
    $bugId = (int) ($bug['id'] ?? 0);
    $statusMeta = [
        'unresearched' => ['label' => 'Unresearched', 'class' => 'bg-secondary'],
        'confirmed' => ['label' => 'Confirmed', 'class' => 'bg-info text-dark'],
        'working' => ['label' => 'Working', 'class' => 'bg-warning text-dark'],
        'fixed_closed' => ['label' => 'Fixed / Closed', 'class' => 'bg-success'],
    ];
    $currentStatus = (string) ($bug['status'] ?? 'unresearched');
    if ($currentStatus === 'new') {
        $currentStatus = 'unresearched';
    } elseif ($currentStatus === 'in_progress') {
        $currentStatus = 'working';
    } elseif ($currentStatus === 'fixed' || $currentStatus === 'wont_fix') {
        $currentStatus = 'fixed_closed';
    }
    $formStatus = (string) old('status', $currentStatus);
    $statusClass = (string) ($statusMeta[$currentStatus]['class'] ?? 'bg-secondary');
    $statusLabel = (string) ($statusMeta[$currentStatus]['label'] ?? ucwords(str_replace('_', ' ', $currentStatus)));
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Bug #<?= e((string) $bugId) ?></h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/dev') ?>">Dev</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/dev/bugs') ?>">Bugs</a></li>
                <li class="breadcrumb-item active">#<?= e((string) $bugId) ?></li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/dev/bugs') ?>">
            <i class="fas fa-arrow-left me-1"></i>
            Back to Bug Board
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-bug me-1"></i>
                        Details
                    </div>
                    <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('/dev/bugs/' . $bugId . '/update') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Title</label>
                                <input class="form-control" type="text" name="title" maxlength="255" value="<?= e((string) old('title', (string) ($bug['title'] ?? ''))) ?>" required />
                            </div>
                            <div class="col-6 col-xl-2">
                                <label class="form-label">Severity</label>
                                <select class="form-select" name="severity">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?= e((string) $i) ?>" <?= (string) old('severity', (string) ($bug['severity'] ?? '3')) === (string) $i ? 'selected' : '' ?>>P<?= e((string) $i) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6 col-xl-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <?php foreach ($statusOptions as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $formStatus === $status ? 'selected' : '' ?>>
                                            <?= e((string) ($statusMeta[$status]['label'] ?? ucwords(str_replace('_', ' ', $status)))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-xl-3">
                                <label class="form-label">Environment</label>
                                <select class="form-select" name="environment">
                                    <?php foreach ($environmentOptions as $env): ?>
                                        <option value="<?= e($env) ?>" <?= (string) old('environment', (string) ($bug['environment'] ?? 'local')) === $env ? 'selected' : '' ?>>
                                            <?= e(ucfirst($env)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-xl-4">
                                <label class="form-label">Assigned User</label>
                                <select class="form-select" name="assigned_user_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= e((string) ($user['id'] ?? '')) ?>" <?= (string) old('assigned_user_id', (string) ($bug['assigned_user_id'] ?? '')) === (string) ($user['id'] ?? '') ? 'selected' : '' ?>>
                                            <?= e((string) ($user['name'] ?? ('User #' . (string) ($user['id'] ?? '')))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-xl-6">
                                <label class="form-label">Module</label>
                                <input class="form-control" type="text" name="module_key" maxlength="80" value="<?= e((string) old('module_key', (string) ($bug['module_key'] ?? ''))) ?>" />
                            </div>
                            <div class="col-12 col-xl-6">
                                <label class="form-label">Route / Path</label>
                                <input class="form-control" type="text" name="route_path" maxlength="255" value="<?= e((string) old('route_path', (string) ($bug['route_path'] ?? ''))) ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Details</label>
                                <textarea class="form-control" name="details" rows="10"><?= e((string) old('details', (string) ($bug['details'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap justify-content-between gap-2 mobile-two-col-buttons">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-save me-1"></i>
                                    Save Changes
                                </button>
                                <button class="btn btn-outline-danger" type="submit" form="deleteDevBugForm">
                                    <i class="fas fa-trash me-1"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteDevBugForm" method="post" action="<?= url('/dev/bugs/' . $bugId . '/delete') ?>" onsubmit="return confirm('Delete this bug log?');">
                        <?= csrf_field() ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Metadata
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="small text-muted">Reported By</div>
                        <div class="fw-semibold"><?= e((string) (($bug['reported_by_name'] ?? '') !== '' ? $bug['reported_by_name'] : '—')) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Assigned To</div>
                        <div class="fw-semibold"><?= e((string) (($bug['assigned_user_name'] ?? '') !== '' ? $bug['assigned_user_name'] : 'Unassigned')) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Closed By</div>
                        <div class="fw-semibold"><?= e((string) (($bug['fixed_by_name'] ?? '') !== '' ? $bug['fixed_by_name'] : '—')) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Closed At</div>
                        <div class="fw-semibold"><?= e(format_datetime($bug['fixed_at'] ?? null)) ?></div>
                    </div>
                    <hr />
                    <div class="mb-2">
                        <div class="small text-muted">Created At</div>
                        <div class="fw-semibold"><?= e(format_datetime($bug['created_at'] ?? null)) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Updated At</div>
                        <div class="fw-semibold"><?= e(format_datetime($bug['updated_at'] ?? null)) ?></div>
                    </div>
                    <div class="mb-0">
                        <div class="small text-muted">Created / Updated By</div>
                        <div class="fw-semibold">
                            <?= e((string) (($bug['created_by_name'] ?? '') !== '' ? $bug['created_by_name'] : '—')) ?>
                            /
                            <?= e((string) (($bug['updated_by_name'] ?? '') !== '' ? $bug['updated_by_name'] : '—')) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-bolt me-1"></i>
                    Quick Status
                </div>
                <div class="card-body d-grid gap-2">
                    <?php foreach (['unresearched', 'confirmed', 'working', 'fixed_closed'] as $quickStatus): ?>
                        <form method="post" action="<?= url('/dev/bugs/' . $bugId . '/status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($quickStatus) ?>" />
                            <input type="hidden" name="return_to" value="<?= e('/dev/bugs/' . $bugId) ?>" />
                            <button class="btn <?= $quickStatus === $currentStatus ? 'btn-primary' : 'btn-outline-primary' ?> w-100" type="submit">
                                Set: <?= e((string) ($statusMeta[$quickStatus]['label'] ?? ucwords(str_replace('_', ' ', $quickStatus)))) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-notes-medical me-1"></i>
                    Notes
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('/dev/bugs/' . $bugId . '/notes') ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <label class="form-label" for="dev_bug_note">Add Note</label>
                        <textarea id="dev_bug_note" class="form-control" name="note" rows="3" maxlength="5000" required><?= e((string) old('note', '')) ?></textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-plus me-1"></i>
                                Add Note
                            </button>
                        </div>
                    </form>

                    <?php if (empty($notes)): ?>
                        <div class="text-muted">No notes logged yet.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($notes as $note): ?>
                                <div class="list-group-item">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <span class="fw-semibold"><?= e((string) (($note['created_by_name'] ?? '') !== '' ? $note['created_by_name'] : 'System')) ?></span>
                                        <span class="small text-muted"><?= e(format_datetime($note['created_at'] ?? null)) ?></span>
                                    </div>
                                    <div class="small text-break"><?= nl2br(e((string) ($note['note'] ?? ''))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
