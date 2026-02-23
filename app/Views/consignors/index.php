<?php
$consignors = $consignors ?? [];
$query = (string) ($query ?? '');
$status = (string) ($status ?? 'active');

$activeFilterCount = count(array_filter([
    $query !== '',
    $status !== 'active',
]));

$currentPath = '/consignors';
$currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Consignors</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Consignors</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/consignors/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Consignor
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
        <div class="card-header d-flex align-items-center justify-content-between py-2" 
             data-bs-toggle="collapse" 
             data-bs-target="#consignorsFilterCollapse" 
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" 
             aria-controls="consignorsFilterCollapse" 
             style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="consignorsFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/consignors') ?>">
                    <div class="row g-3">
                        <div class="col-12 col-lg-9">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="q"
                                    placeholder="Search by name, phone, email, city, state..."
                                    value="<?= e($query) ?>"
                                />
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/consignors') ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Consignor list card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-handshake me-1"></i>
                <span class="fw-semibold">Consignor Directory</span>
            </div>
            <?php if (!empty($consignors)): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($consignors) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($consignors)): ?>
                <div class="jt-empty-state py-5 text-center">
                    <div class="empty-icon-wrapper bg-3 mb-3">
                        <i class="fas fa-handshake fa-3x text-light-subtle"></i>
                    </div>
                    <h5 class="text-muted fw-normal">No consignors found</h5>
                    <p class="text-muted small mb-4">Try adjusting your filters or add a new consignor.</p>
                    <a href="<?= url('/consignors/new') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Consignor
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="consignorsTable" class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>#</th>
                                <th>Consignor</th>
                                <th>Schedule</th>
                                <th>Next Due</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th class="text-end">Total Paid</th>
                                <th class="text-center">Status</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consignors as $consignor): ?>
                                <?php
                                $rowHref = url('/consignors/' . (int) ($consignor['id'] ?? 0));
                                $isActive = empty($consignor['deleted_at']) && !empty($consignor['active']);
                                ?>
                                <tr onclick="window.location.href='<?= e($rowHref) ?>'" style="cursor: pointer;">
                                    <td><?= e((string) ($consignor['id'] ?? '')) ?></td>
                                    <td><small class="text-muted"><?= e((string) (($consignor['consignor_number'] ?? '') !== '' ? $consignor['consignor_number'] : '—')) ?></small></td>
                                    <td>
                                        <a class="text-decoration-none fw-semibold" href="<?= e($rowHref) ?>">
                                            <?= e((string) ($consignor['display_name'] ?? '—')) ?>
                                        </a>
                                    </td>
                                    <td><small><?= e((string) (($consignor['payment_schedule'] ?? '') !== '' ? ucfirst((string) $consignor['payment_schedule']) : '—')) ?></small></td>
                                    <td><?= e(format_date($consignor['next_payment_due_date'] ?? null)) ?></td>
                                    <td><?= e(format_phone($consignor['phone'] ?? null)) ?></td>
                                    <td><small><?= e((string) (($consignor['email'] ?? '') !== '' ? $consignor['email'] : '—')) ?></small></td>
                                    <td class="text-end text-success fw-bold"><?= e('$' . number_format((float) ($consignor['total_paid'] ?? 0), 2)) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= e($isActive ? 'Active' : 'Inactive') ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= e(format_datetime($consignor['updated_at'] ?? null)) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
