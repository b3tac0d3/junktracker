<?php
use App\Models\Subcontractor;

$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? '')));
$subcontractors = is_array($subcontractors ?? null) ? $subcontractors : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($subcontractors), count($subcontractors));
$perPage = (int) ($pagination['per_page'] ?? 25);

$subDisplayName = static function (array $row): string {
    $display = trim((string) ($row['display_name'] ?? ''));
    if ($display !== '') {
        return $display;
    }

    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    $company = trim((string) ($row['company'] ?? ''));
    if ($company !== '') {
        return $company;
    }

    return 'Sub #' . (string) ((int) ($row['id'] ?? 0));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Sub-Contractors</h1>
        <p class="muted">Track sub-contractors you send work to and what they earn.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/subs/create')) ?>"><i class="fas fa-plus me-2"></i>Add Sub-Contractor</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/subs')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="subs-search">Search</label>
                <input id="subs-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, company, phone, address, or notes..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="subs-status">Status</label>
                <select id="subs-status" class="form-select" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/subs')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-hard-hat me-2"></i>Sub-Contractors</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($subcontractors)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/subs';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($subcontractors === []): ?>
            <div class="record-empty">No sub-contractors found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($subcontractors as $sub): ?>
                    <?php
                    if (!is_array($sub)) {
                        continue;
                    }
                    $subId = (int) ($sub['id'] ?? 0);
                    $subStatus = strtolower(trim((string) ($sub['status'] ?? 'active')));
                    $isInactive = $subStatus === 'inactive';
                    $phoneRaw = trim((string) ($sub['phone'] ?? ''));
                    $phoneHref = phone_tel_href($phoneRaw);
                    $company = trim((string) ($sub['company'] ?? ''));
                    $city = trim((string) ($sub['city'] ?? ''));
                    $locationLabel = $city;
                    if ($locationLabel === '') {
                        $address = Subcontractor::formattedAddress($sub);
                        $locationLabel = $address !== '' ? $address : '—';
                    }
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/subs/' . (string) $subId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($subDisplayName($sub)) ?></h3>
                                <?php if ($isInactive): ?>
                                    <span class="badge text-bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="record-row-fields record-row-fields-compact">
                                <div class="record-field">
                                    <span class="record-label">Company</span>
                                    <span class="record-value"><?= e($company !== '' ? $company : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value">
                                        <?php if ($phoneHref !== ''): ?>
                                            <a href="<?= e($phoneHref) ?>"><?= e(format_phone($phoneRaw)) ?></a>
                                        <?php else: ?>
                                            <?= e($phoneRaw !== '' ? format_phone($phoneRaw) : '—') ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Location</span>
                                    <span class="record-value"><?= e($locationLabel) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
