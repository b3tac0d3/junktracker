<?php
$search = trim((string) ($search ?? ''));
$type = strtolower(trim((string) ($type ?? '')));
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($contacts), count($contacts));
$perPage = (int) ($pagination['per_page'] ?? 25);
$typeLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', trim($value)));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Networking</h1>
        <p class="muted">Track referral and relationship contacts.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/networking/create')) ?>"><i class="fas fa-plus me-2"></i>Add Contact</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/networking')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="networking-search">Search</label>
                <input id="networking-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name, company, email, notes..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="networking-type">Type</label>
                <select id="networking-type" class="form-select" name="type">
                    <option value="">All</option>
                    <?php foreach ($typeOptions as $option): ?>
                        <?php $value = strtolower(trim((string) $option)); ?>
                        <?php if ($value === '') continue; ?>
                        <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e($typeLabel($value)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/networking')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-address-book me-2"></i>Contacts</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($contacts)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/networking';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($contacts === []): ?>
            <div class="record-empty">No networking contacts found.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($contacts as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    $name = trim((string) ($row['contact_name'] ?? '')) ?: ('Contact #' . (string) $id);
                    $company = trim((string) ($row['company'] ?? ''));
                    $contactType = strtolower(trim((string) ($row['contact_type'] ?? '')));
                    $phone = trim((string) ($row['phone'] ?? ''));
                    $email = trim((string) ($row['email'] ?? ''));
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/networking/' . (string) $id)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($name) ?></h3>
                                <p class="record-subtitle-simple"><?= e($company !== '' ? $company : '—') ?></p>
                            </div>
                            <div class="record-row-fields record-row-fields-3">
                                <div class="record-field">
                                    <span class="record-label">Type</span>
                                    <span class="record-value"><?= e($contactType !== '' ? $typeLabel($contactType) : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Phone</span>
                                    <span class="record-value"><?= e($phone !== '' ? $phone : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Email</span>
                                    <span class="record-value"><?= e($email !== '' ? $email : '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
