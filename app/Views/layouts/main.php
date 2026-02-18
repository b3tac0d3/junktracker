<?php
    $pageTitle = $pageTitle ?? 'Dashboard';
    $currentUser = auth_user();
    $displayName = $currentUser ? trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title><?= e($pageTitle) ?> - JunkTracker</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="<?= asset('css/styles.css') ?>" rel="stylesheet" />
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
                            <a class="nav-link" href="<?= url('/') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-gauge-high"></i></div>
                                Dashboard
                            </a>
                            <a class="nav-link" href="<?= url('/jobs') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-briefcase"></i></div>
                                Jobs
                            </a>
                            <a class="nav-link" href="<?= url('/prospects') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-plus"></i></div>
                                Prospects
                            </a>
                            <a class="nav-link" href="<?= url('/sales') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-sack-dollar"></i></div>
                                Sales
                            </a>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                                <div class="sb-nav-link-icon"><i class="fas fa-address-book"></i></div>
                                Customers
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                                <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <a class="nav-link" href="<?= url('/companies') ?>"><i class="fas fa-building me-1"></i>Companies</a>
                                        <a class="nav-link" href="<?= url('/estates') ?>"><i class="fas fa-house me-1"></i>Estates</a>
                                        <a class="nav-link" href="<?= url('/clients') ?>"><i class="fas fa-user me-1"></i>Clients</a>
                                        <a class="nav-link" href="<?= url('/client-contacts') ?>"><i class="fas fa-phone me-1"></i>Client Contacts</a>
                                        <a class="nav-link" href="<?= url('/consignors') ?>"><i class="fas fa-handshake me-1"></i>Consignors</a>
                                    </nav>
                                </div>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts1" aria-expanded="false" aria-controls="collapseLayouts1">
                                <div class="sb-nav-link-icon"><i class="fas fa-clock"></i></div>
                                Time
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                                <div class="collapse" id="collapseLayouts1" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                    <nav class="sb-sidenav-menu-nested nav">
                                        <a class="nav-link" href="<?= url('/time-tracking') ?>"><i class="fas fa-business-time me-1"></i>Time Tracking</a>
                                        <a class="nav-link" href="<?= url('/time-tracking/open') ?>"><i class="fas fa-user-clock me-1"></i>Punch Clock</a>
                                    </nav>
                                </div>
                            <!-- <a class="nav-link" href="<?= url('/time-tracking') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Time Tracking
                            </a>
                            <a class="nav-link" href="<?= url('/time-tracking/open') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>
                                Open Clock
                            </a> -->
                            <a class="nav-link" href="<?= url('/expenses') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div>
                                Expenses
                            </a>
                            <a class="nav-link" href="<?= url('/tasks') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-list-check"></i></div>
                                Tasks
                            </a>
                            <div class="sb-sidenav-menu-heading">Admin</div>
                            <a class="nav-link" href="<?= url('/admin/disposal-locations') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-recycle"></i></div>
                                Disposal Locations
                            </a>
                            <a class="nav-link" href="<?= url('/admin/expense-categories') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                                Expense Categories
                            </a>
                            <a class="nav-link" href="<?= url('/users') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                                Users
                            </a>
                            <a class="nav-link" href="<?= url('/employees') ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-id-badge"></i></div>
                                Employees
                            </a>
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
                            <div class="text-muted">Copyright &copy; JunkTracker <?= date('Y') ?></div>
                            <div>
                                <a href="#">Privacy Policy</a>
                                &middot;
                                <a href="#">Terms &amp; Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="<?= asset('js/scripts.js') ?>"></script>
        <?= $pageScripts ?? '' ?>
    </body>
</html>
