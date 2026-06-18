<?php
$stats = is_array($stats ?? null) ? $stats : [];

$companies = is_array($stats['companies'] ?? null) ? $stats['companies'] : [];
$users = is_array($stats['users'] ?? null) ? $stats['users'] : [];
$engagement = is_array($stats['engagement'] ?? null) ? $stats['engagement'] : [];
$devQueue = is_array($stats['dev_queue'] ?? null) ? $stats['dev_queue'] : [];
$growth = is_array($stats['growth'] ?? null) ? $stats['growth'] : [];
$recentLogins = is_array($stats['recent_logins'] ?? null) ? $stats['recent_logins'] : [];
$dailyTrend = is_array($stats['daily_trend'] ?? null) ? $stats['daily_trend'] : [];
$pendingReviewItems = is_array($stats['pending_review_items'] ?? null) ? $stats['pending_review_items'] : [];

$activeCompanies = (int) ($companies['active'] ?? 0);
$companyUsers = (int) ($users['company_users'] ?? 0);
$avgUsersPerCompany = $activeCompanies > 0 ? round($companyUsers / $activeCompanies, 1) : 0.0;

$loginDisplayName = static function (array $row): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }
    return trim((string) ($row['email'] ?? '')) !== '' ? (string) $row['email'] : ('User #' . (string) ((int) ($row['user_id'] ?? 0)));
};

$submitterDisplayName = static function (array $row) use ($loginDisplayName): string {
    return $loginDisplayName(['user_id' => $row['submitted_by'] ?? 0] + $row);
};

$todayLabel = date('l, F j, Y');
$maxTrendActive = 1;
foreach ($dailyTrend as $trendRow) {
    if (!is_array($trendRow)) {
        continue;
    }
    $maxTrendActive = max($maxTrendActive, (int) ($trendRow['active_users'] ?? 0));
}
?>

<div class="page-header site-admin-dashboard-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Platform Overview</h1>
        <p class="muted mb-0"><?= e($todayLabel) ?> · platform overview</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a class="btn btn-primary" href="<?= e(url('/site-admin/businesses/create')) ?>">
            <i class="fas fa-plus me-2"></i>Add Company
        </a>
    </div>
</div>

<div class="kpi-grid kpi-grid--compact site-admin-kpi-grid mb-3">
    <div class="kpi-card kpi-card--income">
        <span>Active Companies</span>
        <strong><?= e((string) $activeCompanies) ?><span class="kpi-card-subamount"> / <?= e((string) ((int) ($companies['all'] ?? 0))) ?></span></strong>
        <small><?= e((string) ((int) ($companies['inactive'] ?? 0))) ?> inactive · avg <?= e(number_format($avgUsersPerCompany, 1)) ?> users each</small>
    </div>
    <div class="kpi-card kpi-card--sales">
        <span>Company Users</span>
        <strong><?= e((string) $companyUsers) ?></strong>
        <small><?= e((string) ((int) ($users['site_admins'] ?? 0))) ?> site admins · <?= e((string) ((int) ($users['total_accounts'] ?? 0))) ?> total accounts</small>
    </div>
    <div class="kpi-card kpi-card--service">
        <span>Logins Today</span>
        <strong><?= e((string) ((int) ($engagement['logins_today'] ?? 0))) ?></strong>
        <small><?= e((string) ((int) ($engagement['logins_7d'] ?? 0))) ?> unique logins in the last 7 days</small>
    </div>
    <div class="kpi-card kpi-card--profit">
        <span>Active Users Today</span>
        <strong><?= e((string) ((int) ($engagement['active_users_today'] ?? 0))) ?></strong>
        <small>Includes stay-logged-in usage · <?= e((string) ((int) ($engagement['active_users_7d'] ?? 0))) ?> active in 7 days</small>
    </div>
</div>

<div class="kpi-grid kpi-grid--compact site-admin-kpi-grid mb-3">
    <div class="kpi-card kpi-card--receivables">
        <span>Activity Events Today</span>
        <strong><?= e((string) ((int) ($engagement['activity_events_today'] ?? 0))) ?></strong>
        <small><?= e((string) ((int) ($engagement['activity_events_7d'] ?? 0))) ?> tracked actions in 7 days</small>
    </div>
    <a class="kpi-card kpi-card-link kpi-card--purchases" href="<?= e(url('/dev?status=pending_review')) ?>">
        <span>Pending Dev Review</span>
        <strong><?= e((string) ((int) ($devQueue['pending_total'] ?? 0))) ?></strong>
        <small><?= e((string) ((int) ($devQueue['pending_bugs'] ?? 0))) ?> bugs · <?= e((string) ((int) ($devQueue['pending_updates'] ?? 0))) ?> update requests</small>
    </a>
    <div class="kpi-card kpi-card--estate-sales">
        <span>New Users (30 days)</span>
        <strong><?= e((string) ((int) ($growth['new_users_30d'] ?? 0))) ?></strong>
        <small><?= e((string) ((int) ($growth['new_memberships_30d'] ?? 0))) ?> new company memberships</small>
    </div>
    <div class="kpi-card kpi-card--expenses">
        <span>Stay-Logged-In Today</span>
        <strong><?= e((string) max(0, (int) ($engagement['active_users_today'] ?? 0) - (int) ($engagement['logins_today'] ?? 0))) ?></strong>
        <small>Active today without a fresh login event</small>
    </div>
