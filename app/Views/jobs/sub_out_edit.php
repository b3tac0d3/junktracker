<?php
$job = is_array($job ?? null) ? $job : [];
$assignment = is_array($assignment ?? null) ? $assignment : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$subs = is_array($subs ?? null) ? $subs : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$actionUrl = (string) ($actionUrl ?? '');
$removeUrl = (string) ($removeUrl ?? '');
$canViewFinancials = (bool) ($canViewFinancials ?? false);
$defaultClientAmount = $defaultClientAmount ?? null;
$jobId = (int) ($job['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
$subName = trim((string) ($assignment['subcontractor_name'] ?? ''));
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$selectedSubId = (int) ($form['subcontractor_id'] ?? 0);
$selectedStatus = strtolower(trim((string) ($form['status'] ?? 'assigned')));
$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', strtolower(trim($value))));
};
$formatAmount = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float) $value, 2, '.', '');
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Manage Sub Assignment</h1>
        <p class="muted"><?= e($jobTitle) ?><?= $subName !== '' ? ' · ' . e($subName) : '' ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Back to Job</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-hard-hat me-2"></i>Sub Assignment</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3" id="sub-out-edit-form">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-edit-subcontractor">Sub-Contractor</label>
                <select id="sub-edit-subcontractor" name="subcontractor_id" class="form-select <?= $hasError('subcontractor_id') ? 'is-invalid' : '' ?>">
                    <?php foreach ($subs as $sub): ?>
                        <?php if (!is_array($sub)) continue; ?>
                        <?php $subId = (int) ($sub['id'] ?? 0); ?>
                        <?php $label = trim((string) ($sub['display_name'] ?? '')) ?: ('Sub #' . (string) $subId); ?>
                        <option value="<?= (string) $subId ?>" <?= $selectedSubId === $subId ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('subcontractor_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('subcontractor_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-edit-status">Status</label>
                <select id="sub-edit-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statusOptions as $option): ?>
                        <?php $value = strtolower(trim((string) $option)); ?>
                        <?php if ($value === '') continue; ?>
                        <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($statusLabel($value)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <?php if ($canViewFinancials): ?>
            <div class="col-12">
                <hr>
                <h2 class="h6">Financials (when complete)</h2>
                <p class="small text-muted mb-0">Log what the sub charged. Your metrics will use <strong>our cut</strong> only.</p>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-edit-client-amount">Client amount</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input id="sub-edit-client-amount" name="client_amount" type="number" step="0.01" min="0" class="form-control <?= $hasError('client_amount') ? 'is-invalid' : '' ?>" value="<?= e($formatAmount($form['client_amount'] ?? ($defaultClientAmount ?? ''))) ?>" />
                </div>
                <?php if ($hasError('client_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_amount')) ?></div><?php endif; ?>
                <?php if ($defaultClientAmount !== null && ($form['client_amount'] ?? '') === '' && ($assignment['client_amount'] ?? null) === null): ?>
                    <div class="form-text">Pre-filled from invoice total when available.</div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-edit-sub-amount">Sub charged</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input id="sub-edit-sub-amount" name="sub_amount" type="number" step="0.01" min="0" class="form-control <?= $hasError('sub_amount') ? 'is-invalid' : '' ?>" value="<?= e($formatAmount($form['sub_amount'] ?? '')) ?>" />
                </div>
                <?php if ($hasError('sub_amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sub_amount')) ?></div><?php endif; ?>
                <div class="form-text">What you pay the sub — shown on their profile.</div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-edit-our-cut">Our cut</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input id="sub-edit-our-cut" name="our_cut" type="number" step="0.01" class="form-control <?= $hasError('our_cut') ? 'is-invalid' : '' ?>" value="<?= e($formatAmount($form['our_cut'] ?? '')) ?>" />
                </div>
                <?php if ($hasError('our_cut')): ?><div class="invalid-feedback d-block"><?= e($fieldError('our_cut')) ?></div><?php endif; ?>
                <div class="form-text">Auto-calculated from client − sub if left blank.</div>
            </div>
            <?php endif; ?>

            <div class="col-12">
                <label class="form-label fw-semibold" for="sub-edit-notes">Notes</label>
                <textarea id="sub-edit-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Cancel</a>
            </div>
        </form>

        <hr>

        <form method="post" action="<?= e($removeUrl) ?>" onsubmit="return confirm('Remove this sub assignment?');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger" type="submit">Remove Sub Assignment</button>
        </form>
    </div>
</section>

<?php if ($canViewFinancials): ?>
<script>
(() => {
    const clientInput = document.getElementById('sub-edit-client-amount');
    const subInput = document.getElementById('sub-edit-sub-amount');
    const ourCutInput = document.getElementById('sub-edit-our-cut');
    if (!clientInput || !subInput || !ourCutInput) {
        return;
    }

    const recalc = () => {
        if (ourCutInput.dataset.manual === '1') {
            return;
        }
        const client = parseFloat(clientInput.value);
        const sub = parseFloat(subInput.value);
        if (Number.isFinite(client) && Number.isFinite(sub)) {
            ourCutInput.value = (client - sub).toFixed(2);
        }
    };

    ourCutInput.addEventListener('input', () => {
        ourCutInput.dataset.manual = ourCutInput.value.trim() !== '' ? '1' : '0';
    });
    clientInput.addEventListener('input', recalc);
    subInput.addEventListener('input', recalc);
})();
</script>
<?php endif; ?>
