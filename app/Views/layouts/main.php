<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$currentUser = auth_user();
$displayName = $currentUser ? trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) : 'Guest';
$isPunchOnlyRole = $currentUser && (int) ($currentUser['role'] ?? -1) === 0;
$showSiteAdminNav = $currentUser && has_role(4);
$appVersion = trim((string) config('app.version', ''));
$activeBusinessName = '';
$siteAdminSupportUnread = 0;
if ($showSiteAdminNav) {
    try {
        $activeBusiness = \App\Models\Business::findById(current_business_id());
        $activeBusinessName = trim((string) ($activeBusiness['name'] ?? ''));
    } catch (\Throwable) {
        $activeBusinessName = '';
    }
    try {
        $siteAdminSupportUnread = \App\Models\SiteAdminTicket::adminUnreadCount();
    } catch (\Throwable) {
        $siteAdminSupportUnread = 0;
    }
}
$notificationUnread = 0;
if ($currentUser && can_access('notifications', 'view')) {
    try {
        $notificationUnread = \App\Models\NotificationCenter::unreadCount((int) ($currentUser['id'] ?? 0));
    } catch (\Throwable) {
        $notificationUnread = 0;
    }
}

$canViewCustomersSection = can_access('customers', 'view')
    || can_access('clients', 'view')
    || can_access('estates', 'view')
    || can_access('companies', 'view')
    || can_access('client_contacts', 'view')
    || can_access('contacts', 'view')
    || can_access('consignors', 'view');

$canViewPhotoLibrary = can_access('jobs', 'view')
    || can_access('clients', 'view')
    || can_access('prospects', 'view')
    || can_access('sales', 'view');

