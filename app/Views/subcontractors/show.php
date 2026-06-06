<?php
use App\Models\Subcontractor;

$subcontractor = is_array($subcontractor ?? null) ? $subcontractor : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$earnings = is_array($earnings ?? null) ? $earnings : [];
$subcontractorId = (int) ($subcontractor['id'] ?? 0);
$name = trim((string) ($subcontractor['display_name'] ?? '')) ?: ('Sub #' . (string) $subcontractorId);
$company = trim((string) ($subcontractor['company'] ?? ''));
$phone = trim((string) ($subcontractor['phone'] ?? ''));
$phoneHref = phone_tel_href($phone);
$email = trim((string) ($subcontractor['email'] ?? ''));
$notes = trim((string) ($subcontractor['notes'] ?? ''));
$status = strtolower(trim((string) ($subcontractor['status'] ?? 'active')));
$formattedAddress = Subcontractor::formattedAddress($subcontractor);
$mapsAddressUrl = maps_directions_url_from_parts([
    (string) ($subcontractor['address_line1'] ?? ''),
    (string) ($subcontractor['address_line2'] ?? ''),
    (string) ($subcontractor['city'] ?? ''),
    (string) ($subcontractor['state'] ?? ''),
    (string) ($subcontractor['postal_code'] ?? ''),
]);
$activeTab = strtolower(trim((string) ($activeTab ?? 'details')));
$allowedTabs = ['details', 'jobs', 'earnings'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'details';
}
$detailsTabActive = $activeTab === 'details';
$jobsTabActive = $activeTab === 'jobs';
$earningsTabActive = $activeTab === 'earnings';
$jobsTabCount = (int) ($jobsTabCount ?? count($assignments));
$formatMoney = static fn (float $value): string => '$' . number_format($value, 2);
$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', strtolower(trim($value))));
};
$formatDate = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    return $stamp === false ? '—' : date('m/d/Y', $stamp);
};
$jobAddressLabel = static function (array $assignment): string {
    return Subcontractor::formattedAddress([
        'address_line1' => (string) ($assignment['job_address_line1'] ?? ''),
        'address_line2' => (string) ($assignment['job_address_line2'] ?? ''),
        'city' => (string) ($assignment['job_city'] ?? ''),
        'state' => (string) ($assignment['job_state'] ?? ''),
        'postal_code' => (string) ($assignment['job_postal_code'] ?? ''),
    ]);
};
$canViewFinancials = (bool) ($canViewFinancials ?? can_view_financials());
$assignJobUrl = url('/subs/' . (string) $subcontractorId . '/jobs/assign');
$referralFeeUrl = url('/expenses/create') . '?' . http_build_query([
    'preset' => 'referral_fee',
    'subcontractor_id' => (string) $subcontractorId,
    'return_to' => '/subs/' . (string) $subcontractorId . '?tab=earnings',
]);
?>

