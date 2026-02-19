<?php
    $health = is_array($health ?? null) ? $health : [];
    $deletedCounts = is_array($deletedCounts ?? null) ? $deletedCounts : [];
    $systemStatus = is_array($systemStatus ?? null) ? $systemStatus : [];
    $lastMigration = is_array($systemStatus['last_migration'] ?? null) ? $systemStatus['last_migration'] : null;

    $statusBadge = static function (bool $ok, string $okText, string $badText): array {
        return [
            'class' => $ok ? 'bg-success' : 'bg-danger',
            'label' => $ok ? $okText : $badText,
        ];
    };

    $dbStatus = $statusBadge(!empty($systemStatus['database']), 'Connected', 'Error');
    $sessionStatus = $statusBadge(!empty($systemStatus['sessions_path']['ok']), 'Writable', 'Not Writable');
    $logStatus = $statusBadge(!empty($systemStatus['logs_path']['ok']), 'Writable', 'Not Writable');
    $contractsStatus = $statusBadge(!empty($systemStatus['contracts_path']['ok']), 'Writable', 'Not Writable');
    $migrationStatus = [
        'class' => !empty($systemStatus['migrations_table']) ? 'bg-success' : 'bg-warning text-dark',
        'label' => !empty($systemStatus['migrations_table']) ? 'Ready' : 'Missing',
    ];

    $metricCards = [
        [
            'label' => 'Pending Invites',
            'value' => (int) ($health['pending_invites'] ?? 0),
            'sub' => 'Expired: ' . (int) ($health['expired_invites'] ?? 0),
            'tone' => 'neutral',
            'icon' => 'fa-envelope-open-text',
        ],
        [
            'label' => 'Failed Mail (24h)',
            'value' => (int) ($health['failed_mail_24h'] ?? 0),
            'sub' => 'Delivery issues',
            'tone' => 'danger',
            'icon' => 'fa-triangle-exclamation',
        ],
        [
            'label' => 'Failed Logins (24h)',
            'value' => (int) ($health['failed_logins_24h'] ?? 0),
            'sub' => 'Security signal',
            'tone' => 'danger',
            'icon' => 'fa-user-lock',
        ],
        [
            'label' => 'Active Sessions',
            'value' => (int) ($health['active_sessions'] ?? 0),
            'sub' => 'Current active session files',
            'tone' => 'info',
            'icon' => 'fa-users',
        ],
        [
            'label' => 'Overdue Tasks',
            'value' => (int) ($health['overdue_tasks'] ?? 0),
            'sub' => 'Needs follow-up',
            'tone' => 'warning',
            'icon' => 'fa-list-check',
        ],
    ];
