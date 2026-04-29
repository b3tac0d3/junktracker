<?php
$quote = is_array($quote ?? null) ? $quote : [];
$estimates = is_array($estimates ?? null) ? $estimates : [];
$quoteId = (int) ($quote['id'] ?? 0);
$clientName = trim((string) ($quote['client_name'] ?? '')) ?: '—';
$status = strtolower(trim((string) ($quote['status'] ?? 'new')));
$statusLabel = ucwords(str_replace('_', ' ', $status));
$followUpAt = trim((string) ($quote['next_follow_up_at'] ?? ''));
$followUpTs = $followUpAt !== '' ? strtotime($followUpAt) : false;
$convertedJobId = (int) ($quote['converted_job_id'] ?? 0);
$mapsAddressUrl = maps_directions_url_from_parts([
    (string) ($quote['address_line1'] ?? ''),
    (string) ($quote['address_line2'] ?? ''),
    (string) ($quote['city'] ?? ''),
    (string) ($quote['state'] ?? ''),
    (string) ($quote['postal_code'] ?? ''),
]);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e(trim((string) ($quote['title'] ?? 'Quote #' . (string) $quoteId))) ?></h1>
        <p class="muted"><?= e($clientName) ?> · <?= e($statusLabel) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/quotes')) ?>">Back to Quotes</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/quotes/' . (string) $quoteId . '/edit')) ?>">Edit</a>
        <?php if ($convertedJobId <= 0): ?>
            <form method="post" action="<?= e(url('/quotes/' . (string) $quoteId . '/convert-to-job')) ?>" onsubmit="return confirm('Convert this quote into a job?');">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><i class="fas fa-briefcase me-2"></i>Convert to Job</button>
            </form>
        <?php else: ?>
            <a class="btn btn-success" href="<?= e(url('/jobs/' . (string) $convertedJobId)) ?>">Open Job</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <section class="card index-card">
            <div class="card-header index-card-header">
                <strong><i class="fas fa-file-signature me-2"></i>Quote Details</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Service Type</dt>
                    <dd class="col-sm-8"><?= e(trim((string) ($quote['service_type'] ?? '')) ?: '—') ?></dd>

                    <dt class="col-sm-4">Quote Date</dt>
                    <dd class="col-sm-8"><?= e($followUpTs === false ? '—' : date('m/d/Y', $followUpTs)) ?></dd>

                    <dt class="col-sm-4">Address</dt>
                    <?php
                    $addr = trim((string) ($quote['address_line1'] ?? ''));
                    $addr2 = trim((string) ($quote['address_line2'] ?? ''));
                    $city = trim((string) ($quote['city'] ?? ''));
                    $state = trim((string) ($quote['state'] ?? ''));
                    $postal = trim((string) ($quote['postal_code'] ?? ''));
                    $cityStateZip = trim(implode(' ', array_filter([$city . ($state !== '' ? ',' : ''), $state, $postal], static fn (string $v): bool => trim($v) !== '')));
                    $addrDisplay = trim($addr . ($addr2 !== '' ? ', ' . $addr2 : '') . ($cityStateZip !== '' ? ' · ' . $cityStateZip : ''));
                    ?>
                    <dd class="col-sm-8">
                        <?php if ($mapsAddressUrl !== '' && $addrDisplay !== ''): ?>
                            <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($addrDisplay) ?></a>
                        <?php else: ?>
                            <?= e($addrDisplay !== '' ? $addrDisplay : '—') ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Notes</dt>
                    <dd class="col-sm-8"><?= nl2br(e(trim((string) ($quote['notes'] ?? '')) ?: '—')) ?></dd>

                    <dt class="col-sm-4">Lost Reason</dt>
                    <dd class="col-sm-8"><?= e(trim((string) ($quote['lost_reason'] ?? '')) ?: '—') ?></dd>
                </dl>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-5">
        <section class="card index-card">
            <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-file-invoice-dollar me-2"></i>Estimates</strong>
                <a class="btn btn-sm btn-primary text-white" href="<?= e(url('/billing/create?type=estimate&quote_id=' . (string) $quoteId . '&client_id=' . (string) ((int) ($quote['client_id'] ?? 0)))) ?>">Add Estimate</a>
            </div>
            <div class="card-body">
                <?php if ($estimates === []): ?>
                    <div class="record-empty">No estimates linked yet.</div>
                <?php else: ?>
                    <div class="record-list-simple">
                        <?php foreach ($estimates as $estimate): ?>
                            <?php
                            $invoiceId = (int) ($estimate['id'] ?? 0);
                            $invoiceNumber = trim((string) ($estimate['invoice_number'] ?? ''));
                            $invoiceStatus = trim((string) ($estimate['status'] ?? 'draft'));
                            $total = (float) ($estimate['total'] ?? 0);
                            ?>
                            <article class="record-row-simple">
                                <a class="record-row-link" href="<?= e(url('/billing/' . (string) $invoiceId)) ?>">
                                    <div class="record-row-main">
                                        <h3 class="record-title-simple"><?= e($invoiceNumber !== '' ? $invoiceNumber : ('Estimate #' . (string) $invoiceId)) ?></h3>
                                    </div>
                                    <div class="record-row-fields record-row-fields-2">
                                        <div class="record-field">
                                            <span class="record-label">Status</span>
                                            <span class="record-value"><?= e(ucwords(str_replace('_', ' ', strtolower($invoiceStatus)))) ?></span>
                                        </div>
                                        <div class="record-field">
                                            <span class="record-label">Total</span>
                                            <span class="record-value"><?= e('$' . number_format($total, 2)) ?></span>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

