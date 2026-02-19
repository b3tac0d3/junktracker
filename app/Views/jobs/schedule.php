<?php
    $statusScope = (string) ($statusScope ?? 'dispatch');
    $search = (string) ($search ?? '');
    $unscheduledJobs = is_array($unscheduledJobs ?? null) ? $unscheduledJobs : [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Scheduling Board</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs') ?>">Jobs</a></li>
                <li class="breadcrumb-item active">Schedule</li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">
                <i class="fas fa-list me-1"></i>
                Jobs List
            </a>
            <a class="btn btn-primary" href="<?= url('/jobs/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Job
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/jobs/schedule') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label">Status Scope</label>
                    <select class="form-select" name="status_scope">
                        <option value="dispatch" <?= $statusScope === 'dispatch' ? 'selected' : '' ?>>Dispatch (Pending + Active)</option>
                        <option value="all" <?= $statusScope === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusScope === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                        <option value="active" <?= $statusScope === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="complete" <?= $statusScope === 'complete' ? 'selected' : '' ?>>Complete Only</option>
                        <option value="cancelled" <?= $statusScope === 'cancelled' ? 'selected' : '' ?>>Cancelled Only</option>
                    </select>
                </div>
                <div class="col-12 col-lg-5">
                    <label class="form-label">Unscheduled Search</label>
                    <input class="form-control" type="text" name="q" placeholder="Search unscheduled jobs by name/client/location..." value="<?= e($search) ?>" />
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/jobs/schedule') ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <input type="hidden" id="schedule_events_url" value="<?= e(url('/jobs/schedule/events')) ?>" />
    <input type="hidden" id="schedule_update_url" value="<?= e(url('/jobs/schedule/update')) ?>" />
    <input type="hidden" id="schedule_job_base_url" value="<?= e(url('/jobs/')) ?>" />
    <input type="hidden" id="schedule_status_scope" value="<?= e($statusScope) ?>" />
    <input type="hidden" id="schedule_csrf_token" value="<?= e(csrf_token()) ?>" />

    <div class="row g-3">
        <div class="col-12 col-xl-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-grip-lines me-1"></i>Unscheduled Jobs</span>
                    <span class="badge bg-secondary schedule-unscheduled-count"><?= e((string) count($unscheduledJobs)) ?></span>
                </div>
                <div class="card-body p-0">
                    <div id="schedule_unscheduled_list" class="schedule-unscheduled-list">
                        <?php if (empty($unscheduledJobs)): ?>
                            <div class="p-3 text-muted small">No unscheduled jobs in this scope.</div>
                        <?php else: ?>
                            <?php foreach ($unscheduledJobs as $job): ?>
                                <?php
                                    $jobId = (int) ($job['id'] ?? 0);
                                    if ($jobId <= 0) {
                                        continue;
                                    }
                                    $jobStatus = strtolower(trim((string) ($job['job_status'] ?? 'pending')));
                                    $location = trim((string) ($job['city'] ?? ''));
                                    $state = trim((string) ($job['state'] ?? ''));
                                    if ($location !== '' && $state !== '') {
                                        $location .= ', ' . $state;
                                    } elseif ($location === '') {
                                        $location = $state;
                                    }
                                ?>
                                <div
                                    class="schedule-unscheduled-item"
                                    data-job-id="<?= e((string) $jobId) ?>"
                                    data-job-name="<?= e((string) ($job['name'] ?? ('Job #' . $jobId))) ?>"
                                    data-job-status="<?= e($jobStatus) ?>"
                                >
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="fw-semibold text-truncate">#<?= e((string) $jobId) ?> - <?= e((string) ($job['name'] ?? '')) ?></div>
                                        <span class="badge text-bg-light text-uppercase border"><?= e($jobStatus) ?></span>
                                    </div>
                                    <div class="small text-muted text-truncate"><?= e((string) ($job['client_name'] ?? '')) ?></div>
                                    <div class="small text-muted text-truncate"><?= e($location !== '' ? $location : 'No location') ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-9">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-calendar-days me-1"></i>Calendar</span>
                    <span class="small text-muted">Drag unscheduled jobs onto a date, or drag existing events to reschedule.</span>
                </div>
                <div class="card-body">
                    <div id="jobsScheduleCalendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
