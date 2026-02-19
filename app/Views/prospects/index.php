<?php
    $filters = $filters ?? [];
    $statuses = is_array($statuses ?? null) ? $statuses : ['active', 'converted', 'closed'];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Prospects</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Prospects</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/prospects/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Prospect
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/prospects') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search client, next step, notes..."
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                            />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?= ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($statuses as $statusOption): ?>
                                <option value="<?= e((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === (string) $statusOption ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('_', ' ', (string) $statusOption))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($filters['record_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-clock me-1"></i>
            Prospect Pipeline
        </div>
        <div class="card-body">
            <?php if (empty($prospects ?? [])): ?>
                <div class="text-muted">No prospects found.</div>
            <?php else: ?>
                <table id="prospectsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Next Step</th>
                            <th>Follow Up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prospects as $prospect): ?>
                            <?php
                                $rowHref = url('/prospects/' . (string) ($prospect['id'] ?? ''));
                                $status = (string) ($prospect['status'] ?? 'active');
                                $statusClass = match ($status) {
                                    'converted' => 'bg-success',
                                    'closed' => 'bg-secondary',
                                    default => 'bg-warning text-dark',
                                };

                                $priority = (int) ($prospect['priority_rating'] ?? 2);
                                $priorityLabel = match ($priority) {
                                    4 => 'Urgent',
                                    3 => 'High',
                                    1 => 'Low',
                                    default => 'Normal',
                                };
                                $priorityClass = match ($priority) {
                                    4 => 'text-danger',
                                    3 => 'text-warning',
                                    1 => 'text-muted',
                                    default => 'text-primary',
                                };
                            ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($prospect['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none" href="<?= $rowHref ?>">
                                        <?= e((string) (($prospect['client_name'] ?? '') !== '' ? $prospect['client_name'] : '—')) ?>
                                    </a>
                                </td>
                                <td><span class="badge <?= e($statusClass) ?> text-uppercase"><?= e($status) ?></span></td>
                                <td><span class="fw-semibold <?= e($priorityClass) ?>"><?= e($priorityLabel) ?></span></td>
                                <td><?= e((string) (($prospect['next_step'] ?? '') !== '' ? str_replace('_', ' ', (string) $prospect['next_step']) : '—')) ?></td>
                                <td><?= e(format_date($prospect['follow_up_on'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