</div>

<?php if ((int) ($devQueue['pending_total'] ?? 0) > 0 || $pendingReviewItems !== []): ?>
<section class="card index-card mb-3 site-admin-review-queue">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-clipboard-check me-2"></i>Needs Review</strong>
        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/dev?status=pending_review')) ?>">
            View all<?= (int) ($devQueue['pending_total'] ?? 0) > 0 ? ' (' . e((string) ((int) ($devQueue['pending_total'] ?? 0))) . ')' : '' ?>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if ($pendingReviewItems === []): ?>
            <div class="record-empty m-3">No submissions waiting for review.</div>
        <?php else: ?>
            <div class="site-admin-recent-list">
                <?php foreach ($pendingReviewItems as $reviewRow): ?>
                    <?php
                    if (!is_array($reviewRow)) {
                        continue;
                    }
                    $reviewId = (int) ($reviewRow['id'] ?? 0);
                    $reviewType = trim((string) ($reviewRow['item_type'] ?? ''));
                    $reviewTitle = trim((string) ($reviewRow['title'] ?? ''));
                    $businessName = trim((string) ($reviewRow['business_name'] ?? ''));
                    $reviewArea = trim((string) ($reviewRow['area'] ?? ''));
                    $typeLabel = $reviewType === 'update' ? 'Update' : 'Bug';
                    ?>
                    <div class="site-admin-recent-item site-admin-review-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="site-admin-recent-main">
                            <div class="site-admin-recent-title"><?= e($reviewTitle !== '' ? $reviewTitle : ('Item #' . (string) $reviewId)) ?></div>
                            <div class="site-admin-recent-meta muted small">
                                <span class="badge text-bg-warning text-dark"><?= e($typeLabel) ?></span>
                                <?php if ($businessName !== ''): ?> · <?= e($businessName) ?><?php endif; ?>
                                <?php if ($reviewArea !== ''): ?> · <?= e($reviewArea) ?><?php endif; ?>
                                · <?= e(format_datetime((string) ($reviewRow['created_at'] ?? null))) ?>
                                · <?= e($submitterDisplayName($reviewRow)) ?>
                            </div>
                        </div>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('/dev/' . (string) $reviewId)) ?>">Review</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <section class="card index-card h-100">
            <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-chart-column me-2"></i>7-Day Engagement</strong>
                <span class="small muted">Unique active users per day</span>
            </div>
            <div class="card-body">
                <?php if ($dailyTrend === []): ?>
                    <div class="record-empty">No activity recorded yet.</div>
                <?php else: ?>
                    <div class="site-admin-trend-chart">
                        <?php foreach ($dailyTrend as $trendRow): ?>
                            <?php
                            if (!is_array($trendRow)) {
                                continue;
                            }
                            $activeUsers = (int) ($trendRow['active_users'] ?? 0);
                            $logins = (int) ($trendRow['logins'] ?? 0);
                            $barHeight = $maxTrendActive > 0 ? max(8, (int) round(($activeUsers / $maxTrendActive) * 100)) : 8;
                            $isToday = ($trendRow['date'] ?? '') === date('Y-m-d');
                            ?>
                            <div class="site-admin-trend-day<?= $isToday ? ' is-today' : '' ?>">
                                <div class="site-admin-trend-bar-wrap" title="<?= e((string) $activeUsers) ?> active · <?= e((string) $logins) ?> logins">
                                    <div class="site-admin-trend-bar" style="height: <?= e((string) $barHeight) ?>%;"></div>
                                </div>
                                <div class="site-admin-trend-label"><?= e((string) ($trendRow['label'] ?? '')) ?></div>
                                <div class="site-admin-trend-value"><?= e((string) $activeUsers) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="small muted mt-3">Active users counts anyone with app activity today, including remember-me sessions without a new login.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="card index-card h-100">
            <div class="card-header index-card-header">
                <strong><i class="fas fa-right-to-bracket me-2"></i>Recent Sign-Ins</strong>
            </div>
            <div class="card-body p-0">
                <?php if ($recentLogins === []): ?>
                    <div class="record-empty m-3">No login events recorded yet.</div>
                <?php else: ?>
                    <div class="site-admin-recent-list">
                        <?php foreach ($recentLogins as $loginRow): ?>
                            <?php
                            if (!is_array($loginRow)) {
                                continue;
                            }
                            $businessName = trim((string) ($loginRow['business_name'] ?? ''));
                            $role = trim((string) ($loginRow['role'] ?? ''));
                            ?>
                            <div class="site-admin-recent-item">
                                <div class="site-admin-recent-main">
                                    <div class="site-admin-recent-title"><?= e($loginDisplayName($loginRow)) ?></div>
                                    <div class="site-admin-recent-meta muted small">
                                        <?= e(format_datetime((string) ($loginRow['created_at'] ?? null))) ?>
                                        <?php if ($businessName !== ''): ?> · <?= e($businessName) ?><?php endif; ?>
                                        <?php if ($role === 'site_admin'): ?> · Site admin<?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="card index-card">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="muted small">Exit the current workspace and return to global site admin context.</div>
        <form method="post" action="<?= e(url('/site-admin/exit-workspace')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit">Clear Workspace Context</button>
        </form>
    </div>
</section>
