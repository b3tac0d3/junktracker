<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Estates</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Estates</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/estates/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Estate
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php
    $activeFilterCount = count(array_filter([
        !empty($query),
        ($status ?? 'active') !== 'active',
    ]));
    ?>

    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" data-bs-toggle="collapse" data-bs-target="#estatesFilterCollapse" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="estatesFilterCollapse" style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="estatesFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/estates') ?>">
                    <input id="estate_lookup_url" type="hidden" value="<?= e(url('/estates/lookup')) ?>" />
                    <div class="row g-3">
                        <div class="col-12 col-lg-7">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input
                                    id="estate-search-input"
                                    class="form-control"
                                    type="text"
                                    name="q"
                                    list="estateSearchSuggestions"
                                    placeholder="Search by name, client, phone, email..."
                                    value="<?= e((string) ($query ?? '')) ?>"
                                />
                                <datalist id="estateSearchSuggestions"></datalist>
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/estates') ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-house me-1"></i>
            Estate Directory
        </div>
        <div class="card-body">
            <?php if (empty($estates)): ?>
                <div class="text-muted text-center py-4">
                    <i class="fas fa-ghost fa-3x mb-3 d-block"></i>
                    No estates found matching your filters.
                </div>
            <?php else: ?>
                <table id="estatesTable" class="js-card-list-source">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Primary Client</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estates as $estate): ?>
                            <?php $rowHref = url('/estates/' . ($estate['id'] ?? '')); ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($estate['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none" href="<?= $rowHref ?>">
                                        <?= e((string) ($estate['name'] ?? '')) ?>
                                    </a>
                                </td>
                                <td><?= e((string) (($estate['primary_client_name'] ?? '') !== '' ? $estate['primary_client_name'] : '—')) ?></td>
                                <td><?= e(format_phone($estate['phone'] ?? null)) ?></td>
                                <td><?= e((string) (($estate['email'] ?? '') !== '' ? $estate['email'] : '—')) ?></td>
                                <td>
                                    <?php
                                    $city = trim((string) ($estate['city'] ?? ''));
                                    $state = trim((string) ($estate['state'] ?? ''));
                                    $location = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
                                    ?>
                                    <?= e($location !== '' ? $location : '—') ?>
                                </td>
                                <td>
                                    <?php if (empty($estate['deleted_at']) && !empty($estate['active'])): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(format_datetime($estate['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
