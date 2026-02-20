<?php
    $prospect = $prospect ?? [];
    $priorityLabels = $priorityLabels ?? [];
    $priority = (int) ($prospect['priority_rating'] ?? 2);
    $priorityLabel = (string) ($priorityLabels[$priority] ?? 'Normal');
    $priorityClass = match ($priority) {
        4 => 'text-danger',
        3 => 'text-warning',
        1 => 'text-muted',
        default => 'text-primary',
    };

    $status = (string) ($prospect['status'] ?? 'active');
    $statusClass = match ($status) {
        'converted' => 'bg-success',
        'closed' => 'bg-secondary',
        default => 'bg-warning text-dark',
    };
    $isActive = empty($prospect['deleted_at']) && !empty($prospect['active']);
    $contacts = $contacts ?? [];
    $attachments = is_array($attachments ?? null) ? $attachments : [];
    $prospectPath = '/prospects/' . (string) ($prospect['id'] ?? '');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Prospect Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/prospects') ?>">Prospects</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($prospect['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-primary" href="<?= url('/client-contacts/new?prospect_id=' . ($prospect['id'] ?? '')) ?>">
                <i class="fas fa-phone me-1"></i>
                Log Contact
            </a>
            <?php if ($isActive && $status !== 'converted'): ?>
                <a class="btn btn-success" href="<?= url('/prospects/' . ($prospect['id'] ?? '') . '/convert') ?>">
                    <i class="fas fa-briefcase me-1"></i>
                    Convert to Job
                </a>
            <?php endif; ?>
            <?php if ($isActive): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteProspectModal">
                    <i class="fas fa-trash me-1"></i>
                    Delete
                </button>
            <?php endif; ?>
            <a class="btn btn-warning" href="<?= url('/prospects/' . ($prospect['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Prospect
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/prospects') ?>">Back to Prospects</a>
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
            <i class="fas fa-user-clock me-1"></i>
            Pipeline Details
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Prospect ID</div>
                    <div class="fw-semibold"><?= e((string) ($prospect['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold"><span class="badge <?= e($statusClass) ?> text-uppercase"><?= e($status) ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Priority</div>
                    <div class="fw-semibold <?= e($priorityClass) ?>"><?= e($priorityLabel) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Next Step</div>
                    <div class="fw-semibold"><?= e((string) (($prospect['next_step'] ?? '') !== '' ? ucwords(str_replace('_', ' ', (string) $prospect['next_step'])) : '—')) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Client Contact</div>
                    <div class="fw-semibold">
                        <?php if (!empty($prospect['client_id'])): ?>
                            <a class="text-decoration-none" href="<?= url('/clients/' . (string) $prospect['client_id']) ?>">
                                <?= e((string) (($prospect['client_name'] ?? '') !== '' ? $prospect['client_name'] : ('Client #' . $prospect['client_id']))) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Contacted On</div>
                    <div class="fw-semibold"><?= e(format_date($prospect['contacted_on'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Follow Up On</div>
                    <div class="fw-semibold"><?= e(format_date($prospect['follow_up_on'] ?? null)) ?></div>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($prospect['note'] ?? '') !== '' ? $prospect['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 mobile-two-col-buttons">
            <div>
                <i class="fas fa-address-book me-1"></i>
                Contact History
            </div>
            <a class="btn btn-sm btn-primary" href="<?= url('/client-contacts/new?prospect_id=' . ($prospect['id'] ?? '')) ?>">
                <i class="fas fa-plus me-1"></i>
                Log Contact
            </a>
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
                            <th>Notes</th>
                            <th>Linked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No contact history yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <?php $contactUrl = url('/client-contacts/' . (string) ($contact['id'] ?? '')); ?>
                                <tr>
                                    <td>
                                        <a class="text-decoration-none" href="<?= $contactUrl ?>">
                                            <?= e(format_datetime($contact['contacted_at'] ?? null)) ?>
                                        </a>
                                    </td>
                                    <td class="text-capitalize"><?= e(ucwords(str_replace('_', ' ', (string) ($contact['contact_method'] ?? '')))) ?></td>
                                    <td class="text-capitalize"><?= e((string) ($contact['direction'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= $contactUrl ?>">
                                            <?= e((string) (($contact['subject'] ?? '') !== '' ? $contact['subject'] : '—')) ?>
                                        </a>
                                    </td>
                                    <td style="white-space: pre-wrap; max-width: 420px;">
                                        <?= e((string) (($contact['notes'] ?? '') !== '' ? $contact['notes'] : '—')) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($contact['link_url'])): ?>
                                            <a class="text-decoration-none" href="<?= url((string) $contact['link_url']) ?>">
                                                <?= e((string) ($contact['link_label'] ?? '—')) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= e((string) ($contact['link_label'] ?? '—')) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
        $attachmentPanelTitle = 'Attachments';
        $attachmentLinkType = 'prospect';
        $attachmentLinkId = (int) ($prospect['id'] ?? 0);
        $attachmentReturnTo = $prospectPath;
        require __DIR__ . '/../partials/attachments_panel.php';
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($prospect['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($prospect['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($prospect['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($prospect['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($prospect['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($prospect['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($prospect['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isActive): ?>
        <div class="modal fade" id="deleteProspectModal" tabindex="-1" aria-labelledby="deleteProspectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteProspectModalLabel">Delete Prospect</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the prospect and hide it from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/prospects/' . ($prospect['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Delete Prospect</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
