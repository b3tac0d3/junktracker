<?php
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $lastLogin = is_array($lastLogin ?? null) ? $lastLogin : null;
    $lastLoginBrowser = trim((string) ($lastLogin['browser_name'] ?? ''));
    $lastLoginBrowserVersion = trim((string) ($lastLogin['browser_version'] ?? ''));
    if ($lastLoginBrowser !== '' && $lastLoginBrowserVersion !== '') {
        $lastLoginBrowser .= ' ' . $lastLoginBrowserVersion;
    }
    $lastLoginOs = trim((string) ($lastLogin['os_name'] ?? ''));
    $lastLoginDevice = trim((string) ($lastLogin['device_type'] ?? ''));
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">User Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/users') ?>">Users</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($user['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-warning" href="<?= url('/users/' . ($user['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit User
            </a>
            <a class="btn btn-info text-white" href="<?= url('/users/' . ($user['id'] ?? '') . '/activity') ?>">
                <i class="fas fa-clock-rotate-left me-1"></i>
                Activity Log
            </a>
            <a class="btn btn-primary" href="<?= url('/users/' . ($user['id'] ?? '') . '/logins') ?>">
                <i class="fas fa-shield-alt me-1"></i>
                Login Records
            </a>
            <?php if (!empty($user['is_active'])): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deactivateUserModal">
                    <i class="fas fa-user-slash me-1"></i>
                    Deactivate
                </button>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Inactive</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/users') ?>">Back to Users</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            Profile
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Name</div>
                    <div class="fw-semibold"><?= e($name !== '' ? $name : '—') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e($user['email'] ?? '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">User ID</div>
                    <div class="fw-semibold"><?= e((string) ($user['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Role</div>
                    <div class="fw-semibold"><?= e(role_label(isset($user['role']) ? (int) $user['role'] : null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (!empty($user['is_active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Last Login</div>
                    <div class="fw-semibold"><?= e(format_datetime($lastLogin['logged_in_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Login Method</div>
                    <div class="fw-semibold"><?= e($lastLogin !== null ? login_method_label((string) ($lastLogin['login_method'] ?? '')) : '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Last Login IP</div>
                    <div class="fw-semibold"><?= e((string) ($lastLogin['ip_address'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Browser / System</div>
                    <div class="fw-semibold">
                        <?php
                            $browserSystem = trim($lastLoginBrowser);
                            if ($lastLoginOs !== '') {
                                $browserSystem = $browserSystem !== '' ? $browserSystem . ' on ' . $lastLoginOs : $lastLoginOs;
                            }
                            if ($browserSystem === '') {
                                $browserSystem = '—';
                            }
                            if ($lastLoginDevice !== '') {
                                $browserSystem .= ' (' . ucfirst($lastLoginDevice) . ')';
                            }
                            echo e($browserSystem);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($user['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($user['created_by'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($user['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($user['updated_by'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($user['deleted_at']) || !empty($user['deleted_by'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($user['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($user['deleted_by'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($user['is_active'])): ?>
        <div class="modal fade" id="deactivateUserModal" tabindex="-1" aria-labelledby="deactivateUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateUserModalLabel">Deactivate User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the user and prevent login. Are you sure you want to continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/users/' . ($user['id'] ?? '') . '/deactivate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Deactivate User</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
