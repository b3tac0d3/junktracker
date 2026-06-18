<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$locationPickers = is_array($locationPickers ?? null) ? $locationPickers : [];
$locationsAvailable = (bool) ($locationsAvailable ?? true);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

if ($locationPickers === []) {
    return;
}
?>

<div class="col-12 mt-1">
    <hr class="my-1" />
    <h3 class="h5 mb-1">Default Operating Locations</h3>
    <p class="small muted mb-2">Shown when you have two or more active locations of the same type. Leave blank to use the base of operations.</p>
</div>

<?php foreach ($locationPickers as $picker): ?>
    <?php
    if (!is_array($picker)) {
        continue;
    }
    $field = trim((string) ($picker['field'] ?? ''));
    $label = trim((string) ($picker['label'] ?? 'Location'));
    $options = is_array($picker['options'] ?? null) ? $picker['options'] : [];
    if ($field === '') {
        continue;
    }
    $selectedId = (int) ($form[$field] ?? 0);
    ?>
    <div class="col-12 col-lg-4">
        <label class="form-label fw-semibold" for="employee-<?= e($field) ?>">Default <?= e($label) ?></label>
        <select id="employee-<?= e($field) ?>" name="<?= e($field) ?>" class="form-select <?= $hasError($field) ? 'is-invalid' : '' ?>" <?= !$locationsAvailable ? 'disabled' : '' ?>>
            <option value="">Base of operations</option>
            <?php foreach ($options as $option): ?>
                <?php
                if (!is_array($option)) {
                    continue;
                }
                $optionId = (int) ($option['id'] ?? 0);
                $optionName = trim((string) ($option['name'] ?? ''));
                if ($optionId <= 0 || $optionName === '') {
                    continue;
                }
                ?>
                <option value="<?= e((string) $optionId) ?>" <?= $selectedId === $optionId ? 'selected' : '' ?>><?= e($optionName) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($hasError($field)): ?><div class="invalid-feedback d-block"><?= e($fieldError($field)) ?></div><?php endif; ?>
    </div>
<?php endforeach; ?>
