<?php
    $pageTitle = $pageTitle ?? 'Dashboard';
    $currentUser = auth_user();
    $displayName = $currentUser ? trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) : 'Guest';
    $isPunchOnlyRole = $currentUser && (int) ($currentUser['role'] ?? -1) === 0;
    $showSiteAdminNav = $currentUser && has_role(4);
    $appVersion = trim((string) config('app.version', ''));
    $activeBusinessName = '';
    if ($showSiteAdminNav) {
        try {
            $activeBusiness = \App\Models\Business::findById(current_business_id());
            $activeBusinessName = trim((string) ($activeBusiness['name'] ?? ''));
        } catch (\Throwable) {
            $activeBusinessName = '';
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
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="<?= asset('css/styles.css') ?>" rel="stylesheet" />
        <link href="<?= asset('css/jt-theme.css') ?>" rel="stylesheet" />
        <?= $pageStyles ?? '' ?>
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand ps-3" href="<?= url('/') ?>">JunkTracker</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
            <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0" method="get" action="<?= url('/search') ?>">
                <div class="input-group">
                    <input
                        class="form-control"
                        id="globalNavSearchInput"
                        type="text"
                        name="q"
                        value="<?= e((string) ($_GET['q'] ?? '')) ?>"
                        placeholder="Search everything..."
                        aria-label="Search everything"
                        aria-describedby="btnNavbarSearch"
                    />
                    <button class="btn btn-primary" id="btnNavbarSearch" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <?php if (can_access('notifications', 'view')): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative nav-notification-link" href="<?= url('/notifications') ?>" title="Notifications">
                            <i class="fas fa-bell fa-fw"></i>
                            <?php if ($notificationUnread > 0): ?>
                                <span class="badge rounded-pill bg-danger nav-notification-badge">
                                    <?= e((string) $notificationUnread) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="<?= url('/settings') ?>">Settings</a></li>
                        <li><a class="dropdown-item" href="<?= url('/activity-log') ?>">Activity Log</a></li>
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
                                    <?php if ($notificationUnread > 0): ?>
                                        <span class="badge bg-danger ms-auto"><?= e((string) $notificationUnread) ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                            <?php if (can_access('tasks', 'view')): ?>
                                <a class="nav-link" href="<?= url('/tasks') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-list-check"></i></div>
                                    Tasks
                                </a>
                            <?php endif; ?>
                            <?php if ($canViewServiceSection): ?>
                                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseServiceMenu" aria-expanded="false" aria-controls="collapseServiceMenu">
                                    <div class="sb-nav-link-icon"><i class="fas fa-screwdriver-wrench"></i></div>
                                    Service
                                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                </a>
                                <div class="collapse" id="collapseServiceMenu" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <?php if (can_access('jobs', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/jobs') ?>"><i class="fas fa-briefcase me-1"></i>Jobs</a>
                                        <?php endif; ?>
                                        <?php if ($canViewPhotoLibrary): ?>
                                            <a class="nav-link" href="<?= url('/photos') ?>"><i class="fas fa-images me-1"></i>Photos</a>
                                        <?php endif; ?>
                                        <?php if (can_access('jobs', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/jobs/schedule') ?>"><i class="fas fa-calendar-days me-1"></i>Schedule Board</a>
                                        <?php endif; ?>
                                        <?php if (can_access('prospects', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/prospects') ?>"><i class="fas fa-user-plus me-1"></i>Prospects</a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            <?php endif; ?>
                            <?php if (can_access('sales', 'view')): ?>
                                <a class="nav-link" href="<?= url('/sales') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-sack-dollar"></i></div>
                                    Sales
                                </a>
                            <?php endif; ?>
                            <?php if ($canViewCustomersSection): ?>
                                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                                    <div class="sb-nav-link-icon"><i class="fas fa-address-book"></i></div>
                                    Customers
                                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                </a>
                                <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <?php if (can_access('companies', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/companies') ?>"><i class="fas fa-building me-1"></i>Companies</a>
                                        <?php endif; ?>
                                        <?php if (can_access('estates', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/estates') ?>"><i class="fas fa-house me-1"></i>Estates</a>
                                        <?php endif; ?>
                                        <?php if (can_access('clients', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/clients') ?>"><i class="fas fa-user me-1"></i>Clients</a>
                                        <?php endif; ?>
                                        <?php if (can_access('client_contacts', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/client-contacts') ?>"><i class="fas fa-phone me-1"></i>Client Contacts</a>
                                        <?php endif; ?>
                                        <?php if (can_access('consignors', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/consignors') ?>"><i class="fas fa-handshake me-1"></i>Consignors</a>
                                        <?php endif; ?>
                                        <?php if (can_access('contacts', 'view')): ?>
                                            <a class="nav-link" href="<?= url('/network') ?>"><i class="fas fa-address-card me-1"></i>Network</a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            <?php endif; ?>
                            <?php if (can_access('time_tracking', 'view')): ?>
                                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts1" aria-expanded="false" aria-controls="collapseLayouts1">
                                    <div class="sb-nav-link-icon"><i class="fas fa-clock"></i></div>
                                    Time
                                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                </a>
                                <div class="collapse" id="collapseLayouts1" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <?php if (!$isPunchOnlyRole): ?>
                                            <a class="nav-link" href="<?= url('/time-tracking') ?>"><i class="fas fa-business-time me-1"></i>Time Tracking</a>
                                        <?php endif; ?>
                                        <a class="nav-link" href="<?= url('/punch-clock') ?>"><i class="fas fa-user-clock me-1"></i>Punch Clock</a>
                                    </nav>
                                </div>
                            <?php endif; ?>
                            <!-- <a class="nav-link" href="<?= url('/time-tracking') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Time Tracking
                            </a>
                            <a class="nav-link" href="<?= url('/time-tracking/open') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>
                                Open Clock
                            </a> -->
                            <?php if (can_access('expenses', 'view')): ?>
                                <a class="nav-link" href="<?= url('/expenses') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div>
                                    Expenses
                                </a>
                            <?php endif; ?>
                            <?php if (can_access('employees', 'view')): ?>
                                <a class="nav-link" href="<?= url('/employees') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-id-badge"></i></div>
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
                                    <div class="sb-nav-link-icon"><i class="fas fa-user-shield"></i></div>
                                    Admin
                                </a>
                            <?php endif; ?>
                            <?php if ($showSiteAdminNav): ?>
                                <a class="nav-link" href="<?= url('/site-admin') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-building-shield"></i></div>
                                    Site Admin
                                </a>
                            <?php endif; ?>
                            <?php if (has_role(4)): ?>
                                <a class="nav-link" href="<?= url('/dev') ?>">
                                    <div class="sb-nav-link-icon"><i class="fas fa-code"></i></div>
                                    Dev
                                </a>
                            <?php endif; ?>
                            <!-- <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapsePages" aria-expanded="false" aria-controls="collapsePages">
                                <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                                Pages
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapsePages" aria-labelledby="headingTwo" data-bs-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav accordion" id="sidenavAccordionPages">
                                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#pagesCollapseAuth" aria-expanded="false" aria-controls="pagesCollapseAuth">
                                        Authentication
                                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                    </a>
                                    <div class="collapse" id="pagesCollapseAuth" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordionPages">
                                        <nav class="sb-sidenav-menu-nested nav">
                                            <a class="nav-link" href="<?= url('/login') ?>">Login</a>
                                            <a class="nav-link" href="<?= url('/register') ?>">Register</a>
                                            <a class="nav-link" href="<?= url('/forgot-password') ?>">Forgot Password</a>
                                        </nav>
                                    </div>
                                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#pagesCollapseError" aria-expanded="false" aria-controls="pagesCollapseError">
                                        Error
                                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                                    </a>
                                    <div class="collapse" id="pagesCollapseError" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordionPages">
                                        <nav class="sb-sidenav-menu-nested nav">
                                            <a class="nav-link" href="<?= url('/401') ?>">401 Page</a>
                                            <a class="nav-link" href="<?= url('/404') ?>">404 Page</a>
                                            <a class="nav-link" href="<?= url('/500') ?>">500 Page</a>
                                        </nav>
                                    </div>
                                </nav>
                            </div> -->
                            <!-- <div class="sb-sidenav-menu-heading">Addons</div>
                            <a class="nav-link" href="<?= url('/charts') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-chart-area"></i></div>
                                Charts
                            </a>
                            <a class="nav-link" href="<?= url('/tables') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-table"></i></div>
                                Tables
                            </a> -->
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Logged in as:</div>
                        <?= e($displayName !== '' ? $displayName : 'Guest') ?>
                        <?php if ($activeBusinessName !== ''): ?>
                            <div class="small text-muted mt-1"><?= e($activeBusinessName) ?></div>
                        <?php endif; ?>
                        <?php if ($appVersion !== ''): ?>
                            <div class="small text-muted mt-1">v<?= e($appVersion) ?></div>
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
                                    &middot; v<?= e($appVersion) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="<?= url('/privacy-policy') ?>">Privacy Policy</a>
                                &middot;
                                <a href="<?= url('/terms-and-conditions') ?>">Terms &amp; Conditions</a>
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
        <script src="<?= asset('js/datatable-external-top.js') ?>"></script>
        <script src="<?= asset('js/mobile-filter-accordion.js') ?>"></script>
        <script>
            (function () {
                const searchInput = document.getElementById('globalNavSearchInput');
                if (!searchInput) {
                    return;
                }

                document.addEventListener('keydown', function (event) {
                    const target = event.target;
                    const tagName = target && target.tagName ? target.tagName.toLowerCase() : '';
                    const isEditable = tagName === 'input' || tagName === 'textarea' || tagName === 'select' || (target && target.isContentEditable);
                    if (isEditable) {
                        return;
                    }

                    if (event.key === '/' || (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey))) {
                        event.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                });
            })();
        </script>
        <?= $pageScripts ?? '' ?>
    </body>
</html>
