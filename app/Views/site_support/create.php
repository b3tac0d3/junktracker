<?php
    $defaultEmail = trim((string) ($defaultEmail ?? ''));
    $categories = is_array($categories ?? null) ? $categories : [];
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Contact Site Admin</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/support') ?>">Site Requests</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/support') ?>">Back to Requests</a>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-paper-plane me-1"></i>Submit Request
        </div>
        <div class="card-body">
            <form method="post" action="<?= url('/support/new') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="submitted_by_email">Reply Email</label>
                        <input
                            class="form-control"
                            id="submitted_by_email"
                            name="submitted_by_email"
                            type="email"
                            maxlength="255"
                            required
                            value="<?= e((string) old('submitted_by_email', $defaultEmail)) ?>"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select" id="category" name="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category) ?>" <?= (string) old('category', 'question') === $category ? 'selected' : '' ?>>
                                    <?= e(\App\Models\SiteAdminTicket::labelCategory($category)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="priority">Priority (1-5)</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= e((string) $i) ?>" <?= (int) old('priority', 3) === $i ? 'selected' : '' ?>><?= e((string) $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="subject">Subject</label>
                        <input
                            class="form-control"
                            id="subject"
                            name="subject"
                            type="text"
                            maxlength="255"
                            required
                            value="<?= e((string) old('subject', '')) ?>"
                        />
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="message">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="8" required><?= e((string) old('message', '')) ?></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Send to Site Admin</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/support') ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
