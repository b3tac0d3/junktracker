<?php
    $ticket = is_array($ticket ?? null) ? $ticket : [];
    $notes = is_array($notes ?? null) ? $notes : [];
    $categories = is_array($categories ?? null) ? $categories : [];
    $statuses = is_array($statuses ?? null) ? $statuses : [];
    $assignees = is_array($assignees ?? null) ? $assignees : [];
    $statusLabel = (string) ($statusLabel ?? 'Unopened');
    $categoryLabel = (string) ($categoryLabel ?? 'Other');
    $ticketId = (int) ($ticket['id'] ?? 0);
    $status = (string) ($ticket['status'] ?? 'unopened');
    $convertedBugId = (int) ($ticket['converted_bug_id'] ?? 0);
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Support Ticket #<?= e((string) $ticketId) ?></h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/site-admin/support') ?>">Support Queue</a></li>
                <li class="breadcrumb-item active">#<?= e((string) $ticketId) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url('/site-admin/support') ?>">Back to Queue</a>
            <?php if ($convertedBugId > 0): ?>
                <a class="btn btn-outline-primary" href="<?= url('/dev/bugs/' . $convertedBugId) ?>">Open Bug #<?= e((string) $convertedBugId) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-envelope-open-text me-1"></i>Requester Message</span>
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
                    <div class="mb-0">
                        <div class="text-muted small text-uppercase">Message</div>
                        <div><?= nl2br(e((string) ($ticket['message'] ?? ''))) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-sliders-h me-1"></i>Admin Controls</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/site-admin/support/' . $ticketId . '/update') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statuses as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>" <?= (string) ($ticket['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                                        <?= e(\App\Models\SiteAdminTicket::labelStatus($statusOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="category">Category</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category) ?>" <?= (string) ($ticket['category'] ?? '') === $category ? 'selected' : '' ?>>
                                        <?= e(\App\Models\SiteAdminTicket::labelCategory($category)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="priority">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= e((string) $i) ?>" <?= (int) ($ticket['priority'] ?? 3) === $i ? 'selected' : '' ?>><?= e((string) $i) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="assigned_to_user_id">Assigned To</label>
                            <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
                                <option value="0">Unassigned</option>
                                <?php foreach ($assignees as $assignee): ?>
                                    <?php $assigneeId = (int) ($assignee['id'] ?? 0); ?>
                                    <option value="<?= e((string) $assigneeId) ?>" <?= (int) ($ticket['assigned_to_user_id'] ?? 0) === $assigneeId ? 'selected' : '' ?>>
                                        <?= e((string) ($assignee['name'] ?? ('User #' . $assigneeId))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="note">Status/Update Note (optional)</label>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="Add context for status or assignment changes..."></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="internal_only" name="internal_only" type="checkbox" value="1" />
                            <label class="form-check-label" for="internal_only">Internal note only (hidden from requester)</label>
                        </div>
                        <div class="d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Save Changes</button>
                        </div>
                    </form>

                    <form class="mt-2" method="post" action="<?= url('/site-admin/support/' . $ticketId . '/pickup') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-success w-100" type="submit">Pick Up</button>
                    </form>

                    <?php if ($convertedBugId <= 0): ?>
                        <hr />
                        <form method="post" action="<?= url('/site-admin/support/' . $ticketId . '/convert-bug') ?>">
                            <?= csrf_field() ?>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" for="severity">Bug Severity</label>
                                    <select class="form-select" id="severity" name="severity">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?= e((string) $i) ?>" <?= (int) ($ticket['priority'] ?? 3) === $i ? 'selected' : '' ?>>P<?= e((string) $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="environment">Environment</label>
                                    <select class="form-select" id="environment" name="environment">
                                        <option value="both">Both</option>
                                        <option value="live">Live</option>
                                        <option value="local">Local</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-outline-danger w-100 mt-2" type="submit">
                                <i class="fas fa-bug me-1"></i>
                                Close + Convert to Bug
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-id-card me-1"></i>Requester Snapshot</div>
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Requester</div>
                    <div class="fw-semibold mb-2"><?= e((string) (($ticket['submitted_by_name'] ?? '') !== '' ? $ticket['submitted_by_name'] : ('User #' . (int) ($ticket['submitted_by_user_id'] ?? 0)))) ?></div>
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
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-notes-medical me-1"></i>Ticket Notes</div>
                <div class="card-body">
                    <?php if (empty($notes)): ?>
                        <div class="text-muted">No notes yet.</div>
                    <?php else: ?>
                        <div class="list-group mb-3">
                            <?php foreach ($notes as $note): ?>
                                <?php $isInternal = (string) ($note['visibility'] ?? 'customer') === 'internal'; ?>
                                <div class="list-group-item">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <strong><?= e((string) (($note['user_name'] ?? '') !== '' ? $note['user_name'] : 'System')) ?></strong>
                                            <?php if ($isInternal): ?>
                                                <span class="badge bg-secondary ms-2">Internal</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark ms-2">Customer Visible</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="small text-muted"><?= e(format_datetime($note['created_at'] ?? null)) ?></span>
                                    </div>
                                    <div class="mt-1"><?= nl2br(e((string) ($note['note'] ?? ''))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= url('/site-admin/support/' . $ticketId . '/notes') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label" for="new_note">Add Note</label>
                        <textarea class="form-control" id="new_note" name="note" rows="4" required><?= e((string) old('note', '')) ?></textarea>
                        <div class="form-check mt-2">
                            <input class="form-check-input" id="new_note_internal_only" name="internal_only" type="checkbox" value="1" />
                            <label class="form-check-label" for="new_note_internal_only">Internal only</label>
                        </div>
                        <button class="btn btn-primary mt-3" type="submit">Add Note</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
