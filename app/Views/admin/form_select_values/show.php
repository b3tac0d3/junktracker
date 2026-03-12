<?php
$formCatalog = is_array($formCatalog ?? null) ? $formCatalog : [];
$csrfToken = csrf_token();
$formKey = strtolower(trim((string) ($formCatalog['form_key'] ?? '')));
$formLabel = trim((string) ($formCatalog['form_label'] ?? $formKey));
$sections = is_array($formCatalog['sections'] ?? null) ? $formCatalog['sections'] : [];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($formLabel) ?> Select Values</h1>
        <p class="muted">Quick add and inline edit/delete by section.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/form-select-values')) ?>">Back to Forms</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-sliders-h me-2"></i><?= e($formLabel) ?> Sections</strong>
    </div>
    <div class="card-body d-flex flex-column gap-3">
        <?php foreach ($sections as $section): ?>
            <?php
            $sectionKey = strtolower(trim((string) ($section['section_key'] ?? '')));
            $sectionLabel = trim((string) ($section['section_label'] ?? $sectionKey));
            $sectionHint = trim((string) ($section['section_hint'] ?? ''));
            $options = is_array($section['options'] ?? null) ? $section['options'] : [];
            ?>
            <article class="border rounded p-3 select-values-section" data-form-key="<?= e($formKey) ?>" data-section-key="<?= e($sectionKey) ?>">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h3 class="h6 mb-1"><?= e($sectionLabel) ?></h3>
                        <?php if ($sectionHint !== ''): ?><p class="small muted mb-0"><?= e($sectionHint) ?></p><?php endif; ?>
                    </div>
                </div>

                <form class="row g-2 align-items-center js-select-value-add-form" action="<?= e(url('/admin/form-select-values/quick-create')) ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="form_key" value="<?= e($formKey) ?>">
                    <input type="hidden" name="section_key" value="<?= e($sectionKey) ?>">
                    <div class="col-12 col-lg-9">
                        <input
                            type="text"
                            name="option_value"
                            class="form-control"
                            placeholder="Quick add option..."
                            maxlength="160"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-lg-3 d-grid">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-plus me-2"></i>Add</button>
                    </div>
                    <div class="col-12">
                        <div class="small text-danger d-none js-select-value-error"></div>
                    </div>
                </form>

                <div class="record-list-simple mt-3 js-select-value-list">
                    <?php if ($options === []): ?>
                        <div class="record-empty mb-0 js-select-value-empty">No options configured yet.</div>
                    <?php else: ?>
                        <div class="record-empty mb-0 js-select-value-empty d-none">No options configured yet.</div>
                    <?php endif; ?>

                    <?php foreach ($options as $option): ?>
                        <?php
                        $optionId = (int) ($option['id'] ?? 0);
                        $optionValue = trim((string) ($option['option_value'] ?? ''));
                        ?>
                        <form
                            class="record-row-simple d-flex flex-wrap gap-2 align-items-center js-select-value-row"
                            data-option-id="<?= e((string) $optionId) ?>"
                            action="<?= e(url('/admin/form-select-values/' . (string) $optionId . '/quick-update')) ?>"
                            method="post"
                        >
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <div class="flex-grow-1">
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    name="option_value"
                                    value="<?= e($optionValue) ?>"
                                    maxlength="160"
                                >
                            </div>
                            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i>Save</button>
                            <button
                                class="btn btn-outline-danger btn-sm js-select-value-delete"
                                type="button"
                                data-delete-url="<?= e(url('/admin/form-select-values/' . (string) $optionId . '/quick-delete')) ?>"
                            ><i class="fas fa-trash"></i></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<template id="select-value-row-template">
    <form class="record-row-simple d-flex flex-wrap gap-2 align-items-center js-select-value-row" data-option-id="" action="" method="post">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="flex-grow-1">
            <input type="text" class="form-control form-control-sm" name="option_value" value="" maxlength="160">
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i>Save</button>
        <button class="btn btn-outline-danger btn-sm js-select-value-delete" type="button" data-delete-url=""><i class="fas fa-trash"></i></button>
    </form>
