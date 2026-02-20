<?php
    $sale = $sale ?? [];
    $types = $types ?? ['shop', 'scrap', 'ebay', 'other'];
    $supportsDisposalLocation = !empty($supportsDisposalLocation);
    $isEdit = !empty($sale['id']);

    $type = (string) old('type', $sale['type'] ?? 'shop');
    if (!in_array($type, $types, true)) {
        $type = 'shop';
    }

    $name = (string) old('name', $sale['name'] ?? '');
    $note = (string) old('note', $sale['note'] ?? '');
    $jobId = (string) old('job_id', isset($sale['job_id']) ? (string) $sale['job_id'] : '');
    $jobSearch = (string) old(
        'job_search',
        ($sale['job_name'] ?? '') !== '' ? (string) $sale['job_name'] : ($jobId !== '' ? ('Job #' . $jobId) : '')
    );
    $isScrapType = $type === 'scrap';
    $startDate = (string) old('start_date', $sale['start_date'] ?? '');
    $endDate = (string) old('end_date', $sale['end_date'] ?? '');
    $grossAmount = (string) old('gross_amount', isset($sale['gross_amount']) ? (string) $sale['gross_amount'] : '');
    $netAmount = (string) old('net_amount', isset($sale['net_amount']) ? (string) $sale['net_amount'] : '');
    $disposalLocationId = (string) old(
        'disposal_location_id',
        isset($sale['disposal_location_id']) && $sale['disposal_location_id'] !== null ? (string) $sale['disposal_location_id'] : ''
    );
    $scrapYardName = (string) old('scrap_yard_name', $sale['disposal_location_name'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/sales/' . ($sale['id'] ?? '') . '/edit' : '/sales/new') ?>">
    <?= csrf_field() ?>
    <?php if ($supportsDisposalLocation): ?>
        <input id="scrap_yard_lookup_url" type="hidden" value="<?= e(url('/sales/lookup/scrap-yards')) ?>" />
    <?php endif; ?>
    <input id="sale_job_lookup_url" type="hidden" value="<?= e(url('/sales/lookup/jobs')) ?>" />
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type" required>
                <?php foreach ($types as $value): ?>
                    <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e($name) ?>" required />
        </div>
        <div class="col-md-4 position-relative <?= $isScrapType ? '' : 'd-none' ?>" id="sale_job_lookup_group">
            <label class="form-label" for="job_search">Linked Job (Scrap)</label>
            <input id="job_id" name="job_id" type="hidden" value="<?= e($jobId) ?>" />
            <input
                class="form-control"
                id="job_search"
                name="job_search"
                type="text"
                value="<?= e($jobSearch) ?>"
                autocomplete="off"
                placeholder="Search jobs by name, ID, city..."
            />
            <div id="sale_job_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Select a job from the suggestion list.</div>
        </div>
        <?php if ($supportsDisposalLocation): ?>
            <div class="col-md-4 position-relative">
                <label class="form-label" for="scrap_yard_name">Scrap Yard</label>
                <input id="disposal_location_id" name="disposal_location_id" type="hidden" value="<?= e($disposalLocationId) ?>" />
                <input
                    class="form-control"
                    id="scrap_yard_name"
                    name="scrap_yard_name"
                    type="text"
                    value="<?= e($scrapYardName) ?>"
                    autocomplete="off"
                    placeholder="Search scrap yards..."
                />
                <div id="scrap_yard_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
                <div class="form-text">Only active scrap locations are allowed.</div>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label" for="start_date">Start Date</label>
            <input class="form-control" id="start_date" name="start_date" type="date" value="<?= e($startDate) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="end_date">End Date</label>
            <input class="form-control" id="end_date" name="end_date" type="date" value="<?= e($endDate) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="gross_amount">Gross Amount</label>
            <input class="form-control" id="gross_amount" name="gross_amount" type="number" min="0" step="0.01" value="<?= e($grossAmount) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="net_amount">Net Amount (Optional)</label>
            <input class="form-control" id="net_amount" name="net_amount" type="number" min="0" step="0.01" value="<?= e($netAmount) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Sale' : 'Save Sale' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/sales/' . ($sale['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/sales') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
