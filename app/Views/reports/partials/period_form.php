<?php
/** @var string $formAction GET target for Run Report */
/** @var string $fromDate */
/** @var string $toDate */
/** @var string $resetHref href for “Current month” (same report, default range) */
$formAction = trim((string) ($formAction ?? ''));
$resetHref = trim((string) ($resetHref ?? $formAction));
if ($formAction === '') {
    $formAction = url('/reports/income');
}
if ($resetHref === '') {
    $resetHref = $formAction;
}
$fromId = (string) ($fromInputId ?? 'report-from-date');
$toId = (string) ($toInputId ?? 'report-to-date');
?>
<form method="get" action="<?= e($formAction) ?>" class="row g-3 align-items-end">
    <div class="col-12 col-md-4">
        <label class="form-label fw-semibold" for="<?= e($fromId) ?>">From</label>
        <input id="<?= e($fromId) ?>" class="form-control" type="date" name="from" value="<?= e($fromDate) ?>" />
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label fw-semibold" for="<?= e($toId) ?>">To</label>
        <input id="<?= e($toId) ?>" class="form-control" type="date" name="to" value="<?= e($toDate) ?>" />
    </div>
    <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit">Run Report</button>
        <a class="btn btn-outline-secondary flex-fill" href="<?= e($resetHref) ?>">Current Month</a>
    </div>
</form>
