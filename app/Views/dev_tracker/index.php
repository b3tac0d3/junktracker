<?php

use App\Models\DevTrackerItem;

$search = trim((string) ($search ?? ''));
$statusFilter = strtolower(trim((string) ($statusFilter ?? 'active')));
$type = strtolower(trim((string) ($type ?? '')));
$priority = strtolower(trim((string) ($priority ?? '')));
$items = is_array($items ?? null) ? $items : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 50, count($items), count($items));
$perPage = (int) ($pagination['per_page'] ?? 50);
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : DevTrackerItem::typeOptions();
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : DevTrackerItem::statusOptions();
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : DevTrackerItem::priorityOptions();

$statusBadgeClass = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'in_progress' => 'text-bg-primary',
        'testing' => 'text-bg-info',
        'triage' => 'text-bg-warning',
        'done' => 'text-bg-success',
        'wont_fix' => 'text-bg-secondary',
        default => 'text-bg-light text-dark border',
    };
};

$typeBadgeClass = static function (string $type): string {
    return match (strtolower(trim($type))) {
        'bug' => 'text-bg-danger',
        'update' => 'text-bg-info',
        'feature' => 'text-bg-primary',
        'note' => 'text-bg-secondary',
        default => 'text-bg-light text-dark border',
    };
};

$priorityBadgeClass = static function (string $priority): string {
    return match (strtolower(trim($priority))) {
        'urgent' => 'text-bg-danger',
        'high' => 'text-bg-warning text-dark',
        'low' => 'text-bg-light text-dark border',
        default => 'text-bg-secondary',
    };
};

$activeCount = 0;
foreach ($statusOptions as $statusOption) {
    if (!in_array($statusOption, ['done', 'wont_fix'], true)) {
        $activeCount += (int) ($summary[$statusOption] ?? 0);
    }
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Dev Tracker</h1>
        <p class="muted">Running list of bugs, updates, and notes — track progress as you build.</p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-outline-primary w-100 w-md-auto" href="<?= e(url('/dev/create?type=bug')) ?>"><i class="fas fa-bug me-2"></i>Log Bug</a>
        <a class="btn btn-outline-primary w-100 w-md-auto" href="<?= e(url('/dev/create?type=update')) ?>"><i class="fas fa-wrench me-2"></i>Log Update</a>
        <a class="btn btn-primary w-100 w-md-auto" href="<?= e(url('/dev/create')) ?>"><i class="fas fa-plus me-2"></i>Add Item</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Active</span>
                <span class="record-value"><?= e((string) $activeCount) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">In Progress</span>
                <span class="record-value"><?= e((string) ((int) ($summary['in_progress'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Testing</span>
                <span class="record-value"><?= e((string) ((int) ($summary['testing'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Done</span>
                <span class="record-value"><?= e((string) ((int) ($summary['done'] ?? 0))) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/dev')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="dev-search">Search</label>
                <input id="dev-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Title, notes, area, or id..." />
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold" for="dev-status">Status</label>
                <select id="dev-status" class="form-select" name="status">
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold" for="dev-type">Type</label>
                <select id="dev-type" class="form-select" name="type">
                    <option value="">All types</option>
                    <?php foreach ($typeOptions as $typeOption): ?>
                        <option value="<?= e($typeOption) ?>" <?= $type === $typeOption ? 'selected' : '' ?>><?= e(DevTrackerItem::typeLabel($typeOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold" for="dev-priority">Priority</label>
                <select id="dev-priority" class="form-select" name="priority">
                    <option value="">All priorities</option>
                    <?php foreach ($priorityOptions as $priorityOption): ?>
                        <option value="<?= e($priorityOption) ?>" <?= $priority === $priorityOption ? 'selected' : '' ?>><?= e(DevTrackerItem::priorityLabel($priorityOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/dev')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-code-branch me-2"></i>Items</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($items)))) ?> record(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if ($items === []): ?>
            <div class="record-empty p-4">No items match your filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col" class="d-none d-md-table-cell">Area</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-lg-table-cell">Updated</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            if (!is_array($item)) {
                                continue;
                            }
                            $itemId = (int) ($item['id'] ?? 0);
                            $itemType = trim((string) ($item['item_type'] ?? ''));
                            $itemStatus = trim((string) ($item['status'] ?? ''));
                            $itemPriority = trim((string) ($item['priority'] ?? ''));
                            $itemTitle = trim((string) ($item['title'] ?? ''));
                            $itemArea = trim((string) ($item['area'] ?? ''));
                            $updatedRaw = trim((string) ($item['updated_at'] ?? ''));
                            $updatedStamp = $updatedRaw !== '' ? strtotime($updatedRaw) : false;
                            $updatedDisplay = $updatedStamp === false ? '—' : date('m/d/Y', $updatedStamp);
                            ?>
                            <tr>
                                <td>
                                    <a class="fw-bold text-decoration-none" href="<?= e(url('/dev/' . (string) $itemId)) ?>"><?= e($itemTitle) ?></a>
                                    <div class="small mt-1">
                                        <span class="badge <?= e($typeBadgeClass($itemType)) ?>"><?= e(DevTrackerItem::typeLabel($itemType)) ?></span>
                                        <span class="badge <?= e($priorityBadgeClass($itemPriority)) ?>"><?= e(DevTrackerItem::priorityLabel($itemPriority)) ?></span>
                                        <span class="text-muted">#<?= e((string) $itemId) ?></span>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($itemArea !== '' ? $itemArea : '—') ?></td>
                                <td>
                                    <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/quick-status')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/dev')) ?>">
                                        <select name="status" class="form-select form-select-sm" style="min-width: 8.5rem;" onchange="this.form.submit()" aria-label="Status for <?= e($itemTitle) ?>">
                                            <?php foreach ($statusOptions as $statusOption): ?>
                                                <option value="<?= e($statusOption) ?>" <?= $itemStatus === $statusOption ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="d-none d-lg-table-cell small text-muted"><?= e($updatedDisplay) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/dev/' . (string) $itemId)) ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <?php
        $basePath = '/dev';
        $fixedQueryParams = array_filter([
            'q' => $search,
            'status' => $statusFilter,
            'type' => $type,
            'priority' => $priority,
        ], static fn ($value): bool => $value !== '');
        require base_path('app/Views/components/index_pagination.php');
        ?>
    </div>
</section>
