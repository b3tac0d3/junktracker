<?php
    $queue = is_array($queue ?? null) ? $queue : [];
    $limit = (int) ($limit ?? 30);
    $canMerge = can_access('data_quality', 'edit');

    $clientGroups = is_array($queue['clients'] ?? null) ? $queue['clients'] : [];
    $companyGroups = is_array($queue['companies'] ?? null) ? $queue['companies'] : [];
    $jobGroups = is_array($queue['jobs'] ?? null) ? $queue['jobs'] : [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Data Quality</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Duplicate Merge Queue</li>
            </ol>
        </div>
        <form method="get" action="<?= url('/data-quality') ?>" class="d-flex gap-2">
            <input class="form-control" type="number" min="5" max="100" name="limit" value="<?= e((string) $limit) ?>" />
            <button class="btn btn-outline-primary" type="submit">Refresh Queue</button>
        </form>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Client Duplicate Groups</div>
                    <div class="h4 mb-0"><?= e((string) count($clientGroups)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Company Duplicate Groups</div>
                    <div class="h4 mb-0"><?= e((string) count($companyGroups)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Job Duplicate Groups</div>
                    <div class="h4 mb-0"><?= e((string) count($jobGroups)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user me-1"></i>Clients</div>
        <div class="card-body">
            <?php if (empty($clientGroups)): ?>
                <div class="text-muted">No client duplicates detected.</div>
            <?php else: ?>
                <div class="accordion" id="dqClientsAccordion">
                    <?php foreach ($clientGroups as $index => $group): ?>
                        <?php $records = is_array($group['records'] ?? null) ? $group['records'] : []; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="dqClientHeading<?= e((string) $index) ?>">
                                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#dqClientCollapse<?= e((string) $index) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <?= e((string) ($group['reason'] ?? 'Potential duplicate')) ?>
                                    <span class="badge bg-light text-dark ms-2"><?= e((string) count($records)) ?> records</span>
                                </button>
                            </h2>
                            <div id="dqClientCollapse<?= e((string) $index) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#dqClientsAccordion">
                                <div class="accordion-body">
                                    <ul class="list-group mb-3">
                                        <?php foreach ($records as $record): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <a class="text-decoration-none fw-semibold" href="<?= url((string) ($record['url'] ?? '/clients')) ?>">
                                                        #<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? 'Record')) ?>
                                                    </a>
                                                    <?php if (!empty($record['meta'])): ?>
                                                        <div class="small text-muted"><?= e((string) $record['meta']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if ($canMerge): ?>
                                        <form method="post" action="<?= url('/data-quality/merge/client') ?>" class="row g-2 align-items-end">
                                            <?= csrf_field() ?>
                                            <div class="col-md-5">
                                                <label class="form-label">Source (to deactivate)</label>
                                                <select class="form-select" name="source_id" required>
                                                    <option value="">Select source...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Target (keep)</label>
                                                <select class="form-select" name="target_id" required>
                                                    <option value="">Select target...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button class="btn btn-danger" type="submit">Merge</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-muted">Read-only access: merge actions require edit permission.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-building me-1"></i>Companies</div>
        <div class="card-body">
            <?php if (empty($companyGroups)): ?>
                <div class="text-muted">No company duplicates detected.</div>
            <?php else: ?>
                <div class="accordion" id="dqCompaniesAccordion">
                    <?php foreach ($companyGroups as $index => $group): ?>
                        <?php $records = is_array($group['records'] ?? null) ? $group['records'] : []; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="dqCompanyHeading<?= e((string) $index) ?>">
                                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#dqCompanyCollapse<?= e((string) $index) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <?= e((string) ($group['reason'] ?? 'Potential duplicate')) ?>
                                    <span class="badge bg-light text-dark ms-2"><?= e((string) count($records)) ?> records</span>
                                </button>
                            </h2>
                            <div id="dqCompanyCollapse<?= e((string) $index) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#dqCompaniesAccordion">
                                <div class="accordion-body">
                                    <ul class="list-group mb-3">
                                        <?php foreach ($records as $record): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <a class="text-decoration-none fw-semibold" href="<?= url((string) ($record['url'] ?? '/companies')) ?>">
                                                        #<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? 'Record')) ?>
                                                    </a>
                                                    <?php if (!empty($record['meta'])): ?>
                                                        <div class="small text-muted"><?= e((string) $record['meta']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if ($canMerge): ?>
                                        <form method="post" action="<?= url('/data-quality/merge/company') ?>" class="row g-2 align-items-end">
                                            <?= csrf_field() ?>
                                            <div class="col-md-5">
                                                <label class="form-label">Source (to deactivate)</label>
                                                <select class="form-select" name="source_id" required>
                                                    <option value="">Select source...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Target (keep)</label>
                                                <select class="form-select" name="target_id" required>
                                                    <option value="">Select target...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button class="btn btn-danger" type="submit">Merge</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-muted">Read-only access: merge actions require edit permission.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-briefcase me-1"></i>Jobs</div>
        <div class="card-body">
            <?php if (empty($jobGroups)): ?>
                <div class="text-muted">No job duplicates detected.</div>
            <?php else: ?>
                <div class="accordion" id="dqJobsAccordion">
                    <?php foreach ($jobGroups as $index => $group): ?>
                        <?php $records = is_array($group['records'] ?? null) ? $group['records'] : []; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="dqJobHeading<?= e((string) $index) ?>">
                                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#dqJobCollapse<?= e((string) $index) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <?= e((string) ($group['reason'] ?? 'Potential duplicate')) ?>
                                    <span class="badge bg-light text-dark ms-2"><?= e((string) count($records)) ?> records</span>
                                </button>
                            </h2>
                            <div id="dqJobCollapse<?= e((string) $index) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#dqJobsAccordion">
                                <div class="accordion-body">
                                    <ul class="list-group mb-3">
                                        <?php foreach ($records as $record): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <a class="text-decoration-none fw-semibold" href="<?= url((string) ($record['url'] ?? '/jobs')) ?>">
                                                        #<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? 'Record')) ?>
                                                    </a>
                                                    <?php if (!empty($record['meta'])): ?>
                                                        <div class="small text-muted"><?= e((string) $record['meta']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if ($canMerge): ?>
                                        <form method="post" action="<?= url('/data-quality/merge/job') ?>" class="row g-2 align-items-end">
                                            <?= csrf_field() ?>
                                            <div class="col-md-5">
                                                <label class="form-label">Source (to deactivate)</label>
                                                <select class="form-select" name="source_id" required>
                                                    <option value="">Select source...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Target (keep)</label>
                                                <select class="form-select" name="target_id" required>
                                                    <option value="">Select target...</option>
                                                    <?php foreach ($records as $record): ?>
                                                        <option value="<?= e((string) ($record['id'] ?? '')) ?>">#<?= e((string) ($record['id'] ?? '')) ?> - <?= e((string) ($record['label'] ?? '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button class="btn btn-danger" type="submit">Merge</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-muted">Read-only access: merge actions require edit permission.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
