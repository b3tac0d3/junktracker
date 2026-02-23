<?php
    $ticket = is_array($ticket ?? null) ? $ticket : [];
    $notes = is_array($notes ?? null) ? $notes : [];
    $statusLabel = (string) ($statusLabel ?? 'Unopened');
    $categoryLabel = (string) ($categoryLabel ?? 'Other');
    $ticketId = (int) ($ticket['id'] ?? 0);
    $status = (string) ($ticket['status'] ?? 'unopened');
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Site Request #<?= e((string) $ticketId) ?></h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/support') ?>">Site Requests</a></li>
                <li class="breadcrumb-item active">#<?= e((string) $ticketId) ?></li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/support') ?>">Back to Requests</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-envelope-open-text me-1"></i>Request Details</span>
                    <span class="badge <?= $status === 'closed' ? 'bg-secondary' : ($status === 'working' ? 'bg-primary' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-danger')) ?>">
                        <?= e($statusLabel) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-muted small text-uppercase">Subject</div>
                        <div class="fw-semibold"><?= e((string) ($ticket['subject'] ?? '')) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small text-uppercase">Category</div>
                        <div><?= e($categoryLabel) ?></div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small text-uppercase">Message</div>
                        <div><?= nl2br(e((string) ($ticket['message'] ?? ''))) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-info-circle me-1"></i>Snapshot</div>
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Priority</div>
                    <div class="fw-semibold mb-2"><?= e((string) ((int) ($ticket['priority'] ?? 3))) ?></div>
                    <div class="text-muted small text-uppercase">Assigned To</div>
                    <div class="fw-semibold mb-2"><?= e((string) (($ticket['assigned_to_name'] ?? '') !== '' ? $ticket['assigned_to_name'] : 'Unassigned')) ?></div>
                    <div class="text-muted small text-uppercase">Reply Email</div>
                    <div class="fw-semibold mb-2"><?= e((string) ($ticket['submitted_by_email'] ?? '')) ?></div>
                    <div class="text-muted small text-uppercase">Business</div>
                    <div class="fw-semibold mb-2"><?= e((string) (($ticket['business_name'] ?? '') !== '' ? $ticket['business_name'] : '—')) ?></div>
                    <div class="text-muted small text-uppercase">Created</div>
                    <div class="fw-semibold mb-2"><?= e(format_datetime($ticket['created_at'] ?? null)) ?></div>
                    <div class="text-muted small text-uppercase">Updated</div>
                    <div class="fw-semibold"><?= e(format_datetime($ticket['updated_at'] ?? null)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-notes-medical me-1"></i>Conversation</div>
        <div class="card-body">
            <?php if (empty($notes)): ?>
                <div class="text-muted mb-3">No updates yet.</div>
            <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($notes as $note): ?>
                        <div class="list-group-item">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <strong><?= e((string) (($note['user_name'] ?? '') !== '' ? $note['user_name'] : 'System')) ?></strong>
                                <span class="small text-muted"><?= e(format_datetime($note['created_at'] ?? null)) ?></span>
                            </div>
                            <div class="small text-muted mb-1">Update</div>
                            <div><?= nl2br(e((string) ($note['note'] ?? ''))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('/support/' . $ticketId . '/notes') ?>">
                <?= csrf_field() ?>
                <label class="form-label" for="note">Add Reply / Follow-Up</label>
                <textarea class="form-control" id="note" name="note" rows="4" required><?= e((string) old('note', '')) ?></textarea>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit">Send Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
