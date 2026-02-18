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

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/consignors') ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search consignors by name, phone, email, city, state..."
                                value="<?= e($query ?? '') ?>"
                            />
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/consignors') ?>">Clear</a>
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
            <i class="fas fa-handshake me-1"></i>
            Consignor Directory
        </div>
        <div class="card-body">
            <table id="consignorsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Consignor #</th>
                        <th>Consignor</th>
                        <th>Schedule</th>
                        <th>Next Due</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Est. Inventory</th>
                        <th>Contracts</th>
                        <th>Total Paid</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($consignors ?? []) as $consignor): ?>
                        <?php $rowHref = url('/consignors/' . (int) ($consignor['id'] ?? 0)); ?>
                        <tr data-href="<?= e($rowHref) ?>" style="cursor: pointer;">
                            <td data-href="<?= e($rowHref) ?>"><?= e((string) ($consignor['id'] ?? '')) ?></td>
                            <td><?= e((string) (($consignor['consignor_number'] ?? '') !== '' ? $consignor['consignor_number'] : '—')) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= e($rowHref) ?>">
                                    <?= e((string) ($consignor['display_name'] ?? '—')) ?>
                                </a>
                            </td>
                            <td><?= e((string) (($consignor['payment_schedule'] ?? '') !== '' ? ucfirst((string) $consignor['payment_schedule']) : '—')) ?></td>
                            <td><?= e(format_date($consignor['next_payment_due_date'] ?? null)) ?></td>
                            <td><?= e(format_phone($consignor['phone'] ?? null)) ?></td>
                            <td><?= e((string) (($consignor['email'] ?? '') !== '' ? $consignor['email'] : '—')) ?></td>
                            <td>
                                <?php
                                    $city = trim((string) ($consignor['city'] ?? ''));
                                    $stateVal = trim((string) ($consignor['state'] ?? ''));
                                    $location = trim($city . ($city !== '' && $stateVal !== '' ? ', ' : '') . $stateVal);
                                ?>
                                <?= e($location !== '' ? $location : '—') ?>
                            </td>
                            <td><?= e(isset($consignor['inventory_estimate_amount']) && $consignor['inventory_estimate_amount'] !== null ? ('$' . number_format((float) $consignor['inventory_estimate_amount'], 2)) : '—') ?></td>
                            <td><?= e((string) ((int) ($consignor['contract_count'] ?? 0))) ?></td>
                            <td class="text-success"><?= e('$' . number_format((float) ($consignor['total_paid'] ?? 0), 2)) ?></td>
                            <td>
                                <?php if (empty($consignor['deleted_at']) && !empty($consignor['active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_datetime($consignor['updated_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
