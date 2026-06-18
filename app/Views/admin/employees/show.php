<?php
$employee = is_array($employee ?? null) ? $employee : [];
$operatingLocations = is_array($operatingLocations ?? null) ? $operatingLocations : [];
$employeeId = (int) ($employee['id'] ?? 0);
$linkedUserName = trim((string) ($employee['linked_user_name'] ?? ''));
$linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
$employeeName = trim((string) ($employee['employee_name'] ?? ''));
$displayName = trim((string) ($employee['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = $employeeName !== '' ? $employeeName : ('Employee #' . (string) $employeeId);
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($displayName) ?></h1>
        <p class="muted">Employee #<?= e((string) $employeeId) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-primary w-100 w-md-auto" href="<?= e(url('/admin/employees/' . (string) $employeeId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Employee</a>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/admin/employees')) ?>">Back to Employees</a>
    </div>
</div>

<?php if ($linkedUserName !== ''): ?>
    <section class="card index-card mb-3">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-link me-2"></i>Linked User (Primary)</strong>
        </div>
        <div class="card-body">
            <div class="record-row-fields record-row-fields-3">
                <div class="record-field">
                    <span class="record-label">User</span>
                    <span class="record-value"><?= e($linkedUserName) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Email</span>
                    <span class="record-value"><?= e($linkedUserEmail !== '' ? $linkedUserEmail : '—') ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Employee Profile Name</span>
                    <span class="record-value"><?= e($employeeName !== '' ? $employeeName : '—') ?></span>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($operatingLocations !== []): ?>
    <section class="card index-card mb-3">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-location-dot me-2"></i>Operating Locations</strong>
        </div>
        <div class="card-body">
            <div class="record-list-simple">
                <?php foreach ($operatingLocations as $location): ?>
                    <?php
                    if (!is_array($location)) {
                        continue;
                    }
                    $source = trim((string) ($location['source'] ?? ''));
                    $typeLabel = trim((string) ($location['type_label'] ?? ''));
                    $name = trim((string) ($location['name'] ?? ''));
                    $address = trim((string) ($location['formatted_address'] ?? ''));
                    if ($address === '') {
                        $address = \App\Models\BusinessLocation::formatAddress(
                            trim((string) ($location['address_line1'] ?? '')),
                            trim((string) ($location['address_line2'] ?? '')),
                            trim((string) ($location['city'] ?? '')),
                            trim((string) ($location['state'] ?? '')),
                            trim((string) ($location['postal_code'] ?? ''))
                        );
                    }
                    $sourceLabel = $source === 'base' ? 'Base of operations' : 'Assigned location';
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-main">
                            <h3 class="record-title-simple"><?= e($typeLabel !== '' ? $typeLabel : 'Location') ?></h3>
                            <div class="record-subline small muted"><?= e($sourceLabel) ?></div>
                        </div>
                        <div class="record-row-fields record-row-fields-compact">
                            <div class="record-field">
                                <span class="record-label">Name</span>
                                <span class="record-value"><?= e($name !== '' ? $name : '—') ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Address</span>
                                <span class="record-value"><?= e($address !== '' ? $address : '—') ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-id-card me-2"></i>Employee Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">First Name</span>
                <span class="record-value"><?= e(trim((string) ($employee['first_name'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Last Name</span>
                <span class="record-value"><?= e(trim((string) ($employee['last_name'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Suffix</span>
                <span class="record-value"><?= e(trim((string) ($employee['suffix'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Hourly Rate</span>
                <span class="record-value"><?= e(($employee['hourly_rate'] ?? null) !== null ? ('$' . number_format((float) ($employee['hourly_rate'] ?? 0), 2)) : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Phone Number</span>
                <span class="record-value"><?= e(trim((string) ($employee['phone'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Email</span>
                <span class="record-value"><?= e(trim((string) ($employee['email'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($employee['note'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
