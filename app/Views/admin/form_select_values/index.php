<?php
$forms = is_array($forms ?? null) ? $forms : [];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Form Select Values</h1>
        <p class="muted">Choose a form to manage its dropdown options.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-list me-2"></i>Forms</strong>
    </div>
    <div class="card-body p-0">
        <?php if ($forms === []): ?>
            <div class="record-empty m-3">No forms configured.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($forms as $form): ?>
                    <?php
                    $formKey = strtolower(trim((string) ($form['form_key'] ?? '')));
                    $formLabel = trim((string) ($form['form_label'] ?? $formKey));
                    $sectionCount = (int) ($form['section_count'] ?? 0);
                    $optionCount = (int) ($form['option_count'] ?? 0);
                    ?>
                    <a class="record-row-simple text-decoration-none d-flex justify-content-between align-items-center gap-2" href="<?= e(url('/admin/form-select-values/' . $formKey)) ?>">
                        <div>
                            <div class="record-row-title mb-1"><?= e($formLabel) ?></div>
                            <div class="record-row-meta"><?= e((string) $sectionCount) ?> section(s) · <?= e((string) $optionCount) ?> option(s)</div>
                        </div>
                        <span class="text-primary fw-semibold">Open</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
