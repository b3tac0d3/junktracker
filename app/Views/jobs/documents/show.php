<?php
    $jobId = (int) ($job['id'] ?? 0);
    $documentId = (int) ($document['id'] ?? 0);
    $typeLabel = \App\Models\JobDocument::typeLabel((string) ($document['document_type'] ?? 'document'));
    $statusLabel = \App\Models\JobDocument::statusLabel((string) ($document['status'] ?? 'draft'));
    $statusClass = match ((string) ($document['status'] ?? 'draft')) {
        'paid' => 'bg-success',
        'approved' => 'bg-primary',
        'quote_sent', 'invoiced', 'partially_paid' => 'bg-warning text-dark',
        'void', 'cancelled' => 'bg-secondary',
        default => 'bg-light text-dark',
    };
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1"><?= e($typeLabel) ?> Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs') ?>">Jobs</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs/' . $jobId) ?>">Job #<?= e((string) $jobId) ?></a></li>
                <li class="breadcrumb-item active"><?= e($typeLabel) ?> #<?= e((string) $documentId) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-info text-white" href="<?= url('/jobs/' . $jobId . '/documents/' . $documentId . '/pdf') ?>" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf me-1"></i>
                PDF / Print
            </a>
            <a class="btn btn-warning" href="<?= url('/jobs/' . $jobId . '/documents/' . $documentId . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit
            </a>
            <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteDocumentModal">
                <i class="fas fa-trash me-1"></i>
                Delete
            </button>
            <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . $jobId . '#estimate-invoice') ?>">Back to Job</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between gap-2 mobile-two-col-buttons">
                    <span><i class="fas fa-file-invoice-dollar me-1"></i>Document Overview</span>
                    <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Type</div>
                            <div class="fw-semibold"><?= e($typeLabel) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Status</div>
                            <div class="fw-semibold"><?= e($statusLabel) ?></div>
                        </div>
                        <div class="col-md-8">
                            <div class="text-muted small">Title</div>
                            <div class="fw-semibold"><?= e((string) (($document['title'] ?? '') !== '' ? $document['title'] : '—')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Amount</div>
                            <div class="fw-semibold"><?= isset($document['amount']) && $document['amount'] !== null ? e('$' . number_format((float) $document['amount'], 2)) : '—' ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Issued At</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['issued_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Due At</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['due_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Sent At</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['sent_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Approved At</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['approved_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Paid At</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['paid_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Notes</div>
                            <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($document['note'] ?? '') !== '' ? $document['note'] : '—')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>
                    Activity
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Created</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['created_at'] ?? null)) ?></div>
                            <div class="small text-muted"><?= e((string) (($document['created_by_name'] ?? '') !== '' ? $document['created_by_name'] : '—')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Updated</div>
                            <div class="fw-semibold"><?= e(format_datetime($document['updated_at'] ?? null)) ?></div>
                            <div class="small text-muted"><?= e((string) (($document['updated_by_name'] ?? '') !== '' ? $document['updated_by_name'] : '—')) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-timeline me-1"></i>
                    Status Trail
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>At</th>
                                    <th>Event</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="3" class="text-muted">No events recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><?= e(format_datetime($event['created_at'] ?? null)) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string) ($event['event_type'] ?? 'updated')))) ?></div>
                                                <?php if (!empty($event['from_status']) || !empty($event['to_status'])): ?>
                                                    <div class="small text-muted">
                                                        <?= e(\App\Models\JobDocument::statusLabel((string) ($event['from_status'] ?? 'draft'))) ?>
                                                        →
                                                        <?= e(\App\Models\JobDocument::statusLabel((string) ($event['to_status'] ?? 'draft'))) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($event['event_note'])): ?>
                                                    <div class="small text-muted"><?= e((string) $event['event_note']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e((string) (($event['created_by_name'] ?? '') !== '' ? $event['created_by_name'] : 'System')) ?></td>
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

    <div class="modal fade" id="deleteDocumentModal" tabindex="-1" aria-labelledby="deleteDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDocumentModalLabel">Delete <?= e($typeLabel) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    This will remove the document from active workflow lists. Continue?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?= url('/jobs/' . $jobId . '/documents/' . $documentId . '/delete') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger" type="submit">Delete Document</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
