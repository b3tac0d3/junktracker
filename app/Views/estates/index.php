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

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/estates') ?>">
                <input id="estate_lookup_url" type="hidden" value="<?= e(url('/estates/lookup')) ?>" />
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                id="estate-search-input"
                                class="form-control"
                                type="text"
                                name="q"
                                list="estateSearchSuggestions"
                                placeholder="Search estates by name, client, phone, email, city, state..."
                                value="<?= e((string) ($query ?? '')) ?>"
                            />
                            <datalist id="estateSearchSuggestions"></datalist>
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/estates') ?>">Clear</a>
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
            <i class="fas fa-house me-1"></i>
            Estate Directory
        </div>
        <div class="card-body">
            <table id="estatesTable">
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
        </div>
    </div>
</div>
