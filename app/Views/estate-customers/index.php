<?php
$search = trim((string) ($search ?? ''));
$customers = is_array($customers ?? null) ? $customers : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($customers), count($customers));
$perPage = (int) ($pagination['per_page'] ?? 25);
$contactMethodOptions = is_array($contactMethodOptions ?? null) ? $contactMethodOptions : [];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Estate Customers</h1>
        <p class="muted">Customers from all estate sales.</p>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/estate-customers')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-10">
                <label class="form-label fw-semibold" for="estate-customers-search">Search</label>
                <input
                    id="estate-customers-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Name, email, phone, city, or estate sale..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/estate-customers')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-user-group me-2"></i>Customers</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($customers)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/estate-customers';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($customers === []): ?>
            <div class="record-empty">No estate customers found.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($customers as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    $estateSaleId = (int) ($row['estate_sale_id'] ?? 0);
                    $name = \App\Models\EstateSale::customerDisplayName($row);
                    $saleTitle = trim((string) ($row['estate_sale_title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
                    $phone = trim((string) ($row['phone'] ?? ''));
                    $email = trim((string) ($row['email'] ?? ''));
                    $city = trim((string) ($row['city'] ?? ''));
                    $state = trim((string) ($row['state'] ?? ''));
                    $cityState = trim(implode(', ', array_filter([$city, $state], static fn (string $v): bool => $v !== '')));
                    $subscribes = !empty($row['subscribes_to_future_sales']);
                    $contactLabel = \App\Models\EstateSale::futureSalesContactMethodLabel($row['future_sales_contact_method'] ?? null);
                    $subscriberLabel = $subscribes
                        ? ('Yes' . ($contactLabel !== '' ? ' · ' . $contactLabel : ''))
                        : 'No';
                    $detailUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $id);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e($detailUrl) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($name) ?></h3>
                                <p class="record-subtitle-simple"><?= e($saleTitle) ?></p>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value"><?= e($phone !== '' ? $phone : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Email</span>
                                    <span class="record-value"><?= e($email !== '' ? $email : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Location</span>
                                    <span class="record-value"><?= e($cityState !== '' ? $cityState : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Future sales</span>
                                    <span class="record-value"><?= e($subscriberLabel) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
