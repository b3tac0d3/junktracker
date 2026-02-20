<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Estate Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/estates') ?>">Estates</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($estate['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-warning" href="<?= url('/estates/' . ($estate['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Estate
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/estates') ?>">Back to Estates</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-house me-1"></i>
            Estate Overview
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Estate Name</div>
                    <div class="fw-semibold"><?= e((string) ($estate['name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Estate ID</div>
                    <div class="fw-semibold">#<?= e((string) ($estate['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (empty($estate['deleted_at']) && !empty($estate['active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Primary Client</div>
                    <div class="fw-semibold">
                        <?php if (!empty($estate['client_id'])): ?>
                            <a class="text-decoration-none" href="<?= url('/clients/' . $estate['client_id']) ?>">
                                <?= e((string) (($estate['primary_client_name'] ?? '') !== '' ? $estate['primary_client_name'] : ('Client #' . $estate['client_id']))) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e(format_phone($estate['phone'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e((string) (($estate['email'] ?? '') !== '' ? $estate['email'] : '—')) ?></div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Can Text</div>
                    <div class="fw-semibold"><?= !empty($estate['can_text']) ? 'Yes' : 'No' ?></div>
                </div>
                <div class="col-md-9">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold">
                        <?php
                            $line1 = trim((string) ($estate['address_1'] ?? ''));
                            $line2 = trim((string) ($estate['address_2'] ?? ''));
                            $city = trim((string) ($estate['city'] ?? ''));
                            $state = trim((string) ($estate['state'] ?? ''));
                            $zip = trim((string) ($estate['zip'] ?? ''));
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

                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($estate['note'] ?? '') !== '' ? $estate['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Related Clients
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
                        <?php if (empty($relatedClients)): ?>
                            <tr>
                                <td colspan="5" class="text-muted">No related clients found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($relatedClients as $client): ?>
                                <tr>
                                    <td><?= e((string) ($client['id'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/clients/' . ($client['id'] ?? '')) ?>">
                                            <?= e((string) ($client['label'] ?? '—')) ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_phone($client['phone'] ?? null)) ?></td>
                                    <td><?= e((string) (($client['email'] ?? '') !== '' ? $client['email'] : '—')) ?></td>
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
                <div class="col-md-4">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($estate['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($estate['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($estate['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($estate['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($estate['deleted_at'])): ?>
                    <div class="col-md-4">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($estate['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($estate['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