?>
<style>
    .admin-shell .metric-card {
        position: relative;
        border: 1px solid #d8e1ec;
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 8px 20px rgba(17, 39, 67, 0.08);
        overflow: hidden;
    }

    .admin-shell .metric-card::before {
        content: "";
        display: block;
        height: 4px;
        background: #7f8fa6;
    }

    .admin-shell .metric-card.metric-danger::before {
        background: #dc3545;
    }

    .admin-shell .metric-card.metric-info::before {
        background: #0d6efd;
    }

    .admin-shell .metric-card.metric-warning::before {
        background: #f59f00;
    }

    .admin-shell .metric-body {
        padding: 1rem 1.25rem 1.1rem;
    }

    .admin-shell .metric-label {
        font-size: 0.86rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #637185;
    }

    .admin-shell .metric-value {
        margin-top: 0.35rem;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: #22324a;
    }

    .admin-shell .metric-sub {
        margin-top: 0.4rem;
        color: #6c7787;
        font-size: 0.95rem;
    }

    .admin-shell .metric-icon {
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 0.7rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        color: #1f5da8;
        background: #e8f0fb;
    }

    .admin-shell .metric-card.metric-danger .metric-icon {
        color: #b42332;
        background: #fdecef;
    }

    .admin-shell .metric-card.metric-warning .metric-icon {
        color: #9f6500;
        background: #fff6dc;
    }

    .admin-shell .health-panel {
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid #d8e1ec;
    }

    .admin-shell .health-header {
        background: linear-gradient(180deg, #e9f1fb 0%, #e4edf8 100%);
        border-bottom: 1px solid #d4dfed;
    }

    .admin-shell .health-list .list-group-item {
        border-color: #e2e8f1;
        padding: 0.95rem 1.25rem;
    }

    .admin-shell .health-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .admin-shell .health-name {
        font-weight: 700;
        color: #243247;
    }

    .admin-shell .health-meta {
        color: #6d7889;
        font-size: 0.92rem;
        margin-top: 0.2rem;
    }

    .admin-shell .health-path {
        margin-top: 0.4rem;
        color: #576377;
        font-size: 1.02rem;
        line-height: 1.35;
        word-break: break-word;
    }

    .admin-shell .health-mini {
        border: 1px solid #dce5f1;
        border-radius: 0.85rem;
        background: #f9fbfe;
        padding: 0.75rem 0.85rem;
        height: 100%;
    }

    .admin-shell .health-mini .label {
        color: #6b7585;
        font-size: 0.88rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .admin-shell .health-mini .value {
        color: #1f2937;
        font-size: 1.05rem;
        font-weight: 700;
    }

    .admin-shell .admin-link-card {
        border: 1px solid #dce5f1;
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 8px 18px rgba(20, 39, 62, 0.07);
    }

    .admin-shell .admin-link-card .card-title {
        color: #263649;
    }
</style>

<div class="container-fluid px-4 admin-shell">
    <h1 class="mt-4">Admin</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Admin</li>
    </ol>

    <div class="row g-3 mb-4">
        <?php foreach ($metricCards as $card): ?>
            <div class="col-md-6 col-xl-3">
                <div class="metric-card metric-<?= e((string) $card['tone']) ?> h-100">
                    <div class="metric-body">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="metric-label"><?= e((string) $card['label']) ?></div>
                            <div class="metric-icon"><i class="fas <?= e((string) $card['icon']) ?>"></i></div>
                        </div>
                        <div class="metric-value"><?= e((string) $card['value']) ?></div>
                        <div class="metric-sub"><?= e((string) $card['sub']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm mb-4 health-panel">
        <div class="card-header d-flex align-items-center justify-content-between health-header">
            <div><i class="fas fa-heart-pulse me-1"></i>System Health</div>
            <span class="small text-muted">Live operational checks</span>
        </div>
        <div class="list-group list-group-flush health-list">
            <div class="list-group-item">
                <div class="health-row">
                    <div>
                        <div class="health-name">Database</div>
                        <div class="health-meta">Primary DB connection</div>
                    </div>
                    <span class="badge <?= e((string) $dbStatus['class']) ?>"><?= e((string) $dbStatus['label']) ?></span>
                </div>
            </div>

            <div class="list-group-item">
                <div class="health-row">
                    <div>
                        <div class="health-name">Session Storage</div>
                        <div class="health-meta">Filesystem session backend</div>
                    </div>
                    <span class="badge <?= e((string) $sessionStatus['class']) ?>"><?= e((string) $sessionStatus['label']) ?></span>
                </div>
                <div class="health-path font-monospace"><?= e((string) ($systemStatus['sessions_path']['path'] ?? '—')) ?></div>
            </div>

            <div class="list-group-item">
                <div class="health-row">
                    <div>
                        <div class="health-name">Log Storage</div>
                        <div class="health-meta">Application event/error logs</div>
                    </div>
                    <span class="badge <?= e((string) $logStatus['class']) ?>"><?= e((string) $logStatus['label']) ?></span>
                </div>
                <div class="health-path font-monospace"><?= e((string) ($systemStatus['logs_path']['path'] ?? '—')) ?></div>
            </div>

            <div class="list-group-item">
                <div class="health-row">
                    <div>
                        <div class="health-name">Contract Storage</div>
                        <div class="health-meta">Consignor contract upload location</div>
                    </div>
                    <span class="badge <?= e((string) $contractsStatus['class']) ?>"><?= e((string) $contractsStatus['label']) ?></span>
                </div>
                <div class="health-path font-monospace"><?= e((string) ($systemStatus['contracts_path']['path'] ?? '—')) ?></div>
            </div>

            <div class="list-group-item">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="health-mini">
                            <div class="label">Mail Mode</div>
                            <div class="value"><?= e((string) ($systemStatus['mail_mode'] ?? 'log')) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-mini">
                            <div class="label">Mail Host</div>
                            <div class="value"><?= e((string) (($systemStatus['mail_host'] ?? '') !== '' ? $systemStatus['mail_host'] : '—')) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-mini">
                            <div class="label">Migration Tracker</div>
                            <div class="value"><span class="badge <?= e((string) $migrationStatus['class']) ?>"><?= e((string) $migrationStatus['label']) ?></span></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-mini">
                            <div class="label">Last Migration</div>
                            <?php if ($lastMigration): ?>
                                <div class="value"><?= e((string) ($lastMigration['migration_key'] ?? '—')) ?></div>
                                <div class="small text-muted mt-1"><?= e(format_datetime($lastMigration['applied_at'] ?? null)) ?></div>
                            <?php else: ?>
                                <div class="value">—</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-users-cog me-2 text-primary"></i>Users</h5>
                    <p class="card-text text-muted mb-4">Manage user accounts, roles, and account status.</p>
                    <a class="btn btn-outline-primary mt-auto" href="<?= url('/users') ?>">Open Users</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-user-lock me-2 text-info"></i>Permissions</h5>
                    <p class="card-text text-muted mb-4">Configure role-based view/create/edit/delete permissions.</p>
                    <a class="btn btn-outline-info mt-auto" href="<?= url('/admin/permissions') ?>">Open Permissions</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-sliders me-2 text-dark"></i>Admin Settings</h5>
                    <p class="card-text text-muted mb-4">Manage system toggles, display settings, and mail defaults.</p>
                    <a class="btn btn-outline-dark mt-auto" href="<?= url('/admin/settings') ?>">Open Settings</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-tags me-2 text-warning"></i>Expense Categories</h5>
                    <p class="card-text text-muted mb-4">Maintain the categories used when recording expenses.</p>
                    <a class="btn btn-outline-warning mt-auto" href="<?= url('/admin/expense-categories') ?>">Open Categories</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-recycle me-2 text-success"></i>Disposal Locations</h5>
                    <p class="card-text text-muted mb-4">Manage dump and scrap locations used by jobs and sales.</p>
                    <a class="btn btn-outline-success mt-auto" href="<?= url('/admin/disposal-locations') ?>">Open Locations</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-list me-2 text-secondary"></i>Lookups</h5>
                    <p class="card-text text-muted mb-4">Maintain status and next-step option lists used by forms.</p>
                    <a class="btn btn-outline-secondary mt-auto" href="<?= url('/admin/lookups') ?>">Open Lookups</a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-shield-halved me-2 text-danger"></i>Recovery</h5>
                    <p class="card-text text-muted mb-3">Restore soft-deleted records across core operational tables.</p>
                    <div class="small text-muted mb-4">
                        Deleted records:
                        Jobs <?= e((string) ((int) ($deletedCounts['jobs'] ?? 0))) ?>,
                        Clients <?= e((string) ((int) ($deletedCounts['clients'] ?? 0))) ?>,
                        Employees <?= e((string) ((int) ($deletedCounts['employees'] ?? 0))) ?>,
                        Prospects <?= e((string) ((int) ($deletedCounts['prospects'] ?? 0))) ?>.
                    </div>
                    <a class="btn btn-outline-danger mt-auto" href="<?= url('/admin/recovery') ?>">Open Recovery</a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-scroll me-2 text-primary"></i>Audit Log</h5>
                    <p class="card-text text-muted mb-4">Search user actions by user, table, action key, and date range.</p>
                    <a class="btn btn-outline-primary mt-auto" href="<?= url('/admin/audit') ?>">Open Audit Log</a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-clone me-2 text-dark"></i>Data Quality</h5>
                    <p class="card-text text-muted mb-4">Review duplicate queues for clients, companies, and jobs, then merge safely.</p>
                    <a class="btn btn-outline-dark mt-auto" href="<?= url('/data-quality') ?>">Open Data Quality</a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100 admin-link-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-chart-line me-2 text-success"></i>Reporting Hub</h5>
                    <p class="card-text text-muted mb-4">Open financial and operational reports, then save presets for recurring review.</p>
                    <a class="btn btn-outline-success mt-auto" href="<?= url('/reports') ?>">Open Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>
