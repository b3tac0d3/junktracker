<?php
use App\Models\Business;
use App\Models\DevTrackerItem;

$internalBarMode = internal_context_bar_mode();
if ($internalBarMode === null) {
    return;
}

$isDevBar = $internalBarMode === 'dev';
$businessId = current_business_id();
$inWorkspace = $businessId > 0;
$contextLabel = site_admin_context_label();
$switcherBusinesses = Business::activeNamesForSwitcher(15);
$devSummary = $isDevBar ? DevTrackerItem::statusSummary() : [];
$openBugCount = 0;
if ($devSummary !== []) {
    foreach (DevTrackerItem::statusOptions() as $statusOption) {
        if (!in_array($statusOption, ['done', 'wont_fix'], true)) {
            $openBugCount += (int) ($devSummary[$statusOption] ?? 0);
        }
    }
}
?>

<div
    class="jt-internal-bar jt-internal-bar--<?= e($internalBarMode) ?>"
    role="banner"
    aria-label="<?= e($isDevBar ? 'Developer context' : 'Site admin context') ?>"
>
    <div class="jt-internal-bar-inner">
        <div class="jt-internal-bar-brand">
            <span class="jt-internal-bar-badge"><?= e($isDevBar ? 'DEV' : 'ADMIN') ?></span>
            <span class="jt-internal-bar-context d-none d-sm-inline"><?= e($contextLabel) ?></span>
            <span class="jt-internal-bar-context d-inline d-sm-none"><?= e($inWorkspace ? (current_business_label() !== '' ? current_business_label() : 'Workspace') : 'Global') ?></span>
        </div>

        <nav class="jt-internal-bar-nav d-none d-lg-flex" aria-label="Internal shortcuts">
            <?php if ($isDevBar): ?>
                <a class="jt-internal-bar-link" href="<?= e(url('/dev')) ?>"><i class="fas fa-code-branch fa-fw"></i> Dev Tracker</a>
                <a class="jt-internal-bar-link" href="<?= e(url('/dev/create?type=bug')) ?>"><i class="fas fa-bug fa-fw"></i> Log Bug</a>
                <a class="jt-internal-bar-link" href="<?= e(url('/dev?type=bug')) ?>"><i class="fas fa-list fa-fw"></i> Bugs<?php if ($openBugCount > 0): ?><span class="jt-internal-bar-count"><?= e((string) $openBugCount) ?></span><?php endif; ?></a>
                <a class="jt-internal-bar-link" href="<?= e(url('/site-admin/businesses')) ?>"><i class="fas fa-building fa-fw"></i> Companies</a>
            <?php else: ?>
                <a class="jt-internal-bar-link" href="<?= e(url('/site-admin')) ?>"><i class="fas fa-chart-line fa-fw"></i> Platform</a>
                <a class="jt-internal-bar-link" href="<?= e(url('/site-admin/businesses')) ?>"><i class="fas fa-building fa-fw"></i> Companies</a>
                <a class="jt-internal-bar-link" href="<?= e(url('/admin/users?scope=company_users')) ?>"><i class="fas fa-users fa-fw"></i> Users</a>
                <a class="jt-internal-bar-link" href="<?= e(url('/dev')) ?>"><i class="fas fa-code-branch fa-fw"></i> Dev Tracker</a>
                <?php if ($inWorkspace): ?>
                    <a class="jt-internal-bar-link" href="<?= e(url('/admin')) ?>"><i class="fas fa-gear fa-fw"></i> Business Admin</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="jt-internal-bar-actions">
            <?php if ($switcherBusinesses !== []): ?>
                <div class="dropdown">
                    <button
                        class="jt-internal-bar-btn dropdown-toggle"
                        type="button"
                        id="jtCompanySwitcher"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="fas fa-right-left fa-fw"></i>
                        <span class="d-none d-md-inline"><?= e($inWorkspace ? 'Switch company' : 'Enter company') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end jt-internal-bar-menu" aria-labelledby="jtCompanySwitcher">
                        <li class="dropdown-header">Quick switch</li>
                        <?php foreach ($switcherBusinesses as $business): ?>
                            <?php
                            $switchId = (int) ($business['id'] ?? 0);
                            $switchName = trim((string) ($business['name'] ?? ''));
                            if ($switchId <= 0) {
                                continue;
                            }
                            $isCurrent = $switchId === $businessId;
                            ?>
                            <li>
                                <?php if ($isCurrent): ?>
                                    <span class="dropdown-item active jt-internal-bar-menu-current">
                                        <i class="fas fa-check fa-fw me-2"></i><?= e($switchName !== '' ? $switchName : ('Business #' . (string) $switchId)) ?>
                                    </span>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/site-admin/switch-business')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="business_id" value="<?= e((string) $switchId) ?>">
                                        <button class="dropdown-item" type="submit"><?= e($switchName !== '' ? $switchName : ('Business #' . (string) $switchId)) ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="<?= e(url('/site-admin/businesses')) ?>">All companies…</a></li>
                    </ul>
                </div>
            <?php elseif ($switcherBusinesses === []): ?>
                <a class="jt-internal-bar-btn" href="<?= e(url('/site-admin/businesses')) ?>">
                    <i class="fas fa-building fa-fw"></i>
                    <span class="d-none d-md-inline">Companies</span>
                </a>
            <?php endif; ?>

            <?php if ($isDevBar): ?>
                <a class="jt-internal-bar-btn" href="<?= e(url('/site-admin')) ?>" title="Site admin">
                    <i class="fas fa-shield-halved fa-fw"></i>
                    <span class="d-none d-md-inline">Admin</span>
                </a>
            <?php else: ?>
                <a class="jt-internal-bar-btn" href="<?= e(url('/dev')) ?>" title="Dev tracker">
                    <i class="fas fa-code-branch fa-fw"></i>
                    <span class="d-none d-md-inline">Dev</span>
                </a>
            <?php endif; ?>

            <?php if ($inWorkspace): ?>
                <form method="post" action="<?= e(url('/site-admin/exit-workspace')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button class="jt-internal-bar-btn" type="submit" title="Return to global site admin">
                        <i class="fas fa-arrow-up-right-from-square fa-fw"></i>
                        <span class="d-none d-md-inline">Exit workspace</span>
                    </button>
                </form>
            <?php endif; ?>

            <div class="dropdown d-lg-none">
                <button
                    class="jt-internal-bar-btn dropdown-toggle"
                    type="button"
                    id="jtInternalBarMenu"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Internal menu"
                >
                    <i class="fas fa-ellipsis-vertical fa-fw"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end jt-internal-bar-menu" aria-labelledby="jtInternalBarMenu">
                    <?php if ($isDevBar): ?>
                        <li><a class="dropdown-item" href="<?= e(url('/dev')) ?>">Dev Tracker</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/dev/create?type=bug')) ?>">Log Bug</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/dev?type=bug')) ?>">Bugs<?php if ($openBugCount > 0): ?> (<?= e((string) $openBugCount) ?>)<?php endif; ?></a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/site-admin/businesses')) ?>">Companies</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?= e(url('/site-admin')) ?>">Platform</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/site-admin/businesses')) ?>">Companies</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/admin/users?scope=company_users')) ?>">Users</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/dev')) ?>">Dev Tracker</a></li>
                        <?php if ($inWorkspace): ?>
                            <li><a class="dropdown-item" href="<?= e(url('/admin')) ?>">Business Admin</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
