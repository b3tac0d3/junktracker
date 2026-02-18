<?php
    $contact = $contact ?? [];
    $method = (string) ($contact['contact_method'] ?? 'call');
    $direction = (string) ($contact['direction'] ?? 'outbound');
    $statusBadge = (empty($contact['deleted_at']) && !empty($contact['active'])) ? 'bg-success' : 'bg-secondary';
    $statusLabel = (empty($contact['deleted_at']) && !empty($contact['active'])) ? 'Active' : 'Inactive';
    $addTaskUrl = url('/tasks/new?link_type=client&link_id=' . (string) ($contact['client_id'] ?? ''));
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Client Contact</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/client-contacts') ?>">Client Contacts</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($contact['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-success" href="<?= e($addTaskUrl) ?>">
                <i class="fas fa-list-check me-1"></i>
                Add Follow-Up Task
            </a>
            <a class="btn btn-primary" href="<?= url('/client-contacts/new?client_id=' . (string) ($contact['client_id'] ?? '')) ?>">
                <i class="fas fa-phone me-1"></i>
                Log Another Contact
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/client-contacts') ?>">Back to Contacts</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-phone-volume me-1"></i>
            Contact Details
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Client</div>
                    <div class="fw-semibold">
                        <a class="text-decoration-none" href="<?= url('/clients/' . (string) ($contact['client_id'] ?? '')) ?>">
                            <?= e((string) ($contact['client_name'] ?? '—')) ?>
                        </a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Method</div>
                    <div class="fw-semibold text-capitalize"><?= e(ucwords(str_replace('_', ' ', $method))) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Direction</div>
                    <div class="fw-semibold text-capitalize"><?= e($direction) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Contacted At</div>
                    <div class="fw-semibold"><?= e(format_datetime($contact['contacted_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Follow Up At</div>
                    <div class="fw-semibold"><?= e(format_datetime($contact['follow_up_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold"><span class="badge <?= e($statusBadge) ?>"><?= e($statusLabel) ?></span></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Subject</div>
                    <div class="fw-semibold"><?= e((string) (($contact['subject'] ?? '') !== '' ? $contact['subject'] : '—')) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($contact['notes'] ?? '') !== '' ? $contact['notes'] : '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Linked Type</div>
                    <div class="fw-semibold"><?= e((string) ($contact['link_type_label'] ?? 'General')) ?></div>
                </div>
                <div class="col-md-8">
                    <div class="text-muted small">Linked Record</div>
                    <div class="fw-semibold">
                        <?php if (!empty($contact['link_url'])): ?>
                            <a class="text-decoration-none" href="<?= url((string) $contact['link_url']) ?>">
                                <?= e((string) ($contact['link_label'] ?? '—')) ?>
                            </a>
                        <?php else: ?>
                            <?= e((string) ($contact['link_label'] ?? '—')) ?>
                        <?php endif; ?>
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
                    <div class="fw-semibold"><?= e(format_datetime($contact['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($contact['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($contact['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($contact['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($contact['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($contact['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($contact['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
