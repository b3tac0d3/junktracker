<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $tickets = is_array($tickets ?? null) ? $tickets : [];
    $statuses = is_array($statuses ?? null) ? $statuses : [];
    $categories = is_array($categories ?? null) ? $categories : [];
    $currentStatus = trim((string) ($filters['status'] ?? 'all'));
    $currentCategory = trim((string) ($filters['category'] ?? 'all'));
    $query = trim((string) ($filters['q'] ?? ''));
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">My Site Requests</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Site Requests</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/support/new') ?>">
            <i class="fas fa-plus me-1"></i>
            New Request
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>Filters
        </div>
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="<?= url('/support') ?>">
                <div class="col-md-4">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e($query) ?>" placeholder="Subject, message, ID..." />
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
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
                <div class="col-md-2 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/support') ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-inbox me-1"></i>Your Requests</div>
        <div class="card-body p-0">
            <?php if (empty($tickets)): ?>
                <div class="p-3 text-muted">No site requests found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                    $id = (int) ($ticket['id'] ?? 0);
                                    $status = (string) ($ticket['status'] ?? 'unopened');
                                ?>
                                <tr>
                                    <td>#<?= e((string) $id) ?></td>
                                    <td>
                                        <a class="fw-semibold text-decoration-none" href="<?= url('/support/' . $id) ?>">
                                            <?= e((string) ($ticket['subject'] ?? ('Ticket #' . $id))) ?>
                                        </a>
                                        <div class="small text-muted"><?= e(substr((string) ($ticket['message'] ?? ''), 0, 120)) ?></div>
                                    </td>
                                    <td><?= e(\App\Models\SiteAdminTicket::labelCategory((string) ($ticket['category'] ?? 'other'))) ?></td>
                                    <td>
                                        <span class="badge <?= $status === 'closed' ? 'bg-secondary' : ($status === 'working' ? 'bg-primary' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-danger')) ?>">
                                            <?= e(\App\Models\SiteAdminTicket::labelStatus($status)) ?>
                                        </span>
                                    </td>
                                    <td><?= e((string) ((int) ($ticket['priority'] ?? 3))) ?></td>
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
