<?php
    $recentBugs = is_array($recentBugs ?? null) ? $recentBugs : [];
    $health = is_array($health ?? null) ? $health : [];
    $systemStatus = is_array($systemStatus ?? null) ? $systemStatus : [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Dev Center</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Dev</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-primary" href="<?= url('/dev/bugs') ?>">
                <i class="fas fa-bug me-1"></i>
                Bug Board
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/admin/audit') ?>">
                <i class="fas fa-clipboard-list me-1"></i>
                Audit
            </a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-bug me-1"></i>Recent Open Bugs</div>
                    <a class="small text-decoration-none" href="<?= url('/dev/bugs') ?>">Open Board</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Severity</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBugs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-muted">No open bugs.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBugs as $bug): ?>
                                        <?php
                                            $status = (string) ($bug['status'] ?? 'unresearched');
                                            if ($status === 'new') {
                                                $status = 'unresearched';
                                            } elseif ($status === 'in_progress') {
                                                $status = 'working';
                                            } elseif ($status === 'fixed' || $status === 'wont_fix') {
                                                $status = 'fixed_closed';
                                            }
                                            $statusClass = match ($status) {
                                                'fixed_closed' => 'bg-success',
                                                'working' => 'bg-warning text-dark',
                                                'confirmed' => 'bg-info text-dark',
                                                default => 'bg-secondary',
                                            };
                                            $statusLabel = match ($status) {
                                                'fixed_closed' => 'Fixed / Closed',
                                                'working' => 'Working',
                                                'confirmed' => 'Confirmed',
                                                default => 'Unresearched',
                                            };
                                        ?>
                                        <tr>
                                            <td>#<?= e((string) ($bug['id'] ?? '')) ?></td>
                                            <td>
                                                <a class="text-decoration-none fw-semibold" href="<?= url('/dev/bugs/' . (int) ($bug['id'] ?? 0)) ?>">
                                                    <?= e((string) ($bug['title'] ?? 'Bug')) ?>
                                                </a>
                                            </td>
                                            <td><span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                            <td>P<?= e((string) ((int) ($bug['severity'] ?? 0))) ?></td>
                                            <td><?= e(format_datetime($bug['updated_at'] ?? null)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-heart-pulse me-1"></i>
                    Ops Snapshot
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="small text-muted">Failed Logins (24h)</div>
                            <div class="h5 mb-0"><?= e((string) ((int) ($health['failed_logins_24h'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Failed Mail (24h)</div>
                            <div class="h5 mb-0"><?= e((string) ((int) ($health['failed_mail_24h'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Overdue Tasks</div>
                            <div class="h5 mb-0"><?= e((string) ((int) ($health['overdue_tasks'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Active Sessions</div>
                            <div class="h5 mb-0"><?= e((string) ((int) ($health['active_sessions'] ?? 0))) ?></div>
                        </div>
                    </div>

                    <hr class="my-3" />

                    <div class="small text-muted mb-1">Database</div>
                    <div class="mb-2">
                        <?php if (!empty($systemStatus['database'])): ?>
                            <span class="badge bg-success">Connected</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Disconnected</span>
                        <?php endif; ?>
                    </div>

                    <div class="small text-muted mb-1">Session Storage</div>
                    <div class="mb-2">
                        <?php $sessionsPath = (string) (($systemStatus['sessions_path']['path'] ?? '') ?: '—'); ?>
                        <?php if (!empty($systemStatus['sessions_path']['ok'])): ?>
                            <span class="badge bg-success me-1">Writable</span>
                        <?php else: ?>
                            <span class="badge bg-danger me-1">Not Writable</span>
                        <?php endif; ?>
                        <span class="text-muted small"><?= e($sessionsPath) ?></span>
                    </div>

                    <div class="small text-muted mb-1">Log Storage</div>
                    <div class="mb-0">
                        <?php $logsPath = (string) (($systemStatus['logs_path']['path'] ?? '') ?: '—'); ?>
                        <?php if (!empty($systemStatus['logs_path']['ok'])): ?>
                            <span class="badge bg-success me-1">Writable</span>
                        <?php else: ?>
                            <span class="badge bg-danger me-1">Not Writable</span>
                        <?php endif; ?>
                        <span class="text-muted small"><?= e($logsPath) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
