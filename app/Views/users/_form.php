<?php
    $user = $user ?? [];
    $isEdit = !empty($user);
    $formValues = is_array($formValues ?? null) ? $formValues : [];
    $employeeLinkReview = is_array($employeeLinkReview ?? null) ? $employeeLinkReview : null;
    $employeeLinkSupported = !empty($employeeLinkSupported);
    $canCreateEmployee = !empty($canCreateEmployee);
?>
<form method="post" action="<?= url(isset($user['id']) ? '/users/' . $user['id'] : '/users') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e(old('first_name', $formValues['first_name'] ?? ($user['first_name'] ?? ''))) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e(old('last_name', $formValues['last_name'] ?? ($user['last_name'] ?? ''))) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e(old('email', $formValues['email'] ?? ($user['email'] ?? ''))) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <?php $roleValue = (int) old('role', isset($formValues['role']) ? (int) $formValues['role'] : (isset($user['role']) ? (int) $user['role'] : 1)); ?>
                <option value="1" <?= $roleValue === 1 ? 'selected' : '' ?>>User</option>
                <option value="2" <?= $roleValue === 2 ? 'selected' : '' ?>>Manager</option>
                <option value="3" <?= $roleValue === 3 ? 'selected' : '' ?>>Admin</option>
                <option value="99" <?= $roleValue === 99 ? 'selected' : '' ?>>Dev</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="is_active">Status</label>
            <select class="form-select" id="is_active" name="is_active">
                <?php $activeValue = (int) old('is_active', isset($formValues['is_active']) ? (int) $formValues['is_active'] : (isset($user['is_active']) ? (int) $user['is_active'] : 1)); ?>
                <option value="1" <?= $activeValue === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $activeValue === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php if ($isEdit): ?>
            <div class="col-md-6">
                <label class="form-label" for="password">Password (leave blank to keep)</label>
                <input class="form-control" id="password" name="password" type="password" />
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">Confirm Password</label>
                <input class="form-control" id="password_confirm" name="password_confirm" type="password" />
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    An invite email will be sent so the user can set their password.
                    The setup link expires in 72 hours.
                </div>
            </div>
            <?php if ($employeeLinkSupported && $employeeLinkReview !== null): ?>
                <?php
                    $reviewSelectedDecision = (string) ($employeeLinkReview['selected_decision'] ?? '');
                    $reviewCandidates = is_array($employeeLinkReview['candidates'] ?? null) ? $employeeLinkReview['candidates'] : [];
                    $hasCandidates = !empty($employeeLinkReview['has_candidates']);
                    $defaultCandidateDecision = '';
                    if ($hasCandidates && $reviewSelectedDecision === '' && !empty($reviewCandidates)) {
                        $defaultCandidateDecision = 'employee:' . (int) ($reviewCandidates[0]['id'] ?? 0);
                    }
                    $defaultNoMatchDecision = $reviewSelectedDecision !== ''
                        ? $reviewSelectedDecision
                        : ($canCreateEmployee ? 'create_new' : 'skip');
                ?>
                <div class="col-12">
                    <div class="card border-warning bg-light">
                        <div class="card-body">
                            <h5 class="card-title mb-2">
                                <i class="fas fa-link me-1 text-primary"></i>
                                Employee Link Check
                            </h5>
                            <?php if ($hasCandidates): ?>
                                <p class="card-text mb-3">
                                    Possible employee matches found. Choose how this user should be linked for punch in/out, then save again.
                                </p>
                                <div class="d-grid gap-2">
                                    <?php foreach ($reviewCandidates as $candidate): ?>
                                        <?php
                                            $candidateId = (int) ($candidate['id'] ?? 0);
                                            $candidateDecision = 'employee:' . $candidateId;
                                            $linkedUserName = trim((string) ($candidate['linked_user_name'] ?? ''));
                                        ?>
                                        <label class="border rounded p-2 bg-white">
                                            <input class="form-check-input me-2" type="radio" name="employee_link_decision" value="<?= e($candidateDecision) ?>" <?= ($reviewSelectedDecision === $candidateDecision || $defaultCandidateDecision === $candidateDecision) ? 'checked' : '' ?> />
                                            <span class="fw-semibold"><?= e((string) ($candidate['name'] ?? ('Employee #' . $candidateId))) ?></span>
                                            <span class="small text-muted ms-1">
                                                <?= e((string) ($candidate['email'] ?? '')) ?>
                                                <?php if (!empty($candidate['phone'])): ?>
                                                    &middot; <?= e(format_phone((string) $candidate['phone'])) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="badge bg-info text-dark ms-2 text-uppercase"><?= e((string) ($candidate['match_reason'] ?? 'possible')) ?></span>
                                            <?php if ($linkedUserName !== ''): ?>
                                                <span class="badge bg-warning text-dark ms-2">Currently linked to <?= e($linkedUserName) ?></span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <?php if ($canCreateEmployee): ?>
                                    <p class="card-text mb-3">
                                        No employee match found. Do you want to create and auto-link a new employee profile now?
                                    </p>
                                <?php else: ?>
                                    <p class="card-text mb-3">
                                        No employee match found. You can skip linking now and link an employee later from the user details page.
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="d-grid gap-2 mt-3">
                                <?php if ($canCreateEmployee): ?>
                                    <label class="border rounded p-2 bg-white">
                                        <input class="form-check-input me-2" type="radio" name="employee_link_decision" value="create_new" <?= (!$hasCandidates && $defaultNoMatchDecision === 'create_new') || $reviewSelectedDecision === 'create_new' ? 'checked' : '' ?> />
                                        Create and auto-link a new employee profile
                                    </label>
                                <?php endif; ?>
                                <label class="border rounded p-2 bg-white">
                                    <input class="form-check-input me-2" type="radio" name="employee_link_decision" value="skip" <?= (!$hasCandidates && $defaultNoMatchDecision === 'skip') || $reviewSelectedDecision === 'skip' ? 'checked' : '' ?> />
                                    Skip linking for now
                                </label>
                            </div>
                            <input type="hidden" name="employee_link_reviewed" value="1" />
                            <?php if (!$canCreateEmployee): ?>
                                <div class="small text-muted mt-2">You do not currently have permission to create employee records.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save User</button>
        <?php if (!empty($user['id'])): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/users/' . $user['id']) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/users') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
