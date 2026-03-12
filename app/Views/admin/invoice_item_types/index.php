<?php
$search = trim((string) ($search ?? ''));
$status = trim((string) ($status ?? 'active'));
$types = is_array($types ?? null) ? $types : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($types), count($types));
$perPage = (int) ($pagination['per_page'] ?? 25);
$tableAvailable = (bool) ($tableAvailable ?? true);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Invoice Item Types</h1>
        <p class="muted">Manage reusable invoice/estimate line item defaults.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/admin/invoice-item-types/create')) ?>"><i class="fas fa-plus me-2"></i>Add Item Type</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<?php if (!$tableAvailable): ?>
    <section class="card index-card mb-3">
        <div class="card-body">
            <div class="alert alert-warning mb-0">Invoice item types table is missing. Run migrations and refresh this page.</div>
        </div>
    </section>
<?php endif; ?>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/invoice-item-types')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="invoice-item-type-search">Search</label>
                <input id="invoice-item-type-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, note, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="invoice-item-type-status">Status</label>
                <select id="invoice-item-type-status" class="form-select" name="status">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/invoice-item-types')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-list me-2"></i>Item Type List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($types)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/invoice-item-types';
        require base_path('app/Views/components/index_pagination.php');
        ?>

        <?php if ($types === []): ?>
            <div class="record-empty">No invoice item types found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($types as $type): ?>
                    <?php $id = (int) ($type['id'] ?? 0); ?>
                    <article class="record-row-simple">
                        <div class="record-row-main">
                            <h3 class="record-title-simple"><?= e(trim((string) ($type['name'] ?? '')) ?: ('Type #' . (string) $id)) ?></h3>
                        </div>
                        <div class="record-row-fields record-row-fields-5">
                            <div class="record-field">
                                <span class="record-label">Default Rate</span>
                                <span class="record-value">$<?= e(number_format((float) ($type['default_unit_price'] ?? 0), 2)) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Taxable</span>
                                <span class="record-value"><?= ((int) ($type['default_taxable'] ?? 0)) === 1 ? 'Yes' : 'No' ?></span>
                            </div>
                            <div class="record-field record-field-full">
                                <span class="record-label">Default Note</span>
                                <span class="record-value"><?= e(trim((string) ($type['default_note'] ?? '')) ?: '—') ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Sort</span>
                                <span class="record-value"><?= e((string) ((int) ($type['sort_order'] ?? 100))) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Status</span>
                                <span class="record-value"><?= ((int) ($type['is_active'] ?? 1)) === 1 ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/admin/invoice-item-types/' . (string) $id . '/edit')) ?>">Edit</a>
                            <form method="post" action="<?= e(url('/admin/invoice-item-types/' . (string) $id . '/delete')) ?>" onsubmit="return confirm('Remove this invoice item type?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
