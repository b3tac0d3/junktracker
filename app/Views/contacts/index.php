<?php
    $contactTypeOptions = is_array($contactTypeOptions ?? null) ? $contactTypeOptions : [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Network Clients</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Network</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/network/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Network Client
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
            <form method="get" action="<?= url('/network') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search network clients..." value="<?= e((string) ($query ?? '')) ?>" />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?= (string) ($type ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($contactTypeOptions as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= (string) ($type ?? '') === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= (string) ($status ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (string) ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="all" <?= (string) ($status ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3 d-flex gap-2 mobile-two-col-buttons">
                        <button class="btn btn-primary" type="submit">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/network') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-address-book me-1"></i>
            Network Directory
        </div>
        <div class="card-body">
            <table id="contactsTable" class="js-card-list-source">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Company</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($contacts ?? []) as $contact): ?>
                        <?php
                            $contactId = (int) ($contact['id'] ?? 0);
                            $rowHref = url('/network/' . $contactId);
                            $name = trim((string) ($contact['display_name'] ?? ''));
                            if ($name === '') {
                                $name = trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')));
                            }
                            if ($name === '') {
                                $name = (string) ($contact['email'] ?? '');
                            }
                            if ($name === '') {
                                $name = 'Network Client #' . $contactId;
                            }

                            $sourceType = trim((string) ($contact['source_type'] ?? 'manual'));
                            $sourceId = (int) ($contact['source_id'] ?? 0);
                            $sourceLabel = ucfirst($sourceType);
                            if ($sourceType === 'manual' || $sourceType === '') {
                                $sourceLabel = 'Manual';
                            } elseif ($sourceId > 0) {
                                $sourceLabel .= ' #' . $sourceId;
                            }

                            $isInactive = !empty($contact['deleted_at']) || (int) ($contact['is_active'] ?? 1) !== 1;
                        ?>
                        <tr data-href="<?= e($rowHref) ?>" style="cursor: pointer;">
                            <td data-href="<?= e($rowHref) ?>"><?= e((string) $contactId) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= e($rowHref) ?>"><?= e($name) ?></a>
                            </td>
                            <td class="text-capitalize"><?= e(str_replace('_', ' ', (string) ($contact['contact_type'] ?? 'general'))) ?></td>
                            <td><?= e((string) (($contact['company_name'] ?? '') !== '' ? $contact['company_name'] : '—')) ?></td>
                            <td><?= e(format_phone($contact['phone'] ?? null)) ?></td>
                            <td><?= e((string) (($contact['email'] ?? '') !== '' ? $contact['email'] : '—')) ?></td>
                            <td><?= e($sourceLabel) ?></td>
                            <td>
                                <?php if (!$isInactive): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_datetime($contact['updated_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
