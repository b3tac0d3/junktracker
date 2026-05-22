<?php
$search = trim((string) ($search ?? ''));
$userId = (int) ($userId ?? 0);
$date = trim((string) ($date ?? ''));
$entity = trim((string) ($entity ?? ''));
$today = trim((string) ($today ?? date('Y-m-d')));
$entries = is_array($entries ?? null) ? $entries : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($entries), count($entries));
$perPage = (int) ($pagination['per_page'] ?? 25);
$userOptions = is_array($userOptions ?? null) ? $userOptions : [];
$entityOptions = is_array($entityOptions ?? null) ? $entityOptions : [];
$filterQuery = is_array($filterQuery ?? null) ? $filterQuery : [];

$userDisplayName = static function (array $row): string {
    return audit_user_display_name($row);
};

$userLabel = static function (array $row): string {
    $name = audit_user_display_name($row);
    $email = trim((string) ($row['user_email'] ?? ''));
    if ($email !== '' && $name !== $email && !str_starts_with($name, 'User #')) {
        return $name . ' (' . $email . ')';
    }
    return $name;
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Activity Log</h1>
        <p class="muted mb-0">Who changed what — adds, edits, deletes, and key actions.</p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/activity-log') . query_with(['date' => $today, 'page' => 1, 'per_page' => $perPage])) ?>">Today</a>
            <?php if ($userId > 0): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/activity-log') . query_with(['user_id' => $userId, 'page' => 1, 'per_page' => $perPage], ['date', 'entity', 'q'])) ?>">All dates for this user</a>
            <?php endif; ?>
            <?php if ($date !== ''): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/activity-log') . query_with(['date' => $date, 'page' => 1, 'per_page' => $perPage], ['user_id', 'entity', 'q'])) ?>">Full log for <?= e(format_date($date)) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/admin/activity-log')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="activity-log-search">Search</label>
                <input id="activity-log-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Action, entity, user, details..." autocomplete="off" />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="activity-log-user">User</label>
                <select id="activity-log-user" class="form-select" name="user_id">
                    <option value="">All users</option>
                    <?php foreach ($userOptions as $userRow): ?>
                        <?php $optionId = (int) ($userRow['id'] ?? 0); ?>
                        <option value="<?= e((string) $optionId) ?>" <?= $userId === $optionId ? 'selected' : '' ?>>
                            <?= e($userDisplayName($userRow)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="activity-log-date">Date</label>
                <input id="activity-log-date" class="form-control" type="date" name="date" value="<?= e($date) ?>" />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="activity-log-entity">Record type</label>
                <select id="activity-log-entity" class="form-select" name="entity">
                    <option value="">All types</option>
                    <?php foreach ($entityOptions as $entityKey): ?>
                        <option value="<?= e($entityKey) ?>" <?= $entity === $entityKey ? 'selected' : '' ?>>
                            <?= e(audit_entity_label($entityKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/admin/activity-log')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-clipboard-list me-2"></i>Log entries</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($entries)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/admin/activity-log';
        $fixedQueryParams = $filterQuery;
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($entries === []): ?>
            <div class="record-empty">No activity found for the current filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 activity-log-table">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">User</th>
                            <th scope="col">Action</th>
                            <th scope="col">Record</th>
                            <th scope="col">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                            <?php
                            if (!is_array($row)) {
                                continue;
                            }
                            $action = trim((string) ($row['action'] ?? ''));
                            $entityKey = trim((string) ($row['entity'] ?? ''));
                            $entityId = (int) ($row['entity_id'] ?? 0);
                            $meta = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
                            $entityUrl = audit_entity_url($entityKey, $entityId > 0 ? $entityId : null, $meta);
                            $metaSummary = audit_metadata_summary($meta);
                            $rowUserId = (int) ($row['user_id'] ?? 0);
                            ?>
                            <tr>
                                <td class="text-nowrap small"><?= e(format_datetime((string) ($row['created_at'] ?? ''))) ?></td>
                                <td>
                                    <?php if ($rowUserId > 0): ?>
                                        <a class="text-decoration-none" href="<?= e(url('/admin/activity-log') . query_with(['user_id' => $rowUserId, 'page' => 1, 'per_page' => $perPage], ['date', 'q', 'entity'])) ?>">
                                            <?= e($userLabel($row)) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($userLabel($row)) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(audit_action_label($action)) ?></td>
                                <td>
                                    <?php if ($entityUrl !== null): ?>
                                        <a class="text-decoration-none" href="<?= e($entityUrl) ?>">
                                            <?= e(audit_entity_label($entityKey)) ?><?= $entityId > 0 ? ' #' . e((string) $entityId) : '' ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e(audit_entity_label($entityKey)) ?><?= $entityId > 0 ? ' #' . e((string) $entityId) : '' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= e($metaSummary !== '' ? $metaSummary : '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
        require base_path('app/Views/components/index_pagination.php');
        ?>
    </div>
</section>
