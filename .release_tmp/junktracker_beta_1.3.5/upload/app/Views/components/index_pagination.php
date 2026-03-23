<?php
$pagination = is_array($pagination ?? null) ? $pagination : [];
$basePath = trim((string) ($basePath ?? '/'));
if ($basePath === '') {
    $basePath = '/';
}

$page = max(1, (int) ($pagination['page'] ?? 1));
$perPage = pagination_per_page($pagination['per_page'] ?? null);
$totalRows = max(0, (int) ($pagination['total_rows'] ?? 0));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$from = max(0, (int) ($pagination['from'] ?? 0));
$to = max(0, (int) ($pagination['to'] ?? 0));
$visiblePages = pagination_visible_pages($page, $totalPages);
$queryParams = current_query_params(['page', 'per_page']);
?>

<div class="index-pagination d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
    <form method="get" action="<?= e(url($basePath)) ?>" class="d-flex align-items-center gap-2">
        <?php foreach ($queryParams as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="page" value="1">
        <label class="small text-muted fw-semibold" for="index-per-page">Rows</label>
        <select id="index-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach (pagination_per_page_options() as $option): ?>
                <option value="<?= e((string) $option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
        <span class="small text-muted">
            Showing <?= e((string) $from) ?>-<?= e((string) $to) ?> of <?= e((string) $totalRows) ?>
        </span>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $prevDisabled = $page <= 1;
                    $nextDisabled = $page >= $totalPages;
                    ?>
                    <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
                        <?php if ($prevDisabled): ?>
                            <span class="page-link">Prev</span>
                        <?php else: ?>
                            <a class="page-link" href="<?= e(url($basePath) . query_with(['page' => $page - 1, 'per_page' => $perPage])) ?>">Prev</a>
                        <?php endif; ?>
                    </li>
                    <?php foreach ($visiblePages as $pageNumber): ?>
                        <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                            <?php if ($pageNumber === $page): ?>
                                <span class="page-link"><?= e((string) $pageNumber) ?></span>
                            <?php else: ?>
                                <a class="page-link" href="<?= e(url($basePath) . query_with(['page' => $pageNumber, 'per_page' => $perPage])) ?>"><?= e((string) $pageNumber) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
                        <?php if ($nextDisabled): ?>
                            <span class="page-link">Next</span>
                        <?php else: ?>
                            <a class="page-link" href="<?= e(url($basePath) . query_with(['page' => $page + 1, 'per_page' => $perPage])) ?>">Next</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
