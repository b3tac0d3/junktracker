<?php
$logEntries = is_array($logEntries ?? null) ? $logEntries : [];
$showAddForm = (bool) ($showAddForm ?? true);
$addLogUrl = trim((string) ($addLogUrl ?? ''));
$createdEntryLabel = trim((string) ($createdEntryLabel ?? ''));
?>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-clock-rotate-left me-2"></i>Activity Log</strong>
        <?php if ($showAddForm && $addLogUrl !== ''): ?>
            <span class="small muted">Newest at the bottom</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($logEntries === []): ?>
            <div class="record-empty">No activity yet.</div>
        <?php else: ?>
            <ol class="jt-bug-log">
                <?php foreach ($logEntries as $entry): ?>
                    <?php
                    if (!is_array($entry)) {
                        continue;
                    }
                    $entryType = trim((string) ($entry['entry_type'] ?? 'comment'));
                    $body = trim((string) ($entry['body'] ?? ''));
                    $statusFrom = trim((string) ($entry['status_from'] ?? ''));
                    $statusTo = trim((string) ($entry['status_to'] ?? ''));
                    $screenshotPath = trim((string) ($entry['screenshot_path'] ?? ''));
                    $screenshotUrl = dev_tracker_screenshot_url($screenshotPath);
                    $createdDisplay = format_datetime((string) ($entry['created_at'] ?? null));
                    $entryTypeLabel = $entryType === 'created' && $createdEntryLabel !== ''
                        ? $createdEntryLabel
                        : \App\Models\DevTrackerLog::entryTypeLabel($entryType);
                    ?>
                    <li class="jt-bug-log-entry jt-bug-log-entry--<?= e($entryType) ?>">
                        <div class="jt-bug-log-marker" aria-hidden="true"></div>
                        <div class="jt-bug-log-content">
                            <div class="jt-bug-log-head">
                                <span class="jt-bug-log-type"><?= e($entryTypeLabel) ?></span>
                                <span class="jt-bug-log-meta">
                                    <?= e(\App\Models\DevTrackerLog::authorLabel($entry)) ?>
                                    ·
                                    <?= e($createdDisplay) ?>
                                </span>
                            </div>
                            <?php if ($entryType === 'status_change' && ($statusFrom !== '' || $statusTo !== '')): ?>
                                <div class="jt-bug-log-status-change">
                                    <?= e(\App\Models\DevTrackerItem::statusLabel($statusFrom !== '' ? $statusFrom : '—')) ?>
                                    <i class="fas fa-arrow-right mx-1" aria-hidden="true"></i>
                                    <?= e(\App\Models\DevTrackerItem::statusLabel($statusTo !== '' ? $statusTo : '—')) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($body !== ''): ?>
                                <div class="jt-bug-log-body"><?= nl2br(e($body)) ?></div>
                            <?php endif; ?>
                            <?php if ($screenshotUrl !== null): ?>
                                <div class="jt-bug-log-screenshot">
                                    <?php
                                    $url = $screenshotUrl;
                                    $caption = $entryTypeLabel;
                                    $alt = 'Screenshot attachment';
                                    require base_path('app/Views/components/screenshot_thumbnail.php');
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($showAddForm && $addLogUrl !== ''): ?>
            <hr class="my-4" />
            <form method="post" action="<?= e($addLogUrl) ?>" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="bug-log-body">Add update</label>
                    <textarea id="bug-log-body" name="body" class="form-control" rows="4" placeholder="What changed, steps to reproduce, or extra context..."></textarea>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold" for="bug-log-screenshot">Screenshot</label>
                    <input id="bug-log-screenshot" type="file" name="screenshot" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" />
                    <div class="form-text">Optional. PNG, JPG, GIF, or WebP up to 5 MB.</div>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Post Update</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
