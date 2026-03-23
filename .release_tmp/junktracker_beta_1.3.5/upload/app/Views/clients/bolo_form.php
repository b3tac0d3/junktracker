<?php
$client = is_array($client ?? null) ? $client : [];
$clientId = (int) ($clientId ?? ($client['id'] ?? 0));
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/clients/' . (string) $clientId . '/bolo'));

$displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = trim((string) ($client['company_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'Client #' . (string) max(0, $clientId);
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$boloHasActiveFlag = (bool) ($boloHasActiveFlag ?? false);
$boloIsActive = (bool) ($boloIsActive ?? true);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>BOLO profile</h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId)) ?>">Back to Client</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-binoculars me-2"></i>Buyer / BOLO</strong>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">List what this client is looking for (one item per line). Add optional notes about how they buy. Save with both empty to remove the BOLO profile.</p>

        <?php if ($boloHasActiveFlag && !$boloIsActive): ?>
            <div class="alert alert-warning mb-3">This BOLO profile is <strong>inactive</strong> and does not appear in the BOLO list or search. Saving changes will reactivate it.</div>
        <?php endif; ?>

        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12">
                <label class="form-label fw-semibold" for="bolo-items-text">Line items</label>
                <textarea
                    id="bolo-items-text"
                    name="items_text"
                    class="form-control <?= $hasError('items_text') ? 'is-invalid' : '' ?>"
                    rows="8"
                    placeholder="Example: Hot Wheels, vintage tools, LEGO (one per line)"
                ><?= e((string) ($form['items_text'] ?? '')) ?></textarea>
                <?php if ($hasError('items_text')): ?><div class="invalid-feedback d-block"><?= e($fieldError('items_text')) ?></div><?php endif; ?>
                <div class="form-text">One wanted item or category per line.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="bolo-notes">Notes</label>
                <textarea
                    id="bolo-notes"
                    name="notes"
                    class="form-control <?= $hasError('notes') ? 'is-invalid' : '' ?>"
                    rows="4"
                    placeholder="Prefers loose cars, pays cash, etc."
                ><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                <?php if ($hasError('notes')): ?><div class="invalid-feedback d-block"><?= e($fieldError('notes')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save BOLO profile</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/clients/' . (string) $clientId)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
