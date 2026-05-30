<?php
$purchaseQuote = is_array($purchaseQuote ?? null) ? $purchaseQuote : [];
$pqId = (int) ($purchaseQuote['id'] ?? 0);
$clientName = trim((string) ($purchaseQuote['client_name'] ?? '')) ?: '—';
$clientId = (int) ($purchaseQuote['client_id'] ?? 0);
$clientPhone = trim((string) ($purchaseQuote['client_phone'] ?? ''));
$clientPhoneHref = phone_tel_href($clientPhone);
$status = strtolower(trim((string) ($purchaseQuote['status'] ?? 'new')));
$statusLabel = ucwords(str_replace('_', ' ', $status));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\PurchaseQuote::statusOptions();
$followUpAt = trim((string) ($purchaseQuote['next_follow_up_at'] ?? ''));
$followUpTs = $followUpAt !== '' ? strtotime($followUpAt) : false;
$contactDate = trim((string) ($purchaseQuote['contact_date'] ?? ''));
$contactDateTs = $contactDate !== '' ? strtotime($contactDate) : false;
$convertedPurchaseId = (int) ($purchaseQuote['converted_purchase_id'] ?? 0);
$quoteConverted = \App\Models\PurchaseQuote::hasConversion($purchaseQuote);
$offers = is_array($offers ?? null) ? $offers : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];
$offerTypeOptions = is_array($offerTypeOptions ?? null) ? $offerTypeOptions : \App\Models\PurchaseQuoteOffer::typeOptions();
$contactTypeOptions = is_array($contactTypeOptions ?? null) ? $contactTypeOptions : \App\Models\PurchaseQuoteContact::typeOptions();
$activeTab = (string) ($activeTab ?? 'details');
$detailsTabActive = (bool) ($detailsTabActive ?? $activeTab === 'details');
$offersTabActive = (bool) ($offersTabActive ?? $activeTab === 'offers');
$contactsTabActive = (bool) ($contactsTabActive ?? $activeTab === 'contacts');
$offerCount = count($offers);
$contactCount = count($contacts);
$nowTs = time();
$followUpOverdue = $followUpTs !== false && $followUpTs < $nowTs && in_array($status, ['new', 'sent', 'follow_up'], true);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e(trim((string) ($purchaseQuote['title'] ?? 'Purchase Quote #' . (string) $pqId))) ?></h1>
        <p class="muted"><?= e($clientName) ?> · <?= e($statusLabel) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= e(url('/purchase-quotes/' . (string) $pqId . '/edit')) ?>">
                        <i class="fas fa-pen me-2"></i>Edit
                    </a>
                </li>
                <?php if (!$quoteConverted && !in_array($status, ['lost', 'expired'], true)): ?>
                    <li>
                        <form method="post" action="<?= e(url('/purchase-quotes/' . (string) $pqId . '/convert-to-purchase')) ?>" class="m-0" onsubmit="return confirm('Convert this quote into a purchase order?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-cart-arrow-down me-2"></i>Convert to Purchase
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider" /></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#pqMarkLostModal">
                            <i class="fas fa-times-circle me-2"></i>Mark Lost
                        </button>
                    </li>
                <?php elseif ($convertedPurchaseId > 0): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(url('/purchases/' . (string) $convertedPurchaseId)) ?>">
                            <i class="fas fa-cart-arrow-down me-2"></i>Open Purchase
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/purchase-quotes')) ?>">Back to list</a>
    </div>
</div>

