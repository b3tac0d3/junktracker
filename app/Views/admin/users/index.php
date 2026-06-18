<?php
$search = trim((string) ($search ?? ''));
$status = trim((string) ($status ?? 'active'));
if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    $status = 'active';
}
$scope = trim((string) ($scope ?? 'business'));
$users = is_array($users ?? null) ? $users : [];
$membershipsByUser = is_array($membershipsByUser ?? null) ? $membershipsByUser : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($users), count($users));
$perPage = (int) ($pagination['per_page'] ?? 25);
$isSiteAdminGlobal = (bool) ($isSiteAdminGlobal ?? false);
$canSearchAllCompanies = (bool) ($canSearchAllCompanies ?? false);
$isCompanyUserDirectory = $scope === 'company_users';

$scopeLabels = [
    'site_admins' => 'Site Admins',
    'company_users' => 'All Company Users',
    'business' => 'This Workspace',
];
$scopeLabel = $scopeLabels[$scope] ?? 'Users';

$displayName = static function (array $row): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    return trim((string) ($row['email'] ?? '')) !== '' ? (string) $row['email'] : ('User #' . (string) ((int) ($row['id'] ?? 0)));
};
$mailTransport = trim((string) config('mail.transport', 'log'));
$mailLogOnly = $mailTransport === 'log';

$scopeQuery = static function (string $targetScope) use ($search, $status, $perPage): string {
    return url('/admin/users') . query_with([
        'scope' => $targetScope,
        'q' => $search,
        'status' => $status,
        'page' => 1,
        'per_page' => $perPage,
    ]);
};
?>

<?php if ($mailLogOnly): ?>
<div class="alert alert-warning mb-3" role="alert">
    <strong>Email is in log-only mode.</strong> User invites and password resets are written to <code>storage/logs/mail-*.log</code> but are not emailed. This is normal on localhost; beta-live servers on a real hostname send mail automatically.
</div>
<?php endif; ?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Users</h1>
        <p class="muted mb-0">
            <?php if ($isCompanyUserDirectory): ?>
                Search every company workspace to see whether a user already exists and where they belong.
            <?php elseif ($isSiteAdminGlobal): ?>
                Manage global site admin accounts.
            <?php else: ?>
                Business user management for the current workspace.
            <?php endif; ?>
        </p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <?php if (!$isCompanyUserDirectory): ?>
            <a class="btn btn-primary w-100 w-md-auto" href="<?= e(url('/admin/users/create')) ?>">
                <i class="fas fa-plus me-2"></i><?= e($isSiteAdminGlobal ? 'Add Site Admin' : 'Add User') ?>
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url($isSiteAdminGlobal ? '/site-admin' : '/admin')) ?>">
            <?= e($isSiteAdminGlobal ? 'Back to Companies' : 'Back to Admin') ?>
        </a>
    </div>
</div>

