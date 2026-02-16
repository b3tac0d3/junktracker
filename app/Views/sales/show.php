<?php
    $sale = $sale ?? [];
    $saleType = (string) ($sale['type'] ?? 'other');
    $typeClass = match ($saleType) {
        'shop' => 'bg-primary',
        'scrap' => 'bg-info text-dark',
        'ebay' => 'bg-warning text-dark',
        default => 'bg-secondary',
    };
    $isActive = empty($sale['deleted_at']) && !empty($sale['active']);
    $grossAmount = (float) ($sale['gross_amount'] ?? 0);
    $netAmount = ($sale['net_amount'] ?? null) !== null ? (float) $sale['net_amount'] : $grossAmount;
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Sale Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/sales') ?>">Sales</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($sale['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isActive): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteSaleModal">
                    <i class="fas fa-trash me-1"></i>
                    Delete Sale
                </button>
            <?php endif; ?>
            <a class="btn btn-warning" href="<?= url('/sales/' . ($sale['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Sale
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/sales') ?>">Back to Sales</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-receipt me-1"></i>
            Sale Overview
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Name</div>
                    <div class="fw-semibold"><?= e((string) (($sale['name'] ?? '') !== '' ? $sale['name'] : '—')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Sale ID</div>
                    <div class="fw-semibold"><?= e((string) ($sale['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if ($isActive): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Type</div>
                    <div class="fw-semibold">
                        <span class="badge <?= e($typeClass) ?> text-uppercase"><?= e($saleType) ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Gross Amount</div>
                    <div class="fw-semibold text-success"><?= e('$' . number_format($grossAmount, 2)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Net Amount</div>
                    <div class="fw-semibold"><?= e('$' . number_format($netAmount, 2)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Date Range</div>
                    <div class="fw-semibold">
                        <?= e(format_date($sale['start_date'] ?? null)) ?>
                        <?php if (!empty($sale['end_date'])): ?>
                            <span class="text-muted">to</span>
                            <?= e(format_date($sale['end_date'] ?? null)) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Job</div>
                    <div class="fw-semibold">
                        <?php if (!empty($sale['job_id'])): ?>
                            <a class="text-decoration-none" href="<?= url('/jobs/' . (string) $sale['job_id']) ?>">
                                <?= e((string) (($sale['job_name'] ?? '') !== '' ? $sale['job_name'] : ('Job #' . $sale['job_id']))) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Client</div>
                    <div class="fw-semibold"><?= e((string) (($sale['client_name'] ?? '') !== '' ? $sale['client_name'] : '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Disposal Location</div>
                    <div class="fw-semibold"><?= e((string) (($sale['disposal_location_name'] ?? '') !== '' ? $sale['disposal_location_name'] : '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($sale['note'] ?? '') !== '' ? $sale['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($sale['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($sale['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($sale['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($sale['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($sale['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($sale['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($sale['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isActive): ?>
        <div class="modal fade" id="deleteSaleModal" tabindex="-1" aria-labelledby="deleteSaleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteSaleModalLabel">Delete Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the sale and hide it from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/sales/' . ($sale['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Delete Sale</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
