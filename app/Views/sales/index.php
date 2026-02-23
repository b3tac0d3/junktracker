<?php
$filters = $filters ?? [];
$activeFilterCount = count(array_filter([
    ($filters['q'] ?? '') !== '',
    ($filters['type'] ?? 'all') !== 'all',
    ($filters['record_status'] ?? 'active') !== 'active',
    ($filters['start_date'] ?? '') !== '',
    ($filters['end_date'] ?? '') !== '',
]));
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <div class="d-flex align-items-center">
                <i class="fas fa-receipt me-2 text-primary fs-4"></i>
                <h1 class="mb-0">Sales</h1>
            </div>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Sales</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/sales/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Sale
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" data-bs-toggle="collapse" data-bs-target="#salesFilterCollapse" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="salesFilterCollapse">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="salesFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/sales') ?>">
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input class="form-control" type="text" name="q" placeholder="Search name, note, job..." value="<?= e((string) ($filters['q'] ?? '')) ?>" />
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Type</label>
                            <select class="form-select" name="type">
                                <option value="all" <?= ($filters['type'] ?? 'all') == 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="shop" <?= ($filters['type'] ?? '') == 'shop' ? 'selected' : '' ?>>Shop</option>
                                <option value="scrap" <?= ($filters['type'] ?? '') == 'scrap' ? 'selected' : '' ?>>Scrap</option>
                                <option value="ebay" <?= ($filters['type'] ?? '') == 'ebay' ? 'selected' : '' ?>>eBay</option>
                                <option value="other" <?= ($filters['type'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Record Status</label>
                            <select class="form-select" name="record_status">
                                <option value="active" <?= ($filters['record_status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deleted" <?= ($filters['record_status'] ?? '') == 'deleted' ? 'selected' : '' ?>>Deleted</option>
                                <option value="all" <?= ($filters['record_status'] ?? '') == 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Start Date</label>
                            <input class="form-control" type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')) ?>">
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">End Date</label>
                            <input class="form-control" type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end align-items-end">
                            <a class="btn btn-outline-secondary btn-sm" href="<?= url('/sales') ?>">Clear</a>
                            <button class="btn btn-primary btn-sm px-4" type="submit">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-list me-2 text-primary opacity-75"></i>
            <span class="fw-semibold">Sales Log</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rows)): ?>
                <div class="jt-empty-state text-center">
                    <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                    <h4>No sales found</h4>
                    <p class="text-muted">No sales records found for this filter set.</p>
                    <a href="<?= url('/sales/new') ?>" class="btn btn-primary btn-sm mt-2">Add New Sale</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="salesTable" class="table table-hover mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Job</th>
                                <th>Date Range</th>
                                <th class="text-end-numeric">Gross</th>
                                <th class="text-end-numeric">Net</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $typeBadgeClass = match($row['type'] ?? '') {
                                    'shop' => 'bg-primary',
                                    'scrap' => 'bg-info text-dark',
                                    'ebay' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                                $gross = (float) ($row['gross_amount'] ?? 0);
                                $net = $row['net_amount'] !== null ? (float) $row['net_amount'] : $gross;
                                $rowHref = url('/sales/' . (string) ($row['id'] ?? ''));
                                ?>
                                <tr onclick="window.location.href='<?= $rowHref ?>'" style="cursor: pointer;">
                                    <td><?= e((string) ($row['id'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none fw-semibold" href="<?= $rowHref ?>"><?= e((string) ($row['display_name'] ?? 'Untitled Sale')) ?></a>
                                    </td>
                                    <td><span class="badge <?= $typeBadgeClass ?>"><?= e(ucfirst((string) ($row['type'] ?? ''))) ?></span></td>
                                    <td>
                                        <?php if (!empty($row['job_id'])): ?>
                                            <a class="text-decoration-none small" href="<?= url('/jobs/' . (string) $row['job_id']) ?>">#<?= e((string) $row['job_id']) ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= e(format_date($row['start_date'] ?? null)) ?> to <?= e(format_date($row['end_date'] ?? null)) ?>
                                    </td>
                                    <td class="text-end-numeric fw-500 font-monospace"><?= format_currency($gross) ?></td>
                                    <td class="text-end-numeric fw-semibold font-monospace"><?= format_currency($net) ?></td>
                                    <td class="small text-muted"><?= e(format_datetime($row['updated_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
