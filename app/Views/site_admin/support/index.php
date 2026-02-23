<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $summary = is_array($summary ?? null) ? $summary : [];
    $tickets = is_array($tickets ?? null) ? $tickets : [];
    $categories = is_array($categories ?? null) ? $categories : [];
    $statuses = is_array($statuses ?? null) ? $statuses : [];
    $assignees = is_array($assignees ?? null) ? $assignees : [];
    $businesses = is_array($businesses ?? null) ? $businesses : [];

    $currentStatus = trim((string) ($filters['status'] ?? 'all'));
    $currentCategory = trim((string) ($filters['category'] ?? 'all'));
    $currentPriority = isset($filters['priority']) ? (int) $filters['priority'] : 0;
    $currentAssigned = trim((string) ($filters['assigned_to_user_id'] ?? ''));
    $currentBusiness = isset($filters['business_id']) ? (int) $filters['business_id'] : 0;
    $query = trim((string) ($filters['q'] ?? ''));
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Site Admin Queue</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                <li class="breadcrumb-item active">Support Queue</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Back to Site Admin</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a class="card h-100 text-decoration-none" href="<?= url('/site-admin/support?status=all') ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total</div>
                    <div class="h3 mb-0"><?= e((string) ((int) ($summary['total_count'] ?? 0))) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card h-100 text-decoration-none" href="<?= url('/site-admin/support?status=unopened') ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Unopened</div>
                    <div class="h3 mb-0 text-danger"><?= e((string) ((int) ($summary['unopened_count'] ?? 0))) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card h-100 text-decoration-none" href="<?= url('/site-admin/support?status=pending') ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Pending</div>
                    <div class="h3 mb-0 text-warning"><?= e((string) ((int) ($summary['pending_count'] ?? 0))) ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card h-100 text-decoration-none" href="<?= url('/site-admin/support?status=working') ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Working</div>
                    <div class="h3 mb-0 text-primary"><?= e((string) ((int) ($summary['working_count'] ?? 0))) ?></div>
                </div>
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-filter me-1"></i>Filters</div>
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="<?= url('/site-admin/support') ?>">
                <div class="col-md-3">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e($query) ?>" placeholder="ID, subject, message, email..." />
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?= $currentStatus === 'all' ? 'selected' : '' ?>>All</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= $currentStatus === $status ? 'selected' : '' ?>>
                                <?= e(\App\Models\SiteAdminTicket::labelStatus($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="category">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="all" <?= $currentCategory === 'all' ? 'selected' : '' ?>>All</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>" <?= $currentCategory === $category ? 'selected' : '' ?>>
                                <?= e(\App\Models\SiteAdminTicket::labelCategory($category)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="0" <?= $currentPriority <= 0 ? 'selected' : '' ?>>All</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= e((string) $i) ?>" <?= $currentPriority === $i ? 'selected' : '' ?>><?= e((string) $i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="assigned_to_user_id">Assigned</label>
                    <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
                        <option value="" <?= $currentAssigned === '' ? 'selected' : '' ?>>All</option>
                        <option value="unassigned" <?= $currentAssigned === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($assignees as $assignee): ?>
                            <?php $assigneeId = (int) ($assignee['id'] ?? 0); ?>
                            <option value="<?= e((string) $assigneeId) ?>" <?= $currentAssigned === (string) $assigneeId ? 'selected' : '' ?>>
                                <?= e((string) ($assignee['name'] ?? ('User #' . $assigneeId))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="business_id">Business</label>
                    <select class="form-select" id="business_id" name="business_id">
                        <option value="0" <?= $currentBusiness <= 0 ? 'selected' : '' ?>>All</option>
                        <?php foreach ($businesses as $business): ?>
                            <?php $businessId = (int) ($business['id'] ?? 0); ?>
                            <option value="<?= e((string) $businessId) ?>" <?= $currentBusiness === $businessId ? 'selected' : '' ?>>
                                <?= e((string) ($business['name'] ?? ('Business #' . $businessId))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/site-admin/support') ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-inbox me-1"></i>Queue</div>
        <div class="card-body p-0">
            <?php if (empty($tickets)): ?>
                <div class="p-3 text-muted">No support tickets found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Category</th>
                                <th>Business</th>
                                <th>Assigned</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                    $ticketId = (int) ($ticket['id'] ?? 0);
                                    $status = (string) ($ticket['status'] ?? 'unopened');
                                ?>
                                <tr>
                                    <td>#<?= e((string) $ticketId) ?></td>
                                    <td>
                                        <a class="fw-semibold text-decoration-none" href="<?= url('/site-admin/support/' . $ticketId) ?>">
                                            <?= e((string) ($ticket['subject'] ?? ('Ticket #' . $ticketId))) ?>
                                        </a>
                                        <div class="small text-muted">
                                            From: <?= e((string) (($ticket['submitted_by_name'] ?? '') !== '' ? $ticket['submitted_by_name'] : ('User #' . (int) ($ticket['submitted_by_user_id'] ?? 0)))) ?>
                                            &middot; Notes: <?= e((string) ((int) ($ticket['note_count'] ?? 0))) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status === 'closed' ? 'bg-secondary' : ($status === 'working' ? 'bg-primary' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-danger')) ?>">
                                            <?= e(\App\Models\SiteAdminTicket::labelStatus($status)) ?>
                                        </span>
                                    </td>
                                    <td><?= e((string) ((int) ($ticket['priority'] ?? 3))) ?></td>
                                    <td><?= e(\App\Models\SiteAdminTicket::labelCategory((string) ($ticket['category'] ?? 'other'))) ?></td>
                                    <td><?= e((string) (($ticket['business_name'] ?? '') !== '' ? $ticket['business_name'] : '—')) ?></td>
                                    <td><?= e((string) (($ticket['assigned_to_name'] ?? '') !== '' ? $ticket['assigned_to_name'] : 'Unassigned')) ?></td>
                                    <td><?= e(format_datetime($ticket['updated_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
