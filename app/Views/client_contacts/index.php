<?php
    $contacts = $contacts ?? [];
    $filters = $filters ?? [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Client Contacts</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Client Contacts</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/client-contacts/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Log Contact
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/client-contacts') ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-9">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search client, subject, notes..."
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                            />
                            <?php if (!empty($filters['q'])): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/client-contacts') ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($filters['record_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1 d-grid">
                        <button class="btn btn-primary" type="submit">Go</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-address-book me-1"></i>
            Contact Log
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Method</th>
                            <th>Direction</th>
                            <th>Subject</th>
                            <th>Linked</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" class="text-muted">No contact records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <?php $href = url('/client-contacts/' . (string) ($contact['id'] ?? '')); ?>
                                <tr>
                                    <td><a class="text-decoration-none" href="<?= $href ?>"><?= e(format_datetime($contact['contacted_at'] ?? null)) ?></a></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/clients/' . (string) ($contact['client_id'] ?? '')) ?>">
                                            <?= e((string) ($contact['client_name'] ?? '—')) ?>
                                        </a>
                                    </td>
                                    <td class="text-capitalize"><?= e(ucwords(str_replace('_', ' ', (string) ($contact['contact_method'] ?? '')))) ?></td>
                                    <td class="text-capitalize"><?= e((string) ($contact['direction'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= $href ?>">
                                            <?= e((string) (($contact['subject'] ?? '') !== '' ? $contact['subject'] : '—')) ?>
                                        </a>
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
                                    <td><?= e((string) (($contact['created_by_name'] ?? '') !== '' ? $contact['created_by_name'] : '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