<div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div>
        <h1><?= e($name) ?></h1>
        <p class="muted"><?= e($company !== '' ? $company : 'Sub-Contractor') ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <?php if ($status === 'inactive'): ?>
            <span class="badge text-bg-secondary align-self-center justify-self-start">Inactive</span>
        <?php endif; ?>
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end jt-actions-menu">
                <li>
                    <a class="dropdown-item" href="<?= e(url('/subs/' . (string) $subcontractorId . '/edit')) ?>">
                        <i class="fas fa-pen me-2"></i>Edit Sub-Contractor
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e($assignJobUrl) ?>">
                        <i class="fas fa-briefcase me-2"></i>Add Job
                    </a>
                </li>
                <?php if ($canViewFinancials): ?>
                <li>
                    <a class="dropdown-item" href="<?= e($referralFeeUrl) ?>">
                        <i class="fas fa-hand-holding-dollar me-2"></i>Add Referral Fee
                    </a>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/subs/' . (string) $subcontractorId . '/delete')) ?>" onsubmit="return confirm('Delete this sub-contractor?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="fas fa-trash me-2"></i>Delete Sub-Contractor
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/subs')) ?>">Back to Sub-Contractors</a>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs client-tabs" id="subcontractor-tabs" role="tablist" data-detail-tabs>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
                    id="sub-details-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#sub-tab-details"
                    data-tab="details"
                    aria-controls="sub-tab-details"
                    aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-address-card"></i></span>
                    <span class="estate-sale-tab-label">Details</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $jobsTabActive ? ' active' : '' ?>"
                    id="sub-jobs-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#sub-tab-jobs"
                    data-tab="jobs"
                    aria-controls="sub-tab-jobs"
                    aria-selected="<?= $jobsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-briefcase"></i></span>
                    <span class="estate-sale-tab-label">Jobs</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $jobsTabCount) ?>"><?= e((string) $jobsTabCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $earningsTabActive ? ' active' : '' ?>"
                    id="sub-earnings-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#sub-tab-earnings"
                    data-tab="earnings"
                    aria-controls="sub-tab-earnings"
                    aria-selected="<?= $earningsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                    <span class="estate-sale-tab-label">Earnings</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="tab-content">
            <div
                class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>"
                id="sub-tab-details"
                role="tabpanel"
                aria-labelledby="sub-details-tab"
                tabindex="0"
            >
                <dl class="row mb-0">
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9"><?= e($name) ?></dd>

                    <dt class="col-sm-3">Company</dt>
                    <dd class="col-sm-9"><?= e($company !== '' ? $company : '—') ?></dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        <span class="badge <?= $status === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(ucfirst($status)) ?></span>
                    </dd>

                    <dt class="col-sm-3">Phone</dt>
                    <dd class="col-sm-9">
                        <?php if ($phoneHref !== ''): ?>
                            <a href="<?= e($phoneHref) ?>"><?= e(format_phone($phone)) ?></a>
                        <?php else: ?>
                            <?= e($phone !== '' ? format_phone($phone) : '—') ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?= e($email !== '' ? $email : '—') ?></dd>

                    <dt class="col-sm-3">Address</dt>
                    <dd class="col-sm-9">
                        <?php if ($formattedAddress !== ''): ?>
                            <?php if ($mapsAddressUrl !== ''): ?>
                                <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($formattedAddress) ?></a>
                            <?php else: ?>
                                <?= e($formattedAddress) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-3">Notes</dt>
                    <dd class="col-sm-9"><?= nl2br(e($notes !== '' ? $notes : '—')) ?></dd>
                </dl>
            </div>

            <div
                class="tab-pane fade<?= $jobsTabActive ? ' show active' : '' ?>"
                id="sub-tab-jobs"
                role="tabpanel"
                aria-labelledby="sub-jobs-tab"
                tabindex="0"
            >
                <?php if ($assignments === []): ?>
                    <div class="record-empty mb-0">No jobs assigned to this sub-contractor yet. Use <strong>Sub Out</strong> on a job to send work their way.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Job location</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>Completed</th>
                                    <th class="text-end">Sub earned</th>
                                    <th class="text-end">Our cut</th>
                                    <th class="text-end">Travel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php if (!is_array($assignment)) continue; ?>
                                    <?php
                                    $jobId = (int) ($assignment['job_id'] ?? 0);
                                    $jobTitle = trim((string) ($assignment['job_title'] ?? '')) ?: ('Job #' . (string) $jobId);
                                    $assignmentStatus = strtolower(trim((string) ($assignment['status'] ?? 'assigned')));
                                    $subAmount = $assignment['sub_amount'] ?? null;
                                    $ourCut = $assignment['our_cut'] ?? null;
                                    $jobAddress = $jobAddressLabel($assignment);
                                    $travelUrl = $formattedAddress !== '' && $jobAddress !== ''
                                        ? maps_directions_url_between($formattedAddress, $jobAddress)
                                        : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($jobId > 0): ?>
                                                <a href="<?= e(url('/jobs/' . (string) $jobId)) ?>"><?= e($jobTitle) ?></a>
                                            <?php else: ?>
                                                <?= e($jobTitle) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($jobAddress !== '' ? $jobAddress : '—') ?></td>
                                        <td><?= e($statusLabel($assignmentStatus)) ?></td>
                                        <td><?= e($formatDate((string) ($assignment['assigned_at'] ?? ''))) ?></td>
                                        <td><?= e($formatDate((string) ($assignment['completed_at'] ?? ''))) ?></td>
                                        <td class="text-end">
                                            <?= $assignmentStatus === 'completed' && $subAmount !== null ? e($formatMoney((float) $subAmount)) : '—' ?>
                                        </td>
                                        <td class="text-end">
                                            <?= $assignmentStatus === 'completed' && $ourCut !== null ? e($formatMoney((float) $ourCut)) : '—' ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($travelUrl !== ''): ?>
                                                <a class="btn btn-outline-secondary btn-sm" href="<?= e($travelUrl) ?>" target="_blank" rel="noopener noreferrer">Directions</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div
                class="tab-pane fade<?= $earningsTabActive ? ' show active' : '' ?>"
                id="sub-tab-earnings"
                role="tabpanel"
                aria-labelledby="sub-earnings-tab"
                tabindex="0"
            >
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Jobs assigned</div>
                        <div class="h5 mb-0"><?= e((string) ((int) ($earnings['job_count'] ?? 0))) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Completed</div>
                        <div class="h5 mb-0"><?= e((string) ((int) ($earnings['completed_count'] ?? 0))) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Total earned (sub)</div>
                        <div class="h5 mb-0"><?= e($formatMoney((float) ($earnings['sub_total'] ?? 0))) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Our cut (completed)</div>
                        <div class="h5 mb-0"><?= e($formatMoney((float) ($earnings['our_cut_total'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabList = document.getElementById('subcontractor-tabs');
    const syncTabUrl = (tab) => {
        const normalized = String(tab || '').trim().toLowerCase();
        if (normalized === '' || normalized === 'details') {
            const url = new URL(window.location.href);
            url.searchParams.delete('tab');
            const next = url.pathname + url.search + url.hash;
            const current = window.location.pathname + window.location.search + window.location.hash;
            if (next !== current) {
                window.history.replaceState(null, '', next);
            }
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('tab', normalized);
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