<ul class="nav nav-tabs index-card-tabs estate-sale-tabs mb-3" role="tablist" data-detail-tabs>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
            id="pq-details-tab"
            data-bs-toggle="tab"
            data-bs-target="#pq-tab-details"
            data-tab="details"
            type="button"
            role="tab"
            aria-controls="pq-tab-details"
            aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
        >
            <span class="estate-sale-tab-label">Details</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link estate-sale-tab-link estate-sale-tab-link--offers<?= $offersTabActive ? ' active' : '' ?>"
            id="pq-offers-tab"
            data-bs-toggle="tab"
            data-bs-target="#pq-tab-offers"
            data-tab="offers"
            type="button"
            role="tab"
            aria-controls="pq-tab-offers"
            aria-selected="<?= $offersTabActive ? 'true' : 'false' ?>"
        >
            <span class="estate-sale-tab-label">Offers</span>
            <span class="estate-sale-tab-badge" data-count="<?= e((string) $offerCount) ?>"><?= e((string) $offerCount) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link estate-sale-tab-link estate-sale-tab-link--contacts<?= $contactsTabActive ? ' active' : '' ?>"
            id="pq-contacts-tab"
            data-bs-toggle="tab"
            data-bs-target="#pq-tab-contacts"
            data-tab="contacts"
            type="button"
            role="tab"
            aria-controls="pq-tab-contacts"
            aria-selected="<?= $contactsTabActive ? 'true' : 'false' ?>"
        >
            <span class="estate-sale-tab-label">Contacts</span>
            <span class="estate-sale-tab-badge" data-count="<?= e((string) $contactCount) ?>"><?= e((string) $contactCount) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>" id="pq-tab-details" role="tabpanel" aria-labelledby="pq-details-tab">
        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <section class="card index-card">
                    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <strong><i class="fas fa-tags me-2"></i>Quote Details</strong>
                        <?php if ($statusOptions !== [] && !$quoteConverted): ?>
                            <form method="post" action="<?= e(url('/purchase-quotes/' . (string) $pqId . '/quick-status')) ?>" class="d-flex flex-wrap align-items-center gap-2">
                                <?= csrf_field() ?>
                                <label class="small text-muted mb-0 fw-semibold" for="pq-quick-status">Status</label>
                                <select id="pq-quick-status" name="status" class="form-select form-select-sm" style="width: auto; min-width: 10rem;" onchange="this.form.submit()">
                                    <?php foreach ($statusOptions as $opt): ?>
                                        <?php $opt = strtolower(trim((string) $opt)); ?>
                                        <?php if ($opt === '') continue; ?>
                                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $opt))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="record-row-fields record-row-fields-3 mb-3">
                            <div class="record-field">
                                <span class="record-label">Client</span>
                                <span class="record-value">
                                    <?php if ($clientId > 0): ?>
                                        <a href="<?= e(url('/clients/' . (string) $clientId)) ?>"><?= e($clientName) ?></a>
                                    <?php else: ?>
                                        <?= e($clientName) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Phone</span>
                                <span class="record-value">
                                    <?php if ($clientPhoneHref !== ''): ?>
                                        <a href="<?= e($clientPhoneHref) ?>"><?= e(format_phone($clientPhone)) ?></a>
                                    <?php else: ?>
                                        <?= e($clientPhone !== '' ? format_phone($clientPhone) : '—') ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">First Contact</span>
                                <span class="record-value"><?= e($contactDateTs === false ? '—' : date('m/d/Y', $contactDateTs)) ?></span>
                            </div>
                            <div class="record-field">
                                <span class="record-label">Follow-up</span>
                                <span class="record-value<?= $followUpOverdue ? ' text-danger fw-semibold' : '' ?>">
                                    <?= e($followUpTs === false ? '—' : date('m/d/Y g:i A', $followUpTs)) ?>
                                    <?php if ($followUpOverdue): ?> (overdue)<?php endif; ?>
                                </span>
                            </div>
                            <?php if ($convertedPurchaseId > 0): ?>
                            <div class="record-field">
                                <span class="record-label">Purchase</span>
                                <span class="record-value"><a href="<?= e(url('/purchases/' . (string) $convertedPurchaseId)) ?>">#<?= e((string) $convertedPurchaseId) ?></a></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php $notes = trim((string) ($purchaseQuote['notes'] ?? '')); ?>
                        <?php if ($notes !== ''): ?>
                            <div class="mb-0">
                                <div class="small text-muted fw-semibold mb-1">Notes</div>
                                <div><?= nl2br(e($notes)) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php $lostReason = trim((string) ($purchaseQuote['lost_reason'] ?? '')); ?>
                        <?php if ($lostReason !== ''): ?>
                            <div class="mt-3 mb-0">
                                <div class="small text-muted fw-semibold mb-1">Lost Reason</div>
                                <div><?= e($lostReason) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-5">
                <section class="card index-card">
                    <div class="card-header index-card-header">
                        <strong><i class="fas fa-hand-holding-dollar me-2"></i>Latest Offer</strong>
                    </div>
                    <div class="card-body">
                        <?php if ($offers === []): ?>
                            <div class="record-empty py-2">No offers recorded yet.</div>
                        <?php else: ?>
                            <?php
                            $latest = $offers[0];
                            $latestType = strtolower(trim((string) ($latest['offer_type'] ?? '')));
                            $latestTypeLabel = $offerTypeOptions[$latestType] ?? ucwords(str_replace('_', ' ', $latestType));
                            $latestAmount = $latest['amount'] ?? null;
                            ?>
                            <div class="record-field mb-2">
                                <span class="record-label"><?= e($latestTypeLabel) ?></span>
                                <span class="record-value fs-5 fw-semibold"><?= e($latestAmount !== null && $latestAmount !== '' ? format_money_usd((float) $latestAmount) : '—') ?></span>
                            </div>
                            <?php $latestNote = trim((string) ($latest['note'] ?? '')); ?>
                            <?php if ($latestNote !== ''): ?>
                                <div class="muted small"><?= e($latestNote) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary mt-3" href="#" onclick="document.getElementById('pq-offers-tab').click(); return false;">View offer history</a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="tab-pane fade<?= $offersTabActive ? ' show active' : '' ?>" id="pq-tab-offers" role="tabpanel" aria-labelledby="pq-offers-tab">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <section class="card index-card">
                    <div class="card-header index-card-header">
                        <strong><i class="fas fa-plus me-2"></i>Record Offer</strong>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?= e(url('/purchase-quotes/' . (string) $pqId . '/offers')) ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-offer-type">Type</label>
                                <select id="pq-offer-type" name="offer_type" class="form-select">
                                    <?php foreach ($offerTypeOptions as $value => $label): ?>
                                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-offer-amount">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input id="pq-offer-amount" name="amount" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-offer-date">When</label>
                                <input id="pq-offer-date" name="offered_at" type="datetime-local" class="form-control" value="<?= e(date('Y-m-d\TH:i')) ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-offer-note">Note</label>
                                <textarea id="pq-offer-note" name="note" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Add Offer</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-7">
                <section class="card index-card">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-history me-2"></i>Offer History</strong>
                        <span class="small muted"><?= e((string) $offerCount) ?> offer(s)</span>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($offers === []): ?>
                            <div class="record-empty">No offers yet.</div>
                        <?php else: ?>
                            <div class="simple-list">
                                <?php foreach ($offers as $offer): ?>
                                    <?php
                                    if (!is_array($offer)) continue;
                                    $offerType = strtolower(trim((string) ($offer['offer_type'] ?? '')));
                                    $offerTypeLabel = $offerTypeOptions[$offerType] ?? ucwords(str_replace('_', ' ', $offerType));
                                    $offeredAt = format_datetime((string) ($offer['offered_at'] ?? ''));
                                    $amount = $offer['amount'] ?? null;
                                    $offerNote = trim((string) ($offer['note'] ?? ''));
                                    $byName = trim((string) ($offer['created_by_name'] ?? ''));
                                    ?>
                                    <div class="simple-list-item">
                                        <div class="d-flex flex-wrap justify-content-between gap-2">
                                            <div class="simple-list-title"><?= e($offerTypeLabel) ?></div>
                                            <div class="fw-semibold"><?= e($amount !== null && $amount !== '' ? format_money_usd((float) $amount) : '—') ?></div>
                                        </div>
                                        <div class="simple-list-meta">
                                            <span><?= e($offeredAt) ?></span>
                                            <?php if ($byName !== ''): ?><span>By <?= e($byName) ?></span><?php endif; ?>
                                        </div>
                                        <?php if ($offerNote !== ''): ?>
                                            <div class="mt-1 muted"><?= e($offerNote) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="tab-pane fade<?= $contactsTabActive ? ' show active' : '' ?>" id="pq-tab-contacts" role="tabpanel" aria-labelledby="pq-contacts-tab">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <section class="card index-card">
                    <div class="card-header index-card-header">
                        <strong><i class="fas fa-plus me-2"></i>Log Contact</strong>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?= e(url('/purchase-quotes/' . (string) $pqId . '/contacts')) ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-contact-type">Type</label>
                                <select id="pq-contact-type" name="contact_type" class="form-select">
                                    <?php foreach ($contactTypeOptions as $value => $label): ?>
                                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-contact-date">When</label>
                                <input id="pq-contact-date" name="contacted_at" type="datetime-local" class="form-control" value="<?= e(date('Y-m-d\TH:i')) ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="pq-contact-note">Note</label>
                                <textarea id="pq-contact-note" name="note" class="form-control" rows="3" placeholder="What was discussed?"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Log Contact</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-7">
                <section class="card index-card">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-address-book me-2"></i>Contact Log</strong>
                        <span class="small muted"><?= e((string) $contactCount) ?> contact(s)</span>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($contacts === []): ?>
                            <div class="record-empty">No contacts logged yet.</div>
                        <?php else: ?>
                            <div class="simple-list">
                                <?php foreach ($contacts as $contact): ?>
                                    <?php
                                    if (!is_array($contact)) continue;
                                    $contactType = strtolower(trim((string) ($contact['contact_type'] ?? '')));
                                    $contactTypeLabel = $contactTypeOptions[$contactType] ?? ucwords(str_replace('_', ' ', $contactType));
                                    $contactedAt = format_datetime((string) ($contact['contacted_at'] ?? ''));
                                    $contactNote = trim((string) ($contact['note'] ?? ''));
                                    $byName = trim((string) ($contact['created_by_name'] ?? ''));
                                    ?>
                                    <div class="simple-list-item">
                                        <div class="simple-list-title"><?= e($contactTypeLabel) ?></div>
                                        <div class="simple-list-meta">
                                            <span><?= e($contactedAt) ?></span>
                                            <?php if ($byName !== ''): ?><span>By <?= e($byName) ?></span><?php endif; ?>
                                        </div>
                                        <?php if ($contactNote !== ''): ?>
                                            <div class="mt-1 muted"><?= e($contactNote) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pqMarkLostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= e(url('/purchase-quotes/' . (string) $pqId . '/mark-lost')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Lost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="muted">This stops follow-up reminders for this quote.</p>
                    <label class="form-label fw-semibold" for="pq-lost-reason-modal">Reason (optional)</label>
                    <input id="pq-lost-reason-modal" name="lost_reason" class="form-control" maxlength="190" placeholder="Price too high, sold elsewhere, etc." />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Mark Lost</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.estate-sale-tabs [data-tab]');
    const syncTabUrl = (tab) => {
        if (!tab) return;
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    };
    tabButtons.forEach((btn) => {
        btn.addEventListener('shown.bs.tab', () => syncTabUrl(btn.getAttribute('data-tab')));
    });
});
</script>
