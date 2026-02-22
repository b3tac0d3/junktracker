<?php
    $scope = (string) ($scope ?? 'open');
    $notifications = is_array($notifications ?? null) ? $notifications : [];
    $summary = is_array($summary ?? null) ? $summary : [];
    $userOptions = is_array($userOptions ?? null) ? $userOptions : [];
    $viewerRole = isset($viewerRole) ? (int) $viewerRole : 0;
    $subjectUserId = isset($subjectUserId) ? (int) $subjectUserId : (int) (auth_user_id() ?? 0);
    $canViewOtherUsers = $viewerRole === 99 || $viewerRole >= 2;
    $subjectUserLabel = 'My Notifications';
    foreach ($userOptions as $option) {
        if ((int) ($option['id'] ?? 0) === $subjectUserId) {
            $subjectUserLabel = (string) ($option['name'] ?? $subjectUserLabel);
            break;
        }
    }
    $scopes = [
        'open' => 'Open',
        'unread' => 'Unread',
        'dismissed' => 'Dismissed',
        'all' => 'All',
    ];
    $subjectQuery = $canViewOtherUsers ? '&user_id=' . $subjectUserId : '';
    $summaryUrls = [
        'total' => url('/notifications?scope=all' . $subjectQuery),
        'open' => url('/notifications?scope=open' . $subjectQuery),
        'unread' => url('/notifications?scope=unread' . $subjectQuery),
        'dismissed' => url('/notifications?scope=dismissed' . $subjectQuery),
    ];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Notification Center</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Notifications</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Back to Dashboard</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card h-100 position-relative <?= $scope === 'all' ? 'border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total</div>
                    <div class="h3 mb-0"><?= e((string) ((int) ($summary['total'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($summaryUrls['total']) ?>" aria-label="Show all notifications"></a>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 position-relative <?= $scope === 'open' ? 'border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Open</div>
                    <div class="h3 mb-0 text-primary"><?= e((string) ((int) ($summary['open'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($summaryUrls['open']) ?>" aria-label="Show open notifications"></a>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 position-relative <?= $scope === 'unread' ? 'border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Unread</div>
                    <div class="h3 mb-0 text-warning"><?= e((string) ((int) ($summary['unread'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($summaryUrls['unread']) ?>" aria-label="Show unread notifications"></a>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 position-relative <?= $scope === 'dismissed' ? 'border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Dismissed</div>
                    <div class="h3 mb-0 text-secondary"><?= e((string) ((int) ($summary['dismissed'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($summaryUrls['dismissed']) ?>" aria-label="Show dismissed notifications"></a>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="<?= url('/notifications') ?>">
                <div class="col-md-4">
                    <label class="form-label" for="scope">Filter</label>
                    <select class="form-select" id="scope" name="scope">
                        <?php foreach ($scopes as $scopeKey => $scopeLabel): ?>
                            <option value="<?= e($scopeKey) ?>" <?= $scope === $scopeKey ? 'selected' : '' ?>><?= e($scopeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($canViewOtherUsers): ?>
                    <div class="col-md-4">
                        <label class="form-label" for="user_id">User</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <?php foreach ($userOptions as $option): ?>
                                <?php $id = (int) ($option['id'] ?? 0); ?>
                                <option value="<?= e((string) $id) ?>" <?= $id === $subjectUserId ? 'selected' : '' ?>>
                                    <?= e((string) ($option['name'] ?? ('User #' . $id))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="<?= $canViewOtherUsers ? 'col-md-4' : 'col-md-8' ?> d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/notifications' . ($canViewOtherUsers ? '?user_id=' . $subjectUserId : '')) ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4 notifications-alerts-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="fas fa-bell me-1"></i>Alerts</span>
            <span class="small text-muted"><?= e($subjectUserLabel) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Alert</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="4" class="text-muted">No notifications in this view.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $row): ?>
                                <?php
                                    $severity = strtolower((string) ($row['severity'] ?? 'info'));
                                    $badgeClass = match ($severity) {
                                        'danger' => 'bg-danger',
                                        'warning' => 'bg-warning text-dark',
                                        'primary' => 'bg-primary',
                                        default => 'bg-info text-dark',
                                    };
                                    $isRead = !empty($row['is_read']);
                                    $isDismissed = !empty($row['is_dismissed']);
                                    $url = trim((string) ($row['url'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($isDismissed): ?>
                                            <span class="badge bg-secondary">Dismissed</span>
                                        <?php elseif ($isRead): ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge badge-unread-soft">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= e($badgeClass) ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($row['type'] ?? 'alert')))) ?></span></td>
                                    <td>
                                        <?php if ($url !== ''): ?>
                                            <a class="text-decoration-none fw-semibold" href="<?= url($url) ?>"><?= e((string) ($row['title'] ?? 'Alert')) ?></a>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= e((string) ($row['title'] ?? 'Alert')) ?></span>
                                        <?php endif; ?>
                                        <div class="small text-muted"><?= e((string) ($row['message'] ?? '')) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span><?= e(format_datetime($row['due_at'] ?? null)) ?></span>
                                            <div class="dropdown">
                                                <button
                                                    class="btn btn-link btn-sm p-0 border-0 text-muted"
                                                    type="button"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                    title="Notification actions"
                                                >
                                                    <i class="fas fa-ellipsis"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <form method="post" action="<?= url('/notifications/read') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="notification_key" value="<?= e((string) ($row['key'] ?? '')) ?>" />
                                                            <input type="hidden" name="is_read" value="<?= $isRead ? '0' : '1' ?>" />
                                                            <input type="hidden" name="user_id" value="<?= e((string) $subjectUserId) ?>" />
                                                            <input type="hidden" name="return_to" value="<?= e('/notifications?scope=' . $scope . ($canViewOtherUsers ? '&user_id=' . $subjectUserId : '')) ?>" />
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas <?= $isRead ? 'fa-envelope me-2' : 'fa-envelope-open me-2' ?>"></i>
                                                                <?= $isRead ? 'Mark Unread' : 'Mark Read' ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="post" action="<?= url('/notifications/dismiss') ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="notification_key" value="<?= e((string) ($row['key'] ?? '')) ?>" />
                                                            <input type="hidden" name="dismiss" value="<?= $isDismissed ? '0' : '1' ?>" />
                                                            <input type="hidden" name="user_id" value="<?= e((string) $subjectUserId) ?>" />
                                                            <input type="hidden" name="return_to" value="<?= e('/notifications?scope=' . $scope . ($canViewOtherUsers ? '&user_id=' . $subjectUserId : '')) ?>" />
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas <?= $isDismissed ? 'fa-rotate-left me-2' : 'fa-xmark me-2' ?>"></i>
                                                                <?= $isDismissed ? 'Restore Notification' : 'Dismiss Notification' ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
