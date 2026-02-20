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

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/clients') ?>">
                <input id="client_lookup_url" type="hidden" value="<?= e(url('/clients/lookup')) ?>" />
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                id="client-search-input"
                                class="form-control"
                                type="text"
                                name="q"
                                list="clientSearchSuggestions"
                                placeholder="Search clients by name, phone, email, company, city, state..."
                                value="<?= e((string) ($query ?? '')) ?>"
                            />
                            <datalist id="clientSearchSuggestions"></datalist>
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/clients') ?>">Clear</a>
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
            <i class="fas fa-address-book me-1"></i>
            Client Directory
        </div>
        <div class="card-body">
            <table id="clientsTable" class="js-card-list-source">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <?php
                            $rowHref = url('/clients/' . ($client['id'] ?? ''));
                            $name = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
                        ?>
                        <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                            <td data-href="<?= $rowHref ?>"><?= e((string) ($client['id'] ?? '')) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= $rowHref ?>">
                                    <?= e($name !== '' ? $name : '—') ?>
                                </a>
                            </td>
                            <td><?= e(format_phone($client['phone'] ?? null)) ?></td>
                            <td><?= e((string) (($client['email'] ?? '') !== '' ? $client['email'] : '—')) ?></td>
                            <td><?= e((string) (($client['company_names'] ?? '') !== '' ? $client['company_names'] : '—')) ?></td>
                            <td>
                                <?php if (empty($client['deleted_at']) && !empty($client['active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_datetime($client['updated_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
