<?php
    $usersBasePath = trim((string) ($usersBasePath ?? '/users'));
    if ($usersBasePath === '') {
        $usersBasePath = '/users';
    }
    $isGlobalDirectory = !empty($isGlobalDirectory);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1"><?= e($isGlobalDirectory ? 'Site Admin Users' : 'Users') ?></h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <?php if ($isGlobalDirectory): ?>
                    <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                    <li class="breadcrumb-item active">Global Users</li>
                <?php else: ?>
                    <li class="breadcrumb-item active">Users</li>
                <?php endif; ?>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-primary" href="<?= url($usersBasePath . '/new') ?>">
                <i class="fas fa-user-plus me-1"></i>
                <?= e($isGlobalDirectory ? 'Add Site Admin' : 'Add User') ?>
            </a>
        </div>
    </div>

    <?php
    $activeFilterCount = count(array_filter([
        !empty($query),
        ($status ?? 'active') !== 'active',
        ($inviteFilter ?? 'all') !== 'all',
    ]));
    ?>

    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" data-bs-toggle="collapse" data-bs-target="#usersFilterCollapse" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="usersFilterCollapse" style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="usersFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url($usersBasePath) ?>">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input class="form-control" type="text" name="q" placeholder="Search by name or email..." value="<?= e($query ?? '') ?>" />
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Invite Status</label>
                            <select class="form-select" name="invite">
                                <option value="all" <?= ($inviteFilter ?? 'all') === 'all' ? 'selected' : '' ?>>All Invites</option>
                                <option value="pending" <?= ($inviteFilter ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="invited" <?= ($inviteFilter ?? '') === 'invited' ? 'selected' : '' ?>>Invited</option>
                                <option value="expired" <?= ($inviteFilter ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option>
                                <option value="accepted" <?= ($inviteFilter ?? '') === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                                <option value="none" <?= ($inviteFilter ?? '') === 'none' ? 'selected' : '' ?>>No Invite</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url($usersBasePath) ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            User Directory
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="text-muted text-center py-4">
                    <i class="fas fa-user-slash fa-3x mb-3 d-block"></i>
                    No users found matching your filters.
                </div>
            <?php else: ?>
                <table id="usersTable" class="js-card-list-source">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Invite</th>
                            <th>Expires</th>
                            <th>Accepted</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $rowHref = url($usersBasePath . '/' . $user['id']); ?>
                            <?php $invite = is_array($user['invite'] ?? null) ? $user['invite'] : ['label' => 'N/A', 'badge_class' => 'bg-secondary']; ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) $user['id']) ?></td>
                                <td>
                                    <a class="text-decoration-none" href="<?= $rowHref ?>">
                                        <?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                                    </a>
                                </td>
                                <td><?= e($user['email'] ?? '') ?></td>
                                <td><?= e(role_label(isset($user['role']) ? (int) $user['role'] : null)) ?></td>
                                <td>
                                    <?php if (!empty($user['is_active'])): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= e((string) ($invite['badge_class'] ?? 'bg-secondary')) ?>">
                                        <?= e((string) ($invite['label'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td><?= e(format_datetime($invite['expires_at'] ?? null)) ?></td>
                                <td><?= e(format_datetime($invite['accepted_at'] ?? null)) ?></td>
                                <td><?= e(format_datetime($user['last_activity_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
