<?php
$search = trim((string) ($search ?? ''));
$clients = is_array($clients ?? null) ? $clients : [];

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
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/clients')) ?>" class="row g-3 align-items-end">
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
        <span class="small muted"><?= e((string) count($clients)) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php if ($clients === []): ?>
            <div class="record-empty">No clients found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($clients as $client): ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/clients/' . (string) ((int) ($client['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($clientDisplayName($client)) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-compact">
                                <div class="record-field">
                                    <span class="record-label">Client ID</span>
                                    <span class="record-value"><?= e((string) ((int) ($client['id'] ?? 0))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value"><?= e(trim((string) ($client['phone'] ?? '')) ?: '—') ?></span>
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
