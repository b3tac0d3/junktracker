<?php
    $summary = $summary ?? [];
    $filters = $filters ?? [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Sales</h1>
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

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Sales</div>
                    <div class="small text-muted mt-2">Gross</div>
                    <div class="h5 mb-0 text-success">
                        <?= e('$' . number_format((float) ($summary['gross_total'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted mt-2">Net</div>
                    <div class="h6 mb-0">
                        <?= e('$' . number_format((float) ($summary['net_total'] ?? 0), 2)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Scrap Sales</div>
                    <div class="small text-muted mt-2">Gross</div>
                    <div class="h5 mb-0 text-success">
                        <?= e('$' . number_format((float) ($summary['scrap_gross_total'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted mt-2">Net</div>
                    <div class="h6 mb-0">
                        <?= e('$' . number_format((float) ($summary['scrap_net_total'] ?? 0), 2)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Shop Sales</div>
                    <div class="small text-muted mt-2">Gross</div>
                    <div class="h5 mb-0 text-success">
                        <?= e('$' . number_format((float) ($summary['shop_gross_total'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted mt-2">Net</div>
                    <div class="h6 mb-0">
                        <?= e('$' . number_format((float) ($summary['shop_net_total'] ?? 0), 2)) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/sales') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search name, note, job, location..." value="<?= e((string) ($filters['q'] ?? '')) ?>" />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?= ($filters['type'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="shop" <?= ($filters['type'] ?? '') === 'shop' ? 'selected' : '' ?>>Shop</option>
                            <option value="scrap" <?= ($filters['type'] ?? '') === 'scrap' ? 'selected' : '' ?>>Scrap</option>
                            <option value="ebay" <?= ($filters['type'] ?? '') === 'ebay' ? 'selected' : '' ?>>eBay</option>
                            <option value="other" <?= ($filters['type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Start Date</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">End Date</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/sales') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-dollar-sign me-1"></i>
            Sales Table
        </div>
        <div class="card-body">
            <?php if (empty($sales ?? [])): ?>
                <div class="text-muted">No sales records found for this filter set.</div>
            <?php else: ?>
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Job</th>
                            <th>Date Range</th>
                            <th>Gross</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($sales ?? []) as $row): ?>
                            <?php
                                $rowType = (string) ($row['type'] ?? 'other');
                                $typeClass = match ($rowType) {
                                    'shop' => 'bg-primary',
                                    'scrap' => 'bg-info text-dark',
                                    'ebay' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                                $gross = (float) ($row['gross_amount'] ?? 0);
                                $net = $row['net_amount'] !== null ? (float) $row['net_amount'] : $gross;
                                $rowHref = url('/sales/' . (string) ($row['id'] ?? ''));
                            ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($row['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none fw-semibold" href="<?= $rowHref ?>">
                                        <?= e((string) (($row['name'] ?? '') !== '' ? $row['name'] : ('Sale #' . ($row['id'] ?? '')))) ?>
                                    </a>
                                    <?php if (!empty($row['note'])): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 22rem;"><?= e((string) $row['note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= e($typeClass) ?> text-uppercase"><?= e($rowType) ?></span></td>
                                <td>
                                    <?php if (!empty($row['job_id'])): ?>
                                        <a class="text-decoration-none" href="<?= url('/jobs/' . (string) $row['job_id']) ?>">
                                            <?= e((string) (($row['job_name'] ?? '') !== '' ? $row['job_name'] : ('Job #' . $row['job_id']))) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e(format_date($row['start_date'] ?? null)) ?>
                                    <?php if (!empty($row['end_date'])): ?>
                                        <span class="text-muted">to</span>
                                        <?= e(format_date($row['end_date'] ?? null)) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-success"><?= e('$' . number_format($gross, 2)) ?></td>
                                <td><?= e('$' . number_format($net, 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
