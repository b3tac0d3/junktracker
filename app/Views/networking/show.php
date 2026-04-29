<?php
$contact = is_array($contact ?? null) ? $contact : [];
$contactId = (int) ($contact['id'] ?? 0);
$name = trim((string) ($contact['contact_name'] ?? '')) ?: ('Contact #' . (string) $contactId);
$company = trim((string) ($contact['company'] ?? ''));
$contactType = strtolower(trim((string) ($contact['contact_type'] ?? '')));
$phone = trim((string) ($contact['phone'] ?? ''));
$phoneHref = phone_tel_href($phone);
$email = trim((string) ($contact['email'] ?? ''));
$notes = trim((string) ($contact['notes'] ?? ''));
$typeLabel = $contactType !== '' ? ucwords(str_replace('_', ' ', $contactType)) : '—';
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($name) ?></h1>
        <p class="muted"><?= e($company !== '' ? $company : 'Networking Contact') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/networking')) ?>">Back to Networking</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/networking/' . (string) $contactId . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('/networking/' . (string) $contactId . '/delete')) ?>" onsubmit="return confirm('Delete this networking contact?');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger" type="submit">Delete</button>
        </form>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-address-book me-2"></i>Contact Details</strong>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Name</dt>
            <dd class="col-sm-9"><?= e($name) ?></dd>

            <dt class="col-sm-3">Company</dt>
            <dd class="col-sm-9"><?= e($company !== '' ? $company : '—') ?></dd>

            <dt class="col-sm-3">Type</dt>
            <dd class="col-sm-9"><?= e($typeLabel) ?></dd>

            <dt class="col-sm-3">Phone</dt>
            <dd class="col-sm-9">
                <?php if ($phoneHref !== ''): ?>
                    <a href="<?= e($phoneHref) ?>"><?= e(format_phone($phone)) ?></a>
                <?php else: ?>
                    <?= e($phone !== '' ? format_phone($phone) : '—') ?>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-3">Email</dt>
            <dd class="col-sm-9"><?= e($email !== '' ? $email : '—') ?></dd>

            <dt class="col-sm-3">Notes</dt>
            <dd class="col-sm-9"><?= nl2br(e($notes !== '' ? $notes : '—')) ?></dd>
        </dl>
    </div>
</section>
