<?php
$releases = is_array($releases ?? null) ? $releases : [];
$currentVersion = trim((string) ($currentVersion ?? ''));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div>
        <h1>Release History</h1>
        <p class="muted mb-0">Build progress for the last <?= e((string) count($releases)) ?> releases — updates, patches, and bug fixes pulled from release notes.</p>
    </div>
    <?php if ($currentVersion !== ''): ?>
        <div class="text-md-end">
            <span class="badge text-bg-secondary">You are on v<?= e($currentVersion) ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($releases === []): ?>
    <div class="card index-card">
        <div class="card-body record-empty">No release notes found in <code>docs/releases/</code>.</div>
    </div>
<?php else: ?>
    <div class="record-list-simple">
        <?php foreach ($releases as $release): ?>
            <?php
            if (!is_array($release)) {
                continue;
            }
            $version = (string) ($release['version'] ?? '');
            $date = trim((string) ($release['date'] ?? ''));
            $summary = trim((string) ($release['summary'] ?? ''));
            $counts = is_array($release['counts'] ?? null) ? $release['counts'] : [];
            $isCurrent = $currentVersion !== '' && strcasecmp($currentVersion, $version) === 0;
            ?>
            <article class="record-row-simple">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="record-title-simple h5 mb-1">
                            <a href="<?= e(url('/releases/' . rawurlencode($version))) ?>">v<?= e($version) ?></a>
                            <?php if ($isCurrent): ?><span class="badge text-bg-success ms-1">Current</span><?php endif; ?>
                            <?php if (!empty($release['is_patch'])): ?><span class="badge text-bg-warning ms-1">Patch</span><?php endif; ?>
                        </h2>
                        <?php if ($date !== ''): ?>
                            <div class="small muted"><?= e(format_date($date)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php if (((int) ($counts['updates'] ?? 0)) > 0): ?>
                            <span class="badge text-bg-primary"><?= e((string) (int) $counts['updates']) ?> update<?= ((int) $counts['updates']) === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                        <?php if (((int) ($counts['fixes'] ?? 0)) > 0): ?>
                            <span class="badge text-bg-danger"><?= e((string) (int) $counts['fixes']) ?> fix<?= ((int) $counts['fixes']) === 1 ? '' : 'es' ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($summary !== ''): ?>
                    <p class="mb-2 small"><?= e($summary) ?></p>
                <?php endif; ?>

                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/releases/' . rawurlencode($version))) ?>">View details</a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
