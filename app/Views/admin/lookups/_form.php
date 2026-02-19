<?php
    $row = is_array($row ?? null) ? $row : [];
    $groups = is_array($groups ?? null) ? $groups : [];
    $selectedGroup = (string) old('group_key', (string) ($row['group_key'] ?? ($selectedGroup ?? 'job_status')));
    $valueKey = (string) old('value_key', (string) ($row['value_key'] ?? ''));
    $labelValue = (string) old('label', (string) ($row['label'] ?? ''));
    $sortOrder = (string) old('sort_order', isset($row['sort_order']) ? (string) $row['sort_order'] : '100');
    $isActive = (string) old('active', isset($row['active']) ? ((int) $row['active'] === 1 ? '1' : '0') : '1') === '1';
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="group_key">Group</label>
        <select class="form-select" id="group_key" name="group_key">
            <?php foreach ($groups as $groupKey => $groupLabel): ?>
                <option value="<?= e((string) $groupKey) ?>" <?= $selectedGroup === (string) $groupKey ? 'selected' : '' ?>>
                    <?= e((string) $groupLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="value_key">Value Key</label>
        <input class="form-control" id="value_key" name="value_key" type="text" value="<?= e($valueKey) ?>" />
    </div>
    <div class="col-md-4">
        <label class="form-label" for="label">Label</label>
        <input class="form-control" id="label" name="label" type="text" value="<?= e($labelValue) ?>" />
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort_order">Sort Order</label>
        <input class="form-control" id="sort_order" name="sort_order" type="number" min="0" value="<?= e($sortOrder) ?>" />
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">Status</label>
        <div class="form-check mt-2">
            <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= $isActive ? 'checked' : '' ?> />
            <label class="form-check-label" for="active">Active</label>
        </div>
    </div>
</div>

