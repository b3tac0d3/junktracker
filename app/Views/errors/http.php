<?php
$errorStatus = (int) ($errorStatus ?? 500);
$errorTitle = (string) ($errorTitle ?? 'Something went wrong');
$errorMessage = (string) ($errorMessage ?? 'The request could not be completed.');
$errorContext = is_array($errorContext ?? null) ? $errorContext : [];
?>
<div class="error-shell">
    <div class="error-card">
        <div class="error-status"><?= e((string) $errorStatus) ?></div>
        <h1 class="error-title"><?= e($errorTitle) ?></h1>
        <p class="error-message"><?= e($errorMessage) ?></p>

        <?php if ($errorContext !== []): ?>
            <div class="error-context">
                <?php foreach ($errorContext as $label => $value): ?>
                    <div class="error-context-row">
                        <span class="error-context-label"><?= e((string) $label) ?></span>
                        <span class="error-context-value"><?= e((string) $value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="error-actions">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to Dashboard</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('/login')) ?>">Login</a>
        </div>
    </div>
</div>
