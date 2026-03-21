<?php
$search = trim((string) ($search ?? ''));
$sortBy = strtolower(trim((string) ($sortBy ?? 'name')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'asc')));
$clients = is_array($clients ?? null) ? $clients : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($clients), count($clients));
$perPage = (int) ($pagination['per_page'] ?? 25);

$clientDisplayName = static function (array $row): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    $company = trim((string) ($row['company_name'] ?? ''));
    if ($company !== '') {
        return $company;
    }

    return 'Client #' . (string) ((int) ($row['id'] ?? 0));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Clients</h1>
        <p class="muted">Client directory</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/clients/create')) ?>"><i class="fas fa-plus me-2"></i>Add Client</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/clients')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-9">
                <label class="form-label fw-semibold" for="clients-search">Search</label>
                <input
                    id="clients-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search by name, phone, address, note, or type..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="clients-sort-by">Sort By</label>
                <select id="clients-sort-by" class="form-select" name="sort_by">
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-1">
                <label class="form-label fw-semibold" for="clients-sort-dir">Sort Order</label>
                <select id="clients-sort-dir" class="form-select" name="sort_dir">
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                </select>
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/clients')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-users me-2"></i>Client List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($clients)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/clients';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($clients === []): ?>
            <div class="record-empty">No clients found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($clients as $client): ?>
                    <?php
                    $clientStatus = strtolower(trim((string) ($client['status'] ?? 'active')));
                    $isInactive = $clientStatus === 'inactive' || (array_key_exists('is_active', $client) && (int) ($client['is_active'] ?? 1) === 0);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/clients/' . (string) ((int) ($client['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($clientDisplayName($client)) ?></h3>
                                <?php if ($isInactive): ?>
                                    <span class="badge text-bg-secondary">Deactivated</span>
                                <?php endif; ?>
                            </div>
                            <div class="record-row-fields record-row-fields-compact">
                                <div class="record-field">
                                    <span class="record-label">Client ID</span>
                                    <span class="record-value"><?= e((string) ((int) ($client['id'] ?? 0))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value"><?= e(format_phone((string) ($client['phone'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">City</span>
                                    <span class="record-value"><?= e(trim((string) ($client['city'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
