<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? 'dispatch')));
$quotes = is_array($quotes ?? null) ? $quotes : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($quotes), count($quotes));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\Quote::statusOptions();
$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};
$filterStatusOptions = [
    'dispatch' => 'Dispatch (New + Sent + Follow Up)',
    '' => 'All',
];
foreach ($statusOptionsRaw as $opt) {
    $opt = strtolower(trim((string) $opt));
    if ($opt === '' || array_key_exists($opt, $filterStatusOptions)) {
        continue;
    }
    $filterStatusOptions[$opt] = $statusLabel($opt);
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Quotes</h1>
        <p class="muted">Track quote pipeline without clogging up job dispatch.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/quotes/create')) ?>"><i class="fas fa-file-signature me-2"></i>Add quote</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/quotes')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="quotes-search">Search</label>
                <input id="quotes-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Title, client, notes, or id..." autocomplete="off" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="quotes-status">Status</label>
                <select id="quotes-status" class="form-select" name="status">
                    <?php foreach ($filterStatusOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/quotes')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-file-signature me-2"></i>Quote pipeline</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($quotes)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/quotes';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($quotes === []): ?>
            <div class="record-empty">No quotes match the current filters.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($quotes as $row): ?>
                    <?php
                    $qid = (int) ($row['id'] ?? 0);
                    $clientName = trim((string) ($row['client_name'] ?? '')) ?: '—';
                    $title = trim((string) ($row['title'] ?? '')) ?: ('Quote #' . (string) $qid);
                    $st = strtolower(trim((string) ($row['status'] ?? 'new')));
                    $followUp = trim((string) ($row['next_follow_up_at'] ?? ''));
                    $followTs = $followUp !== '' ? strtotime($followUp) : false;
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/quotes/' . (string) $qid)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($title) ?></h3>
                                <p class="record-subtitle-simple"><?= e($clientName) ?></p>
                            </div>
                            <div class="record-row-fields record-row-fields-2">
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel($st)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Quote Date</span>
                                    <span class="record-value"><?= e($followTs === false ? '—' : date('m/d/Y g:i A', $followTs)) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

