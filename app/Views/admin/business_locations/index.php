<?php
$search = trim((string) ($search ?? ''));
$type = trim((string) ($type ?? ''));
$status = trim((string) ($status ?? 'active'));
$locations = is_array($locations ?? null) ? $locations : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($locations), count($locations));
$perPage = (int) ($pagination['per_page'] ?? 25);
$tableAvailable = (bool) ($tableAvailable ?? true);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Business Locations</h1>
        <p class="muted mb-0">Stores, warehouses, terminals, and other operating sites. Company address on Business Details is the base of operations.</p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-primary w-100 w-md-auto" href="<?= e(url('/admin/business-locations/create')) ?>"><i class="fas fa-plus me-2"></i>Add Location</a>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/admin/business-details')) ?>">Base of Operations</a>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<?php if (!$tableAvailable): ?>
    <section class="card index-card mb-3">
        <div class="card-body">
            <div class="alert alert-warning mb-0">Business locations table is missing. Run migrations and refresh this page.</div>
        </div>
    </section>
<?php endif; ?>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/business-locations')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-5">
                <label class="form-label fw-semibold" for="location-search">Search</label>
                <input id="location-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, address, city, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="location-type">Type</label>
                <select id="location-type" class="form-select" name="type">
                    <option value="" <?= $type === '' ? 'selected' : '' ?>>All types</option>
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="location-status">Status</label>
                <select id="location-status" class="form-select" name="status">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/business-locations')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-location-dot me-2"></i>Location List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($locations)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/business-locations';
        $fixedQueryParams = [
            'q' => $search,
            'type' => $type,
            'status' => $status,
        ];
        require base_path('app/Views/components/index_pagination.php');
        ?>

        <?php if ($locations === []): ?>
            <div class="record-empty">No locations found. Add operating locations or use Business Details for your base of operations.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($locations as $location): ?>
                    <?php
                    if (!is_array($location)) {
                        continue;
                    }
                    $id = (int) ($location['id'] ?? 0);
                    $locationType = strtolower(trim((string) ($location['location_type'] ?? '')));
                    $address = \App\Models\BusinessLocation::formatAddress(
                        trim((string) ($location['address_line1'] ?? '')),
                        trim((string) ($location['address_line2'] ?? '')),
                        trim((string) ($location['city'] ?? '')),
                        trim((string) ($location['state'] ?? '')),
                        trim((string) ($location['postal_code'] ?? ''))
                    );
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-main">
                            <h3 class="record-title-simple"><?= e(trim((string) ($location['name'] ?? '')) ?: ('Location #' . (string) $id)) ?></h3>
                            <div class="record-subline small muted"><?= e(\App\Models\BusinessLocation::labelForType($locationType)) ?></div>
                        </div>
                        <div class="record-row-fields record-row-fields-compact">
                            <div class="record-field">
                                <span class="record-label">Address</span>
                                <span class="record-value"><?= e($address !== '' ? $address : '—') ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Phone</span>
                                <span class="record-value"><?= e(format_phone(trim((string) ($location['phone'] ?? '')))) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Status</span>
                                <span class="record-value"><?= e(((int) ($location['is_active'] ?? 1)) === 1 ? 'Active' : 'Inactive') ?></span>
                            </div>
                        </div>
                        <div class="record-row-actions d-flex flex-wrap gap-2 mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/business-locations/' . (string) $id . '/edit')) ?>">Edit</a>
                            <form method="post" action="<?= e(url('/admin/business-locations/' . (string) $id . '/delete')) ?>" class="m-0" onsubmit="return confirm('Remove this location? Employee defaults using it will be cleared.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