</template>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const page = document.body;
    const rowTemplate = document.getElementById('select-value-row-template');
    if (!page || !(rowTemplate instanceof HTMLTemplateElement)) {
        return;
    }

    const setError = (container, message) => {
        if (!container) return;
        container.textContent = message || '';
        container.classList.toggle('d-none', !message);
    };

    const ensureEmptyState = (section) => {
        const list = section.querySelector('.js-select-value-list');
        const empty = section.querySelector('.js-select-value-empty');
        if (!list || !empty) return;
        const hasRows = list.querySelector('.js-select-value-row') !== null;
        empty.classList.toggle('d-none', hasRows);
    };

    const wireRowEvents = (section, row) => {
        if (!(row instanceof HTMLFormElement)) return;

        const errorBox = section.querySelector('.js-select-value-error');
        row.addEventListener('submit', async (event) => {
            event.preventDefault();
            setError(errorBox, '');

            const formData = new FormData(row);
            const response = await fetch(row.action, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) {
                setError(errorBox, payload.error || 'Unable to update option.');
                return;
            }

            const valueInput = row.querySelector('input[name="option_value"]');
            if (valueInput instanceof HTMLInputElement && payload.option && payload.option.option_value) {
                valueInput.value = String(payload.option.option_value);
            }
        });

        const deleteBtn = row.querySelector('.js-select-value-delete');
        if (deleteBtn instanceof HTMLButtonElement) {
            deleteBtn.addEventListener('click', async () => {
                setError(errorBox, '');
                const csrfInput = row.querySelector('input[name="csrf_token"]');
                const csrfToken = csrfInput instanceof HTMLInputElement ? csrfInput.value : '';
                const body = new URLSearchParams();
                body.set('csrf_token', csrfToken);

                const deleteUrl = deleteBtn.dataset.deleteUrl || '';
                const response = await fetch(deleteUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: body.toString(),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) {
                    setError(errorBox, payload.error || 'Unable to delete option.');
                    return;
                }

                row.remove();
                ensureEmptyState(section);
            });
        }
    };

    const addSectionRow = (section, option) => {
        if (!option || !option.id || !option.option_value) {
            return;
        }

        const list = section.querySelector('.js-select-value-list');
        if (!list) return;

        const clone = rowTemplate.content.firstElementChild.cloneNode(true);
        if (!(clone instanceof HTMLFormElement)) return;

        clone.dataset.optionId = String(option.id);
        clone.action = `<?= e(url('/admin/form-select-values')) ?>/${option.id}/quick-update`;

        const input = clone.querySelector('input[name="option_value"]');
        if (input instanceof HTMLInputElement) {
            input.value = String(option.option_value);
        }

        const deleteBtn = clone.querySelector('.js-select-value-delete');
        if (deleteBtn instanceof HTMLButtonElement) {
            deleteBtn.dataset.deleteUrl = `<?= e(url('/admin/form-select-values')) ?>/${option.id}/quick-delete`;
        }

        list.appendChild(clone);
        wireRowEvents(section, clone);
        ensureEmptyState(section);
    };

    document.querySelectorAll('.select-values-section').forEach((sectionNode) => {
        const section = sectionNode;
        if (!(section instanceof HTMLElement)) return;

        section.querySelectorAll('.js-select-value-row').forEach((rowNode) => {
            if (rowNode instanceof HTMLFormElement) {
                wireRowEvents(section, rowNode);
            }
        });

        const addForm = section.querySelector('.js-select-value-add-form');
        const errorBox = section.querySelector('.js-select-value-error');
        if (addForm instanceof HTMLFormElement) {
            addForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                setError(errorBox, '');

                const formData = new FormData(addForm);
                const response = await fetch(addForm.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) {
                    setError(errorBox, payload.error || 'Unable to add option.');
                    return;
                }

                addSectionRow(section, payload.option || null);
                addForm.reset();
            });
        }

        ensureEmptyState(section);
    });
});
</script>
