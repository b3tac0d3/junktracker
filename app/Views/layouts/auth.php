<?php
    $pageTitle = $pageTitle ?? 'Authentication';
    $appVersion = trim((string) config('app.version', ''));
    $globalFlashMessages = [];
    foreach ([
        'success' => 'success',
        'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
    ] as $flashKey => $alertClass) {
        $message = flash($flashKey);
        if ($message !== null && trim((string) $message) !== '') {
            $globalFlashMessages[] = [
                'class' => $alertClass,
                'message' => (string) $message,
            ];
        }
    }
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
        <link href="<?= asset('css/styles.css') ?>" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <?php if (!empty($globalFlashMessages)): ?>
                        <div class="container-fluid px-4 pt-3">
                            <?php foreach ($globalFlashMessages as $flashItem): ?>
                                <div class="alert alert-<?= e((string) ($flashItem['class'] ?? 'info')) ?>" role="alert">
                                    <?= e((string) ($flashItem['message'] ?? '')) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?= $content ?>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
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
        <script src="<?= asset('js/flash-alerts.js') ?>"></script>
        <?= $pageScripts ?? '' ?>
    </body>
</html>
