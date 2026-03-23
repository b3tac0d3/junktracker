<?php
$search = trim((string) ($search ?? ''));
$employees = is_array($employees ?? null) ? $employees : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($employees), count($employees));
$perPage = (int) ($pagination['per_page'] ?? 25);

$employeeName = static function (array $row): string {
    $linked = trim((string) ($row['linked_user_name'] ?? ''));
    if ($linked !== '') {
        return $linked;
    }

    $first = trim((string) ($row['first_name'] ?? ''));
    $last = trim((string) ($row['last_name'] ?? ''));
    $suffix = trim((string) ($row['suffix'] ?? ''));
    $full = trim($first . ' ' . $last . ($suffix !== '' ? (' ' . $suffix) : ''));
    if ($full !== '') {
        return $full;
    }

    return trim((string) ($row['email'] ?? '')) !== ''
        ? (string) $row['email']
        : ('Employee #' . (string) ((int) ($row['id'] ?? 0)));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Employees</h1>
        <p class="muted">Business employee directory</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/admin/employees/create')) ?>"><i class="fas fa-plus me-2"></i>Add Employee</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/employees')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-9">
                <label class="form-label fw-semibold" for="employees-search">Search</label>
                <input id="employees-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, linked user, email, phone, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/employees')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-user-clock me-2"></i>Employee List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($employees)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/employees';
        require base_path('app/Views/components/index_pagination.php');
        ?>

        <?php if ($employees === []): ?>
            <div class="record-empty">No employees found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $id = (int) ($employee['id'] ?? 0);
                    $linkedUserName = trim((string) ($employee['linked_user_name'] ?? ''));
                    $linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
                    $employeeDisplay = $employeeName($employee);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/admin/employees/' . (string) $id)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($employeeDisplay) ?></h3>
                                <?php if ($linkedUserName !== ''): ?>
                                    <div class="record-subline small muted">
                                        <span class="badge text-bg-primary">Linked User</span>
                                        <span><?= e($linkedUserName) ?></span>
                                        <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Employee ID</span>
                                    <span class="record-value"><?= e((string) $id) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Employee Name</span>
                                    <span class="record-value"><?= e(trim((string) ($employee['employee_name'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Hourly Rate</span>
                                    <span class="record-value"><?= e(($employee['hourly_rate'] ?? null) !== null ? ('$' . number_format((float) ($employee['hourly_rate'] ?? 0), 2)) : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value"><?= e(trim((string) ($employee['phone'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
