<?php
    $consignor = $consignor ?? [];
    $contacts = $contacts ?? [];
    $contracts = $contracts ?? [];
    $payouts = $payouts ?? [];
    $contactMethods = $contactMethods ?? ['call', 'text', 'email', 'appointment', 'other'];
    $contactDirections = $contactDirections ?? ['outbound', 'inbound'];
    $payoutMethods = $payoutMethods ?? ['cash', 'check', 'ach', 'zelle', 'venmo', 'paypal', 'other'];
    $payoutStatuses = $payoutStatuses ?? ['paid', 'scheduled', 'pending', 'void'];

    $displayName = trim((string) ($consignor['display_name'] ?? ''));
    if ($displayName === '') {
        $displayName = 'Consignor #' . (string) ($consignor['id'] ?? '');
    }

    $fullName = trim((string) ($consignor['first_name'] ?? '') . ' ' . (string) ($consignor['last_name'] ?? ''));
    $businessName = trim((string) ($consignor['business_name'] ?? ''));
    $email = trim((string) ($consignor['email'] ?? ''));
    $phone = trim((string) ($consignor['phone'] ?? ''));
    $paymentScheduleLabel = trim((string) ($consignor['payment_schedule'] ?? '')) !== ''
        ? ucfirst((string) $consignor['payment_schedule'])
        : '—';

    $fileSizeLabel = static function (mixed $bytes): string {
        $size = (int) ($bytes ?? 0);
        if ($size <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $size;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    };

    $contactedAtDefault = format_datetime_local(date('Y-m-d H:i:s'));
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Consignor Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/consignors') ?>">Consignors</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($consignor['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-warning" href="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Consignor
            </a>
            <?php if (empty($consignor['deleted_at']) && !empty($consignor['active'])): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deactivateConsignorModal">
                    <i class="fas fa-user-slash me-1"></i>
                    Deactivate
                </button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/consignors') ?>">Back to Consignors</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Contracts</div>
                    <div class="h4 mb-1 text-primary"><?= e((string) ((int) ($consignor['contract_count'] ?? 0))) ?></div>
                    <div class="small text-muted">Active contracts on file</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Contacts Logged</div>
                    <div class="h4 mb-1 text-info"><?= e((string) ((int) ($consignor['contact_count'] ?? 0))) ?></div>
                    <div class="small text-muted">Calls, texts, emails</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Payouts Logged</div>
                    <div class="h4 mb-1 text-secondary"><?= e((string) ((int) ($consignor['payout_count'] ?? 0))) ?></div>
                    <div class="small text-muted">All payout records</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Paid</div>
                    <div class="h4 mb-1 text-success"><?= e('$' . number_format((float) ($consignor['total_paid'] ?? 0), 2)) ?></div>
                    <div class="small text-muted">Lifetime consignor payouts</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-id-card me-1"></i>
            Consignor Overview
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Display Name</div>
                    <div class="fw-semibold"><?= e($displayName) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Full Name</div>
                    <div class="fw-semibold"><?= e($fullName !== '' ? $fullName : '—') ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Business Name</div>
                    <div class="fw-semibold"><?= e($businessName !== '' ? $businessName : '—') ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Consignor Number</div>
                    <div class="fw-semibold"><?= e((string) (($consignor['consignor_number'] ?? '') !== '' ? $consignor['consignor_number'] : '—')) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold">
                        <?php if ($phone !== ''): ?>
                            <a class="text-decoration-none" href="tel:<?= e($phone) ?>"><?= e(format_phone($phone)) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold">
                        <?php if ($email !== ''): ?>
                            <a class="text-decoration-none" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (empty($consignor['deleted_at']) && !empty($consignor['active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold">
                        <?php
                            $line1 = trim((string) ($consignor['address_1'] ?? ''));
                            $line2 = trim((string) ($consignor['address_2'] ?? ''));
                            $city = trim((string) ($consignor['city'] ?? ''));
                            $stateVal = trim((string) ($consignor['state'] ?? ''));
                            $zip = trim((string) ($consignor['zip'] ?? ''));
                            $cityStateZip = trim($city . ($city !== '' && $stateVal !== '' ? ', ' : '') . $stateVal . ($zip !== '' ? ' ' . $zip : ''));
                        ?>
                        <?php if ($line1 === '' && $line2 === '' && $cityStateZip === ''): ?>
                            —
                        <?php else: ?>
                            <?= e($line1) ?>
                            <?php if ($line2 !== ''): ?><br><?= e($line2) ?><?php endif; ?>
                            <?php if ($cityStateZip !== ''): ?><br><?= e($cityStateZip) ?><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Inventory Estimate</div>
                    <div class="fw-semibold text-success"><?= e(isset($consignor['inventory_estimate_amount']) && $consignor['inventory_estimate_amount'] !== null ? ('$' . number_format((float) $consignor['inventory_estimate_amount'], 2)) : '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Consignor ID</div>
                    <div class="fw-semibold">#<?= e((string) ($consignor['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Consignment Start</div>
                    <div class="fw-semibold"><?= e(format_date($consignor['consignment_start_date'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Potential End</div>
                    <div class="fw-semibold"><?= e(format_date($consignor['consignment_end_date'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Payment Schedule</div>
                    <div class="fw-semibold"><?= e($paymentScheduleLabel) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Next Payment Due</div>
                    <div class="fw-semibold"><?= e(format_date($consignor['next_payment_due_date'] ?? null)) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Inventory Description</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($consignor['inventory_description'] ?? '') !== '' ? $consignor['inventory_description'] : '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Internal Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($consignor['note'] ?? '') !== '' ? $consignor['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-file-signature me-1"></i>
                    Add Contract
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/contracts/new') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="contract_title">Title</label>
                                <input class="form-control" id="contract_title" name="contract_title" type="text" value="<?= e((string) old('contract_title', '')) ?>" required />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contract_file">Document</label>
                                <input class="form-control" id="contract_file" name="contract_file" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required />
                                <div class="form-text">Stored securely in private server storage.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contract_signed_at">Signed Date</label>
                                <input class="form-control" id="contract_signed_at" name="contract_signed_at" type="date" value="<?= e((string) old('contract_signed_at', '')) ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expires_at">Expires Date</label>
                                <input class="form-control" id="expires_at" name="expires_at" type="date" value="<?= e((string) old('expires_at', '')) ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contract_notes">Notes</label>
                                <textarea class="form-control" id="contract_notes" name="notes" rows="3"><?= e((string) old('notes', '')) ?></textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-upload me-1"></i>
                                Upload Contract
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-folder-open me-1"></i>
                    Contract Records
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Signed</th>
                                    <th>Expires</th>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contracts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No contracts on file.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contracts as $contract): ?>
                                        <tr>
                                            <td><?= e((string) ($contract['contract_title'] ?? '—')) ?></td>
                                            <td><?= e(format_date($contract['contract_signed_at'] ?? null)) ?></td>
                                            <td><?= e(format_date($contract['expires_at'] ?? null)) ?></td>
                                            <td>
                                                <div><?= e((string) ($contract['original_file_name'] ?? '—')) ?></div>
                                                <small class="text-muted"><?= e($fileSizeLabel($contract['file_size'] ?? null)) ?></small>
                                            </td>
                                            <td><?= e(format_datetime($contract['created_at'] ?? null)) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/contracts/' . ((int) ($contract['id'] ?? 0)) . '/download') ?>" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <form class="d-inline" method="post" action="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/contracts/' . ((int) ($contract['id'] ?? 0)) . '/delete') ?>" onsubmit="return confirm('Delete this contract record?');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-dollar-sign me-1"></i>
                    Add Payout
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/payouts/new') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="payout_date">Payout Date</label>
                                <input class="form-control" id="payout_date" name="payout_date" type="date" value="<?= e((string) old('payout_date', date('Y-m-d'))) ?>" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="amount">Amount</label>
                                <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" value="<?= e((string) old('amount', '')) ?>" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="estimate_amount">Estimate (Optional)</label>
                                <input class="form-control" id="estimate_amount" name="estimate_amount" type="number" min="0" step="0.01" value="<?= e((string) old('estimate_amount', '')) ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="reference_no">Reference</label>
                                <input class="form-control" id="reference_no" name="reference_no" type="text" value="<?= e((string) old('reference_no', '')) ?>" placeholder="Check # / ACH ref" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payout_method">Method</label>
                                <select class="form-select" id="payout_method" name="payout_method">
                                    <?php foreach ($payoutMethods as $method): ?>
                                        <?php $selected = strtolower((string) old('payout_method', 'other')) === strtolower((string) $method); ?>
                                        <option value="<?= e((string) $method) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst((string) $method)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payout_status">Status</label>
                                <select class="form-select" id="payout_status" name="status">
                                    <?php foreach ($payoutStatuses as $status): ?>
                                        <?php $selected = strtolower((string) old('status', 'paid')) === strtolower((string) $status); ?>
                                        <option value="<?= e((string) $status) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst((string) $status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="payout_notes">Notes</label>
                                <textarea class="form-control" id="payout_notes" name="notes" rows="3"><?= e((string) old('notes', '')) ?></textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-plus me-1"></i>
                                Add Payout
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-money-check-dollar me-1"></i>
                    Payout History
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Estimate</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payouts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No payouts logged.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payouts as $payout): ?>
                                        <tr>
                                            <td><?= e(format_date($payout['payout_date'] ?? null)) ?></td>
                                            <td class="text-success"><?= e('$' . number_format((float) ($payout['amount'] ?? 0), 2)) ?></td>
                                            <td><?= e(isset($payout['estimate_amount']) && $payout['estimate_amount'] !== null ? ('$' . number_format((float) $payout['estimate_amount'], 2)) : '—') ?></td>
                                            <td><?= e(ucfirst((string) ($payout['payout_method'] ?? 'other'))) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= e(ucfirst((string) ($payout['status'] ?? 'paid'))) ?></span></td>
                                            <td><?= e((string) (($payout['reference_no'] ?? '') !== '' ? $payout['reference_no'] : '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-phone me-1"></i>
                    Log Contact
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/contacts/new') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="contact_method">Method</label>
                                <select class="form-select" id="contact_method" name="contact_method">
                                    <?php foreach ($contactMethods as $method): ?>
                                        <?php $selected = strtolower((string) old('contact_method', 'call')) === strtolower((string) $method); ?>
                                        <option value="<?= e((string) $method) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst((string) $method)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="direction">Direction</label>
                                <select class="form-select" id="direction" name="direction">
                                    <?php foreach ($contactDirections as $direction): ?>
                                        <?php $selected = strtolower((string) old('direction', 'outbound')) === strtolower((string) $direction); ?>
                                        <option value="<?= e((string) $direction) ?>" <?= $selected ? 'selected' : '' ?>><?= e(ucfirst((string) $direction)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contact_subject">Subject</label>
                                <input class="form-control" id="contact_subject" name="subject" type="text" value="<?= e((string) old('subject', '')) ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contacted_at">Contacted At</label>
                                <input class="form-control" id="contacted_at" name="contacted_at" type="datetime-local" value="<?= e((string) old('contacted_at', $contactedAtDefault)) ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="follow_up_at">Follow Up</label>
                                <input class="form-control" id="follow_up_at" name="follow_up_at" type="datetime-local" value="<?= e((string) old('follow_up_at', '')) ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contact_notes">Notes</label>
                                <textarea class="form-control" id="contact_notes" name="notes" rows="3"><?= e((string) old('notes', '')) ?></textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-plus me-1"></i>
                                Add Contact
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>
                    Contact History
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Direction</th>
                                    <th>Subject</th>
                                    <th>Follow Up</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contacts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No contacts logged.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?= e(format_datetime($contact['contacted_at'] ?? null)) ?></td>
                                            <td><?= e(ucfirst((string) ($contact['contact_method'] ?? 'call'))) ?></td>
                                            <td><?= e(ucfirst((string) ($contact['direction'] ?? 'outbound'))) ?></td>
                                            <td><?= e((string) (($contact['subject'] ?? '') !== '' ? $contact['subject'] : '—')) ?></td>
                                            <td><?= e(format_datetime($contact['follow_up_at'] ?? null)) ?></td>
                                            <td style="white-space: pre-wrap;"><?= e((string) (($contact['notes'] ?? '') !== '' ? $contact['notes'] : '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($consignor['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($consignor['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($consignor['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($consignor['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($consignor['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($consignor['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($consignor['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($consignor['deleted_at']) && !empty($consignor['active'])): ?>
        <div class="modal fade" id="deactivateConsignorModal" tabindex="-1" aria-labelledby="deactivateConsignorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateConsignorModalLabel">Deactivate Consignor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the consignor and hide them from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/deactivate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Deactivate</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
