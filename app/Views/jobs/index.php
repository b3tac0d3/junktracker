<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Jobs</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Jobs</li>
            </ol>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/jobs') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search jobs or clients..." value="<?= e($filters['q'] ?? '') ?>" />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?= ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="complete" <?= ($filters['status'] ?? '') === 'complete' ? 'selected' : '' ?>>Complete</option>
                            <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Start Date</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>" />
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">End Date</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-briefcase me-1"></i>
            All Jobs
        </div>
        <div class="card-body">
            <table id="jobsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Job</th>
                        <th>Client</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Quote</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                            $clientName = trim(($job['first_name'] ?? '') . ' ' . ($job['last_name'] ?? ''));
                            if ($clientName === '') {
                                $clientName = $job['business_name'] ?? '';
                            }
                            $status = $job['job_status'] ?? '';
                            $statusClass = match ($status) {
                                'active' => 'bg-primary',
                                'complete' => 'bg-success',
                                'cancelled' => 'bg-secondary',
                                default => 'bg-warning',
                            };
                        ?>
                        <tr>
                            <td><?= e((string) $job['id']) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= url('/jobs/' . $job['id']) ?>">
                                    <?= e($job['name'] ?? '') ?>
                                </a>
                            </td>
                            <td><?= e($clientName) ?></td>
                            <td><?= e(trim(($job['city'] ?? '') . (isset($job['state']) && $job['state'] !== '' ? ', ' . $job['state'] : ''))) ?></td>
                            <td><span class="badge <?= $statusClass ?> text-uppercase"><?= e($status) ?></span></td>
                            <td><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                            <td><?= isset($job['total_quote']) ? e('$' . number_format((float) $job['total_quote'], 2)) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
