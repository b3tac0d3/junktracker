<?php
    $health = is_array($health ?? null) ? $health : [];
    $deletedCounts = is_array($deletedCounts ?? null) ? $deletedCounts : [];
?>
<div class="container-fluid px-4">
    <h1 class="mt-4">Admin</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Admin</li>
    </ol>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Pending Invites</div>
                    <div class="h4 mb-0"><?= e((string) ((int) ($health['pending_invites'] ?? 0))) ?></div>
                    <div class="small text-muted">Expired: <?= e((string) ((int) ($health['expired_invites'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Failed Mail (24h)</div>
                    <div class="h4 mb-0 text-danger"><?= e((string) ((int) ($health['failed_mail_24h'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Active Sessions</div>
                    <div class="h4 mb-0 text-primary"><?= e((string) ((int) ($health['active_sessions'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Overdue Tasks</div>
                    <div class="h4 mb-0 text-warning"><?= e((string) ((int) ($health['overdue_tasks'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-users-cog me-2 text-primary"></i>Users</h5>
                    <p class="card-text text-muted mb-4">Manage user accounts, roles, and account status.</p>
                    <a class="btn btn-outline-primary mt-auto" href="<?= url('/users') ?>">Open Users</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-user-lock me-2 text-info"></i>Permissions</h5>
                    <p class="card-text text-muted mb-4">Configure role-based view/create/edit/delete permissions.</p>
                    <a class="btn btn-outline-info mt-auto" href="<?= url('/admin/permissions') ?>">Open Permissions</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-sliders me-2 text-dark"></i>Admin Settings</h5>
                    <p class="card-text text-muted mb-4">Manage system toggles, display settings, and mail defaults.</p>
                    <a class="btn btn-outline-dark mt-auto" href="<?= url('/admin/settings') ?>">Open Settings</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-tags me-2 text-warning"></i>Expense Categories</h5>
                    <p class="card-text text-muted mb-4">Maintain the categories used when recording expenses.</p>
                    <a class="btn btn-outline-warning mt-auto" href="<?= url('/admin/expense-categories') ?>">Open Categories</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-recycle me-2 text-success"></i>Disposal Locations</h5>
                    <p class="card-text text-muted mb-4">Manage dump and scrap locations used by jobs and sales.</p>
                    <a class="btn btn-outline-success mt-auto" href="<?= url('/admin/disposal-locations') ?>">Open Locations</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-list me-2 text-secondary"></i>Lookups</h5>
                    <p class="card-text text-muted mb-4">Maintain status and next-step option lists used by forms.</p>
                    <a class="btn btn-outline-secondary mt-auto" href="<?= url('/admin/lookups') ?>">Open Lookups</a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100 border-0 shadow-sm">
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
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="fas fa-scroll me-2 text-primary"></i>Audit Log</h5>
                    <p class="card-text text-muted mb-4">Search user actions by user, table, action key, and date range.</p>
                    <a class="btn btn-outline-primary mt-auto" href="<?= url('/admin/audit') ?>">Open Audit Log</a>
                </div>
            </div>
        </div>
    </div>
</div>

