<?php
$search = trim((string) ($search ?? ''));
$status = trim((string) ($status ?? 'active'));
if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    $status = 'active';
}
$users = is_array($users ?? null) ? $users : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($users), count($users));
$perPage = (int) ($pagination['per_page'] ?? 25);
$isSiteAdminGlobal = (bool) ($isSiteAdminGlobal ?? false);

$displayName = static function (array $row): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    return trim((string) ($row['email'] ?? '')) !== '' ? (string) $row['email'] : ('User #' . (string) ((int) ($row['id'] ?? 0)));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Users</h1>
        <p class="muted"><?= e($isSiteAdminGlobal ? 'Global user management' : 'Business user management') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/admin/users/create')) ?>">
            <i class="fas fa-plus me-2"></i><?= e($isSiteAdminGlobal ? 'Add Site Admin' : 'Add User') ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/users')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="users-search">Search</label>
                <input id="users-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, email, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="users-status">Status</label>
                <select id="users-status" class="form-select" name="status">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/users')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-users me-2"></i>User List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($users)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/users';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($users === []): ?>
            <div class="record-empty">No users found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($users as $row): ?>
                    <?php
                    $userId = (int) ($row['id'] ?? 0);
                    $globalRole = trim((string) ($row['role'] ?? 'general_user'));
                    $workspaceRole = trim((string) ($row['workspace_role'] ?? ''));
                    $isUserActive = (int) ($row['is_active'] ?? 1) === 1;
                    $isMembershipActive = (int) ($row['membership_active'] ?? 1) === 1;
                    $effectiveActive = $isSiteAdminGlobal ? $isUserActive : ($isUserActive && $isMembershipActive);
                    $invitedAt = trim((string) ($row['invited_at'] ?? ''));
                    $acceptedAt = trim((string) ($row['invitation_accepted_at'] ?? ''));
                    $expiresAt = trim((string) ($row['invitation_expires_at'] ?? ''));
                    $inviteExpired = $invitedAt !== '' && $acceptedAt === '' && $expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time();
                    $inviteStatus = $acceptedAt !== '' || $invitedAt === '' ? 'Accepted' : ($inviteExpired ? 'Expired' : 'Pending');
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/admin/users/' . (string) $userId . '/edit')) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($displayName($row)) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">User ID</span>
                                    <span class="record-value"><?= e((string) $userId) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Email</span>
                                    <span class="record-value"><?= e(trim((string) ($row['email'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Global Role</span>
                                    <span class="record-value text-capitalize"><?= e(str_replace('_', ' ', $globalRole)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label"><?= e($isSiteAdminGlobal ? 'Scope' : 'Workspace Role') ?></span>
                                    <span class="record-value text-capitalize">
                                        <?= e($isSiteAdminGlobal ? 'Global' : (str_replace('_', ' ', ($workspaceRole !== '' ? $workspaceRole : '—')))) ?>
                                    </span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value">
                                        <span class="badge <?= $effectiveActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= e($effectiveActive ? 'Active' : 'Inactive') ?>
                                        </span>
                                        <span class="badge <?= $acceptedAt !== '' ? 'text-bg-success' : ($inviteExpired ? 'text-bg-danger' : 'text-bg-warning') ?> ms-1">
                                            <?= e($inviteStatus) ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