<?php if ($canSearchAllCompanies): ?>
<section class="card index-card mb-3">
    <div class="card-body py-2">
        <ul class="nav nav-pills index-card-tabs flex-wrap gap-1">
            <?php if ($isSiteAdminGlobal): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $scope === 'site_admins' ? ' active' : '' ?>" href="<?= e($scopeQuery('site_admins')) ?>">Site Admins</a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link<?= $scope === 'company_users' ? ' active' : '' ?>" href="<?= e($scopeQuery('company_users')) ?>">All Company Users</a>
            </li>
            <?php if (!$isSiteAdminGlobal): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $scope === 'business' ? ' active' : '' ?>" href="<?= e($scopeQuery('business')) ?>">This Workspace</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/users')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <?php if ($canSearchAllCompanies): ?>
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
            <?php endif; ?>
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="users-search">Search</label>
                <input
                    id="users-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="<?= e($isCompanyUserDirectory ? 'Search by name, email, or user id across all companies...' : 'Search by name, email, or id...') ?>"
                    autocomplete="off"
                />
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
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/users') . query_with(['scope' => $scope])) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-users me-2"></i><?= e($scopeLabel) ?></strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($users)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/users';
        $fixedQueryParams = ['scope' => $scope, 'q' => $search, 'status' => $status];
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
                    $memberships = is_array($membershipsByUser[$userId] ?? null) ? $membershipsByUser[$userId] : [];
                    $hasActiveMembership = false;
                    foreach ($memberships as $membershipRow) {
                        if (!is_array($membershipRow)) {
                            continue;
                        }
                        if ((int) ($membershipRow['membership_active'] ?? 1) === 1 && (int) ($membershipRow['business_active'] ?? 1) === 1) {
                            $hasActiveMembership = true;
                            break;
                        }
                    }
                    $invitedAt = trim((string) ($row['invited_at'] ?? ''));
                    $acceptedAt = trim((string) ($row['invitation_accepted_at'] ?? ''));
                    $expiresAt = trim((string) ($row['invitation_expires_at'] ?? ''));
                    $inviteExpired = $invitedAt !== '' && $acceptedAt === '' && $expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time();
                    $inviteStatus = $acceptedAt !== '' || $invitedAt === '' ? 'Accepted' : ($inviteExpired ? 'Expired' : 'Pending');
                    $rowHref = $isCompanyUserDirectory ? '' : url('/admin/users/' . (string) $userId . '/edit');
                    $effectiveActive = $isCompanyUserDirectory
                        ? ($isUserActive && $hasActiveMembership)
                        : (($isSiteAdminGlobal && !$isCompanyUserDirectory)
                            ? $isUserActive
                            : ($isUserActive && $isMembershipActive));
                    ?>
                    <article class="record-row-simple">
                        <?php if ($rowHref !== ''): ?>
                            <a class="record-row-link" href="<?= e($rowHref) ?>">
                        <?php else: ?>
                            <div class="record-row-link">
                        <?php endif; ?>
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($displayName($row)) ?></h3>
                            </div>
                            <div class="record-row-fields <?= $isCompanyUserDirectory ? 'record-row-fields-4' : 'record-row-fields-5' ?>">
                                <div class="record-field">
                                    <span class="record-label">User ID</span>
                                    <span class="record-value"><?= e((string) $userId) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Email</span>
                                    <span class="record-value"><?= e(trim((string) ($row['email'] ?? '')) ?: '—') ?></span>
                                </div>
                                <?php if ($isCompanyUserDirectory): ?>
                                    <div class="record-field">
                                        <span class="record-label">Companies</span>
                                        <span class="record-value">
                                            <?php if ($memberships === []): ?>
                                                —
                                            <?php else: ?>
                                                <span class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($memberships as $membership): ?>
                                                        <?php
                                                        if (!is_array($membership)) {
                                                            continue;
                                                        }
                                                        $businessId = (int) ($membership['business_id'] ?? 0);
                                                        $businessName = trim((string) ($membership['business_name'] ?? ''));
                                                        $membershipRole = trim((string) ($membership['role'] ?? 'general_user'));
                                                        $membershipActive = (int) ($membership['membership_active'] ?? 1) === 1;
                                                        $businessActive = (int) ($membership['business_active'] ?? 1) === 1;
                                                        $membershipEffective = $membershipActive && $businessActive;
                                                        $label = ($businessName !== '' ? $businessName : ('Business #' . (string) $businessId))
                                                            . ' · '
                                                            . ucwords(str_replace('_', ' ', $membershipRole));
                                                        ?>
                                                        <a
                                                            class="badge text-decoration-none <?= $membershipEffective ? 'text-bg-primary' : 'text-bg-secondary' ?>"
                                                            href="<?= e(url('/site-admin/businesses/' . (string) $businessId)) ?>"
                                                        ><?= e($label) ?></a>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php else: ?>
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
                                <?php endif; ?>
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
                        <?php if ($rowHref !== ''): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
