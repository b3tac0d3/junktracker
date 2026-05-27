<?php
$quote = is_array($quote ?? null) ? $quote : [];
$estimates = is_array($estimates ?? null) ? $estimates : [];
$quoteId = (int) ($quote['id'] ?? 0);
$clientName = trim((string) ($quote['client_name'] ?? '')) ?: '—';
$clientId = (int) ($quote['client_id'] ?? 0);
$clientPhone = trim((string) ($quote['client_phone'] ?? ''));
$clientPhoneHref = phone_tel_href($clientPhone);
$status = strtolower(trim((string) ($quote['status'] ?? 'new')));
$statusLabel = ucwords(str_replace('_', ' ', $status));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\Quote::statusOptions();
$followUpAt = trim((string) ($quote['next_follow_up_at'] ?? ''));
$followUpTs = $followUpAt !== '' ? strtotime($followUpAt) : false;
$createdAt = trim((string) ($quote['created_at'] ?? ''));
$updatedAt = trim((string) ($quote['updated_at'] ?? ''));
$statusDateLabel = match ($status) {
    'won' => 'Won',
    'lost' => 'Lost',
    'expired' => 'Expired',
    'sent' => 'Sent',
    'follow_up' => 'Follow-up set',
    default => 'Updated',
};
$headerMeta = [$clientName];
if (in_array($status, ['won', 'lost', 'expired', 'sent', 'follow_up'], true) && $updatedAt !== '') {
    $updatedFormatted = format_datetime($updatedAt);
    if ($updatedFormatted !== '' && $updatedFormatted !== '—') {
        $headerMeta[] = $statusLabel . ' · ' . $updatedFormatted;
    } else {
        $headerMeta[] = $statusLabel;
    }
} else {
    $headerMeta[] = $statusLabel;
}
$convertedJobId = (int) ($quote['converted_job_id'] ?? 0);
$convertedEstateSaleId = (int) ($quote['converted_estate_sale_id'] ?? 0);
$convertedPurchaseId = (int) ($quote['converted_purchase_id'] ?? 0);
$quoteConverted = \App\Models\Quote::hasConversion($quote);
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
        <p class="muted"><?= e(implode(' · ', $headerMeta)) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= e(url('/quotes/' . (string) $quoteId . '/edit')) ?>">
                        <i class="fas fa-pen me-2"></i>Edit
                    </a>
                </li>
                <?php if (!$quoteConverted): ?>
                    <li>
                        <form method="post" action="<?= e(url('/quotes/' . (string) $quoteId . '/convert-to-job')) ?>" class="m-0" onsubmit="return confirm('Convert this quote into a job?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-briefcase me-2"></i>Convert to Job
                            </button>
                        </form>
                    </li>
                    <li>
                        <form method="post" action="<?= e(url('/quotes/' . (string) $quoteId . '/convert-to-estate-sale')) ?>" class="m-0" onsubmit="return confirm('Convert this quote into an estate sale? Any linked estimates will be removed from reports.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-store me-2"></i>Convert to Estate Sale
                            </button>
                        </form>
                    </li>
                    <li>
                        <form method="post" action="<?= e(url('/quotes/' . (string) $quoteId . '/convert-to-purchase')) ?>" class="m-0" onsubmit="return confirm('Convert this quote into a purchase? Any linked estimates will be removed from reports.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-shopping-cart me-2"></i>Convert to Purchase
                            </button>
                        </form>
                    </li>
                <?php else: ?>
                    <?php if ($convertedJobId > 0): ?>
                        <li>
                            <a class="dropdown-item" href="<?= e(url('/jobs/' . (string) $convertedJobId)) ?>">
                                <i class="fas fa-briefcase me-2"></i>Open Job
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($convertedEstateSaleId > 0): ?>
                        <li>
                            <a class="dropdown-item" href="<?= e(url('/estate-sales/' . (string) $convertedEstateSaleId)) ?>">
                                <i class="fas fa-store me-2"></i>Open Estate Sale
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($convertedPurchaseId > 0): ?>
                        <li>
                            <a class="dropdown-item" href="<?= e(url('/purchases/' . (string) $convertedPurchaseId)) ?>">
                                <i class="fas fa-shopping-cart me-2"></i>Open Purchase
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/quotes')) ?>">Back to Quotes</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <section class="card index-card">
            <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <strong><i class="fas fa-file-signature me-2"></i>Quote Details</strong>
                <?php if ($statusOptions !== []): ?>
                    <form method="post" action="<?= e(url('/quotes/' . (string) $quoteId . '/quick-status')) ?>" class="d-flex flex-wrap align-items-center gap-2">
                        <?= csrf_field() ?>
                        <label class="small text-muted mb-0 fw-semibold" for="quote-quick-status">Status</label>
                        <select
                            id="quote-quick-status"
                            name="status"
                            class="form-select form-select-sm"
                            style="width: auto; min-width: 10rem;"
                            aria-label="Quote status"
                            onchange="this.form.submit()"
                        >
                            <?php foreach ($statusOptions as $opt): ?>
                                <?php $opt = strtolower(trim((string) $opt)); ?>
                                <?php if ($opt === '') {
                                    continue;
                                } ?>
                                <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $opt))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Client</dt>
                    <dd class="col-sm-8">
                        <?php if ($clientId > 0 && $clientName !== '—'): ?>
                            <a href="<?= e(url('/clients/' . (string) $clientId)) ?>"><?= e($clientName) ?></a>
                        <?php else: ?>
                            <?= e($clientName) ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Phone</dt>
                    <dd class="col-sm-8">
                        <?php if ($clientPhoneHref !== ''): ?>
                            <a href="<?= e($clientPhoneHref) ?>"><?= e(format_phone($clientPhone)) ?></a>
                        <?php else: ?>
                            <?= e($clientPhone !== '' ? format_phone($clientPhone) : '—') ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Service Type</dt>
                    <?php
                    $serviceTypeRaw = trim((string) ($quote['service_type'] ?? ''));
                    $serviceTypeDisplay = $serviceTypeRaw !== '' ? ucwords(str_replace('_', ' ', strtolower($serviceTypeRaw))) : '—';
                    ?>
                    <dd class="col-sm-8"><?= e($serviceTypeDisplay) ?></dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <?= e($statusLabel) ?>
                        <?php if (in_array($status, ['won', 'lost', 'expired', 'sent', 'follow_up'], true) && $updatedAt !== ''): ?>
                            <span class="text-muted">· <?= e($statusDateLabel) ?> <?= e(format_datetime($updatedAt)) ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8"><?= e($createdAt !== '' ? format_datetime($createdAt) : '—') ?></dd>

                    <dt class="col-sm-4">Follow-up</dt>
                    <dd class="col-sm-8"><?= e($followUpTs === false ? '—' : date('m/d/Y g:i A', $followUpTs)) ?></dd>

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

