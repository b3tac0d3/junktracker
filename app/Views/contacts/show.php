<?php
    $fullName = trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')));
    $displayName = trim((string) ($contact['display_name'] ?? ''));
    if ($displayName === '') {
        $displayName = $fullName;
    }
    if ($displayName === '') {
        $displayName = trim((string) ($contact['email'] ?? ''));
    }
    if ($displayName === '') {
        $displayName = 'Network Client #' . (int) ($contact['id'] ?? 0);
    }

    $isInactive = !empty($contact['deleted_at']) || (int) ($contact['is_active'] ?? 1) !== 1;
    $linkedClientId = (int) ($contact['linked_client_id'] ?? 0);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Network Client Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/network') ?>">Network</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($contact['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-warning" href="<?= url('/network/' . ($contact['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Network Client
            </a>
            <?php if ($canCreateClient && $linkedClientId <= 0): ?>
                <form method="post" action="<?= url('/network/' . ($contact['id'] ?? '') . '/create-client') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-user-plus me-1"></i>
                        Create Client
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!$isInactive): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deactivateContactModal">
                    <i class="fas fa-user-slash me-1"></i>
                    Deactivate
                </button>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Inactive</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/network') ?>">Back to Network</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-address-card me-1"></i>
            Profile
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Display Name</div>
                    <div class="fw-semibold"><?= e($displayName) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Network Client ID</div>
                    <div class="fw-semibold"><?= e((string) ($contact['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (!$isInactive): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Type</div>
                    <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', (string) ($contact['contact_type'] ?? 'general'))) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e(format_phone($contact['phone'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e((string) (($contact['email'] ?? '') !== '' ? $contact['email'] : '—')) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Company</div>
                    <div class="fw-semibold">
                        <?php if (!empty($contact['company_id'])): ?>
                            <a class="text-decoration-none" href="<?= url('/companies/' . (int) $contact['company_id']) ?>">
                                <?= e((string) (($contact['company_name'] ?? '') !== '' ? $contact['company_name'] : ('Company #' . (int) $contact['company_id'])) ) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Linked Client</div>
                    <div class="fw-semibold">
                        <?php if ($linkedClientId > 0): ?>
                            <?php
                                $linkedClientName = trim((string) (($contact['linked_client_first_name'] ?? '') . ' ' . ($contact['linked_client_last_name'] ?? '')));
                                if ($linkedClientName === '') {
                                    $linkedClientName = 'Client #' . $linkedClientId;
                                }
                            ?>
                            <a class="text-decoration-none" href="<?= url('/clients/' . $linkedClientId) ?>"><?= e($linkedClientName) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold">
                        <?php
                            $line1 = trim((string) ($contact['address_1'] ?? ''));
                            $line2 = trim((string) ($contact['address_2'] ?? ''));
                            $city = trim((string) ($contact['city'] ?? ''));
                            $state = trim((string) ($contact['state'] ?? ''));
                            $zip = trim((string) ($contact['zip'] ?? ''));
                            $cityStateZip = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state . ($zip !== '' ? ' ' . $zip : ''));
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
                <div class="col-md-6">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) ($contact['note'] ?? '—')) ?></div>
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
                <div class="col-md-4">
                    <div class="text-muted small">Source</div>
                    <div class="fw-semibold text-capitalize">
                        <?= e((string) (($contact['source_type'] ?? '') !== '' ? $contact['source_type'] : 'manual')) ?>
                        <?php if (!empty($contact['source_id'])): ?>
                            #<?= e((string) $contact['source_id']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($contact['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($contact['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($contact['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($contact['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($contact['deleted_at'])): ?>
                    <div class="col-md-4">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($contact['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($contact['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$isInactive): ?>
        <div class="modal fade" id="deactivateContactModal" tabindex="-1" aria-labelledby="deactivateContactModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateContactModalLabel">Deactivate Network Client</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate this network client and hide it from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/network/' . ($contact['id'] ?? '') . '/deactivate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Deactivate Network Client</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
