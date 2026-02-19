<?php
    $query = trim((string) ($query ?? ''));
    $sections = is_array($sections ?? null) ? $sections : [];
    $totalResults = (int) ($totalResults ?? 0);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Search</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Search</li>
            </ol>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/search') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-lg-10">
                    <label class="form-label" for="global_search_q">Search Everything</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input
                            class="form-control"
                            id="global_search_q"
                            type="text"
                            name="q"
                            value="<?= e($query) ?>"
                            placeholder="Search jobs, clients, estates, sales, expenses, tasks, and more..."
                            autocomplete="off"
                        />
                    </div>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Search</button>
                    <a class="btn btn-outline-secondary flex-fill" href="<?= url('/search') ?>">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($query === ''): ?>
        <div class="alert alert-info">Enter a search term to find matching records across the system.</div>
    <?php elseif (empty($sections)): ?>
        <div class="alert alert-warning mb-3">No results found for "<?= e($query) ?>".</div>
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-plus-circle me-1"></i>Create New Record</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= url('/jobs/new') ?>">Add Job</a>
                <a class="btn btn-warning text-dark" href="<?= url('/prospects/new') ?>">Add Prospect</a>
                <a class="btn btn-success" href="<?= url('/clients/new') ?>">Add Client</a>
                <a class="btn btn-info text-white" href="<?= url('/companies/new') ?>">Add Company</a>
                <a class="btn btn-outline-secondary" href="<?= url('/sales/new') ?>">Add Sale</a>
                <a class="btn btn-outline-dark" href="<?= url('/expenses/new') ?>">Add Expense</a>
                <a class="btn btn-outline-primary" href="<?= url('/tasks/new') ?>">Add Task</a>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-3 text-muted">
            Showing results for <strong><?= e($query) ?></strong> • <?= e((string) $totalResults) ?> matches across <?= e((string) count($sections)) ?> sections
        </div>

        <div class="row g-3">
            <?php foreach ($sections as $section): ?>
                <?php
                    $items = is_array($section['items'] ?? null) ? $section['items'] : [];
                    $hasMore = !empty($section['has_more']);
                ?>
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div>
                                <i class="<?= e((string) ($section['icon'] ?? 'fas fa-search')) ?> me-1"></i>
                                <?= e((string) ($section['label'] ?? 'Results')) ?>
                            </div>
                            <span class="badge bg-primary"><?= e((string) ((int) ($section['total'] ?? count($items)))) ?></span>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($items as $item): ?>
                                <?php $itemUrl = trim((string) ($item['url'] ?? '')); ?>
                                <?php if ($itemUrl !== ''): ?>
                                    <a class="list-group-item list-group-item-action" href="<?= url($itemUrl) ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= e((string) ($item['title'] ?? 'Result')) ?></div>
                                                <?php if (!empty($item['meta'])): ?>
                                                    <small class="text-muted"><?= e((string) $item['meta']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($item['badge'])): ?>
                                                <span class="badge bg-light text-dark border"><?= e((string) $item['badge']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <div class="fw-semibold"><?= e((string) ($item['title'] ?? 'Result')) ?></div>
                                        <?php if (!empty($item['meta'])): ?>
                                            <small class="text-muted"><?= e((string) $item['meta']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($hasMore): ?>
                            <div class="card-footer text-muted small">Showing top matches for this section.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
