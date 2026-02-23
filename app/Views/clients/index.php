<?php
$clients = $clients ?? [];
$query = (string) ($query ?? '');
$status = (string) ($status ?? 'active');

$activeFilterCount = count(array_filter([
    $query !== '',
    $status !== 'active',
]));

$currentPath = '/clients';
$currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Clients</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Clients</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/clients/new') ?>">
            <i class="fas fa-user-plus me-1"></i>
            Add Client
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
             data-bs-target="#clientsFilterCollapse" 
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" 
             aria-controls="clientsFilterCollapse" 
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
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="clientsFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/clients') ?>">
                    <input id="client_lookup_url" type="hidden" value="<?= e(url('/clients/lookup')) ?>" />
                    <div class="row g-3">
                        <div class="col-12 col-lg-9">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input
                                    id="client-search-input"
                                    class="form-control"
                                    type="text"
                                    name="q"
                                    list="clientSearchSuggestions"
                                    placeholder="Search by name, phone, email, company, city, state..."
                                    value="<?= e($query) ?>"
                                />
                                <datalist id="clientSearchSuggestions"></datalist>
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
                            <a class="btn btn-outline-secondary" href="<?= url('/clients') ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client list card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-address-book me-1"></i>
                <span class="fw-semibold">Client Directory</span>
            </div>
            <?php if (!empty($clients)): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($clients) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($clients)): ?>
                <div class="jt-empty-state py-5 text-center">
                    <div class="empty-icon-wrapper bg-3 mb-3">
                        <i class="fas fa-address-book fa-3x text-light-subtle"></i>
                    </div>
                    <h5 class="text-muted fw-normal">No clients found</h5>
                    <p class="text-muted small mb-4">Try adjusting your filters or add a new client.</p>
                    <a href="<?= url('/clients/new') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user-plus me-1"></i> Add Client
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="clientsTable" class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Company</th>
                                <th class="text-center">Status</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                $rowHref = url('/clients/' . ($client['id'] ?? ''));
                                $name = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
                                $isActive = empty($client['deleted_at']) && !empty($client['active']);
                                ?>
                                <tr onclick="window.location.href='<?= $rowHref ?>'" style="cursor: pointer;">
                                    <td><?= e((string) ($client['id'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none fw-semibold" href="<?= $rowHref ?>">
                                            <?= e($name !== '' ? $name : '—') ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_phone($client['phone'] ?? null)) ?></td>
                                    <td><small><?= e((string) (($client['email'] ?? '') !== '' ? $client['email'] : '—')) ?></small></td>
                                    <td><?= e((string) (($client['company_names'] ?? '') !== '' ? $client['company_names'] : '—')) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= e($isActive ? 'Active' : 'Inactive') ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= e(format_datetime($client['updated_at'] ?? null)) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
