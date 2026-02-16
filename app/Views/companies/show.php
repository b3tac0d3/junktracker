<?php
    $toHref = static function (?string $value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }
        return 'https://' . $value;
    };
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Company Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/companies') ?>">Companies</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($company['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-warning" href="<?= url('/companies/' . ($company['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Company
            </a>
            <?php if (empty($company['deleted_at']) && !empty($company['active'])): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteCompanyModal">
                    <i class="fas fa-trash me-1"></i>
                    Delete
                </button>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Inactive</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/companies') ?>">Back to Companies</a>
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
            <i class="fas fa-building me-1"></i>
            Company Overview
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Name</div>
                    <div class="fw-semibold"><?= e((string) ($company['name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Company ID</div>
                    <div class="fw-semibold"><?= e((string) ($company['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (empty($company['deleted_at']) && !empty($company['active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e(format_phone($company['phone'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Website</div>
                    <?php $website = trim((string) ($company['web_address'] ?? '')); ?>
                    <div class="fw-semibold">
                        <?php if ($website !== ''): ?>
                            <a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer"><?= e($website) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Linked Clients</div>
                    <div class="fw-semibold"><?= e((string) ($company['client_count'] ?? 0)) ?></div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Facebook</div>
                    <?php $facebook = trim((string) ($company['facebook'] ?? '')); ?>
                    <div class="fw-semibold">
                        <?php if ($facebook !== ''): ?>
                            <a href="<?= e((string) $toHref($facebook)) ?>" target="_blank" rel="noopener noreferrer"><?= e($facebook) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Instagram</div>
                    <?php $instagram = trim((string) ($company['instagram'] ?? '')); ?>
                    <div class="fw-semibold">
                        <?php if ($instagram !== ''): ?>
                            <a href="<?= e((string) $toHref($instagram)) ?>" target="_blank" rel="noopener noreferrer"><?= e($instagram) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">LinkedIn</div>
                    <?php $linkedin = trim((string) ($company['linkedin'] ?? '')); ?>
                    <div class="fw-semibold">
                        <?php if ($linkedin !== ''): ?>
                            <a href="<?= e((string) $toHref($linkedin)) ?>" target="_blank" rel="noopener noreferrer"><?= e($linkedin) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold">
                        <?php
                            $line1 = trim((string) ($company['address_1'] ?? ''));
                            $line2 = trim((string) ($company['address_2'] ?? ''));
                            $city = trim((string) ($company['city'] ?? ''));
                            $state = trim((string) ($company['state'] ?? ''));
                            $zip = trim((string) ($company['zip'] ?? ''));
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
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) ($company['note'] ?? '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Linked Clients
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($linkedClients)): ?>
                            <tr>
                                <td colspan="5" class="text-muted">No linked clients yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($linkedClients as $client): ?>
                                <tr>
                                    <td><?= e((string) ($client['id'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/clients/' . ($client['id'] ?? '')) ?>">
                                            <?= e((string) ($client['display_name'] ?? '—')) ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_phone($client['phone'] ?? null)) ?></td>
                                    <td><?= e((string) ($client['email'] ?? '—')) ?></td>
                                    <td>
                                        <?php
                                            $city = trim((string) ($client['city'] ?? ''));
                                            $state = trim((string) ($client['state'] ?? ''));
                                            $location = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
                                        ?>
                                        <?= e($location !== '' ? $location : '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                    <div class="fw-semibold"><?= e(format_datetime($company['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($company['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($company['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($company['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($company['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($company['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($company['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($company['deleted_at']) && !empty($company['active'])): ?>
        <div class="modal fade" id="deleteCompanyModal" tabindex="-1" aria-labelledby="deleteCompanyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteCompanyModalLabel">Delete Company</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the company and hide it from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/companies/' . ($company['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Delete Company</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
