<?php
    $name = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
    $clientType = (string) ($client['client_type'] ?? 'client');
    if (!in_array($clientType, ['client', 'realtor', 'other'], true)) {
        $clientType = 'other';
    }
    $contacts = $contacts ?? [];
    $tasks = $tasks ?? [];
    $attachments = is_array($attachments ?? null) ? $attachments : [];
    $clientPath = '/clients/' . (string) ($client['id'] ?? '');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Client Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/clients') ?>">Clients</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($client['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= url('/client-contacts/new?client_id=' . ($client['id'] ?? '')) ?>">
                <i class="fas fa-phone me-1"></i>
                Log Contact
            </a>
            <a class="btn btn-warning" href="<?= url('/clients/' . ($client['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Client
            </a>
            <?php if (empty($client['deleted_at']) && !empty($client['active'])): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deactivateClientModal">
                    <i class="fas fa-user-slash me-1"></i>
                    Deactivate
                </button>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Inactive</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/clients') ?>">Back to Clients</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            Profile
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Name</div>
                    <div class="fw-semibold"><?= e($name !== '' ? $name : '—') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Client ID</div>
                    <div class="fw-semibold"><?= e((string) ($client['id'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Type</div>
                    <div class="fw-semibold text-capitalize"><?= e($clientType) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (empty($client['deleted_at']) && !empty($client['active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Can Text</div>
                    <div class="fw-semibold"><?= !empty($client['can_text']) ? 'Yes' : 'No' ?></div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e(format_phone($client['phone'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e((string) (($client['email'] ?? '') !== '' ? $client['email'] : '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Company</div>
                    <div class="fw-semibold"><?= e((string) (($client['company_names'] ?? '') !== '' ? $client['company_names'] : '—')) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold">
                        <?php
                            $line1 = trim((string) ($client['address_1'] ?? ''));
                            $line2 = trim((string) ($client['address_2'] ?? ''));
                            $city = trim((string) ($client['city'] ?? ''));
                            $state = trim((string) ($client['state'] ?? ''));
                            $zip = trim((string) ($client['zip'] ?? ''));
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
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($client['note'] ?? '') !== '' ? $client['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-building me-1"></i>
            Linked Companies
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Website</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="5" class="text-muted">No linked company.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td><?= e((string) ($company['id'] ?? '')) ?></td>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/companies/' . ($company['id'] ?? '')) ?>">
                                            <?= e((string) ($company['name'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= e(format_phone($company['phone'] ?? null)) ?></td>
                                    <td><?= e((string) (($company['web_address'] ?? '') !== '' ? $company['web_address'] : '—')) ?></td>
                                    <td>
                                        <?php
                                            $city = trim((string) ($company['city'] ?? ''));
                                            $state = trim((string) ($company['state'] ?? ''));
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
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <i class="fas fa-address-book me-1"></i>
                Client Contacts
            </div>
            <a class="btn btn-sm btn-primary" href="<?= url('/client-contacts/new?client_id=' . ($client['id'] ?? '')) ?>">
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
                                <td colspan="6" class="text-muted">No contact records for this client yet.</td>
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

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <i class="fas fa-list-check me-1"></i>
                Tasks
            </div>
            <a class="btn btn-sm btn-primary" href="<?= url('/tasks/new?link_type=client&link_id=' . ($client['id'] ?? '')) ?>">
                <i class="fas fa-plus me-1"></i>
                Add Task
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Done</th>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Due</th>
                            <th>Completed</th>
                            <th>Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No tasks linked to this client.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <?php $isCompleted = (string) ($task['status'] ?? '') === 'closed'; ?>
                                <tr>
                                    <td>
                                        <form method="post" action="<?= url('/tasks/' . (string) ($task['id'] ?? '') . '/toggle-complete') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="return_to" value="<?= e($clientPath) ?>" />
                                            <input type="hidden" name="is_completed" value="0" />
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="is_completed"
                                                value="1"
                                                <?= $isCompleted ? 'checked' : '' ?>
                                                onchange="this.form.submit()"
                                            />
                                        </form>
                                    </td>
                                    <td>
                                        <a class="text-decoration-none <?= $isCompleted ? 'text-muted text-decoration-line-through' : '' ?>" href="<?= url('/tasks/' . (string) ($task['id'] ?? '')) ?>">
                                            <?= e((string) ($task['title'] ?? 'Task')) ?>
                                        </a>
                                    </td>
                                    <td class="text-capitalize"><?= e(ucwords(str_replace('_', ' ', (string) ($task['status'] ?? 'open')))) ?></td>
                                    <td><?= e(format_datetime($task['due_at'] ?? null)) ?></td>
                                    <td><?= e(format_datetime($task['completed_at'] ?? null)) ?></td>
                                    <td><?= e((string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned')) ?></td>
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
        $attachmentLinkType = 'client';
        $attachmentLinkId = (int) ($client['id'] ?? 0);
        $attachmentReturnTo = $clientPath;
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
                    <div class="fw-semibold"><?= e(format_datetime($client['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($client['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($client['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($client['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($client['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($client['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($client['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($client['deleted_at']) && !empty($client['active'])): ?>
        <div class="modal fade" id="deactivateClientModal" tabindex="-1" aria-labelledby="deactivateClientModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateClientModalLabel">Deactivate Client</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the client and hide them from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/clients/' . ($client['id'] ?? '') . '/deactivate') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Deactivate Client</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
