<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Employees</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Employees</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/employees/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Employee
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
            <form method="get" action="<?= url('/employees') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-9">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search name, phone, email..." value="<?= e($query ?? '') ?>" />
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                        <button class="btn btn-primary" type="submit">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/employees') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Employee List
        </div>
        <div class="card-body">
            <?php if (empty($employees ?? [])): ?>
                <div class="text-muted">No employees found.</div>
            <?php else: ?>
                <table id="employeesTable" class="js-card-list-source">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Hire Date</th>
                            <th>Pay Rate</th>
                            <th>Wage Type</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <?php
                                $fullName = trim(((string) ($employee['first_name'] ?? '')) . ' ' . ((string) ($employee['last_name'] ?? '')));
                                if ($fullName === '') {
                                    $fullName = 'Employee #' . (string) ($employee['id'] ?? '');
                                }
                                $isActive = empty($employee['deleted_at']) && !empty($employee['active']);
                            ?>
                            <?php $rowHref = url('/employees/' . (string) ($employee['id'] ?? '')); ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($employee['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none" href="<?= $rowHref ?>">
                                        <?= e($fullName) ?>
                                    </a>
                                </td>
                                <td><?= e(format_phone($employee['phone'] ?? null)) ?></td>
                                <td><?= e((string) (($employee['email'] ?? '') !== '' ? $employee['email'] : '—')) ?></td>
                                <td><?= e(format_date($employee['hire_date'] ?? null)) ?></td>
                                <td>
                                    <?php if (isset($employee['pay_rate']) && $employee['pay_rate'] !== null): ?>
                                        <?= e('$' . number_format((float) $employee['pay_rate'], 2)) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) (($employee['wage_type'] ?? '') !== '' ? ucfirst((string) $employee['wage_type']) : '—')) ?></td>
                                <td>
                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= e($isActive ? 'Active' : 'Inactive') ?>
                                    </span>
                                </td>
                                <td><?= e(format_datetime($employee['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
