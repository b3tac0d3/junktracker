<?php
$labels = is_array($labels ?? null) ? $labels : [];
$routePrefix = trim((string) ($routePrefix ?? '/admin/bug-reports'));
$createIcon = trim((string) ($createIcon ?? 'fa-bug'));
$search = trim((string) ($search ?? ''));
$items = is_array($items ?? null) ? $items : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($items), count($items));
$perPage = (int) ($pagination['per_page'] ?? 25);
$business = is_array($business ?? null) ? $business : [];
$businessName = trim((string) ($business['name'] ?? ''));
$sectionTitle = trim((string) ($labels['section'] ?? 'Submissions'));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($sectionTitle) ?></h1>
        <p class="muted mb-0"><?= e((string) ($labels['index_desc'] ?? '')) ?><?= $businessName !== '' ? ' · ' . e($businessName) : '' ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-primary w-100 w-md-auto" href="<?= e(url($routePrefix . '/create')) ?>">
            <i class="fas <?= e($createIcon) ?> me-2"></i><?= e((string) ($labels['create_title'] ?? 'Submit')) ?>
        </a>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url($routePrefix)) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-9">
                <label class="form-label fw-semibold" for="company-submission-search">Search</label>
                <input id="company-submission-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by title, description, area, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Search</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url($routePrefix)) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas <?= e($createIcon) ?> me-2"></i><?= e((string) ($labels['list_title'] ?? 'Submissions')) ?></strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($items)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = $routePrefix;
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($items === []): ?>
            <div class="record-empty"><?= e((string) ($labels['list_empty'] ?? 'No submissions yet.')) ?></div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($items as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $itemId = (int) ($row['id'] ?? 0);
                    $reviewStatus = trim((string) ($row['review_status'] ?? ''));
                    $itemStatus = trim((string) ($row['status'] ?? ''));
                    $badgeClass = match ($reviewStatus) {
                        'accepted' => 'text-bg-success',
                        'rejected' => 'text-bg-secondary',
                        default => 'text-bg-warning text-dark',
                    };
                    $badgeLabel = $reviewStatus !== ''
                        ? \App\Models\DevTrackerItem::reviewStatusLabel($reviewStatus)
                        : \App\Models\DevTrackerItem::statusLabel($itemStatus);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url($routePrefix . '/' . (string) $itemId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($row['title'] ?? ''))) ?></h3>
                                <div class="record-subline muted">#<?= e((string) $itemId) ?><?php $area = trim((string) ($row['area'] ?? '')); if ($area !== '') { echo ' · ' . e($area); } ?></div>
                            </div>
                            <div class="record-row-fields record-row-fields-3">
                                <div class="record-field">
                                    <span class="record-label">Review</span>
                                    <span class="record-value"><span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Dev Status</span>
                                    <span class="record-value"><?= e(\App\Models\DevTrackerItem::statusLabel($itemStatus)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Updated</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($row['updated_at'] ?? null))) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