$canViewServiceSection = can_access('jobs', 'view')
    || can_access('prospects', 'view')
    || $canViewPhotoLibrary;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <?php if (config('app.noindex', true)): ?>
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex" />
        <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex" />
    <?php endif; ?>
    <title><?= e($pageTitle) ?> - JunkTracker</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="<?= asset('css/styles.css') ?>" rel="stylesheet" />
    <link href="<?= asset('css/jt-theme.css') ?>" rel="stylesheet" />
    <?= $pageStyles ?? '' ?>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark">
        <!-- Navbar Brand-->
        <a class="navbar-brand ps-3" href="<?= url('/') ?>">JunkTracker</a>
        <!-- Sidebar Toggle-->
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
        <!-- Navbar Search-->
        <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0" onsubmit="event.preventDefault(); window.location.href='<?= url('/search?q=') ?>' + encodeURIComponent(document.getElementById('globalNavSearchInput').value);">
            <div class="input-group">
                <input class="form-control" id="globalNavSearchInput" type="text" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Search everything..." aria-label="Search everything" aria-describedby="btnNavbarSearch" />
                <button class="btn btn-primary" id="btnNavbarSearch" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
        <!-- Navbar-->
        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
            <li class="nav-item">
                <a
                    class="nav-link position-relative nav-notification-link"
                    href="<?= url('/notifications') ?>"
                    data-sync-url="<?= url('/notifications/summary') ?>"
                >
                    <i class="fas fa-bell fa-fw"></i>
                    <span class="badge rounded-pill bg-danger nav-notification-badge <?= $notificationUnread > 0 ? '' : 'd-none' ?>">
                        <?= $notificationUnread > 99 ? '99+' : $notificationUnread ?>
                    </span>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="<?= url('/settings') ?>">Settings</a></li>
                    <li><a class="dropdown-item" href="<?= url('/activity-log') ?>">Activity Log</a></li>
                    <li><a class="dropdown-item" href="<?= url('/support/new') ?>">Contact Site Admin</a></li>
                    <li><a class="dropdown-item" href="<?= url('/support') ?>">My Site Requests</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li>
                        <form method="post" action="<?= url('/logout') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">Overview</div>
                        <?php if (can_access('dashboard', 'view')): ?>
                            <a class="nav-link" href="<?= url('/') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-gauge-high"></i></div>
                                Dashboard
                            </a>
                        <?php endif; ?>

                        <?php if (can_access('notifications', 'view')): ?>
                            <a class="nav-link" href="<?= url('/notifications') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-bell"></i></div>
                                Notifications
                                <span class="badge bg-danger ms-auto rounded-pill nav-notification-sidebar-badge <?= $notificationUnread > 0 ? '' : 'd-none' ?>">
                                    <?= $notificationUnread > 99 ? '99+' : $notificationUnread ?>
                                </span>
                            </a>
                        <?php endif; ?>

                        <?php if (can_access('tasks', 'view')): ?>
                            <a class="nav-link" href="<?= url('/tasks') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-check-double"></i></div>
                                Tasks
                            </a>
                        <?php endif; ?>

                        <?php if ($canViewServiceSection): ?>
                            <div class="sb-sidenav-menu-heading">Service</div>
                            <?php if (can_access('jobs', 'view')): ?>
                                <a class="nav-link" href="<?= url('/jobs') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-day"></i></div>
                                    Jobs
                                </a>
                            <?php endif; ?>
                            <?php if ($canViewPhotoLibrary): ?>
                                <a class="nav-link" href="<?= url('/photos') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-images"></i></div>
                                    Photos
                                </a>
                            <?php endif; ?>
                            <?php if (can_access('jobs', 'view')): ?>
                                <a class="nav-link" href="<?= url('/jobs/schedule') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
                                    Schedule Board
                                </a>
                            <?php endif; ?>
                            <?php if (can_access('prospects', 'view')): ?>
                                <a class="nav-link" href="<?= url('/prospects') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-user-plus"></i></div>
                                    Prospects
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (can_access('sales', 'view') || $canViewCustomersSection): ?>
                            <div class="sb-sidenav-menu-heading">Business</div>
                            <?php if (can_access('sales', 'view')): ?>
                                <a class="nav-link" href="<?= url('/sales') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div>
                                    Sales
                                </a>
                            <?php endif; ?>
                            <?php if (can_access('expenses', 'view')): ?>
                                <a class="nav-link" href="<?= url('/expenses') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-wallet"></i></div>
                                    Expenses
                                </a>
                            <?php endif; ?>

                            <?php if ($canViewCustomersSection): ?>
                                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseCustomers" aria-expanded="false" aria-controls="collapseCustomers">
                                    <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                                    Customers
                                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                </a>
                                <div class="collapse" id="collapseCustomers" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <?php if (can_access('companies', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/companies') ?>">Companies</a>
                                        <?php endif; ?>
                                        <?php if (can_access('estates', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/estates') ?>">Estates</a>
                                        <?php endif; ?>
                                        <?php if (can_access('clients', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/clients') ?>">Clients</a>
                                        <?php endif; ?>
                                        <?php if (can_access('client_contacts', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/client-contacts') ?>">Contacts</a>
                                        <?php endif; ?>
                                        <?php if (can_access('consignors', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/consignors') ?>">Consignors</a>
                                        <?php endif; ?>
                                        <?php if (can_access('contacts', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/network') ?>">Network</a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (can_access('time_tracking', 'view')): ?>
                            <div class="sb-sidenav-menu-heading">Operations</div>
                            <a class="nav-link" href="<?= url('/time-tracking') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-clock"></i></div>
                                Time Tracking
                            </a>
                            <a class="nav-link" href="<?= url('/punch-clock') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-stopwatch"></i></div>
                                Punch Clock
                            </a>
                        <?php endif; ?>

                        <div class="sb-sidenav-menu-heading">Admin</div>
                        <?php if (can_access('employees', 'view')): ?>
                            <a class="nav-link" href="<?= url('/employees') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-id-card"></i></div>
                                Employees
                            </a>
                        <?php endif; ?>
                        <?php if (can_access('reports', 'view')): ?>
                            <a class="nav-link" href="<?= url('/reports') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
                                Reports
                            </a>
                        <?php endif; ?>
                        <?php if (can_access('admin', 'view')): ?>
                            <a class="nav-link" href="<?= url('/admin') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-sliders-h"></i></div>
                                Admin
                            </a>
                        <?php endif; ?>

                        <div class="sb-sidenav-menu-heading">Dev</div>
                        <?php if ($showSiteAdminNav): ?>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSiteAdminMenu" aria-expanded="false" aria-controls="collapseSiteAdminMenu">
                                <div class="sb-nav-link-icon"><i class="fas fa-shield-alt"></i></div>
                                Site Admin
                                <?php if ($siteAdminSupportUnread > 0): ?>
                                    <span class="badge bg-danger ms-auto me-2"><?= e((string) $siteAdminSupportUnread) ?></span>
                                <?php endif; ?>
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseSiteAdminMenu" data-bs-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="<?= url('/site-admin') ?>">Businesses</a>
                                    <a class="nav-link d-flex align-items-center" href="<?= url('/site-admin/support') ?>">
                                        <span>Support Queue</span>
                                        <?php if ($siteAdminSupportUnread > 0): ?>
                                            <span class="ms-auto badge bg-danger"><?= e((string) $siteAdminSupportUnread) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </nav>
                            </div>
                        <?php endif; ?>
                        <?php if (auth_user_id() === 1): ?>
                            <a class="nav-link" href="<?= url('/dev') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-code"></i></div>
                                Dev
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sb-sidenav-footer">
                    <div class="small text-muted mb-1">Logged in as:</div>
                    <div class="fw-semibold text-white"><?= e($displayName !== '' ? $displayName : 'Guest') ?></div>
                    <?php if ($activeBusinessName !== ''): ?>
                        <div class="small text-muted mt-1 opacity-75"><?= e($activeBusinessName) ?></div>
                    <?php endif; ?>
                    <?php if ($appVersion !== ''): ?>
                        <div class="small mt-1 opacity-75 sidenav-version-tag" style="font-size: 0.65rem;">v<?= e($appVersion) ?></div>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <?= $content ?>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">
                            Copyright &copy; JunkTracker <?= date('Y') ?>
                            <?php if ($appVersion !== ''): ?>
                                &middot; <span class="footer-version-tag">v<?= e($appVersion) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="<?= url('/privacy-policy') ?>" class="text-decoration-none text-muted">Privacy Policy</a>
                            &middot;
                            <a href="<?= url('/terms-and-conditions') ?>" class="text-decoration-none text-muted">Terms &amp; Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= asset('js/scripts.js') ?>"></script>
    <script src="<?= asset('js/ajax-actions.js') ?>"></script>
    <script src="<?= asset('js/punch-geolocation.js') ?>"></script>
    <script src="<?= asset('js/card-list-component.js') ?>"></script>
    <script src="<?= asset('js/notification-badge-sync.js') ?>"></script>
    <script src="<?= asset('js/datatable-external-top.js') ?>"></script>
    <script src="<?= asset('js/mobile-filter-accordion.js') ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script src="<?= asset('js/datatables-simple-demo.js') ?>"></script>
    <?= $pageScripts ?? '' ?>
    <script>
        (function () {
            const searchInput = document.getElementById('globalNavSearchInput');
            if (!searchInput) { return; }
            document.addEventListener('keydown', function (event) {
                const target = event.target;
                const tagName = target && target.tagName ? target.tagName.toLowerCase() : '';
                const isEditable = tagName === 'input' || tagName === 'textarea' || tagName === 'select' || (target && target.isContentEditable);
                if (isEditable) { return; }
                if (event.key === '/' || (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey))) {
                    event.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        })();
    </script>
</body>
</html>
