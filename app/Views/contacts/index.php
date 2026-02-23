<?php
$contactTypeOptions = is_array($contactTypeOptions ?? null) ? $contactTypeOptions : [];
$contacts = $contacts ?? [];
$query = (string) ($query ?? '');
$type = (string) ($type ?? 'all');
$status = (string) ($status ?? 'active');

$activeFilterCount = count(array_filter([
    $query !== '',
    $type !== 'all',
    $status !== 'active',
]));

$currentPath = '/network';
$currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Network Clients</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Network</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/network/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Network Client
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
             data-bs-target="#networkFilterCollapse" 
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" 
             aria-controls="networkFilterCollapse" 
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
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="networkFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/network') ?>">
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="q"
                                    placeholder="Search network clients..."
                                    value="<?= e($query) ?>"
                                />
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Type</label>
                            <select class="form-select" name="type">
                                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All</option>
                                <?php foreach ($contactTypeOptions as $value => $label): ?>
                                    <option value="<?= e((string) $value) ?>" <?= $type === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2 d-flex gap-2 align-items-end mobile-two-col-buttons">
                            <button class="btn btn-primary w-100" type="submit">Apply</button>
                            <a class="btn btn-outline-secondary w-100" href="<?= url('/network') ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Network directory card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-address-book me-1"></i>
                <span class="fw-semibold">Network Directory</span>
            </div>
            <?php if (!empty($contacts)): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($contacts) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($contacts)): ?>
                <div class="jt-empty-state py-5 text-center">
                    <div class="empty-icon-wrapper bg-3 mb-3">
                        <i class="fas fa-address-book fa-3x text-light-subtle"></i>
                    </div>
                    <h5 class="text-muted fw-normal">No network clients found</h5>
                    <p class="text-muted small mb-4">Try adjusting your filters or add a new network client.</p>
                    <a href="<?= url('/network/new') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Network Client
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="contactsTable" class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Source</th>
                                <th class="text-center">Status</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <?php
                                $contactId = (int) ($contact['id'] ?? 0);
                                $rowHref = url('/network/' . $contactId);
                                $name = trim((string) ($contact['display_name'] ?? ''));
                                if ($name === '') {
                                    $name = trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')));
                                }
                                if ($name === '') {
                                    $name = (string) ($contact['email'] ?? '');
                                }
                                if ($name === '') {
                                    $name = 'Network Client #' . $contactId;
                                }
                                $sourceType = trim((string) ($contact['source_type'] ?? 'manual'));
                                $sourceId = (int) ($contact['source_id'] ?? 0);
                                $sourceLabel = ucfirst($sourceType);
                                if ($sourceType === 'manual' || $sourceType === '') {
                                    $sourceLabel = 'Manual';
                                } elseif ($sourceId > 0) {
                                    $sourceLabel .= ' #' . $sourceId;
                                }
                                $isInactive = !empty($contact['deleted_at']) || (int) ($contact['is_active'] ?? 1) !== 1;
                                ?>
                                <tr onclick="window.location.href='<?= e($rowHref) ?>'" style="cursor: pointer;">
                                    <td><?= e((string) $contactId) ?></td>
                                    <td>
                                        <a class="text-decoration-none fw-semibold" href="<?= e($rowHref) ?>">
                                            <?= e($name) ?>
                                        </a>
                                    </td>
                                    <td class="text-capitalize small"><?= e(str_replace('_', ' ', (string) ($contact['contact_type'] ?? 'general'))) ?></td>
                                    <td><?= e((string) (($contact['company_name'] ?? '') !== '' ? $contact['company_name'] : '—')) ?></td>
                                    <td><?= e(format_phone($contact['phone'] ?? null)) ?></td>
                                    <td><small><?= e((string) (($contact['email'] ?? '') !== '' ? $contact['email'] : '—')) ?></small></td>
                                    <td><small class="text-muted"><?= e($sourceLabel) ?></small></td>
                                    <td class="text-center">
                                        <span class="badge <?= !$isInactive ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= e(!$isInactive ? 'Active' : 'Inactive') ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= e(format_datetime($contact['updated_at'] ?? null)) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
