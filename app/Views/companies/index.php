<?php
$query = $query ?? '';
$status = $status ?? 'active';
$activeFilterCount = 0;
if ($query !== '') $activeFilterCount++;
if ($status !== 'active') $activeFilterCount++;
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <div class="d-flex align-items-center">
                <i class="fas fa-building me-2 text-primary fs-4"></i>
                <h1 class="mb-0">Companies</h1>
            </div>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Companies</li>
            </ol>
        </div>
        <a class="btn btn-primary shadow-sm" href="<?= url('/companies/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Company
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card jt-filter-card mb-4 border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 py-2" 
             role="button" 
             data-bs-toggle="collapse" 
             data-bs-target="#filterCollapse" 
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-filter text-muted small"></i>
                    <span class="fw-semibold small text-uppercase tracking-wider">Filters</span>
                    <?php if ($activeFilterCount > 0): ?>
                        <span class="badge rounded-pill bg-primary px-2" style="font-size: 0.7rem;">
                            <?= $activeFilterCount ?>
                        </span>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-down collapse-icon small text-muted"></i>
            </div>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="filterCollapse">
            <div class="card-body pt-0">
                <form method="get" action="<?= url('/companies') ?>">
                    <input id="company_lookup_url" type="hidden" value="<?= e(url('/companies/lookup')) ?>" />
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label small fw-bold text-muted mb-1">Search</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input
                                    id="company-search-input"
                                    class="form-control border-start-0 ps-0"
                                    type="text"
                                    name="q"
                                    list="companySearchSuggestions"
                                    placeholder="Name, phone, city, state..."
                                    value="<?= e($query) ?>"
                                />
                                <datalist id="companySearchSuggestions"></datalist>
                                <?php if ($query !== ''): ?>
                                    <a class="btn btn-outline-secondary" href="<?= url('/companies') ?>">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label small fw-bold text-muted mb-1">Status</label>
                            <select class="form-select form-select-sm shadow-none" name="status">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                            <button class="btn btn-primary btn-sm flex-grow-1" type="submit">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center py-3 border-bottom">
            <i class="fas fa-building me-2 text-muted"></i>
            <span class="fw-bold text-uppercase tracking-wider small">Company Directory</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($companies)): ?>
                <div class="jt-empty-state py-5 text-center">
                    <div class="empty-icon-wrapper mb-3">
                        <i class="fas fa-building fa-3x text-light-subtle"></i>
                    </div>
                    <h5 class="text-muted fw-normal">No companies found</h5>
                    <p class="text-muted small mb-4">Try adjusting your filters or search terms</p>
                    <a href="<?= url('/companies/new') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Company
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="companiesTable" class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th class="ps-4 text-muted small py-3" style="width: 80px;">ID</th>
                                <th class="py-3">Name</th>
                                <th class="py-3">Phone</th>
                                <th class="py-3">Location</th>
                                <th class="py-3 text-end-numeric">Clients</th>
                                <th class="py-3">Status</th>
                                <th class="pe-4 py-3">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <?php 
                                    $rowHref = url('/companies/' . ($company['id'] ?? '')); 
                                    $isActive = empty($company['deleted_at']) && !empty($company['active']);
                                ?>
                                <tr onclick="window.location.href='<?= $rowHref ?>'" style="cursor: pointer;">
                                    <td class="ps-4 text-muted small font-monospace">
                                        #<?= e((string) ($company['id'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <a class="text-decoration-none fw-semibold text-dark" href="<?= $rowHref ?>">
                                            <?= e((string) ($company['name'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_phone($company['phone'] ?? null)) ?: '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php
                                        $city = trim((string) ($company['city'] ?? ''));
                                        $state = trim((string) ($company['state'] ?? ''));
                                        $location = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
                                        ?>
                                        <span class="small text-muted"><?= e($location !== '' ? $location : '—') ?></span>
                                    </td>
                                    <td class="text-end-numeric font-monospace">
                                        <?= e((string) ($company['client_count'] ?? 0)) ?>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 small text-muted">
                                        <?= e(format_datetime($company['updated_at'] ?? null)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
