<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Companies</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Companies</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/companies/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Company
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/companies') ?>">
                <input id="company_lookup_url" type="hidden" value="<?= e(url('/companies/lookup')) ?>" />
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                id="company-search-input"
                                class="form-control"
                                type="text"
                                name="q"
                                list="companySearchSuggestions"
                                placeholder="Search companies by name, phone, website, city, state..."
                                value="<?= e($query ?? '') ?>"
                            />
                            <datalist id="companySearchSuggestions"></datalist>
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/companies') ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <select class="form-select" name="status">
                            <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-building me-1"></i>
            Company Directory
        </div>
        <div class="card-body">
            <table id="companiesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Linked Clients</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                        <?php $rowHref = url('/companies/' . ($company['id'] ?? '')); ?>
                        <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                            <td data-href="<?= $rowHref ?>"><?= e((string) ($company['id'] ?? '')) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= $rowHref ?>">
                                    <?= e((string) ($company['name'] ?? '')) ?>
                                </a>
                            </td>
                            <td><?= e(format_phone($company['phone'] ?? null)) ?></td>
                            <td>
                                <?php
                                    $city = trim((string) ($company['city'] ?? ''));
                                    $state = trim((string) ($company['state'] ?? ''));
                                    $location = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
                                ?>
                                <?= e($location !== '' ? $location : '—') ?>
                            </td>
                            <td><?= e((string) ($company['client_count'] ?? 0)) ?></td>
                            <td>
                                <?php if (empty($company['deleted_at']) && !empty($company['active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_datetime($company['updated_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
