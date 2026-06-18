<?php
$release = is_array($release ?? null) ? $release : [];
$version = (string) ($release['version'] ?? '');
$date = trim((string) ($release['date'] ?? ''));
$items = is_array($release['items'] ?? null) ? $release['items'] : [];
$migrations = trim((string) ($release['migrations'] ?? ''));
$opsNotes = trim((string) ($release['ops_notes'] ?? ''));
$patchOn = trim((string) ($release['patch_on'] ?? ''));

$grouped = [
    'update' => [],
    'fix' => [],
    'patch' => [],
];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $type = (string) ($item['type'] ?? 'update');
    if (!isset($grouped[$type])) {
        $type = 'update';
    }
    $grouped[$type][] = $item;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div>
        <h1>Release v<?= e($version) ?></h1>
        <p class="muted mb-0">
            <?php if ($date !== ''): ?>Released <?= e(format_date($date)) ?><?php endif; ?>
            <?php if ($patchOn !== ''): ?><?= $date !== '' ? ' · ' : '' ?>Patch on v<?= e($patchOn) ?><?php endif; ?>
        </p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/releases')) ?>"><i class="fas fa-arrow-left me-2"></i>All releases</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <?php if ($grouped['update'] !== []): ?>
            <section class="card index-card mb-3">
                <div class="card-header index-card-header"><strong><i class="fas fa-wand-magic-sparkles me-2"></i>Updates</strong></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($grouped['update'] as $item): ?>
                        <li class="list-group-item"><?= e((string) ($item['text'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($grouped['fix'] !== []): ?>
            <section class="card index-card mb-3">
                <div class="card-header index-card-header"><strong><i class="fas fa-bug me-2"></i>Bug fixes</strong></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($grouped['fix'] as $item): ?>
                        <li class="list-group-item"><?= e((string) ($item['text'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($grouped['patch'] !== []): ?>
            <section class="card index-card mb-3">
                <div class="card-header index-card-header"><strong><i class="fas fa-band-aid me-2"></i>Patches</strong></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($grouped['patch'] as $item): ?>
                        <li class="list-group-item"><?= e((string) ($item['text'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($items === []): ?>
            <div class="card index-card mb-3">
                <div class="card-body record-empty">No highlight items recorded for this release.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if ($migrations !== ''): ?>
            <section class="card index-card mb-3">
                <div class="card-header index-card-header"><strong><i class="fas fa-database me-2"></i>Database migrations</strong></div>
                <div class="card-body">
                    <pre class="small mb-0 jt-release-pre"><?= e($migrations) ?></pre>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($opsNotes !== ''): ?>
            <section class="card index-card mb-3">
                <div class="card-header index-card-header"><strong><i class="fas fa-clipboard-check me-2"></i>Ops notes</strong></div>
                <div class="card-body">
                    <pre class="small mb-0 jt-release-pre"><?= e($opsNotes) ?></pre>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($release['source_file'])): ?>
            <p class="small text-muted mb-0">Source: <code>docs/releases/<?= e((string) $release['source_file']) ?></code></p>
        <?php endif; ?>
    </div>
</div>
