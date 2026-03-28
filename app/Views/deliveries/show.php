<?php
$delivery = is_array($delivery ?? null) ? $delivery : [];
$id = (int) ($delivery['id'] ?? 0);
$clientId = (int) ($delivery['client_id'] ?? 0);
$clientName = trim((string) ($delivery['client_name'] ?? '')) ?: '—';
$status = strtolower(trim((string) ($delivery['status'] ?? '')));
$statusLabel = ucwords(str_replace('_', ' ', $status));

$formatDt = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y g:i A', $ts);
};

$addrParts = array_filter([
    trim((string) ($delivery['address_line1'] ?? '')),
    trim((string) ($delivery['city'] ?? '')),
    trim((string) ($delivery['state'] ?? '')),
    trim((string) ($delivery['postal_code'] ?? '')),
], static fn (string $v): bool => $v !== '');
$addrDisplay = $addrParts !== [] ? implode(', ', $addrParts) : '—';

$notes = trim((string) ($delivery['notes'] ?? ''));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Delivery #<?= e((string) $id) ?></h1>
        <p class="muted"><?= e($clientName) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/deliveries')) ?>">All deliveries</a>
        <a class="btn btn-primary" href="<?= e(url('/deliveries/' . (string) $id . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a>
        <form method="post" action="<?= e(url('/deliveries/' . (string) $id . '/delete')) ?>" onsubmit="return confirm('Remove this delivery?');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
        </form>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-truck me-2"></i>Details</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="small text-muted text-uppercase">Client</div>
                <?php if ($clientId > 0): ?>
                    <a href="<?= e(url('/clients/' . (string) $clientId)) ?>"><?= e($clientName) ?></a>
                <?php else: ?>
                    <?= e($clientName) ?>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="small text-muted text-uppercase">Status</div>
                <span class="badge text-bg-secondary"><?= e($statusLabel) ?></span>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="small text-muted text-uppercase">Scheduled start</div>
                <div><?= e($formatDt((string) ($delivery['scheduled_at'] ?? ''))) ?></div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="small text-muted text-uppercase">End time</div>
                <div><?= e($formatDt((string) ($delivery['end_at'] ?? ''))) ?></div>
            </div>
            <div class="col-12">
                <div class="small text-muted text-uppercase">Delivery address</div>
                <div><?= e($addrDisplay) ?></div>
            </div>
            <?php if ($notes !== ''): ?>
                <div class="col-12">
                    <div class="small text-muted text-uppercase">Notes</div>
                    <div class="border rounded p-3 bg-light"><?= nl2br(e($notes)) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
