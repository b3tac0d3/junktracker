<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Add deposit</h1>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/billing/deposits')) ?>">Back</a>
</div>

<section class="card index-card">
    <div class="card-body">
        <?php if (($errors['general'] ?? '') !== ''): ?>
            <div class="alert alert-danger"><?= e((string) $errors['general']) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/billing/deposits')) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label class="form-label" for="deposit_date">Deposit date</label>
                <input class="form-control" id="deposit_date" name="deposit_date" type="date" value="<?= e((string) ($form['deposit_date'] ?? '')) ?>" required>
                <?php if (($errors['deposit_date'] ?? '') !== ''): ?><div class="text-danger small"><?= e((string) $errors['deposit_date']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="amount">Amount</label>
                <input class="form-control" id="amount" name="amount" type="text" inputmode="decimal" value="<?= e((string) ($form['amount'] ?? '')) ?>" required>
                <?php if (($errors['amount'] ?? '') !== ''): ?><div class="text-danger small"><?= e((string) $errors['amount']) ?></div><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="note">Note</label>
                <input class="form-control" id="note" name="note" type="text" value="<?= e((string) ($form['note'] ?? '')) ?>" maxlength="500">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</section>
