<?php
$client = is_array($client ?? null) ? $client : [];
$financial = is_array($financial ?? null) ? $financial : [];
$jobStatusSummary = is_array($jobStatusSummary ?? null) ? $jobStatusSummary : [];
$jobs = is_array($jobs ?? null) ? $jobs : [];
$quotes = is_array($quotes ?? null) ? $quotes : [];
$quoteStatusSummary = is_array($quoteStatusSummary ?? null) ? $quoteStatusSummary : [];
$hasQuotes = (bool) ($hasQuotes ?? false);
$sales = is_array($sales ?? null) ? $sales : [];
$purchases = is_array($purchases ?? null) ? $purchases : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];
$familyMembers = is_array($familyMembers ?? null) ? $familyMembers : [];
$hasFamilyMembers = (bool) ($hasFamilyMembers ?? false);
$bolo = is_array($bolo ?? null) ? $bolo : null;
$hasNewsletter = (bool) ($hasNewsletter ?? false);
$hasReferrals = (bool) ($hasReferrals ?? false);
$referralsSent = is_array($referralsSent ?? null) ? $referralsSent : [];
$hasBolo = (bool) ($hasBolo ?? false);
$boloHasActiveFlag = (bool) ($boloHasActiveFlag ?? false);
$canViewFinancials = (bool) ($canViewFinancials ?? can_view_financials());
$clientId = (int) ($client['id'] ?? 0);
$jobCount = count($jobs);
$quoteCount = count($quotes);
$jobsTabCount = $jobCount + $quoteCount;
$salesCount = count($sales);
$purchaseCount = count($purchases);
$contactCount = count($contacts);
$transactionsCount = $salesCount + $purchaseCount;
$boloLines = ($bolo !== null && is_array($bolo['lines'] ?? null)) ? $bolo['lines'] : [];
$boloLineCount = count($boloLines);

$activeTab = strtolower(trim((string) ($activeTab ?? 'details')));
$allowedTabs = ['details', 'jobs', 'contacts'];
if ($canViewFinancials) {
    $allowedTabs[] = 'financial';
    $allowedTabs[] = 'transactions';
}
if ($hasBolo) {
    $allowedTabs[] = 'bolo';
}
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'details';
}

$detailsTabActive = $activeTab === 'details';
$jobsTabActive = $activeTab === 'jobs';
$financialTabActive = $activeTab === 'financial';
$transactionsTabActive = $activeTab === 'transactions';
$boloTabActive = $activeTab === 'bolo';
$contactsTabActive = $activeTab === 'contacts';

$boloProfileActive = true;
if ($bolo !== null && $boloHasActiveFlag) {
    $boloProfileActive = (int) (($bolo['profile'] ?? [])['is_active'] ?? 1) === 1;
}

$formatDateValue = static function (?string $value): string {
    if (function_exists('format_date')) {
        return format_date($value);
    }

    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    return date('m/d/Y', $timestamp);
};

$displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = trim((string) ($client['company_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'Client #' . (string) $clientId;
}

$referredById = (int) ($client['referred_by_client_id'] ?? 0);
$referrerDisplayName = '';
if ($hasReferrals && $referredById > 0) {
    $referrerDisplayName = trim(((string) ($client['referrer_first_name'] ?? '')) . ' ' . ((string) ($client['referrer_last_name'] ?? '')));
    if ($referrerDisplayName === '') {
        $referrerDisplayName = trim((string) ($client['referrer_company_name'] ?? ''));
    }
    if ($referrerDisplayName === '') {
        $referrerDisplayName = 'Client #' . (string) $referredById;
    }
}

$addressStreet = implode(', ', array_filter([
    trim((string) ($client['address_line1'] ?? '')),
    trim((string) ($client['address_line2'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$addressRegion = implode(', ', array_filter([
    trim((string) ($client['city'] ?? '')),
    trim((string) ($client['state'] ?? '')),
    trim((string) ($client['postal_code'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$mapsAddressUrl = maps_directions_url_from_parts([
    (string) ($client['address_line1'] ?? ''),
    (string) ($client['address_line2'] ?? ''),
    (string) ($client['city'] ?? ''),
    (string) ($client['state'] ?? ''),
    (string) ($client['postal_code'] ?? ''),
]);
if ($addressStreet === '' && $addressRegion === '') {
    $addressStreet = '—';
}

$primaryPhone = trim((string) ($client['phone'] ?? ''));
$secondaryPhone = trim((string) ($client['secondary_phone'] ?? ''));
$primaryPhoneHref = phone_tel_href($primaryPhone);
$secondaryPhoneHref = phone_tel_href($secondaryPhone);
$primaryNote = trim((string) ($client['primary_note'] ?? ''));

$canTextRaw = $client['can_text'] ?? null;
$canTextLabel = $canTextRaw === null ? 'Not Set' : (((int) $canTextRaw) === 1 ? 'Yes' : 'No');
$canTextClass = $canTextRaw === null ? 'text-flag-neutral' : ((((int) $canTextRaw) === 1) ? 'text-flag-yes' : 'text-flag-no');

$secondaryCanTextRaw = $client['secondary_can_text'] ?? null;
$secondaryCanTextLabel = $secondaryCanTextRaw === null ? 'Not Set' : (((int) $secondaryCanTextRaw) === 1 ? 'Yes' : 'No');
$secondaryCanTextClass = $secondaryCanTextRaw === null ? 'text-flag-neutral' : ((((int) $secondaryCanTextRaw) === 1) ? 'text-flag-yes' : 'text-flag-no');
$lastContact = ($contacts !== [] && is_array($contacts[0])) ? $contacts[0] : null;
$clientStatus = strtolower(trim((string) ($client['status'] ?? 'active')));
$isInactive = $clientStatus === 'inactive' || (array_key_exists('is_active', $client) && (int) ($client['is_active'] ?? 1) === 0);
?>

<div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div>
        <h1>Client Details</h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <?php if ($isInactive): ?>
            <span class="badge text-bg-secondary align-self-center justify-self-start">Deactivated</span>
        <?php endif; ?>
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= e(url('/clients/' . (string) $clientId . '/edit')) ?>">
                        <i class="fas fa-pen me-2"></i>Edit Client
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/jobs/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-briefcase me-2"></i>Add Job
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/quotes/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-file-signature me-2"></i>Add Quote
                    </a>
                </li>
                <?php if ($canViewFinancials): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/purchases/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-cart-arrow-down me-2"></i>Add Purchase
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/sales/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-hand-holding-usd me-2"></i>Add Sale
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/tasks/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-list-check me-2"></i>Add Task
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/deliveries/create') . '?client_id=' . (string) $clientId) ?>">
                        <i class="fas fa-truck me-2"></i>Add Delivery
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/clients/' . (string) $clientId . '/contacts/create')) ?>">
                        <i class="fas fa-phone-volume me-2"></i>Add Contact
                    </a>
                </li>
                <?php if ($hasFamilyMembers): ?>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/clients/' . (string) $clientId . '/family/create')) ?>">
                        <i class="fas fa-people-roof me-2"></i>Add Family Member
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($hasBolo): ?>
                    <li>
                        <a class="dropdown-item" href="<?= e(url('/clients/' . (string) $clientId . '/bolo/edit')) ?>">
                            <i class="fas fa-binoculars me-2"></i>Edit BOLO profile
                        </a>
                    </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/clients/' . (string) $clientId . '/deactivate')) ?>" onsubmit="return confirm('Deactivate this client?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit" <?= $isInactive ? 'disabled' : '' ?>>
                            <i class="fas fa-user-slash me-2"></i><?= $isInactive ? 'Already Deactivated' : 'Deactivate Client' ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/clients')) ?>">Back to Clients</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs client-tabs" id="client-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
                    id="client-details-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-details"
                    data-tab="details"
                    aria-controls="client-tab-details"
                    aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-address-card"></i></span>
                    <span class="estate-sale-tab-label">Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $jobsTabActive ? ' active' : '' ?>"
                    id="client-jobs-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-jobs"
                    data-tab="jobs"
                    aria-controls="client-tab-jobs"
                    aria-selected="<?= $jobsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-briefcase"></i></span>
                    <span class="estate-sale-tab-label">Jobs</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $jobsTabCount) ?>"><?= e((string) $jobsTabCount) ?></span>
                </button>
            </li>
            <?php if ($canViewFinancials): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $financialTabActive ? ' active' : '' ?>"
                    id="client-financial-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-financial"
                    data-tab="financial"
                    aria-controls="client-tab-financial"
                    aria-selected="<?= $financialTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                    <span class="estate-sale-tab-label">Financial</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $transactionsTabActive ? ' active' : '' ?>"
                    id="client-transactions-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-transactions"
                    data-tab="transactions"
                    aria-controls="client-tab-transactions"
                    aria-selected="<?= $transactionsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
                    <span class="estate-sale-tab-label">Transactions</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $transactionsCount) ?>"><?= e((string) $transactionsCount) ?></span>
                </button>
            </li>
            <?php endif; ?>
            <?php if ($hasBolo): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $boloTabActive ? ' active' : '' ?>"
                    id="client-bolo-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-bolo"
                    data-tab="bolo"
                    aria-controls="client-tab-bolo"
                    aria-selected="<?= $boloTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-binoculars"></i></span>
                    <span class="estate-sale-tab-label">BOLO</span>
                    <?php if ($boloLineCount > 0): ?>
                        <span class="estate-sale-tab-badge" data-count="<?= e((string) $boloLineCount) ?>"><?= e((string) $boloLineCount) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $contactsTabActive ? ' active' : '' ?>"
                    id="client-contacts-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#client-tab-contacts"
                    data-tab="contacts"
                    aria-controls="client-tab-contacts"
                    aria-selected="<?= $contactsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-phone-volume"></i></span>
                    <span class="estate-sale-tab-label">Contacts</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $contactCount) ?>"><?= e((string) $contactCount) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body tab-content" id="client-tab-content">
        <div
            class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>"
            id="client-tab-details"
            role="tabpanel"
            aria-labelledby="client-details-tab"
            tabindex="0"
        >
            <div class="record-row-fields">
                <div class="record-field">
                    <span class="record-label">Client ID</span>
                    <span class="record-value"><?= e((string) $clientId) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Name</span>
                    <span class="record-value"><?= e($displayName) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Phone</span>
                    <span class="record-value">
                        <?php if ($primaryPhoneHref !== ''): ?>
                            <a href="<?= e($primaryPhoneHref) ?>"><?= e(format_phone($primaryPhone)) ?></a>
                        <?php else: ?>
                            <?= e(format_phone($primaryPhone)) ?>
                        <?php endif; ?>
                        <?php if ($primaryPhone !== ''): ?>
                            <span class="text-flag <?= e($canTextClass) ?>">Text: <?= e($canTextLabel) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="record-field">
                    <span class="record-label">Secondary Phone</span>
                    <span class="record-value">
                        <?php if ($secondaryPhoneHref !== ''): ?>
                            <a href="<?= e($secondaryPhoneHref) ?>"><?= e(format_phone($secondaryPhone)) ?></a>
                        <?php else: ?>
                            <?= e(format_phone($secondaryPhone)) ?>
                        <?php endif; ?>
                        <?php if ($secondaryPhone !== ''): ?>
                            <span class="text-flag <?= e($secondaryCanTextClass) ?>">Text: <?= e($secondaryCanTextLabel) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="record-field">
                    <span class="record-label">Email</span>
                    <span class="record-value"><?= e(trim((string) ($client['email'] ?? '')) ?: '—') ?></span>
                </div>
                <?php if ($hasNewsletter): ?>
                    <?php
                    $newsletterRaw = $client['newsletter_subscribed'] ?? null;
                    $newsletterOn = $newsletterRaw !== null && (int) $newsletterRaw === 1;
                    ?>
                    <div class="record-field">
                        <span class="record-label">Newsletter</span>
                        <span class="record-value"><?= $newsletterOn ? 'Subscribed' : 'Not subscribed' ?></span>
                    </div>
                <?php endif; ?>
                <div class="record-field">
                    <span class="record-label">Full Address</span>
                    <span class="record-value record-value-stack">
                        <?php if ($mapsAddressUrl !== '' && $addressStreet !== '—'): ?>
                            <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e($addressStreet) ?>
                            </a>
                            <?php if ($addressRegion !== ''): ?>
                                <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= e($addressRegion) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span><?= e($addressStreet) ?></span>
                            <?php if ($addressRegion !== ''): ?>
                                <span><?= e($addressRegion) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="record-field">
                    <span class="record-label">Primary Note</span>
                    <span class="record-value"><?= e($primaryNote !== '' ? $primaryNote : '—') ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Last Contact</span>
                    <span class="record-value record-value-stack">
                        <?php if ($lastContact !== null): ?>
                            <?php
                            $lastContactType = trim((string) ($lastContact['contact_type'] ?? ''));
                            $lastContactTypeLabel = $lastContactType === '' ? 'Contact' : ucwords(str_replace('_', ' ', strtolower($lastContactType)));
                            $lastContactedAt = format_datetime((string) ($lastContact['contacted_at'] ?? null));
                            $lastContactBy = trim((string) ($lastContact['created_by_name'] ?? ''));
                            $lastContactNote = trim((string) ($lastContact['note'] ?? ''));
                            ?>
                            <span><?= e($lastContactTypeLabel) ?> · <?= e($lastContactedAt) ?><?php if ($lastContactBy !== ''): ?> · By <?= e($lastContactBy) ?><?php endif; ?></span>
                            <?php if ($lastContactNote !== ''): ?>
                                <span class="muted"><?= e($lastContactNote) ?></span>
                            <?php endif; ?>
                            <?php if ($contactCount > 1): ?>
                                <a class="small" href="<?= e(url('/clients/' . (string) $clientId . '?tab=contacts')) ?>">View all <?= e((string) $contactCount) ?> contacts</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span>—</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($hasReferrals && $referredById > 0): ?>
                    <div class="record-field">
                        <span class="record-label">Referred by</span>
                        <span class="record-value">
                            <a href="<?= e(url('/clients/' . (string) $referredById)) ?>"><?= e($referrerDisplayName) ?></a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($hasFamilyMembers): ?>
                <hr class="my-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 mb-0"><i class="fas fa-people-roof me-2"></i>Family</h2>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/clients/' . (string) $clientId . '/family/create')) ?>">Add Family Member</a>
                </div>
                <?php if ($familyMembers === []): ?>
                    <div class="record-empty">No family members yet.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($familyMembers as $member): ?>
                            <?php
                            if (!is_array($member)) {
                                continue;
                            }
                            $memberId = (int) ($member['id'] ?? 0);
                            if ($memberId <= 0) {
                                continue;
                            }
                            $memberName = \App\Models\ClientFamilyMember::displayName($member);
                            $relationshipLabel = \App\Models\ClientFamilyMember::relationshipLabel((string) ($member['relationship'] ?? ''));
                            $memberPhone = trim((string) ($member['phone'] ?? ''));
                            $memberPhoneHref = phone_tel_href($memberPhone);
                            $linkedClientId = (int) ($member['linked_client_id'] ?? 0);
                            $linkedClientName = trim((string) ($member['linked_client_name'] ?? ''));
                            ?>
                            <div class="simple-list-row">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                    <div>
                                        <div class="simple-list-title"><?= e($memberName) ?></div>
                                        <div class="simple-list-meta">
                                            <?php if ($relationshipLabel !== ''): ?>
                                                <span><?= e($relationshipLabel) ?></span>
                                            <?php endif; ?>
                                            <?php if ($memberPhone !== ''): ?>
                                                <span>
                                                    <?php if ($memberPhoneHref !== ''): ?>
                                                        <a href="<?= e($memberPhoneHref) ?>"><?= e(format_phone($memberPhone)) ?></a>
                                                    <?php else: ?>
                                                        <?= e(format_phone($memberPhone)) ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($linkedClientId > 0): ?>
                                                <span>
                                                    Linked:
                                                    <a href="<?= e(url('/clients/' . (string) $linkedClientId)) ?>">
                                                        <?= e($linkedClientName !== '' ? $linkedClientName : ('Client #' . (string) $linkedClientId)) ?>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/edit')) ?>">Edit</a>
                                        <form method="post" action="<?= e(url('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/delete')) ?>" onsubmit="return confirm('Remove this family member?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($hasReferrals && $referralsSent !== []): ?>
                <hr class="my-4">
                <h2 class="h6 mb-3"><i class="fas fa-user-friends me-2"></i>Referrals sent</h2>
                <div class="simple-list-table">
                    <?php foreach ($referralsSent as $ref): ?>
                        <?php
                        if (!is_array($ref)) {
                            continue;
                        }
                        $refId = (int) ($ref['id'] ?? 0);
                        if ($refId <= 0) {
                            continue;
                        }
                        $refName = trim(((string) ($ref['first_name'] ?? '')) . ' ' . ((string) ($ref['last_name'] ?? '')));
                        if ($refName === '') {
                            $refName = trim((string) ($ref['company_name'] ?? ''));
                        }
                        if ($refName === '') {
                            $refName = 'Client #' . (string) $refId;
                        }
                        $refCity = trim((string) ($ref['city'] ?? ''));
                        $refPhone = trim((string) ($ref['phone'] ?? ''));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/clients/' . (string) $refId)) ?>">
                            <div class="simple-list-title"><?= e($refName) ?></div>
                            <div class="simple-list-meta">
                                <?php if ($refPhone !== ''): ?>
                                    <span><?= e(format_phone($refPhone)) ?></span>
                                <?php endif; ?>
                                <?php if ($refCity !== ''): ?>
                                    <span><?= e($refCity) ?></span>
                                <?php endif; ?>
                                <span>ID #<?= e((string) $refId) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div
            class="tab-pane fade<?= $jobsTabActive ? ' show active' : '' ?>"
            id="client-tab-jobs"
            role="tabpanel"
            aria-labelledby="client-jobs-tab"
            tabindex="0"
        >
            <?php if ($hasQuotes): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 mb-0"><i class="fas fa-file-signature me-2"></i>Quotes</h2>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/quotes/create') . '?client_id=' . (string) $clientId) ?>">Add Quote</a>
                </div>
                <div class="record-row-fields record-row-fields-6 mb-3">
                    <?php foreach (\App\Models\Quote::statusOptions() as $quoteStatus): ?>
                        <?php
                        $quoteStatusKey = strtolower(trim((string) $quoteStatus));
                        $quoteStatusTotal = (int) ($quoteStatusSummary[$quoteStatusKey] ?? 0);
                        $quoteStatusLabel = ucwords(str_replace('_', ' ', $quoteStatusKey));
                        ?>
                        <div class="record-field">
                            <span class="record-label"><?= e($quoteStatusLabel) ?></span>
                            <span class="record-value"><?= e((string) $quoteStatusTotal) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($quotes === []): ?>
                    <div class="record-empty">No quotes for this client yet.</div>
                <?php else: ?>
                    <div class="simple-list-table mb-4">
                        <?php foreach ($quotes as $quote): ?>
                            <?php
                            if (!is_array($quote)) {
                                continue;
                            }
                            $quoteId = (int) ($quote['id'] ?? 0);
                            if ($quoteId <= 0) {
                                continue;
                            }
                            $quoteTitle = trim((string) ($quote['title'] ?? ''));
                            if ($quoteTitle === '') {
                                $quoteTitle = 'Quote #' . (string) $quoteId;
                            }
                            $quoteStatus = strtolower(trim((string) ($quote['status'] ?? 'new')));
                            $quoteStatusLabel = ucwords(str_replace('_', ' ', $quoteStatus));
                            $quotedAmount = (float) ($quote['quoted_amount'] ?? 0);
                            $quoteFollowUp = format_datetime((string) ($quote['next_follow_up_at'] ?? null));
                            $quoteCreated = format_datetime((string) ($quote['created_at'] ?? null));
                            $quoteUpdated = format_datetime((string) ($quote['updated_at'] ?? null));
                            $quoteStatusDateLabel = match ($quoteStatus) {
                                'won' => 'Won',
                                'lost' => 'Lost',
                                'expired' => 'Expired',
                                'sent' => 'Sent',
                                'follow_up' => 'Follow-up set',
                                default => 'Updated',
                            };
                            $quoteLostReason = trim((string) ($quote['lost_reason'] ?? ''));
                            $convertedJobId = (int) ($quote['converted_job_id'] ?? 0);
                            ?>
                            <a class="simple-list-row simple-list-row-link" href="<?= e(url('/quotes/' . (string) $quoteId)) ?>">
                                <div class="simple-list-title"><?= e($quoteTitle) ?></div>
                                <div class="simple-list-meta">
                                    <span>ID #<?= e((string) $quoteId) ?></span>
                                    <span><?= e($quoteStatusLabel) ?></span>
                                    <?php if ($quoteCreated !== '' && $quoteCreated !== '—'): ?>
                                        <span>Created <?= e($quoteCreated) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quoteFollowUp !== '' && $quoteFollowUp !== '—'): ?>
                                        <span>Follow-up <?= e($quoteFollowUp) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quoteUpdated !== '' && $quoteUpdated !== '—' && in_array($quoteStatus, ['won', 'lost', 'expired', 'sent', 'follow_up'], true)): ?>
                                        <span><?= e($quoteStatusDateLabel) ?> <?= e($quoteUpdated) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quotedAmount > 0): ?>
                                        <span>$<?= e(number_format($quotedAmount, 2)) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quoteStatus === 'won' && $convertedJobId > 0): ?>
                                        <span>Job #<?= e((string) $convertedJobId) ?></span>
                                    <?php endif; ?>
                                    <?php
                                    $convertedPurchaseId = (int) ($quote['converted_purchase_id'] ?? 0);
                                    $convertedEstateSaleId = (int) ($quote['converted_estate_sale_id'] ?? 0);
                                    ?>
                                    <?php if ($quoteStatus === 'won' && $convertedPurchaseId > 0): ?>
                                        <span>Purchase #<?= e((string) $convertedPurchaseId) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quoteStatus === 'won' && $convertedEstateSaleId > 0): ?>
                                        <span>Estate sale #<?= e((string) $convertedEstateSaleId) ?></span>
                                    <?php endif; ?>
                                    <?php if ($quoteStatus === 'lost' && $quoteLostReason !== ''): ?>
                                        <span><?= e($quoteLostReason) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="my-4">
            <?php endif; ?>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-briefcase me-2"></i>Jobs</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/jobs/create') . '?client_id=' . (string) $clientId) ?>">Add Job</a>
            </div>
            <div class="record-row-fields record-row-fields-5 mb-3">
                <div class="record-field">
                    <span class="record-label">Prospect</span>
                    <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['prospect'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Pending</span>
                    <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['pending'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Active</span>
                    <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['active'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Complete</span>
                    <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['complete'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Cancelled</span>
                    <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['cancelled'] ?? 0))) ?></span>
                </div>
            </div>

            <?php if ($jobs === []): ?>
                <div class="record-empty">No jobs for this client yet.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($jobs as $job): ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) ((int) ($job['id'] ?? 0)))) ?>">
                            <div class="simple-list-title"><?= e(trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)))) ?></div>
                            <div class="simple-list-meta">
                                <span>ID #<?= e((string) ((int) ($job['id'] ?? 0))) ?></span>
                                <span class="text-capitalize"><?= e((string) ($job['status'] ?? 'pending')) ?></span>
                                <span><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? null))) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canViewFinancials): ?>
        <div
            class="tab-pane fade<?= $financialTabActive ? ' show active' : '' ?>"
            id="client-tab-financial"
            role="tabpanel"
            aria-labelledby="client-financial-tab"
            tabindex="0"
        >
            <h2 class="h6 mb-3"><i class="fas fa-briefcase me-2"></i>Service (lifetime)</h2>
            <div class="record-row-fields record-row-fields-4 mb-4">
                <div class="record-field">
                    <span class="record-label">Gross</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['service_gross'] ?? $financial['gross_income'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Net</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['service_net'] ?? $financial['net_income'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Expenses</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['service_expenses'] ?? $financial['expenses'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Labor</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['service_labor'] ?? 0), 2)) ?></span>
                </div>
            </div>

            <hr class="my-4">

            <h2 class="h6 mb-3"><i class="fas fa-sack-dollar me-2"></i>Sales (lifetime)</h2>
            <div class="record-row-fields record-row-fields-3 mb-4">
                <div class="record-field">
                    <span class="record-label">Gross</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['sales_gross'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Net</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['sales_net'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Transactions</span>
                    <span class="record-value"><?= e((string) ((int) ($financial['sales_count'] ?? $salesCount))) ?></span>
                </div>
            </div>

            <hr class="my-4">

            <h2 class="h6 mb-3"><i class="fas fa-cart-arrow-down me-2"></i>Purchases (lifetime)</h2>
            <div class="record-row-fields record-row-fields-3 mb-0">
                <div class="record-field">
                    <span class="record-label">Total Spend</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['purchase_spend'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Transactions</span>
                    <span class="record-value"><?= e((string) ((int) ($financial['purchase_count'] ?? $purchaseCount))) ?></span>
                </div>
            </div>
        </div>

        <div
            class="tab-pane fade<?= $transactionsTabActive ? ' show active' : '' ?>"
            id="client-tab-transactions"
            role="tabpanel"
            aria-labelledby="client-transactions-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-sack-dollar me-2"></i>Sales</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/sales/create') . '?client_id=' . (string) $clientId) ?>">Add Sale</a>
            </div>
            <?php if ($sales === []): ?>
                <div class="record-empty">No sales linked to this client yet.</div>
            <?php else: ?>
                <div class="simple-list-table mb-4">
                    <?php foreach ($sales as $sale): ?>
                        <?php
                        if (!is_array($sale)) {
                            continue;
                        }
                        $saleId = (int) ($sale['id'] ?? 0);
                        if ($saleId <= 0) {
                            continue;
                        }
                        $saleName = trim((string) ($sale['name'] ?? ''));
                        if ($saleName === '') {
                            $saleName = 'Sale #' . (string) $saleId;
                        }
                        $saleType = trim((string) ($sale['sale_type'] ?? ''));
                        $saleTypeLabel = $saleType === '' ? 'Sale' : ucwords(str_replace('_', ' ', strtolower($saleType)));
                        $saleDate = $formatDateValue((string) ($sale['sale_date'] ?? null));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                            <div class="simple-list-title"><?= e($saleName) ?></div>
                            <div class="simple-list-meta">
                                <span><?= e($saleTypeLabel) ?></span>
                                <span><?= e($saleDate) ?></span>
                                <span>Gross $<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
                                <span>Net $<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
                                <?php if (((int) ($sale['job_id'] ?? 0)) > 0): ?>
                                    <span>Job: <?= e(trim((string) ($sale['job_title'] ?? '')) ?: ('Job #' . (string) ((int) ($sale['job_id'] ?? 0)))) ?></span>
                                <?php elseif (((int) ($sale['purchase_id'] ?? 0)) > 0): ?>
                                    <span>Purchase: <?= e(trim((string) ($sale['purchase_title'] ?? '')) ?: ('Purchase #' . (string) ((int) ($sale['purchase_id'] ?? 0)))) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-cart-arrow-down me-2"></i>Purchases</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/purchases/create') . '?client_id=' . (string) $clientId) ?>">Add Purchase</a>
            </div>
            <?php if ($purchases === []): ?>
                <div class="record-empty">No purchases linked to this client yet.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($purchases as $purchase): ?>
                        <?php
                        if (!is_array($purchase)) {
                            continue;
                        }
                        $purchaseId = (int) ($purchase['id'] ?? 0);
                        if ($purchaseId <= 0) {
                            continue;
                        }
                        $purchaseTitle = trim((string) ($purchase['title'] ?? ''));
                        if ($purchaseTitle === '') {
                            $purchaseTitle = 'Purchase #' . (string) $purchaseId;
                        }
                        $purchaseStatus = trim((string) ($purchase['status'] ?? ''));
                        $purchaseStatusLabel = $purchaseStatus === '' ? '—' : ucwords(str_replace('_', ' ', strtolower($purchaseStatus)));
                        $purchaseDate = $formatDateValue((string) ($purchase['purchase_date'] ?? ($purchase['contact_date'] ?? null)));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/purchases/' . (string) $purchaseId)) ?>">
                            <div class="simple-list-title"><?= e($purchaseTitle) ?></div>
                            <div class="simple-list-meta">
                                <span><?= e($purchaseStatusLabel) ?></span>
                                <span><?= e($purchaseDate) ?></span>
                                <span>Price $<?= e(number_format((float) ($purchase['purchase_price'] ?? 0), 2)) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($hasBolo): ?>
        <div
            class="tab-pane fade<?= $boloTabActive ? ' show active' : '' ?>"
            id="client-tab-bolo"
            role="tabpanel"
            aria-labelledby="client-bolo-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="fas fa-binoculars me-2"></i>BOLO (buyer) profile</h2>
                    <?php if ($bolo !== null && $boloHasActiveFlag): ?>
                        <?php if ($boloProfileActive): ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Inactive</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/bolo')) ?>">BOLO list</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/clients/' . (string) $clientId . '/bolo/edit')) ?>">Edit</a>
                </div>
            </div>

            <?php if ($bolo !== null && $boloHasActiveFlag): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if ($boloProfileActive): ?>
                        <form method="post" action="<?= e(url('/clients/' . (string) $clientId . '/bolo/deactivate')) ?>" onsubmit="return confirm('Deactivate this BOLO profile? It will stay on file but will not appear in the BOLO list or search.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-warning" type="submit">Deactivate BOLO profile</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/clients/' . (string) $clientId . '/bolo/reactivate')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-success" type="submit">Reactivate BOLO profile</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($bolo === null): ?>
                <div class="record-empty mb-0">No BOLO profile yet. Add line items and notes for what this client buys.</div>
            <?php else: ?>
                <?php
                $boloNotes = trim((string) (($bolo['profile'] ?? [])['notes'] ?? ''));
                $boloLines = is_array($bolo['lines'] ?? null) ? $bolo['lines'] : [];
                ?>
                <?php if ($boloLines === [] && $boloNotes === ''): ?>
                    <div class="record-empty mb-0">BOLO profile is empty. Edit to add items or notes.</div>
                <?php else: ?>
                    <?php if ($boloLines !== []): ?>
                        <div class="record-field mb-3">
                            <span class="record-label">Line items</span>
                            <div class="record-value">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($boloLines as $line): ?>
                                        <?php if (!is_array($line)) {
                                            continue;
                                        } ?>
                                        <li><?= e(trim((string) ($line['item_text'] ?? ''))) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="record-field mb-0">
                        <span class="record-label">BOLO notes</span>
                        <span class="record-value"><?= e($boloNotes !== '' ? $boloNotes : '—') ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div
            class="tab-pane fade<?= $contactsTabActive ? ' show active' : '' ?>"
            id="client-tab-contacts"
            role="tabpanel"
            aria-labelledby="client-contacts-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-phone-volume me-2"></i>Contact Log</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/clients/' . (string) $clientId . '/contacts/create')) ?>">Add Contact</a>
            </div>
            <?php if ($contacts === []): ?>
                <div class="record-empty">No contact records yet.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($contacts as $contact): ?>
                        <?php
                        if (!is_array($contact)) {
                            continue;
                        }
                        $contactType = trim((string) ($contact['contact_type'] ?? ''));
                        $contactTypeLabel = $contactType === '' ? 'Contact' : ucwords(str_replace('_', ' ', strtolower($contactType)));
                        $contactedAt = format_datetime((string) ($contact['contacted_at'] ?? null));
                        $contactBy = trim((string) ($contact['created_by_name'] ?? ''));
                        $contactNote = trim((string) ($contact['note'] ?? ''));
                        ?>
                        <div class="simple-list-row">
                            <div class="simple-list-title"><?= e($contactTypeLabel) ?></div>
                            <div class="simple-list-meta">
                                <span><?= e($contactedAt) ?></span>
                                <?php if ($contactBy !== ''): ?>
                                    <span>By <?= e($contactBy) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($contactNote !== ''): ?>
                                <div class="mt-1 muted"><?= e($contactNote) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tabList = document.getElementById('client-tabs');
    const syncTabUrl = (tabName) => {
        if (!tabName) {
            return;
        }
        const url = new URL(window.location.href);
        if (tabName === 'details') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tabName);
        }
        const next = url.pathname + url.search + url.hash;
        const current = window.location.pathname + window.location.search + window.location.hash;
        if (next !== current) {
            window.history.replaceState(null, '', next);
        }
    };

    if (tabList) {
        tabList.addEventListener('shown.bs.tab', (event) => {
            const trigger = event.target;
            if (!(trigger instanceof HTMLElement)) {
                return;
            }
            syncTabUrl(String(trigger.dataset.tab || '').trim());
        });

        const urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab) {
            const normalizedTab = urlTab.toLowerCase();
            const trigger = tabList.querySelector('[data-tab="' + normalizedTab + '"]');
            if (trigger instanceof HTMLElement && !trigger.classList.contains('active') && window.bootstrap) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    }
});
</script>
